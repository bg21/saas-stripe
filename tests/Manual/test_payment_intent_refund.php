<?php

/**
 * Teste Completo e Robusto de Payment Intents e Reembolsos
 * 
 * Este script testa:
 * 1. POST /v1/payment-intents - Criar payment intent para pagamento único
 * 2. POST /v1/refunds - Reembolsar um pagamento
 * 3. Validações de erro (campos obrigatórios, payment intent inválido)
 * 4. Teste direto dos métodos createPaymentIntent() e refundPayment() do StripeService
 * 5. Reembolso total e parcial
 * 
 * IMPORTANTE: Este teste cria recursos reais no Stripe (ambiente de teste)
 * NOTA: Para testar reembolsos, precisamos de um payment intent com status 'succeeded'
 *       Em ambiente de teste, podemos usar cartões de teste do Stripe
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086'; // Substitua pela sua API key do tenant
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE COMPLETO DE PAYMENT INTENTS E REEMBOLSOS            ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;

$testsPassed = 0;
$testsFailed = 0;
$testsSkipped = 0;

try {
    // Inicializa Stripe Client diretamente
    $stripeSecret = Config::get('STRIPE_SECRET');
    if (empty($stripeSecret)) {
        throw new Exception("STRIPE_SECRET não configurado no .env");
    }
    
    $stripe = new \Stripe\StripeClient($stripeSecret);
    echo "✅ Stripe Client inicializado" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 1: Criar Customer para Teste
    // ============================================
    echo "👤 PASSO 1: Criando customer para teste..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/customers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'email' => 'teste.payment.intent@example.com',
            'name' => 'Cliente Teste Payment Intent'
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 && $httpCode !== 200) {
        throw new Exception("Erro ao criar customer (HTTP {$httpCode}): " . $response);
    }
    
    $customerData = json_decode($response, true);
    $customerId = $customerData['data']['id'] ?? null;
    $stripeCustomerId = $customerData['data']['stripe_customer_id'] ?? null;
    
    if (!$customerId || !$stripeCustomerId) {
        throw new Exception("Customer criado mas dados inválidos");
    }
    
    echo "   ✅ Customer criado!" . PHP_EOL;
    echo "   Customer ID (banco): {$customerId}" . PHP_EOL;
    echo "   Stripe Customer ID: {$stripeCustomerId}" . PHP_EOL . PHP_EOL;

    // ============================================
    // TESTE 1: Criar Payment Intent via API
    // ============================================
    echo "🧪 TESTE 1: Criar Payment Intent via API..." . PHP_EOL;
    
    $paymentIntentData = [
        'amount' => 2999, // R$ 29,99
        'currency' => 'brl',
        'customer_id' => $stripeCustomerId,
        'description' => 'Teste de pagamento único',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'payment_intent'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/payment-intents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($paymentIntentData)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $paymentIntentId = $data['data']['id'];
            $clientSecret = $data['data']['client_secret'] ?? null;
            echo "   ✅ TESTE 1 PASSOU: Payment Intent criado com sucesso" . PHP_EOL;
            echo "   Payment Intent ID: {$paymentIntentId}" . PHP_EOL;
            echo "   Status: {$data['data']['status']}" . PHP_EOL;
            echo "   Valor: R$ " . number_format($data['data']['amount'] / 100, 2, ',', '.') . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 1 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 1 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 2: Validação de Campos Obrigatórios
    // ============================================
    echo "🧪 TESTE 2: Validação de campos obrigatórios (sem amount)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/payment-intents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'currency' => 'brl'
            // amount faltando
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($httpCode === 400 || (isset($data['error']) && (strpos(strtolower($data['error']), 'amount') !== false || strpos(strtolower($data['error']), 'obrigatório') !== false))) {
        echo "   ✅ TESTE 2 PASSOU: Validação de campos obrigatórios funcionando" . PHP_EOL;
        if ($httpCode !== 400) {
            echo "   ⚠️  (HTTP {$httpCode} mas mensagem de erro correta)" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 2 FALHOU: HTTP {$httpCode}, resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: Teste Direto do StripeService
    // ============================================
    echo "🧪 TESTE 3: Teste direto do método createPaymentIntent() do StripeService..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        $paymentIntent = $stripeService->createPaymentIntent([
            'amount' => 1999,
            'currency' => 'brl',
            'description' => 'Teste direto do serviço',
            'metadata' => ['test' => 'true']
        ]);
        
        if ($paymentIntent instanceof \Stripe\PaymentIntent && !empty($paymentIntent->id)) {
            echo "   ✅ TESTE 3 PASSOU: Método createPaymentIntent() funcionando corretamente" . PHP_EOL;
            echo "   Payment Intent ID: {$paymentIntent->id}" . PHP_EOL;
            echo "   Status: {$paymentIntent->status}" . PHP_EOL;
            $testPaymentIntentId = $paymentIntent->id; // Guarda para teste de reembolso
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 3 FALHOU: Retorno inválido do método" . PHP_EOL;
            $testsFailed++;
        }
    } catch (\Exception $e) {
        echo "   ❌ TESTE 3 FALHOU: " . $e->getMessage() . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // PASSO 2: Criar Payment Intent com Pagamento Bem-Sucedido para Teste de Reembolso
    // ============================================
    echo "💳 PASSO 2: Criando payment intent com pagamento bem-sucedido para teste de reembolso..." . PHP_EOL;
    echo "   ⚠️  NOTA: Em ambiente de teste, vamos criar um payment intent e confirmá-lo com cartão de teste" . PHP_EOL;
    
    // Cria payment intent
    $paymentIntentForRefund = $stripe->paymentIntents->create([
        'amount' => 4999, // R$ 49,99
        'currency' => 'brl',
        'customer' => $stripeCustomerId,
        'description' => 'Pagamento para teste de reembolso',
        'metadata' => ['test' => 'true', 'test_type' => 'refund']
    ]);
    
    echo "   ✅ Payment Intent criado: {$paymentIntentForRefund->id}" . PHP_EOL;
    echo "   ⚠️  Para testar reembolso completo, você precisa confirmar este payment intent com um cartão de teste" . PHP_EOL;
    echo "   ⚠️  Use o client_secret: {$paymentIntentForRefund->client_secret}" . PHP_EOL;
    echo "   ⚠️  Cartão de teste: 4242 4242 4242 4242 (qualquer data futura, qualquer CVC)" . PHP_EOL;
    echo "   ⚠️  Ou use a API do Stripe para confirmar automaticamente (requer setup adicional)" . PHP_EOL . PHP_EOL;
    
    // Tenta confirmar automaticamente com payment method de teste
    // NOTA: Em ambiente real, isso seria feito pelo frontend
    // Para teste automatizado, vamos tentar criar e confirmar com payment method
    try {
        // Cria payment method de teste
        $paymentMethod = $stripe->paymentMethods->create([
            'type' => 'card',
            'card' => [
                'number' => '4242424242424242',
                'exp_month' => 12,
                'exp_year' => date('Y') + 1,
                'cvc' => '123',
            ],
        ]);
        
        // Anexa ao customer
        $paymentMethod->attach(['customer' => $stripeCustomerId]);
        
        // Confirma o payment intent
        $confirmedPaymentIntent = $stripe->paymentIntents->confirm($paymentIntentForRefund->id, [
            'payment_method' => $paymentMethod->id
        ]);
        
        if ($confirmedPaymentIntent->status === 'succeeded') {
            echo "   ✅ Payment Intent confirmado com sucesso! Status: {$confirmedPaymentIntent->status}" . PHP_EOL;
            $succeededPaymentIntentId = $confirmedPaymentIntent->id;
        } else {
            echo "   ⚠️  Payment Intent não foi confirmado automaticamente. Status: {$confirmedPaymentIntent->status}" . PHP_EOL;
            echo "   ⚠️  Pulando testes de reembolso (requer payment intent com status 'succeeded')" . PHP_EOL;
            $succeededPaymentIntentId = null;
            $testsSkipped += 2; // Pula testes 4 e 5
        }
    } catch (\Exception $e) {
        echo "   ⚠️  Não foi possível confirmar payment intent automaticamente: " . $e->getMessage() . PHP_EOL;
        echo "   ⚠️  Pulando testes de reembolso (requer payment intent com status 'succeeded')" . PHP_EOL;
        $succeededPaymentIntentId = null;
        $testsSkipped += 2; // Pula testes 4 e 5
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Reembolso Total via API
    // ============================================
    if ($succeededPaymentIntentId) {
        echo "🧪 TESTE 4: Reembolso total via API..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/refunds');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'payment_intent_id' => $succeededPaymentIntentId,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'test' => 'true',
                    'test_type' => 'full_refund'
                ]
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201 || $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
                echo "   ✅ TESTE 4 PASSOU: Reembolso total criado com sucesso" . PHP_EOL;
                echo "   Refund ID: {$data['data']['id']}" . PHP_EOL;
                echo "   Valor reembolsado: R$ " . number_format($data['data']['amount'] / 100, 2, ',', '.') . PHP_EOL;
                echo "   Status: {$data['data']['status']}" . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 4 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 4 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⏭️  TESTE 4 PULADO: Requer payment intent com status 'succeeded'" . PHP_EOL . PHP_EOL;
    }

    // ============================================
    // TESTE 5: Reembolso Parcial via API
    // ============================================
    if ($succeededPaymentIntentId) {
        // Cria outro payment intent para reembolso parcial
        try {
            $paymentIntentPartial = $stripe->paymentIntents->create([
                'amount' => 9999, // R$ 99,99
                'currency' => 'brl',
                'customer' => $stripeCustomerId,
                'description' => 'Pagamento para teste de reembolso parcial',
                'metadata' => ['test' => 'true']
            ]);
            
            $paymentMethod2 = $stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'number' => '4242424242424242',
                    'exp_month' => 12,
                    'exp_year' => date('Y') + 1,
                    'cvc' => '123',
                ],
            ]);
            
            $paymentMethod2->attach(['customer' => $stripeCustomerId]);
            $confirmedPartial = $stripe->paymentIntents->confirm($paymentIntentPartial->id, [
                'payment_method' => $paymentMethod2->id
            ]);
            
            if ($confirmedPartial->status === 'succeeded') {
                echo "🧪 TESTE 5: Reembolso parcial via API..." . PHP_EOL;
                
                $ch = curl_init($baseUrl . '/v1/refunds');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $apiKey,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'payment_intent_id' => $confirmedPartial->id,
                        'amount' => 5000, // Reembolsa R$ 50,00 de R$ 99,99
                        'reason' => 'requested_by_customer',
                        'metadata' => [
                            'test' => 'true',
                            'test_type' => 'partial_refund'
                        ]
                    ])
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 201 || $httpCode === 200) {
                    $data = json_decode($response, true);
                    if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
                        $refundAmount = $data['data']['amount'];
                        if ($refundAmount === 5000) {
                            echo "   ✅ TESTE 5 PASSOU: Reembolso parcial criado com sucesso" . PHP_EOL;
                            echo "   Refund ID: {$data['data']['id']}" . PHP_EOL;
                            echo "   Valor reembolsado: R$ " . number_format($refundAmount / 100, 2, ',', '.') . PHP_EOL;
                            $testsPassed++;
                        } else {
                            echo "   ❌ TESTE 5 FALHOU: Valor do reembolso incorreto (esperado: 5000, recebido: {$refundAmount})" . PHP_EOL;
                            $testsFailed++;
                        }
                    } else {
                        echo "   ❌ TESTE 5 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
                        $testsFailed++;
                    }
                } else {
                    echo "   ❌ TESTE 5 FALHOU: HTTP {$httpCode}" . PHP_EOL;
                    echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
                    $testsFailed++;
                }
            } else {
                echo "⏭️  TESTE 5 PULADO: Não foi possível confirmar payment intent para reembolso parcial" . PHP_EOL;
                $testsSkipped++;
            }
        } catch (\Exception $e) {
            echo "⏭️  TESTE 5 PULADO: " . $e->getMessage() . PHP_EOL;
            $testsSkipped++;
        }
        echo PHP_EOL;
    } else {
        echo "⏭️  TESTE 5 PULADO: Requer payment intent com status 'succeeded'" . PHP_EOL . PHP_EOL;
    }

    // ============================================
    // TESTE 6: Validação de Payment Intent Inválido para Reembolso
    // ============================================
    echo "🧪 TESTE 6: Validação de payment intent inválido para reembolso..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/refunds');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'payment_intent_id' => 'pi_invalid_1234567890'
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($httpCode === 400 || (isset($data['error']))) {
        echo "   ✅ TESTE 6 PASSOU: Validação de payment intent inválido funcionando" . PHP_EOL;
        if ($httpCode !== 400) {
            echo "   ⚠️  (HTTP {$httpCode} mas mensagem de erro correta)" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 6 FALHOU: HTTP {$httpCode}, resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 7: Validação de Campos Obrigatórios (Reembolso)
    // ============================================
    echo "🧪 TESTE 7: Validação de campos obrigatórios para reembolso (sem payment_intent_id)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/refunds');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'amount' => 1000
            // payment_intent_id faltando
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($httpCode === 400 || (isset($data['error']) && (strpos(strtolower($data['error']), 'payment_intent_id') !== false || strpos(strtolower($data['error']), 'obrigatório') !== false))) {
        echo "   ✅ TESTE 7 PASSOU: Validação de campos obrigatórios funcionando" . PHP_EOL;
        if ($httpCode !== 400) {
            echo "   ⚠️  (HTTP {$httpCode} mas mensagem de erro correta)" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 7 FALHOU: HTTP {$httpCode}, resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // RESUMO FINAL
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║   RESUMO DOS TESTES                                           ║" . PHP_EOL;
    echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo "✅ Testes Passados: {$testsPassed}" . PHP_EOL;
    echo "❌ Testes Falhados: {$testsFailed}" . PHP_EOL;
    echo "⏭️  Testes Pulados: {$testsSkipped}" . PHP_EOL;
    echo "📊 Total: " . ($testsPassed + $testsFailed + $testsSkipped) . PHP_EOL . PHP_EOL;
    
    if ($testsFailed === 0) {
        echo "🎉 TODOS OS TESTES PASSARAM!" . PHP_EOL;
        exit(0);
    } else {
        echo "⚠️  ALGUNS TESTES FALHARAM!" . PHP_EOL;
        exit(1);
    }
    
} catch (\Exception $e) {
    echo PHP_EOL . "❌ ERRO FATAL: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}


<?php

/**
 * Teste Completo e Robusto de Cupons de Desconto
 * 
 * Este script testa:
 * 1. POST /v1/coupons - Criar cupom de desconto
 * 2. GET /v1/coupons - Listar cupons
 * 3. GET /v1/coupons/:id - Obter cupom específico
 * 4. DELETE /v1/coupons/:id - Deletar cupom
 * 5. Validações de erro (campos obrigatórios, cupom inválido)
 * 6. Teste direto dos métodos do StripeService
 * 7. Diferentes tipos de cupom (percentual, valor fixo, diferentes durações)
 * 
 * IMPORTANTE: Este teste cria recursos reais no Stripe (ambiente de teste)
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086'; // Substitua pela sua API key do tenant
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE COMPLETO DE CUPONS DE DESCONTO                       ║" . PHP_EOL;
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
    // TESTE 1: Criar Cupom Percentual (once)
    // ============================================
    echo "🧪 TESTE 1: Criar cupom percentual (once) via API..." . PHP_EOL;
    
    $couponData1 = [
        'id' => 'TEST_COUPON_' . time(),
        'percent_off' => 20.0,
        'duration' => 'once',
        'name' => 'Cupom Teste 20% OFF',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'percent_once'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/coupons');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($couponData1)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $couponId1 = $data['data']['id'];
            echo "   ✅ TESTE 1 PASSOU: Cupom percentual criado com sucesso" . PHP_EOL;
            echo "   Cupom ID: {$couponId1}" . PHP_EOL;
            echo "   Desconto: {$data['data']['percent_off']}%" . PHP_EOL;
            echo "   Duração: {$data['data']['duration']}" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 1 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 1 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 2: Criar Cupom Valor Fixo (once)
    // ============================================
    echo "🧪 TESTE 2: Criar cupom valor fixo (once) via API..." . PHP_EOL;
    
    $couponData2 = [
        'id' => 'TEST_AMOUNT_' . time(),
        'amount_off' => 1000, // R$ 10,00
        'currency' => 'brl',
        'duration' => 'once', // Stripe não permite 'forever' com amount_off, apenas com percent_off
        'name' => 'Cupom Teste R$ 10 OFF',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'amount_once'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/coupons');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($couponData2)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $couponId2 = $data['data']['id'];
            echo "   ✅ TESTE 2 PASSOU: Cupom valor fixo criado com sucesso" . PHP_EOL;
            echo "   Cupom ID: {$couponId2}" . PHP_EOL;
            $amountOff = $data['data']['amount_off'] ?? 0;
            echo "   Desconto: R$ " . number_format($amountOff / 100, 2, ',', '.') . PHP_EOL;
            echo "   Duração: {$data['data']['duration']}" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 2 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 300) . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 2 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 300) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: Criar Cupom Repeating
    // ============================================
    echo "🧪 TESTE 3: Criar cupom repeating via API..." . PHP_EOL;
    
    $couponData3 = [
        'id' => 'TEST_REPEATING_' . time(),
        'percent_off' => 15.0,
        'duration' => 'repeating',
        'duration_in_months' => 3,
        'name' => 'Cupom Teste 15% OFF - 3 meses',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'repeating'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/coupons');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($couponData3)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $couponId3 = $data['data']['id'];
            echo "   ✅ TESTE 3 PASSOU: Cupom repeating criado com sucesso" . PHP_EOL;
            echo "   Cupom ID: {$couponId3}" . PHP_EOL;
            echo "   Duração: {$data['data']['duration']} ({$data['data']['duration_in_months']} meses)" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 3 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 3 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Validação de Campos Obrigatórios
    // ============================================
    echo "🧪 TESTE 4: Validação de campos obrigatórios (sem duration)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/coupons');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'percent_off' => 10
            // duration faltando
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($httpCode === 400 || (isset($data['error']) && (strpos(strtolower($data['error']), 'duration') !== false || strpos(strtolower($data['error']), 'obrigatório') !== false))) {
        echo "   ✅ TESTE 4 PASSOU: Validação de campos obrigatórios funcionando" . PHP_EOL;
        if ($httpCode !== 400) {
            echo "   ⚠️  (HTTP {$httpCode} mas mensagem de erro correta)" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 4 FALHOU: HTTP {$httpCode}, resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 5: Listar Cupons
    // ============================================
    echo "🧪 TESTE 5: Listar cupons via API..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/coupons?limit=10');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']) && is_array($data['data'])) {
            echo "   ✅ TESTE 5 PASSOU: Listagem de cupons funcionando" . PHP_EOL;
            echo "   📊 Total de cupons retornados: " . count($data['data']) . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 5 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 5 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 6: Obter Cupom Específico
    // ============================================
    if (isset($couponId1)) {
        echo "🧪 TESTE 6: Obter cupom específico via API..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/coupons/' . $couponId1);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] === true && isset($data['data']['id']) && $data['data']['id'] === $couponId1) {
                echo "   ✅ TESTE 6 PASSOU: Obter cupom específico funcionando" . PHP_EOL;
                echo "   Cupom ID: {$data['data']['id']}" . PHP_EOL;
                echo "   Válido: " . ($data['data']['valid'] ? 'Sim' : 'Não') . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 6 FALHOU: Estrutura de resposta inválida ou ID incorreto" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 6 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⏭️  TESTE 6 PULADO: Nenhum cupom criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 7: Teste Direto do StripeService
    // ============================================
    echo "🧪 TESTE 7: Teste direto do método createCoupon() do StripeService..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        $coupon = $stripeService->createCoupon([
            'id' => 'TEST_DIRECT_' . time(),
            'percent_off' => 25.0,
            'duration' => 'once',
            'name' => 'Cupom Teste Direto'
        ]);
        
        if ($coupon instanceof \Stripe\Coupon && !empty($coupon->id)) {
            echo "   ✅ TESTE 7 PASSOU: Método createCoupon() funcionando corretamente" . PHP_EOL;
            echo "   Cupom ID: {$coupon->id}" . PHP_EOL;
            $testCouponId = $coupon->id; // Guarda para teste de delete
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 7 FALHOU: Retorno inválido do método" . PHP_EOL;
            $testsFailed++;
        }
    } catch (\Exception $e) {
        echo "   ❌ TESTE 7 FALHOU: " . $e->getMessage() . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 8: Deletar Cupom
    // ============================================
    if (isset($testCouponId)) {
        echo "🧪 TESTE 8: Deletar cupom via API..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/coupons/' . $testCouponId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] === true && isset($data['data']['deleted']) && $data['data']['deleted'] === true) {
                echo "   ✅ TESTE 8 PASSOU: Cupom deletado com sucesso" . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 8 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 8 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⏭️  TESTE 8 PULADO: Nenhum cupom criado para deletar" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 9: Validação de Cupom Inválido
    // ============================================
    echo "🧪 TESTE 9: Validação de cupom inválido (GET)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/coupons/invalid_coupon_1234567890');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($httpCode === 404 || (isset($data['error']))) {
        echo "   ✅ TESTE 9 PASSOU: Validação de cupom inválido funcionando" . PHP_EOL;
        if ($httpCode !== 404) {
            echo "   ⚠️  (HTTP {$httpCode} mas mensagem de erro correta)" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 9 FALHOU: HTTP {$httpCode}, resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 10: Validação de Estrutura de Resposta
    // ============================================
    echo "🧪 TESTE 10: Validação da estrutura de resposta (listagem)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/coupons?limit=1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        $requiredFields = ['success', 'data', 'count'];
        $hasAllFields = true;
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $hasAllFields = false;
                break;
            }
        }
        
        if ($hasAllFields && !empty($data['data'])) {
            $coupon = $data['data'][0];
            $couponRequiredFields = ['id', 'duration', 'valid', 'created'];
            $couponHasAllFields = true;
            foreach ($couponRequiredFields as $field) {
                if (!isset($coupon[$field])) {
                    $couponHasAllFields = false;
                    break;
                }
            }
            
            if ($couponHasAllFields) {
                echo "   ✅ TESTE 10 PASSOU: Estrutura de resposta válida" . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 10 FALHOU: Estrutura do cupom inválida" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 10 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 10 FALHOU: HTTP {$httpCode}" . PHP_EOL;
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


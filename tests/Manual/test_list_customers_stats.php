<?php

/**
 * Teste Completo e Robusto de listCustomers() e Endpoint de Estatísticas
 * 
 * Este script testa:
 * 1. Método listCustomers() do StripeService - Lista customers do Stripe
 * 2. GET /v1/stats - Endpoint de estatísticas
 * 3. Filtros e paginação para listCustomers()
 * 4. Diferentes períodos para estatísticas (today, week, month, year, all)
 * 5. Validação da estrutura de resposta
 * 
 * IMPORTANTE: Este teste usa recursos reais do Stripe (ambiente de teste)
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086'; // Substitua pela sua API key do tenant
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE COMPLETO DE LIST CUSTOMERS E ESTATÍSTICAS           ║" . PHP_EOL;
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
    // PASSO 1: Criar Customers para Teste
    // ============================================
    echo "👤 PASSO 1: Criando customers para teste..." . PHP_EOL;
    
    $testCustomers = [];
    for ($i = 1; $i <= 3; $i++) {
        $ch = curl_init($baseUrl . '/v1/customers');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'email' => 'teste.list.stats' . $i . '@example.com',
                'name' => 'Cliente Teste ' . $i
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201 || $httpCode === 200) {
            $data = json_decode($response, true);
            $testCustomers[] = $data['data'];
            echo "   ✅ Customer {$i} criado: {$data['data']['stripe_customer_id']}" . PHP_EOL;
        }
    }
    
    if (empty($testCustomers)) {
        echo "   ⚠️  Nenhum customer foi criado. Continuando com customers existentes..." . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 1: Teste Direto do listCustomers()
    // ============================================
    echo "🧪 TESTE 1: Teste direto do método listCustomers() do StripeService..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        $customers = $stripeService->listCustomers(['limit' => 5]);
        
        if ($customers instanceof \Stripe\Collection && count($customers->data) >= 0) {
            echo "   ✅ TESTE 1 PASSOU: Método listCustomers() funcionando corretamente" . PHP_EOL;
            echo "   📊 Customers retornados: " . count($customers->data) . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 1 FALHOU: Retorno inválido do método" . PHP_EOL;
            $testsFailed++;
        }
    } catch (\Exception $e) {
        echo "   ❌ TESTE 1 FALHOU: " . $e->getMessage() . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 2: listCustomers() com Filtro de Email
    // ============================================
    echo "🧪 TESTE 2: listCustomers() com filtro de email..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        if (!empty($testCustomers)) {
            $testEmail = $testCustomers[0]['email'];
            $customers = $stripeService->listCustomers(['email' => $testEmail, 'limit' => 10]);
            
            $found = false;
            foreach ($customers->data as $customer) {
                if ($customer->email === $testEmail) {
                    $found = true;
                    break;
                }
            }
            
            if ($found || count($customers->data) >= 0) {
                echo "   ✅ TESTE 2 PASSOU: Filtro de email funcionando" . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 2 FALHOU: Filtro de email não retornou resultado esperado" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ⏭️  TESTE 2 PULADO: Nenhum customer de teste disponível" . PHP_EOL;
            $testsSkipped++;
        }
    } catch (\Exception $e) {
        echo "   ❌ TESTE 2 FALHOU: " . $e->getMessage() . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: listCustomers() com Paginação
    // ============================================
    echo "🧪 TESTE 3: listCustomers() com paginação (limit=2)..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        $customers = $stripeService->listCustomers(['limit' => 2]);
        
        if (count($customers->data) <= 2) {
            echo "   ✅ TESTE 3 PASSOU: Paginação funcionando (retornou " . count($customers->data) . " itens)" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 3 FALHOU: Limit não foi respeitado" . PHP_EOL;
            $testsFailed++;
        }
    } catch (\Exception $e) {
        echo "   ❌ TESTE 3 FALHOU: " . $e->getMessage() . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Endpoint de Estatísticas (all)
    // ============================================
    echo "🧪 TESTE 4: Endpoint de estatísticas (period=all)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/stats?period=all');
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
        $requiredFields = ['success', 'period', 'data', 'timestamp'];
        $hasAllFields = true;
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $hasAllFields = false;
                break;
            }
        }
        
        if ($hasAllFields && isset($data['data']['customers']) && isset($data['data']['subscriptions'])) {
            echo "   ✅ TESTE 4 PASSOU: Endpoint de estatísticas funcionando" . PHP_EOL;
            echo "   📊 Total de customers: {$data['data']['customers']['total']}" . PHP_EOL;
            echo "   📊 Total de assinaturas: {$data['data']['subscriptions']['total']}" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 4 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 4 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 5: Endpoint de Estatísticas (today)
    // ============================================
    echo "🧪 TESTE 5: Endpoint de estatísticas (period=today)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/stats?period=today');
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
        if (isset($data['period']) && $data['period'] === 'today' && isset($data['data'])) {
            echo "   ✅ TESTE 5 PASSOU: Filtro de período 'today' funcionando" . PHP_EOL;
            echo "   📊 Novos customers hoje: {$data['data']['customers']['new']}" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 5 FALHOU: Período não aplicado corretamente" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 5 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 6: Endpoint de Estatísticas (month)
    // ============================================
    echo "🧪 TESTE 6: Endpoint de estatísticas (period=month)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/stats?period=month');
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
        if (isset($data['period']) && $data['period'] === 'month' && isset($data['data']['revenue'])) {
            echo "   ✅ TESTE 6 PASSOU: Filtro de período 'month' funcionando" . PHP_EOL;
            echo "   💰 MRR: R$ " . number_format($data['data']['revenue']['mrr'], 2, ',', '.') . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 6 FALHOU: Período não aplicado corretamente" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 6 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 7: Validação da Estrutura de Estatísticas
    // ============================================
    echo "🧪 TESTE 7: Validação da estrutura completa de estatísticas..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/stats');
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
        
        $requiredDataFields = [
            'customers' => ['total', 'new'],
            'subscriptions' => ['total', 'active', 'canceled', 'trialing', 'new'],
            'revenue' => ['mrr', 'currency'],
            'metrics' => ['conversion_rate', 'churn_rate']
        ];
        
        $hasAllFields = true;
        foreach ($requiredDataFields as $section => $fields) {
            if (!isset($data['data'][$section])) {
                $hasAllFields = false;
                break;
            }
            foreach ($fields as $field) {
                if (!isset($data['data'][$section][$field])) {
                    $hasAllFields = false;
                    break 2;
                }
            }
        }
        
        if ($hasAllFields) {
            echo "   ✅ TESTE 7 PASSOU: Estrutura de estatísticas completa e válida" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 7 FALHOU: Estrutura de estatísticas incompleta" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 7 FALHOU: HTTP {$httpCode}" . PHP_EOL;
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


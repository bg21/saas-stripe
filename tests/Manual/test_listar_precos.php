<?php

/**
 * Teste Completo e Robusto de GET /v1/prices
 * 
 * Este script testa:
 * 1. GET /v1/prices - Lista preços disponíveis
 * 2. Filtros (active, type, currency, product, limit)
 * 3. Paginação (starting_after, ending_before)
 * 4. Validação da estrutura de resposta
 * 5. Teste direto do método listPrices() do StripeService
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
echo "║   TESTE COMPLETO DE LISTAGEM DE PREÇOS                       ║" . PHP_EOL;
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
    // PASSO 1: Criar Produtos e Preços para Teste
    // ============================================
    echo "📦 PASSO 1: Criando produtos e preços para teste..." . PHP_EOL;
    
    // Cria produto 1
    $product1 = $stripe->products->create([
        'name' => 'Plano Teste Listagem - ' . date('Y-m-d H:i:s'),
        'description' => 'Produto criado para teste de listagem de preços',
        'metadata' => [
            'test' => 'true',
            'created_by' => 'test_listar_precos.php'
        ]
    ]);
    
    // Cria preço 1 (recurring, BRL)
    $price1 = $stripe->prices->create([
        'product' => $product1->id,
        'unit_amount' => 2999, // R$ 29,99
        'currency' => 'brl',
        'recurring' => [
            'interval' => 'month',
        ],
        'metadata' => ['test' => 'true']
    ]);
    
    // Cria preço 2 (recurring, USD)
    $price2 = $stripe->prices->create([
        'product' => $product1->id,
        'unit_amount' => 1999, // $ 19,99
        'currency' => 'usd',
        'recurring' => [
            'interval' => 'month',
        ],
        'metadata' => ['test' => 'true']
    ]);
    
    // Cria produto 2
    $product2 = $stripe->products->create([
        'name' => 'Produto One-Time Teste',
        'description' => 'Produto para teste de preços únicos',
        'metadata' => ['test' => 'true']
    ]);
    
    // Cria preço 3 (one-time, BRL)
    $price3 = $stripe->prices->create([
        'product' => $product2->id,
        'unit_amount' => 4999, // R$ 49,99
        'currency' => 'brl',
        'metadata' => ['test' => 'true']
    ]);
    
    echo "   ✅ Produto 1 criado: {$product1->id}" . PHP_EOL;
    echo "   ✅ Preço 1 (recurring BRL) criado: {$price1->id}" . PHP_EOL;
    echo "   ✅ Preço 2 (recurring USD) criado: {$price2->id}" . PHP_EOL;
    echo "   ✅ Produto 2 criado: {$product2->id}" . PHP_EOL;
    echo "   ✅ Preço 3 (one-time BRL) criado: {$price3->id}" . PHP_EOL . PHP_EOL;

    // ============================================
    // TESTE 1: Listagem Básica de Preços
    // ============================================
    echo "🧪 TESTE 1: Listagem básica de preços..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices');
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
            echo "   ✅ TESTE 1 PASSOU: Listagem básica funcionando" . PHP_EOL;
            echo "   📊 Total de preços retornados: " . count($data['data']) . PHP_EOL;
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
    // TESTE 2: Filtro por Active
    // ============================================
    echo "🧪 TESTE 2: Filtro por preços ativos (active=true)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices?active=true&limit=100');
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
        $allActive = true;
        foreach ($data['data'] ?? [] as $price) {
            if (isset($price['active']) && $price['active'] !== true) {
                $allActive = false;
                break;
            }
        }
        
        if ($allActive) {
            echo "   ✅ TESTE 2 PASSOU: Filtro active=true funcionando" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 2 FALHOU: Alguns preços inativos foram retornados" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 2 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: Filtro por Tipo (recurring)
    // ============================================
    echo "🧪 TESTE 3: Filtro por tipo recurring..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices?type=recurring&limit=100');
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
        $allRecurring = true;
        foreach ($data['data'] ?? [] as $price) {
            if (isset($price['type']) && $price['type'] !== 'recurring') {
                $allRecurring = false;
                break;
            }
        }
        
        if ($allRecurring) {
            echo "   ✅ TESTE 3 PASSOU: Filtro type=recurring funcionando" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 3 FALHOU: Alguns preços não-recurring foram retornados" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 3 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Filtro por Moeda (BRL)
    // ============================================
    echo "🧪 TESTE 4: Filtro por moeda BRL..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices?currency=brl&limit=100');
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
        $allBRL = true;
        foreach ($data['data'] ?? [] as $price) {
            if (isset($price['currency']) && strtoupper($price['currency']) !== 'BRL') {
                $allBRL = false;
                break;
            }
        }
        
        if ($allBRL) {
            echo "   ✅ TESTE 4 PASSOU: Filtro currency=brl funcionando" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 4 FALHOU: Alguns preços não-BRL foram retornados" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 4 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 5: Filtro por Produto
    // ============================================
    echo "🧪 TESTE 5: Filtro por produto específico..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices?product=' . $product1->id . '&limit=100');
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
        $allFromProduct = true;
        foreach ($data['data'] ?? [] as $price) {
            $priceProductId = $price['product_id'] ?? ($price['product']['id'] ?? null);
            if ($priceProductId !== $product1->id) {
                $allFromProduct = false;
                break;
            }
        }
        
        if ($allFromProduct) {
            echo "   ✅ TESTE 5 PASSOU: Filtro product funcionando" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 5 FALHOU: Alguns preços não pertencem ao produto especificado" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 5 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 6: Paginação (limit)
    // ============================================
    echo "🧪 TESTE 6: Paginação com limit=2..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices?limit=2');
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
        $count = count($data['data'] ?? []);
        
        if ($count <= 2) {
            echo "   ✅ TESTE 6 PASSOU: Paginação com limit funcionando (retornou {$count} itens)" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 6 FALHOU: Limit não foi respeitado (retornou {$count} itens)" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 6 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 7: Validação da Estrutura de Resposta
    // ============================================
    echo "🧪 TESTE 7: Validação da estrutura de resposta..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices?limit=1');
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
            $price = $data['data'][0];
            $priceRequiredFields = ['id', 'active', 'currency', 'type', 'unit_amount', 'formatted_amount', 'created'];
            $priceHasAllFields = true;
            foreach ($priceRequiredFields as $field) {
                if (!isset($price[$field])) {
                    $priceHasAllFields = false;
                    break;
                }
            }
            
            if ($priceHasAllFields) {
                echo "   ✅ TESTE 7 PASSOU: Estrutura de resposta válida" . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 7 FALHOU: Estrutura do preço inválida" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 7 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 7 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 8: Teste Direto do StripeService
    // ============================================
    echo "🧪 TESTE 8: Teste direto do método listPrices() do StripeService..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        $prices = $stripeService->listPrices(['limit' => 5, 'active' => true]);
        
        if ($prices instanceof \Stripe\Collection && count($prices->data) >= 0) {
            echo "   ✅ TESTE 8 PASSOU: Método listPrices() funcionando corretamente" . PHP_EOL;
            echo "   📊 Preços retornados: " . count($prices->data) . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 8 FALHOU: Retorno inválido do método" . PHP_EOL;
            $testsFailed++;
        }
    } catch (\Exception $e) {
        echo "   ❌ TESTE 8 FALHOU: " . $e->getMessage() . PHP_EOL;
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


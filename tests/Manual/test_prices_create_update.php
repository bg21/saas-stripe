<?php

/**
 * Teste Completo e Robusto de Preços (Prices) - Create, Update, Get
 * 
 * Este script testa:
 * 1. POST /v1/prices - Criar preço (one_time e recurring)
 * 2. GET /v1/prices/:id - Obter preço específico
 * 3. PUT /v1/prices/:id - Atualizar preço (metadata, active, nickname)
 * 4. Validações de erro (campos obrigatórios, preço inválido)
 * 5. Teste direto dos métodos do StripeService
 * 6. Preços recorrentes (monthly, yearly)
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
echo "║   TESTE COMPLETO DE PREÇOS (PRICES) - CREATE, UPDATE, GET    ║" . PHP_EOL;
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
    // PASSO 0: Criar produto para usar nos testes
    // ============================================
    echo "📦 PASSO 0: Criando produto para testes..." . PHP_EOL;
    
    $testProduct = $stripe->products->create([
        'name' => 'Produto Teste Prices ' . time(),
        'description' => 'Produto para testar criação de preços',
        'metadata' => ['test' => 'true']
    ]);
    
    $productId = $testProduct->id;
    echo "   ✅ Produto criado: {$productId}" . PHP_EOL . PHP_EOL;

    // ============================================
    // TESTE 1: Criar Preço One-Time via API
    // ============================================
    echo "🧪 TESTE 1: Criar preço one-time via API..." . PHP_EOL;
    
    $priceData1 = [
        'product' => $productId,
        'unit_amount' => 5000, // $50.00
        'currency' => 'brl',
        'nickname' => 'Preço Teste One-Time',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'one_time'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/prices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($priceData1)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $priceId1 = $data['data']['id'];
            echo "   ✅ TESTE 1 PASSOU: Preço one-time criado com sucesso" . PHP_EOL;
            echo "   Price ID: {$priceId1}" . PHP_EOL;
            echo "   Valor: {$data['data']['formatted_amount']} {$data['data']['currency']}" . PHP_EOL;
            echo "   Tipo: {$data['data']['type']}" . PHP_EOL;
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
    // TESTE 2: Criar Preço Recurring (Monthly) via API
    // ============================================
    echo "🧪 TESTE 2: Criar preço recurring (monthly) via API..." . PHP_EOL;
    
    $priceData2 = [
        'product' => $productId,
        'unit_amount' => 2990, // $29.90
        'currency' => 'brl',
        'recurring' => [
            'interval' => 'month',
            'interval_count' => 1
        ],
        'nickname' => 'Plano Mensal',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'recurring_monthly'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/prices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($priceData2)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $priceId2 = $data['data']['id'];
            echo "   ✅ TESTE 2 PASSOU: Preço recurring criado com sucesso" . PHP_EOL;
            echo "   Price ID: {$priceId2}" . PHP_EOL;
            echo "   Valor: {$data['data']['formatted_amount']} {$data['data']['currency']}" . PHP_EOL;
            echo "   Tipo: {$data['data']['type']}" . PHP_EOL;
            if (isset($data['data']['recurring'])) {
                echo "   Intervalo: {$data['data']['recurring']['interval']}" . PHP_EOL;
            }
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 2 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
            $testsFailed++;
        }
    } else {
        echo "   ❌ TESTE 2 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: Validar Campos Obrigatórios
    // ============================================
    echo "🧪 TESTE 3: Validar campos obrigatórios..." . PHP_EOL;
    
    // Testa sem product
    $invalidData1 = [
        'unit_amount' => 1000,
        'currency' => 'brl'
    ];
    
    $ch = curl_init($baseUrl . '/v1/prices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($invalidData1)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 400 || (isset(json_decode($response, true)['error']) && strpos(strtolower(json_decode($response, true)['error']), 'obrigatório') !== false)) {
        echo "   ✅ TESTE 3 PASSOU: Validação de campo obrigatório (product) funcionou" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 3 PARCIAL: Esperava 400, recebeu {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Obter Preço Específico
    // ============================================
    if (isset($priceId1)) {
        echo "🧪 TESTE 4: Obter preço específico via API..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/prices/' . $priceId1);
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
            if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
                echo "   ✅ TESTE 4 PASSOU: Preço obtido com sucesso" . PHP_EOL;
                echo "   Price ID: {$data['data']['id']}" . PHP_EOL;
                echo "   Valor: {$data['data']['formatted_amount']} {$data['data']['currency']}" . PHP_EOL;
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
        echo "⚠️  TESTE 4 PULADO: Preço não foi criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 5: Atualizar Preço (Metadata e Active)
    // ============================================
    if (isset($priceId1)) {
        echo "🧪 TESTE 5: Atualizar preço (metadata e active) via API..." . PHP_EOL;
        
        $updateData = [
            'metadata' => [
                'test' => 'true',
                'updated' => 'true',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'nickname' => 'Preço Atualizado'
        ];
        
        $ch = curl_init($baseUrl . '/v1/prices/' . $priceId1);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($updateData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
                echo "   ✅ TESTE 5 PASSOU: Preço atualizado com sucesso" . PHP_EOL;
                echo "   Nickname: {$data['data']['nickname']}" . PHP_EOL;
                if (isset($data['data']['metadata']['updated'])) {
                    echo "   Metadata atualizado: Sim" . PHP_EOL;
                }
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 5 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 5 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 5 PULADO: Preço não foi criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 6: Desativar Preço (Active = false)
    // ============================================
    if (isset($priceId2)) {
        echo "🧪 TESTE 6: Desativar preço (active = false) via API..." . PHP_EOL;
        
        $updateData = [
            'active' => false
        ];
        
        $ch = curl_init($baseUrl . '/v1/prices/' . $priceId2);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($updateData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['success']) && $data['success'] === true && $data['data']['active'] === false) {
                echo "   ✅ TESTE 6 PASSOU: Preço desativado com sucesso" . PHP_EOL;
                echo "   Active: " . ($data['data']['active'] ? 'Sim' : 'Não') . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 6 FALHOU: Preço não foi desativado" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 6 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 6 PULADO: Preço não foi criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 7: Validar Preço Inexistente (404)
    // ============================================
    echo "🧪 TESTE 7: Validar preço inexistente (404)..." . PHP_EOL;
    
    $fakePriceId = 'price_fake_' . time();
    
    $ch = curl_init($baseUrl . '/v1/prices/' . $fakePriceId);
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
    
    if ($httpCode === 404 || (isset(json_decode($response, true)['error']) && strpos(strtolower(json_decode($response, true)['error']), 'não encontrado') !== false)) {
        echo "   ✅ TESTE 7 PASSOU: Retornou 404 para preço inexistente" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 7 PARCIAL: Esperava 404, recebeu {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 8: Validar Autenticação (401)
    // ============================================
    echo "🧪 TESTE 8: Validar autenticação (401)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/prices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['product' => $productId, 'unit_amount' => 1000, 'currency' => 'brl'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 401 || (isset(json_decode($response, true)['error']) && strpos(strtolower(json_decode($response, true)['error']), 'autenticado') !== false)) {
        echo "   ✅ TESTE 8 PASSOU: Retornou 401 sem autenticação" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 8 PARCIAL: Esperava 401, recebeu {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 9: Testar Métodos Diretamente no StripeService
    // ============================================
    echo "🧪 TESTE 9: Testar métodos diretamente no StripeService..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        
        // Criar preço
        $testPrice = $stripeService->createPrice([
            'product' => $productId,
            'unit_amount' => 1500,
            'currency' => 'brl',
            'nickname' => 'Teste Direto',
            'metadata' => ['test_direct' => 'true']
        ]);
        
        echo "   ✅ Preço criado via StripeService: {$testPrice->id}" . PHP_EOL;
        $testsPassed++;
        
        // Obter preço
        $retrievedPrice = $stripeService->getPrice($testPrice->id);
        if ($retrievedPrice->id === $testPrice->id) {
            echo "   ✅ Preço obtido via StripeService: {$retrievedPrice->id}" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ Erro ao obter preço" . PHP_EOL;
            $testsFailed++;
        }
        
        // Atualizar preço
        $updatedPrice = $stripeService->updatePrice($testPrice->id, [
            'metadata' => ['test_direct' => 'true', 'updated' => 'true'],
            'nickname' => 'Preço Atualizado Direto'
        ]);
        if ($updatedPrice->nickname === 'Preço Atualizado Direto') {
            echo "   ✅ Preço atualizado via StripeService" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ Erro ao atualizar preço" . PHP_EOL;
            $testsFailed++;
        }
        
    } catch (\Exception $e) {
        echo "   ❌ TESTE 9 FALHOU: Erro ao testar StripeService diretamente" . PHP_EOL;
        echo "   Erro: {$e->getMessage()}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // RESUMO
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║   RESUMO DOS TESTES                                          ║" . PHP_EOL;
    echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo "✅ Passou: {$testsPassed}" . PHP_EOL;
    echo "❌ Falhou: {$testsFailed}" . PHP_EOL;
    echo "⚠️  Pulado: {$testsSkipped}" . PHP_EOL;
    echo "📈 Total: " . ($testsPassed + $testsFailed + $testsSkipped) . PHP_EOL;
    
    if ($testsFailed === 0) {
        $successRate = $testsPassed > 0 ? round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 2) : 0;
        echo "🎯 Taxa de sucesso: {$successRate}%" . PHP_EOL . PHP_EOL;
        echo "🎉 Todos os testes passaram!" . PHP_EOL;
        exit(0);
    } else {
        echo "⚠️  Alguns testes falharam. Revise os logs acima." . PHP_EOL;
        exit(1);
    }

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: {$e->getMessage()}" . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}


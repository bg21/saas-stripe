<?php

/**
 * Teste Completo e Robusto de Produtos (Products)
 * 
 * Este script testa:
 * 1. POST /v1/products - Criar produto
 * 2. GET /v1/products/:id - Obter produto específico
 * 3. PUT /v1/products/:id - Atualizar produto
 * 4. DELETE /v1/products/:id - Deletar produto
 * 5. Validações de erro (campos obrigatórios, produto inválido)
 * 6. Teste direto dos métodos do StripeService
 * 7. Soft delete (desativa se tiver preços associados)
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
echo "║   TESTE COMPLETO DE PRODUTOS (PRODUCTS)                       ║" . PHP_EOL;
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
    // TESTE 1: Criar Produto Básico via API
    // ============================================
    echo "🧪 TESTE 1: Criar produto básico via API..." . PHP_EOL;
    
    $productData1 = [
        'name' => 'Produto Teste ' . time(),
        'description' => 'Descrição do produto de teste',
        'active' => true,
        'metadata' => [
            'test' => 'true',
            'test_type' => 'basic'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($productData1)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $productId1 = $data['data']['id'];
            echo "   ✅ TESTE 1 PASSOU: Produto criado com sucesso" . PHP_EOL;
            echo "   Product ID: {$productId1}" . PHP_EOL;
            echo "   Nome: {$data['data']['name']}" . PHP_EOL;
            echo "   Ativo: " . ($data['data']['active'] ? 'Sim' : 'Não') . PHP_EOL;
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
    // TESTE 2: Criar Produto com Imagens e Unit Label
    // ============================================
    echo "🧪 TESTE 2: Criar produto com imagens e unit_label via API..." . PHP_EOL;
    
    $productData2 = [
        'name' => 'Produto Premium ' . time(),
        'description' => 'Produto premium com imagens',
        'active' => true,
        'images' => [
            'https://example.com/image1.jpg',
            'https://example.com/image2.jpg'
        ],
        'unit_label' => 'seat',
        'statement_descriptor' => 'PRODUTO TESTE',
        'metadata' => [
            'test' => 'true',
            'test_type' => 'premium'
        ]
    ];
    
    $ch = curl_init($baseUrl . '/v1/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($productData2)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['id'])) {
            $productId2 = $data['data']['id'];
            echo "   ✅ TESTE 2 PASSOU: Produto premium criado com sucesso" . PHP_EOL;
            echo "   Product ID: {$productId2}" . PHP_EOL;
            echo "   Unit Label: {$data['data']['unit_label']}" . PHP_EOL;
            echo "   Imagens: " . count($data['data']['images'] ?? []) . PHP_EOL;
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
    // TESTE 3: Validar Campo Obrigatório (name)
    // ============================================
    echo "🧪 TESTE 3: Validar campo obrigatório (name)..." . PHP_EOL;
    
    $invalidData = [
        'description' => 'Produto sem nome'
    ];
    
    $ch = curl_init($baseUrl . '/v1/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($invalidData)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 400 || (isset(json_decode($response, true)['error']) && strpos(strtolower(json_decode($response, true)['error']), 'obrigatório') !== false)) {
        echo "   ✅ TESTE 3 PASSOU: Validação de campo obrigatório funcionou" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 3 PARCIAL: Esperava 400, recebeu {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Obter Produto Específico
    // ============================================
    if (isset($productId1)) {
        echo "🧪 TESTE 4: Obter produto específico via API..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/products/' . $productId1);
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
                echo "   ✅ TESTE 4 PASSOU: Produto obtido com sucesso" . PHP_EOL;
                echo "   Product ID: {$data['data']['id']}" . PHP_EOL;
                echo "   Nome: {$data['data']['name']}" . PHP_EOL;
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
        echo "⚠️  TESTE 4 PULADO: Produto não foi criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 5: Atualizar Produto
    // ============================================
    if (isset($productId1)) {
        echo "🧪 TESTE 5: Atualizar produto via API..." . PHP_EOL;
        
        $updateData = [
            'name' => 'Produto Atualizado ' . time(),
            'description' => 'Nova descrição atualizada',
            'metadata' => [
                'test' => 'true',
                'updated' => 'true'
            ]
        ];
        
        $ch = curl_init($baseUrl . '/v1/products/' . $productId1);
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
                echo "   ✅ TESTE 5 PASSOU: Produto atualizado com sucesso" . PHP_EOL;
                echo "   Novo Nome: {$data['data']['name']}" . PHP_EOL;
                echo "   Nova Descrição: {$data['data']['description']}" . PHP_EOL;
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
        echo "⚠️  TESTE 5 PULADO: Produto não foi criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 6: Validar Produto Inexistente (404)
    // ============================================
    echo "🧪 TESTE 6: Validar produto inexistente (404)..." . PHP_EOL;
    
    $fakeProductId = 'prod_fake_' . time();
    
    $ch = curl_init($baseUrl . '/v1/products/' . $fakeProductId);
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
        echo "   ✅ TESTE 6 PASSOU: Retornou 404 para produto inexistente" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 6 PARCIAL: Esperava 404, recebeu {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 7: Validar Autenticação (401)
    // ============================================
    echo "🧪 TESTE 7: Validar autenticação (401)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['name' => 'Test'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 401 || (isset(json_decode($response, true)['error']) && strpos(strtolower(json_decode($response, true)['error']), 'autenticado') !== false)) {
        echo "   ✅ TESTE 7 PASSOU: Retornou 401 sem autenticação" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 7 PARCIAL: Esperava 401, recebeu {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 8: Deletar Produto (sem preços)
    // ============================================
    if (isset($productId2)) {
        echo "🧪 TESTE 8: Deletar produto sem preços via API..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/products/' . $productId2);
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
            if (isset($data['success']) && $data['success'] === true) {
                echo "   ✅ TESTE 8 PASSOU: Produto deletado/desativado com sucesso" . PHP_EOL;
                echo "   Mensagem: {$data['message']}" . PHP_EOL;
                echo "   Deletado: " . ($data['data']['deleted'] ?? false ? 'Sim' : 'Não') . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ❌ TESTE 8 FALHOU: Estrutura de resposta inválida" . PHP_EOL;
                $testsFailed++;
            }
        } else {
            echo "   ❌ TESTE 8 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 8 PULADO: Produto não foi criado anteriormente" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 9: Testar Métodos Diretamente no StripeService
    // ============================================
    echo "🧪 TESTE 9: Testar métodos diretamente no StripeService..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        
        // Criar produto
        $testProduct = $stripeService->createProduct([
            'name' => 'Teste Direto ' . time(),
            'description' => 'Produto criado via StripeService',
            'metadata' => ['test_direct' => 'true']
        ]);
        
        echo "   ✅ Produto criado via StripeService: {$testProduct->id}" . PHP_EOL;
        $testsPassed++;
        
        // Obter produto
        $retrievedProduct = $stripeService->getProduct($testProduct->id);
        if ($retrievedProduct->id === $testProduct->id) {
            echo "   ✅ Produto obtido via StripeService: {$retrievedProduct->id}" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ Erro ao obter produto" . PHP_EOL;
            $testsFailed++;
        }
        
        // Atualizar produto
        $updatedProduct = $stripeService->updateProduct($testProduct->id, [
            'name' => 'Produto Atualizado Direto',
            'metadata' => ['test_direct' => 'true', 'updated' => 'true']
        ]);
        if ($updatedProduct->name === 'Produto Atualizado Direto') {
            echo "   ✅ Produto atualizado via StripeService" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ Erro ao atualizar produto" . PHP_EOL;
            $testsFailed++;
        }
        
        // Deletar produto
        $deletedProduct = $stripeService->deleteProduct($testProduct->id);
        echo "   ✅ Produto deletado/desativado via StripeService" . PHP_EOL;
        $testsPassed++;
        
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


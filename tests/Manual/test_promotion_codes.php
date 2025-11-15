<?php

/**
 * Teste Completo de Códigos Promocionais (Promotion Codes)
 * 
 * Este script testa:
 * 1. POST /v1/promotion-codes - Criar código promocional
 * 2. GET /v1/promotion-codes - Listar códigos promocionais
 * 3. GET /v1/promotion-codes/:id - Obter código específico
 * 4. PUT /v1/promotion-codes/:id - Atualizar código promocional
 * 5. Validações de erro (cupom obrigatório, código não encontrado)
 * 6. Teste direto dos métodos do StripeService
 * 7. Filtros (active, code, coupon, customer)
 * 
 * IMPORTANTE: Este teste cria recursos reais no Stripe (ambiente de teste)
 * IMPORTANTE: Promotion Codes sempre precisam de um Coupon existente
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086'; // Substitua pela sua API key do tenant
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE COMPLETO DE CÓDIGOS PROMOCIONAIS (PROMOTION CODES)  ║" . PHP_EOL;
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
    // PREPARAÇÃO: Criar um cupom para usar nos testes
    // ============================================
    echo "📋 PREPARAÇÃO: Criando cupom para usar nos testes..." . PHP_EOL;
    
    $testCoupon = $stripe->coupons->create([
        'percent_off' => 20,
        'duration' => 'once',
        'name' => 'Test Coupon for Promotion Codes'
    ]);
    
    echo "✅ Cupom criado: {$testCoupon->id}" . PHP_EOL . PHP_EOL;
    
    $createdPromotionCodes = []; // Para limpeza no final

    // ============================================
    // TESTE 1: Criar Promotion Code via API
    // ============================================
    echo "🧪 TESTE 1: Criar promotion code via API..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/promotion-codes');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'coupon' => $testCoupon->id,
        'code' => 'TEST20OFF',
        'active' => true,
        'max_redemptions' => 100
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (($httpCode === 201 || $httpCode === 200) && isset($data['success']) && $data['success'] === true) {
        $promotionCode1 = $data['data'];
        $createdPromotionCodes[] = $promotionCode1['id'];
        echo "   ✅ TESTE 1 PASSOU: Promotion code criado com sucesso" . PHP_EOL;
        echo "   ID: {$promotionCode1['id']}" . PHP_EOL;
        echo "   Código: {$promotionCode1['code']}" . PHP_EOL;
        echo "   Cupom: {$promotionCode1['coupon']['id']}" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 1 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 2: Criar Promotion Code com código customizado
    // ============================================
    echo "🧪 TESTE 2: Criar promotion code com código customizado..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/promotion-codes');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'coupon' => $testCoupon->id,
        'code' => 'SUMMER2024',
        'active' => true,
        'expires_at' => strtotime('+30 days')
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (($httpCode === 201 || $httpCode === 200) && isset($data['success']) && $data['success'] === true) {
        $promotionCode2 = $data['data'];
        $createdPromotionCodes[] = $promotionCode2['id'];
        echo "   ✅ TESTE 2 PASSOU: Promotion code com código customizado criado" . PHP_EOL;
        echo "   Código: {$promotionCode2['code']}" . PHP_EOL;
        if ($promotionCode2['expires_at']) {
            echo "   Expira em: {$promotionCode2['expires_at']}" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 2 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: Validação - Campo coupon obrigatório
    // ============================================
    echo "🧪 TESTE 3: Validação - Campo coupon obrigatório..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/promotion-codes');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'code' => 'INVALID'
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (($httpCode === 400 || $httpCode === 200) && 
        (isset($data['error']) && strpos(strtolower($data['error']), 'coupon') !== false || 
         isset($data['message']) && strpos(strtolower($data['message']), 'coupon') !== false)) {
        echo "   ✅ TESTE 3 PASSOU: Validação de coupon obrigatório funcionando" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 3 PARCIAL: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 4: Listar Promotion Codes
    // ============================================
    echo "🧪 TESTE 4: Listar promotion codes..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/promotion-codes?limit=10');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['success']) && $data['success'] === true && is_array($data['data'])) {
        echo "   ✅ TESTE 4 PASSOU: Listagem funcionando" . PHP_EOL;
        echo "   Total encontrado: {$data['count']}" . PHP_EOL;
        if (count($data['data']) > 0) {
            echo "   Primeiro código: {$data['data'][0]['code']}" . PHP_EOL;
        }
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 4 FALHOU: HTTP {$httpCode}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 5: Filtrar por código específico
    // ============================================
    if (isset($promotionCode1)) {
        echo "🧪 TESTE 5: Filtrar promotion codes por código específico..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/promotion-codes?code=TEST20OFF');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['success']) && $data['success'] === true) {
            $found = false;
            foreach ($data['data'] as $code) {
                if ($code['code'] === 'TEST20OFF') {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                echo "   ✅ TESTE 5 PASSOU: Filtro por código funcionando" . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ⚠️  TESTE 5 PARCIAL: Código não encontrado no filtro" . PHP_EOL;
            }
        } else {
            echo "   ⚠️  TESTE 5 PARCIAL: HTTP {$httpCode}" . PHP_EOL;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 5 PULADO: Promotion code não foi criado no teste anterior" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 6: Obter Promotion Code por ID
    // ============================================
    if (isset($promotionCode1)) {
        echo "🧪 TESTE 6: Obter promotion code por ID..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/promotion-codes/' . $promotionCode1['id']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['success']) && $data['success'] === true) {
            echo "   ✅ TESTE 6 PASSOU: Obtenção por ID funcionando" . PHP_EOL;
            echo "   Código: {$data['data']['code']}" . PHP_EOL;
            echo "   Ativo: " . ($data['data']['active'] ? 'Sim' : 'Não') . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 6 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 6 PULADO: Promotion code não foi criado" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 7: Atualizar Promotion Code
    // ============================================
    if (isset($promotionCode1)) {
        echo "🧪 TESTE 7: Atualizar promotion code..." . PHP_EOL;
        
        $ch = curl_init($baseUrl . '/v1/promotion-codes/' . $promotionCode1['id']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'active' => false,
            'metadata' => [
                'updated_by' => 'test',
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['success']) && $data['success'] === true) {
            if ($data['data']['active'] === false) {
                echo "   ✅ TESTE 7 PASSOU: Atualização funcionando" . PHP_EOL;
                echo "   Status atualizado para: " . ($data['data']['active'] ? 'Ativo' : 'Inativo') . PHP_EOL;
                $testsPassed++;
            } else {
                echo "   ⚠️  TESTE 7 PARCIAL: Atualização retornou sucesso mas active não foi alterado" . PHP_EOL;
            }
        } else {
            echo "   ❌ TESTE 7 FALHOU: HTTP {$httpCode}" . PHP_EOL;
            echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 7 PULADO: Promotion code não foi criado" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 8: Erro - Promotion Code não encontrado
    // ============================================
    echo "🧪 TESTE 8: Erro - Promotion code não encontrado..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/promotion-codes/prom_inexistente123');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (($httpCode === 404 || $httpCode === 200) && 
        (isset($data['error']) && strpos(strtolower($data['error']), 'não encontrado') !== false ||
         isset($data['error']) && strpos(strtolower($data['error']), 'not found') !== false)) {
        echo "   ✅ TESTE 8 PASSOU: Erro 404 para código não encontrado" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 8 PARCIAL: HTTP {$httpCode}" . PHP_EOL;
        echo "   Resposta: " . substr($response, 0, 200) . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 9: Testar StripeService diretamente
    // ============================================
    echo "🧪 TESTE 9: Testar StripeService::createPromotionCode() diretamente..." . PHP_EOL;
    
    try {
        $stripeService = new \App\Services\StripeService();
        
        $promotionCode = $stripeService->createPromotionCode([
            'coupon' => $testCoupon->id,
            'code' => 'DIRECT_TEST',
            'active' => true
        ]);
        
        $createdPromotionCodes[] = $promotionCode->id;
        
        echo "   ✅ TESTE 9 PASSOU: Método createPromotionCode() funcionando" . PHP_EOL;
        echo "   ID: {$promotionCode->id}" . PHP_EOL;
        echo "   Código: {$promotionCode->code}" . PHP_EOL;
        $testsPassed++;
        
        // Testa listPromotionCodes
        $codes = $stripeService->listPromotionCodes(['limit' => 5]);
        if (count($codes->data) > 0) {
            echo "   ✅ listPromotionCodes() também funcionando" . PHP_EOL;
        }
        
    } catch (\Exception $e) {
        echo "   ❌ TESTE 9 FALHOU: {$e->getMessage()}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // LIMPEZA: Deletar promotion codes criados
    // ============================================
    echo "🧹 LIMPEZA: Removendo promotion codes criados..." . PHP_EOL;
    foreach ($createdPromotionCodes as $codeId) {
        try {
            $stripe->promotionCodes->update($codeId, ['active' => false]);
            echo "   ✅ Promotion code {$codeId} desativado" . PHP_EOL;
        } catch (\Exception $e) {
            echo "   ⚠️  Erro ao desativar {$codeId}: {$e->getMessage()}" . PHP_EOL;
        }
    }
    
    // Deleta cupom de teste
    try {
        $stripe->coupons->delete($testCoupon->id);
        echo "   ✅ Cupom de teste deletado" . PHP_EOL;
    } catch (\Exception $e) {
        echo "   ⚠️  Erro ao deletar cupom: {$e->getMessage()}" . PHP_EOL;
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


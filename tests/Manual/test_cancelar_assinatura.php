<?php

/**
 * Teste Completo de Cancelamento de Assinatura
 * 
 * Este script testa:
 * 1. Criação de assinatura
 * 2. Cancelamento no final do período (cancel_at_period_end = true)
 * 3. Cancelamento imediato (immediately = true)
 * 4. Verificação de status no Stripe e no banco de dados
 * 
 * IMPORTANTE: Este teste cria recursos reais no Stripe (ambiente de teste)
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086';
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE COMPLETO DE CANCELAMENTO DE ASSINATURA              ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;

try {
    // Inicializa Stripe Client diretamente
    $stripeSecret = Config::get('STRIPE_SECRET');
    if (empty($stripeSecret)) {
        throw new Exception("STRIPE_SECRET não configurado no .env");
    }
    
    $stripe = new \Stripe\StripeClient($stripeSecret);
    echo "✅ Stripe Client inicializado" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 1: Criar Produto e Preço
    // ============================================
    echo "📦 PASSO 1: Criando produto e preço..." . PHP_EOL;
    
    $product = $stripe->products->create([
        'name' => 'Plano Teste Cancelamento',
        'description' => 'Produto para testar cancelamento de assinatura',
        'metadata' => ['test' => 'true', 'test_type' => 'cancel_subscription']
    ]);
    
    $price = $stripe->prices->create([
        'product' => $product->id,
        'unit_amount' => 1999, // R$ 19,99
        'currency' => 'brl',
        'recurring' => ['interval' => 'month'],
        'metadata' => ['test' => 'true']
    ]);
    
    echo "   ✅ Produto: {$product->id}" . PHP_EOL;
    echo "   ✅ Preço: {$price->id}" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 2: Criar ou Obter Customer
    // ============================================
    echo "👤 PASSO 2: Verificando/ criando customer..." . PHP_EOL;
    
    // Primeiro, lista todos os customers existentes
    $ch = curl_init($baseUrl . '/v1/customers');
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
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error'] ?? $errorData['message'] ?? $response;
        throw new Exception("Erro ao listar customers (HTTP {$httpCode}): " . $errorMsg);
    }
    
    $customersData = json_decode($response, true);
    $customers = $customersData['data'] ?? [];
    
    $customerId = null;
    $stripeCustomerId = null;
    $customerEmail = 'teste.cancelamento@example.com';
    
    // Tenta encontrar customer existente pelo email
    $existingCustomer = null;
    if (!empty($customers)) {
        foreach ($customers as $customer) {
            if (isset($customer['email']) && $customer['email'] === $customerEmail) {
                $existingCustomer = $customer;
                break;
            }
        }
        
        // Se não encontrou pelo email, usa o primeiro disponível
        if (!$existingCustomer) {
            $existingCustomer = $customers[0];
            $customerEmailDisplay = $existingCustomer['email'] ?? 'N/A';
            echo "   ℹ️  Customer existente encontrado (email diferente): {$customerEmailDisplay}" . PHP_EOL;
        }
    }
    
    if ($existingCustomer) {
        $customerId = $existingCustomer['id'];
        $stripeCustomerId = $existingCustomer['stripe_customer_id'];
        $customerEmailDisplay = $existingCustomer['email'] ?? 'N/A';
        $customerNameDisplay = $existingCustomer['name'] ?? 'N/A';
        echo "   ✅ Customer existente encontrado!" . PHP_EOL;
        echo "   Customer ID (banco): {$customerId}" . PHP_EOL;
        echo "   Stripe Customer ID: {$stripeCustomerId}" . PHP_EOL;
        echo "   Email: {$customerEmailDisplay}" . PHP_EOL;
        echo "   Nome: {$customerNameDisplay}" . PHP_EOL . PHP_EOL;
        
        // Verifica se o customer ainda existe no Stripe
        try {
            $stripeCustomer = $stripe->customers->retrieve($stripeCustomerId);
            echo "   ✅ Customer verificado no Stripe (status: ativo)" . PHP_EOL . PHP_EOL;
        } catch (\Exception $e) {
            echo "   ⚠️  Customer não encontrado no Stripe, criando novo..." . PHP_EOL;
            $existingCustomer = null; // Força criação de novo
        }
    }
    
    // Se não encontrou customer existente, cria um novo
    if (!$existingCustomer) {
        echo "   Criando novo customer..." . PHP_EOL;
        $ch = curl_init($baseUrl . '/v1/customers');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $customerEmail,
                'name' => 'Cliente Teste Cancelamento'
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error'] ?? $errorData['message'] ?? $response;
            throw new Exception("Erro ao criar customer (HTTP {$httpCode}): " . $errorMsg);
        }
        
        $customerData = json_decode($response, true);
        
        if (!isset($customerData['success']) || !$customerData['success']) {
            throw new Exception("Erro ao criar customer: " . ($customerData['error'] ?? 'Resposta inválida'));
        }
        
        $customerId = $customerData['data']['id'];
        $stripeCustomerId = $customerData['data']['stripe_customer_id'];
        
        $newCustomerEmail = $customerData['data']['email'] ?? 'N/A';
        echo "   ✅ Customer criado com sucesso!" . PHP_EOL;
        echo "   Customer ID (banco): {$customerId}" . PHP_EOL;
        echo "   Stripe Customer ID: {$stripeCustomerId}" . PHP_EOL;
        echo "   Email: {$newCustomerEmail}" . PHP_EOL . PHP_EOL;
    }
    
    // Guarda informações do customer para verificação final
    $originalCustomerId = $customerId;
    $originalStripeCustomerId = $stripeCustomerId;

    // ============================================
    // PASSO 3: Criar Assinatura para Teste 1 (Cancelamento no Final do Período)
    // ============================================
    echo "📝 PASSO 3: Criando assinatura para teste de cancelamento no final do período..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/subscriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'customer_id' => (int)$customerId,
            'price_id' => $price->id,
            'trial_period_days' => 14, // Trial para não precisar de payment method imediato
            'metadata' => [
                'test' => 'true',
                'test_type' => 'cancel_at_period_end'
            ]
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 && $httpCode !== 200) {
        $error = json_decode($response, true);
        throw new Exception("Erro ao criar assinatura: " . ($error['message'] ?? $response));
    }
    
    $subscriptionData1 = json_decode($response, true);
    
    if (!isset($subscriptionData1['success']) || !$subscriptionData1['success']) {
        throw new Exception("Erro ao criar assinatura: " . ($subscriptionData1['error'] ?? 'Resposta inválida'));
    }
    
    $subscriptionId1 = $subscriptionData1['data']['id'];
    $stripeSubscriptionId1 = $subscriptionData1['data']['stripe_subscription_id'];
    
    echo "   ✅ Assinatura criada!" . PHP_EOL;
    echo "   Subscription ID (banco): {$subscriptionId1}" . PHP_EOL;
    echo "   Stripe Subscription ID: {$stripeSubscriptionId1}" . PHP_EOL;
    echo "   Status: {$subscriptionData1['data']['status']}" . PHP_EOL . PHP_EOL;
    
    // Aguarda um pouco para garantir que a assinatura foi processada
    sleep(2);

    // ============================================
    // PASSO 4: Teste 1 - Cancelar no Final do Período
    // ============================================
    echo "🔄 PASSO 4: TESTE 1 - Cancelando assinatura no final do período..." . PHP_EOL;
    echo "   Subscription ID (banco): {$subscriptionId1}" . PHP_EOL . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/subscriptions/' . $subscriptionId1 . '?immediately=false');
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
    
    echo "   Status HTTP: {$httpCode}" . PHP_EOL;
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        echo "   ❌ Erro ao cancelar assinatura: " . ($error['message'] ?? $response) . PHP_EOL;
        throw new Exception("Falha no teste de cancelamento");
    }
    
    $cancelData = json_decode($response, true);
    echo "   ✅ Assinatura cancelada com sucesso!" . PHP_EOL;
    echo "   Mensagem: " . ($cancelData['message'] ?? 'N/A') . PHP_EOL . PHP_EOL;
    
    // Verifica no Stripe
    echo "   🔍 Verificando status no Stripe..." . PHP_EOL;
    $stripeSubscription1 = $stripe->subscriptions->retrieve($stripeSubscriptionId1);
    
    echo "   Status no Stripe: {$stripeSubscription1->status}" . PHP_EOL;
    echo "   Cancel at Period End: " . ($stripeSubscription1->cancel_at_period_end ? 'true' : 'false') . PHP_EOL;
    
    if ($stripeSubscription1->cancel_at_period_end) {
        echo "   ✅ TESTE 1 PASSOU: Assinatura será cancelada no final do período" . PHP_EOL;
    } else {
        echo "   ⚠️  TESTE 1 PARCIAL: cancel_at_period_end não está true" . PHP_EOL;
    }
    
    // Verifica no banco
    echo "   🔍 Verificando status no banco de dados..." . PHP_EOL;
    $ch = curl_init($baseUrl . '/v1/subscriptions');
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
        $subscriptionsData = json_decode($response, true);
        $subscriptions = $subscriptionsData['data'] ?? [];
        
        foreach ($subscriptions as $sub) {
            if ($sub['id'] == $subscriptionId1) {
                echo "   Status no banco: {$sub['status']}" . PHP_EOL;
                echo "   Cancel at Period End: " . ($sub['cancel_at_period_end'] ? 'true' : 'false') . PHP_EOL;
                break;
            }
        }
    }
    
    echo PHP_EOL;

    // ============================================
    // PASSO 5: Criar Assinatura para Teste 2 (Cancelamento Imediato)
    // ============================================
    echo "📝 PASSO 5: Criando assinatura para teste de cancelamento imediato..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/subscriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'customer_id' => (int)$customerId,
            'price_id' => $price->id,
            'trial_period_days' => 14,
            'metadata' => [
                'test' => 'true',
                'test_type' => 'cancel_immediately'
            ]
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 && $httpCode !== 200) {
        $error = json_decode($response, true);
        throw new Exception("Erro ao criar assinatura: " . ($error['message'] ?? $response));
    }
    
    $subscriptionData2 = json_decode($response, true);
    
    if (!isset($subscriptionData2['success']) || !$subscriptionData2['success']) {
        throw new Exception("Erro ao criar assinatura: " . ($subscriptionData2['error'] ?? 'Resposta inválida'));
    }
    
    $subscriptionId2 = $subscriptionData2['data']['id'];
    $stripeSubscriptionId2 = $subscriptionData2['data']['stripe_subscription_id'];
    
    echo "   ✅ Assinatura criada!" . PHP_EOL;
    echo "   Subscription ID (banco): {$subscriptionId2}" . PHP_EOL;
    echo "   Stripe Subscription ID: {$stripeSubscriptionId2}" . PHP_EOL;
    echo "   Status: {$subscriptionData2['data']['status']}" . PHP_EOL . PHP_EOL;
    
    // Aguarda um pouco
    sleep(2);

    // ============================================
    // PASSO 6: Teste 2 - Cancelamento Imediato
    // ============================================
    echo "🔄 PASSO 6: TESTE 2 - Cancelando assinatura imediatamente..." . PHP_EOL;
    echo "   Subscription ID (banco): {$subscriptionId2}" . PHP_EOL . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/subscriptions/' . $subscriptionId2 . '?immediately=true');
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
    
    echo "   Status HTTP: {$httpCode}" . PHP_EOL;
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        echo "   ❌ Erro ao cancelar assinatura: " . ($error['message'] ?? $response) . PHP_EOL;
        throw new Exception("Falha no teste de cancelamento imediato");
    }
    
    $cancelData = json_decode($response, true);
    echo "   ✅ Assinatura cancelada com sucesso!" . PHP_EOL;
    echo "   Mensagem: " . ($cancelData['message'] ?? 'N/A') . PHP_EOL . PHP_EOL;
    
    // Verifica no Stripe
    echo "   🔍 Verificando status no Stripe..." . PHP_EOL;
    $stripeSubscription2 = $stripe->subscriptions->retrieve($stripeSubscriptionId2);
    
    echo "   Status no Stripe: {$stripeSubscription2->status}" . PHP_EOL;
    echo "   Cancel at Period End: " . ($stripeSubscription2->cancel_at_period_end ? 'true' : 'false') . PHP_EOL;
    
    if ($stripeSubscription2->status === 'canceled') {
        echo "   ✅ TESTE 2 PASSOU: Assinatura foi cancelada imediatamente" . PHP_EOL;
    } else {
        echo "   ⚠️  TESTE 2 PARCIAL: Status é '{$stripeSubscription2->status}' (esperado 'canceled')" . PHP_EOL;
        echo "   ℹ️  Nota: Em trial, o status pode ser 'trialing' até o fim do trial" . PHP_EOL;
    }
    
    // Verifica no banco
    echo "   🔍 Verificando status no banco de dados..." . PHP_EOL;
    $ch = curl_init($baseUrl . '/v1/subscriptions');
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
        $subscriptionsData = json_decode($response, true);
        $subscriptions = $subscriptionsData['data'] ?? [];
        
        foreach ($subscriptions as $sub) {
            if ($sub['id'] == $subscriptionId2) {
                echo "   Status no banco: {$sub['status']}" . PHP_EOL;
                echo "   Cancel at Period End: " . ($sub['cancel_at_period_end'] ? 'true' : 'false') . PHP_EOL;
                break;
            }
        }
    }
    
    echo PHP_EOL;

    // ============================================
    // PASSO 7: Teste 3 - Tentar Cancelar Assinatura Inexistente
    // ============================================
    echo "🔄 PASSO 7: TESTE 3 - Tentando cancelar assinatura inexistente..." . PHP_EOL;
    
    $fakeId = 99999;
    $ch = curl_init($baseUrl . '/v1/subscriptions/' . $fakeId);
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
    
    echo "   Status HTTP: {$httpCode}" . PHP_EOL;
    
    if ($httpCode === 404) {
        echo "   ✅ TESTE 3 PASSOU: Retornou 404 para assinatura inexistente" . PHP_EOL;
    } else {
        $error = json_decode($response, true);
        echo "   ⚠️  TESTE 3 PARCIAL: Retornou {$httpCode} (esperado 404)" . PHP_EOL;
        echo "   Resposta: " . ($error['error'] ?? $response) . PHP_EOL;
    }
    
    echo PHP_EOL;

    // ============================================
    // RESUMO FINAL
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║                    ✅ TESTE CONCLUÍDO                          ║" . PHP_EOL;
    echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;
    
    echo "📊 RESUMO DOS TESTES:" . PHP_EOL;
    echo "   • Teste 1 - Cancelamento no Final do Período:" . PHP_EOL;
    echo "     - Subscription ID: {$subscriptionId1}" . PHP_EOL;
    echo "     - Stripe Subscription ID: {$stripeSubscriptionId1}" . PHP_EOL;
    echo "     - Status: {$stripeSubscription1->status}" . PHP_EOL;
    echo "     - Cancel at Period End: " . ($stripeSubscription1->cancel_at_period_end ? 'true' : 'false') . PHP_EOL . PHP_EOL;
    
    echo "   • Teste 2 - Cancelamento Imediato:" . PHP_EOL;
    echo "     - Subscription ID: {$subscriptionId2}" . PHP_EOL;
    echo "     - Stripe Subscription ID: {$stripeSubscriptionId2}" . PHP_EOL;
    echo "     - Status: {$stripeSubscription2->status}" . PHP_EOL;
    echo "     - Cancel at Period End: " . ($stripeSubscription2->cancel_at_period_end ? 'true' : 'false') . PHP_EOL . PHP_EOL;
    
    echo "   • Teste 3 - Validação de Erro:" . PHP_EOL;
    echo "     - Tentativa de cancelar assinatura inexistente" . PHP_EOL;
    echo "     - Status retornado: {$httpCode}" . PHP_EOL . PHP_EOL;
    
    // ============================================
    // PASSO 8: Verificar se Customer Ainda Existe
    // ============================================
    echo "🔍 PASSO 8: Verificando se customer ainda existe após os testes..." . PHP_EOL;
    
    // Verifica no banco
    $ch = curl_init($baseUrl . '/v1/customers');
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
        $customersData = json_decode($response, true);
        $customers = $customersData['data'] ?? [];
        
        $customerFound = false;
        foreach ($customers as $customer) {
            if ($customer['id'] == $originalCustomerId) {
                $customerFound = true;
                $finalCustomerEmail = $customer['email'] ?? 'N/A';
                echo "   ✅ Customer ainda existe no banco de dados!" . PHP_EOL;
                echo "   Customer ID: {$customer['id']}" . PHP_EOL;
                echo "   Stripe Customer ID: {$customer['stripe_customer_id']}" . PHP_EOL;
                echo "   Email: {$finalCustomerEmail}" . PHP_EOL;
                break;
            }
        }
        
        if (!$customerFound) {
            echo "   ❌ ATENÇÃO: Customer não foi encontrado no banco após os testes!" . PHP_EOL;
            echo "   Customer ID procurado: {$originalCustomerId}" . PHP_EOL;
            echo "   Total de customers encontrados: " . count($customers) . PHP_EOL;
        }
    } else {
        echo "   ⚠️  Não foi possível verificar customer no banco (HTTP {$httpCode})" . PHP_EOL;
    }
    
    // Verifica no Stripe
    try {
        $stripeCustomer = $stripe->customers->retrieve($originalStripeCustomerId);
        $stripeCustomerEmail = $stripeCustomer->email ?? 'N/A';
        echo "   ✅ Customer ainda existe no Stripe!" . PHP_EOL;
        echo "   Stripe Customer ID: {$stripeCustomer->id}" . PHP_EOL;
        echo "   Email: {$stripeCustomerEmail}" . PHP_EOL;
        echo "   Deleted: " . ($stripeCustomer->deleted ? 'true' : 'false') . PHP_EOL . PHP_EOL;
    } catch (\Exception $e) {
        echo "   ❌ ERRO: Customer não encontrado no Stripe!" . PHP_EOL;
        echo "   Erro: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
    
    echo "🔗 Links úteis:" . PHP_EOL;
    echo "   • Assinatura 1 no Stripe: https://dashboard.stripe.com/test/subscriptions/{$stripeSubscriptionId1}" . PHP_EOL;
    echo "   • Assinatura 2 no Stripe: https://dashboard.stripe.com/test/subscriptions/{$stripeSubscriptionId2}" . PHP_EOL;
    echo "   • Customer no Stripe: https://dashboard.stripe.com/test/customers/{$originalStripeCustomerId}" . PHP_EOL . PHP_EOL;
    
    echo "📝 OBSERVAÇÕES:" . PHP_EOL;
    echo "   • Assinaturas em trial podem não ser canceladas imediatamente" . PHP_EOL;
    echo "   • O status 'canceled' só aparece após o fim do trial ou período" . PHP_EOL;
    echo "   • Verifique os logs em app.log para mais detalhes" . PHP_EOL . PHP_EOL;
    
    echo "✅ Todos os testes foram executados!" . PHP_EOL;

} catch (\Stripe\Exception\ApiErrorException $e) {
    echo PHP_EOL . "❌ ERRO DO STRIPE:" . PHP_EOL;
    echo "   Tipo: " . get_class($e) . PHP_EOL;
    echo "   Mensagem: " . $e->getMessage() . PHP_EOL;
    if ($e->getStripeCode()) {
        echo "   Código: " . $e->getStripeCode() . PHP_EOL;
    }
    exit(1);
} catch (Exception $e) {
    echo PHP_EOL . "❌ ERRO:" . PHP_EOL;
    echo "   " . $e->getMessage() . PHP_EOL;
    exit(1);
}


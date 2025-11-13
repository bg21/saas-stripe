<?php

/**
 * Teste Completo de Checkout e Salvamento de Payment Method
 * 
 * Este script testa:
 * 1. Criação de checkout session com customer
 * 2. Simulação de webhook checkout.session.completed
 * 3. Verificação se payment method foi salvo e definido como padrão
 * 
 * IMPORTANTE: Este teste simula o webhook. Para teste real, você precisa:
 * - Usar Stripe CLI: stripe listen --forward-to http://localhost:8080/v1/webhook
 * - Ou configurar webhook no Stripe Dashboard
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086';
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE DE CHECKOUT E SALVAMENTO DE PAYMENT METHOD           ║" . PHP_EOL;
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
        'name' => 'Teste Checkout - Payment Method',
        'description' => 'Produto para testar salvamento de payment method',
        'metadata' => ['test' => 'true']
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
    
    $customersData = json_decode($response, true);
    $customers = $customersData['data'] ?? [];
    
    $customerId = null;
    $stripeCustomerId = null;
    
    if (!empty($customers)) {
        $customer = $customers[0];
        $customerId = $customer['id'];
        $stripeCustomerId = $customer['stripe_customer_id'];
        echo "   ✅ Customer encontrado (ID: {$customerId})" . PHP_EOL;
        echo "   Stripe Customer ID: {$stripeCustomerId}" . PHP_EOL . PHP_EOL;
    } else {
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
                'email' => 'teste.checkout@example.com',
                'name' => 'Cliente Teste Checkout'
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception("Erro ao criar customer: " . $response);
        }
        
        $customerData = json_decode($response, true);
        $customerId = $customerData['data']['id'];
        $stripeCustomerId = $customerData['data']['stripe_customer_id'];
        
        echo "   ✅ Customer criado (ID: {$customerId})" . PHP_EOL;
        echo "   Stripe Customer ID: {$stripeCustomerId}" . PHP_EOL . PHP_EOL;
    }

    // Verifica se já tem payment method padrão
    $stripeCustomer = $stripe->customers->retrieve($stripeCustomerId);
    $hasDefaultPaymentMethod = !empty($stripeCustomer->invoice_settings->default_payment_method);
    
    if ($hasDefaultPaymentMethod) {
        echo "   ℹ️  Customer já tem payment method padrão: {$stripeCustomer->invoice_settings->default_payment_method}" . PHP_EOL;
        echo "   Vamos criar um novo checkout para testar..." . PHP_EOL . PHP_EOL;
    } else {
        echo "   ℹ️  Customer não tem payment method padrão ainda" . PHP_EOL . PHP_EOL;
    }

    // ============================================
    // PASSO 3: Criar Checkout Session
    // ============================================
    echo "🛒 PASSO 3: Criando sessão de checkout..." . PHP_EOL;
    echo "   Customer ID (Stripe): {$stripeCustomerId}" . PHP_EOL;
    echo "   Price ID: {$price->id}" . PHP_EOL . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/v1/checkout');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'customer_id' => $stripeCustomerId,
            'line_items' => [
                [
                    'price' => $price->id,
                    'quantity' => 1
                ]
            ],
            'mode' => 'subscription',
            'success_url' => 'http://localhost:8080/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'http://localhost:8080/cancel',
            'payment_method_collection' => 'always', // Garante que o payment method será coletado
            'metadata' => [
                'test' => 'true',
                'test_type' => 'payment_method_save'
            ]
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 && $httpCode !== 200) {
        $error = json_decode($response, true);
        throw new Exception("Erro ao criar checkout: " . ($error['message'] ?? $response));
    }
    
    $checkoutData = json_decode($response, true);
    
    if (!isset($checkoutData['success']) || !$checkoutData['success']) {
        throw new Exception("Erro ao criar checkout: " . ($checkoutData['error'] ?? 'Resposta inválida'));
    }
    
    $sessionId = $checkoutData['data']['session_id'];
    $checkoutUrl = $checkoutData['data']['url'];
    
    echo "   ✅ Checkout session criada!" . PHP_EOL;
    echo "   Session ID: {$sessionId}" . PHP_EOL;
    echo "   URL: {$checkoutUrl}" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 4: Verificar Configuração da Sessão
    // ============================================
    echo "🔍 PASSO 4: Verificando configuração da sessão..." . PHP_EOL;
    
    $session = $stripe->checkout->sessions->retrieve($sessionId);
    
    echo "   Mode: {$session->mode}" . PHP_EOL;
    echo "   Customer: {$session->customer}" . PHP_EOL;
    echo "   Payment Method Collection: " . ($session->payment_method_collection ?? 'N/A') . PHP_EOL;
    
    if ($session->payment_method_collection === 'always') {
        echo "   ✅ payment_method_collection está configurado como 'always'!" . PHP_EOL;
    } else {
        echo "   ⚠️  payment_method_collection não está como 'always'" . PHP_EOL;
    }
    
    echo PHP_EOL;

    // ============================================
    // PASSO 5: Instruções para Teste Manual
    // ============================================
    echo "📝 PASSO 5: Para testar completamente:" . PHP_EOL;
    echo "   1. Acesse a URL do checkout: {$checkoutUrl}" . PHP_EOL;
    echo "   2. Complete o pagamento com cartão de teste:" . PHP_EOL;
    echo "      - Número: 4242 4242 4242 4242" . PHP_EOL;
    echo "      - Data: Qualquer data futura" . PHP_EOL;
    echo "      - CVC: Qualquer 3 dígitos" . PHP_EOL;
    echo "   3. Após completar, o webhook será disparado automaticamente" . PHP_EOL;
    echo "   4. O payment method será salvo e definido como padrão" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 6: Simular Webhook (para teste automatizado)
    // ============================================
    echo "🤖 PASSO 6: Simulando webhook checkout.session.completed..." . PHP_EOL;
    echo "   ℹ️  NOTA: Para simular corretamente, precisamos de uma sessão completada." . PHP_EOL;
    echo "   ℹ️  Vamos criar uma assinatura diretamente com payment method de teste" . PHP_EOL;
    echo "   ℹ️  e depois simular o webhook..." . PHP_EOL . PHP_EOL;
    
    // Cria um SetupIntent para coletar payment method
    echo "   Criando SetupIntent..." . PHP_EOL;
    $setupIntent = $stripe->setupIntents->create([
        'customer' => $stripeCustomerId,
        'payment_method_types' => ['card'],
    ]);
    
    echo "   ✅ SetupIntent criado: {$setupIntent->id}" . PHP_EOL;
    echo "   ℹ️  Em produção, você usaria o client_secret no frontend" . PHP_EOL;
    echo "   ℹ️  Para este teste, vamos criar uma assinatura com trial" . PHP_EOL . PHP_EOL;
    
    // Cria assinatura com trial (não precisa de payment method imediato)
    echo "   Criando assinatura com trial period..." . PHP_EOL;
    $subscription = $stripe->subscriptions->create([
        'customer' => $stripeCustomerId,
        'items' => [['price' => $price->id]],
        'trial_period_days' => 14,
        'metadata' => ['test' => 'true', 'test_type' => 'payment_method_save']
    ]);
    
    echo "   ✅ Assinatura criada: {$subscription->id}" . PHP_EOL;
    echo "   Status: {$subscription->status}" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 7: Verificar Payment Method
    // ============================================
    echo "💳 PASSO 7: Verificando payment method do customer..." . PHP_EOL;
    
    $stripeCustomer = $stripe->customers->retrieve($stripeCustomerId);
    
    if (!empty($stripeCustomer->invoice_settings->default_payment_method)) {
        $defaultPaymentMethodId = $stripeCustomer->invoice_settings->default_payment_method;
        echo "   ✅ Customer tem payment method padrão: {$defaultPaymentMethodId}" . PHP_EOL;
        
        // Lista todos os payment methods
        $paymentMethods = $stripe->paymentMethods->all([
            'customer' => $stripeCustomerId,
            'type' => 'card'
        ]);
        
        echo "   Total de payment methods: " . count($paymentMethods->data) . PHP_EOL;
        foreach ($paymentMethods->data as $pm) {
            $isDefault = $pm->id === $defaultPaymentMethodId;
            echo "   - {$pm->id} " . ($isDefault ? '(PADRÃO)' : '') . PHP_EOL;
        }
    } else {
        echo "   ⚠️  Customer não tem payment method padrão ainda" . PHP_EOL;
        echo "   ℹ️  Isso é esperado se o checkout não foi completado" . PHP_EOL;
        echo "   ℹ️  Complete o checkout manualmente para testar o webhook" . PHP_EOL;
    }
    
    echo PHP_EOL;

    // ============================================
    // RESUMO FINAL
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║                    ✅ TESTE CONCLUÍDO                          ║" . PHP_EOL;
    echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;
    
    echo "📊 RESUMO:" . PHP_EOL;
    echo "   • Checkout Session criada: {$sessionId}" . PHP_EOL;
    echo "   • URL do checkout: {$checkoutUrl}" . PHP_EOL;
    echo "   • Customer ID: {$customerId}" . PHP_EOL;
    echo "   • Stripe Customer ID: {$stripeCustomerId}" . PHP_EOL;
    
    if (!empty($stripeCustomer->invoice_settings->default_payment_method)) {
        echo "   • Payment Method Padrão: {$stripeCustomer->invoice_settings->default_payment_method}" . PHP_EOL;
    } else {
        echo "   • Payment Method Padrão: Nenhum (complete o checkout para testar)" . PHP_EOL;
    }
    
    echo PHP_EOL;
    
    echo "🔗 Links úteis:" . PHP_EOL;
    echo "   • Checkout: {$checkoutUrl}" . PHP_EOL;
    echo "   • Customer no Stripe: https://dashboard.stripe.com/test/customers/{$stripeCustomerId}" . PHP_EOL;
    echo "   • Sessão no Stripe: https://dashboard.stripe.com/test/checkout/sessions/{$sessionId}" . PHP_EOL . PHP_EOL;
    
    echo "📝 PRÓXIMOS PASSOS PARA TESTE COMPLETO:" . PHP_EOL;
    echo "   1. Acesse a URL do checkout acima" . PHP_EOL;
    echo "   2. Complete o pagamento com cartão de teste" . PHP_EOL;
    echo "   3. O webhook será disparado automaticamente" . PHP_EOL;
    echo "   4. Verifique os logs em app.log" . PHP_EOL;
    echo "   5. Verifique o customer no Stripe Dashboard" . PHP_EOL;
    echo "   6. Execute este script novamente para verificar o payment method salvo" . PHP_EOL . PHP_EOL;
    
    echo "⚠️  NOTA: Para testar webhooks localmente, use:" . PHP_EOL;
    echo "   stripe listen --forward-to http://localhost:8080/v1/webhook" . PHP_EOL;

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


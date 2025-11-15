<?php

/**
 * Testes manuais para Subscription Items
 * 
 * Subscription Items representam produtos/preços individuais dentro de uma assinatura.
 * Útil para adicionar add-ons, upgrades, ou múltiplos produtos em uma assinatura.
 * 
 * Execute: php tests/Manual/test_subscription_items.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use Config;
use App\Services\StripeService;

// Configurações
$baseUrl = 'http://localhost/saas-stripe/public';
$apiKey = Config::get('TEST_API_KEY'); // Use uma API key de teste do seu tenant

if (empty($apiKey)) {
    die("ERRO: TEST_API_KEY não configurado em config.php\n");
}

$stripeService = new StripeService();

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

$testCount = 0;
$passCount = 0;
$failCount = 0;

function printTest($number, $description) {
    global $blue, $reset;
    echo "\n{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
    echo "{$blue}TESTE {$number}: {$description}{$reset}\n";
    echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
}

function printResult($passed, $message = '') {
    global $green, $red, $reset, $passCount, $failCount, $testCount;
    $testCount++;
    if ($passed) {
        $passCount++;
        echo "{$green}✓ PASSOU{$reset}";
    } else {
        $failCount++;
        echo "{$red}✗ FALHOU{$reset}";
    }
    if ($message) {
        echo " - {$message}";
    }
    echo "\n";
}

function makeRequest($method, $endpoint, $data = null) {
    global $baseUrl, $apiKey;
    
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

// Variáveis para armazenar IDs criados durante os testes
$subscriptionId = null;
$subscriptionItemId = null;
$customerId = null;
$priceId1 = null;
$priceId2 = null;

echo "\n{$blue}╔═══════════════════════════════════════════════════════════════╗{$reset}";
echo "\n{$blue}║     TESTES MANUAIS - SUBSCRIPTION ITEMS                       ║{$reset}";
echo "\n{$blue}╚═══════════════════════════════════════════════════════════════╝{$reset}\n";

// ============================================================================
// PREPARAÇÃO: Criar recursos necessários (customer, produto, preços, assinatura)
// ============================================================================
echo "\n{$yellow}═══════════════════════════════════════════════════════════════{$reset}";
echo "\n{$yellow}PREPARAÇÃO: Criando recursos necessários...{$reset}\n";
echo "{$yellow}═══════════════════════════════════════════════════════════════{$reset}\n";

// 1. Criar customer
echo "1. Criando customer...\n";
$customerData = [
    'email' => 'test_subscription_items_' . time() . '@example.com',
    'name' => 'Test Subscription Items Customer'
];
$response = makeRequest('POST', '/v1/customers', $customerData);
if ($response['code'] === 201 || $response['code'] === 200) {
    $customerId = $response['body']['data']['id'] ?? null;
    echo "   ✅ Customer criado: ID {$customerId}\n";
} else {
    die("   ❌ Erro ao criar customer: " . $response['raw'] . "\n");
}

// 2. Criar produto e preço 1
echo "2. Criando produto e preço 1...\n";
$productData1 = [
    'name' => 'Produto Base Test ' . time(),
    'description' => 'Produto base para teste de subscription items'
];
$response = makeRequest('POST', '/v1/products', $productData1);
if ($response['code'] === 201 || $response['code'] === 200) {
    $productId1 = $response['body']['data']['id'] ?? null;
    echo "   ✅ Produto criado: {$productId1}\n";
    
    // Criar preço para o produto
    $priceData1 = [
        'product' => $productId1,
        'unit_amount' => 2000, // R$ 20,00
        'currency' => 'brl',
        'recurring' => [
            'interval' => 'month'
        ]
    ];
    $response = makeRequest('POST', '/v1/prices', $priceData1);
    if ($response['code'] === 201 || $response['code'] === 200) {
        $priceId1 = $response['body']['data']['id'] ?? null;
        echo "   ✅ Preço criado: {$priceId1}\n";
    } else {
        die("   ❌ Erro ao criar preço 1: " . $response['raw'] . "\n");
    }
} else {
    die("   ❌ Erro ao criar produto 1: " . $response['raw'] . "\n");
}

// 3. Criar produto e preço 2 (para add-on)
echo "3. Criando produto e preço 2 (add-on)...\n";
$productData2 = [
    'name' => 'Add-on Test ' . time(),
    'description' => 'Add-on para teste de subscription items'
];
$response = makeRequest('POST', '/v1/products', $productData2);
if ($response['code'] === 201 || $response['code'] === 200) {
    $productId2 = $response['body']['data']['id'] ?? null;
    echo "   ✅ Produto criado: {$productId2}\n";
    
    // Criar preço para o produto
    $priceData2 = [
        'product' => $productId2,
        'unit_amount' => 1000, // R$ 10,00
        'currency' => 'brl',
        'recurring' => [
            'interval' => 'month'
        ]
    ];
    $response = makeRequest('POST', '/v1/prices', $priceData2);
    if ($response['code'] === 201 || $response['code'] === 200) {
        $priceId2 = $response['body']['data']['id'] ?? null;
        echo "   ✅ Preço criado: {$priceId2}\n";
    } else {
        die("   ❌ Erro ao criar preço 2: " . $response['raw'] . "\n");
    }
} else {
    die("   ❌ Erro ao criar produto 2: " . $response['raw'] . "\n");
}

// 4. Criar assinatura com o primeiro preço (trial para não precisar de payment method)
echo "4. Criando assinatura com trial...\n";
$subscriptionData = [
    'customer_id' => (int)$customerId,
    'price_id' => $priceId1,
    'trial_period_days' => 14,
    'metadata' => [
        'test' => 'subscription_items'
    ]
];
$response = makeRequest('POST', '/v1/subscriptions', $subscriptionData);
if ($response['code'] === 201 || $response['code'] === 200) {
    $subscriptionId = $response['body']['data']['stripe_subscription_id'] ?? null;
    echo "   ✅ Assinatura criada: {$subscriptionId}\n";
} else {
    die("   ❌ Erro ao criar assinatura: " . $response['raw'] . "\n");
}

echo "\n{$green}✅ Preparação concluída!{$reset}\n";

// ============================================================================
// TESTE 1: Listar Subscription Items de uma assinatura
// ============================================================================
printTest(1, "Listar Subscription Items de uma assinatura");

$response = makeRequest('GET', "/v1/subscriptions/{$subscriptionId}/items");
$passed = $response['code'] === 200 
    && isset($response['body']['success']) 
    && $response['body']['success'] === true
    && isset($response['body']['data'])
    && is_array($response['body']['data']);

if ($passed && !empty($response['body']['data'])) {
    $subscriptionItemId = $response['body']['data'][0]['id'] ?? null;
    echo "   Encontrado {$response['body']['count']} item(s)\n";
    if ($subscriptionItemId) {
        echo "   Subscription Item ID: {$subscriptionItemId}\n";
    }
}

printResult($passed, $passed ? "Listagem retornou " . count($response['body']['data'] ?? []) . " item(s)" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 2: Adicionar Subscription Item (add-on) via API
// ============================================================================
printTest(2, "Adicionar Subscription Item (add-on) via API");

$itemData = [
    'price_id' => $priceId2,
    'quantity' => 1,
    'metadata' => [
        'addon' => 'true',
        'test' => 'subscription_items'
    ]
];

$response = makeRequest('POST', "/v1/subscriptions/{$subscriptionId}/items", $itemData);
$passed = ($response['code'] === 201 || $response['code'] === 200)
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data']['id']);

if ($passed) {
    $newItemId = $response['body']['data']['id'] ?? null;
    echo "   Subscription Item criado: {$newItemId}\n";
    echo "   Price ID: {$response['body']['data']['price']}\n";
    echo "   Quantity: {$response['body']['data']['quantity']}\n";
}

printResult($passed, $passed ? "Item adicionado com sucesso" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 3: Obter Subscription Item por ID
// ============================================================================
printTest(3, "Obter Subscription Item por ID");

if (empty($subscriptionItemId)) {
    printResult(false, "Subscription Item ID não disponível (pule este teste)");
} else {
    $response = makeRequest('GET', "/v1/subscription-items/{$subscriptionItemId}");
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data']['id'])
        && $response['body']['data']['id'] === $subscriptionItemId;

    if ($passed) {
        echo "   ID: {$response['body']['data']['id']}\n";
        echo "   Subscription: {$response['body']['data']['subscription']}\n";
        echo "   Price: {$response['body']['data']['price']}\n";
        echo "   Quantity: {$response['body']['data']['quantity']}\n";
    }

    printResult($passed, $passed ? "Item obtido com sucesso" : "HTTP {$response['code']}");
}

// ============================================================================
// TESTE 4: Atualizar Subscription Item (quantidade)
// ============================================================================
printTest(4, "Atualizar Subscription Item (quantidade)");

if (empty($subscriptionItemId)) {
    printResult(false, "Subscription Item ID não disponível (pule este teste)");
} else {
    $updateData = [
        'quantity' => 2,
        'metadata' => [
            'updated' => 'true',
            'test' => 'subscription_items'
        ]
    ];

    $response = makeRequest('PUT', "/v1/subscription-items/{$subscriptionItemId}", $updateData);
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data']['quantity'])
        && $response['body']['data']['quantity'] === 2;

    if ($passed) {
        echo "   Quantity atualizada: {$response['body']['data']['quantity']}\n";
    }

    printResult($passed, $passed ? "Item atualizado com sucesso" : "HTTP {$response['code']}");
}

// ============================================================================
// TESTE 5: Listar Subscription Items com paginação
// ============================================================================
printTest(5, "Listar Subscription Items com paginação (limit)");

$response = makeRequest('GET', "/v1/subscriptions/{$subscriptionId}/items?limit=1");
$passed = $response['code'] === 200
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data'])
    && is_array($response['body']['data']);

if ($passed) {
    echo "   Retornou " . count($response['body']['data']) . " item(s) (limit=1)\n";
}

printResult($passed, $passed ? "Paginação funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 6: Validação - Tentar criar item sem price_id
// ============================================================================
printTest(6, "Validação - Tentar criar item sem price_id");

$invalidData = [
    'quantity' => 1
];

$response = makeRequest('POST', "/v1/subscriptions/{$subscriptionId}/items", $invalidData);
$passed = $response['code'] === 400
    || (isset($response['body']['error']) && stripos($response['body']['error'], 'price_id') !== false);

printResult($passed, $passed ? "Validação funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 7: Teste direto do StripeService - createSubscriptionItem
// ============================================================================
printTest(7, "Teste direto do StripeService - createSubscriptionItem");

try {
    $item = $stripeService->createSubscriptionItem($subscriptionId, [
        'price_id' => $priceId2,
        'quantity' => 1,
        'metadata' => [
            'test' => 'direct_service'
        ]
    ]);
    
    $passed = !empty($item->id) && $item->subscription === $subscriptionId;
    echo "   Subscription Item ID: {$item->id}\n";
    echo "   Subscription: {$item->subscription}\n";
    echo "   Price: {$item->price->id}\n";
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao criar");
} catch (\Exception $e) {
    printResult(false, "Erro: " . $e->getMessage());
}

// ============================================================================
// TESTE 8: Teste direto do StripeService - listSubscriptionItems
// ============================================================================
printTest(8, "Teste direto do StripeService - listSubscriptionItems");

try {
    $collection = $stripeService->listSubscriptionItems($subscriptionId, ['limit' => 10]);
    
    $passed = !empty($collection) && isset($collection->data);
    echo "   Encontrado " . count($collection->data) . " item(s)\n";
    echo "   Has more: " . ($collection->has_more ? 'sim' : 'não') . "\n";
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao listar");
} catch (\Exception $e) {
    printResult(false, "Erro: " . $e->getMessage());
}

// ============================================================================
// TESTE 9: Remover Subscription Item
// ============================================================================
printTest(9, "Remover Subscription Item (último teste - remove add-on)");

// Primeiro, listar items para pegar o ID do add-on criado
$response = makeRequest('GET', "/v1/subscriptions/{$subscriptionId}/items");
if ($response['code'] === 200 && !empty($response['body']['data'])) {
    // Pega o último item (provavelmente o add-on)
    $items = $response['body']['data'];
    $itemToDelete = end($items);
    $itemIdToDelete = $itemToDelete['id'] ?? null;
    
    if ($itemIdToDelete && $itemIdToDelete !== $subscriptionItemId) {
        // Não remove o item principal, apenas add-ons
        $response = makeRequest('DELETE', "/v1/subscription-items/{$itemIdToDelete}?prorate=true");
        $passed = $response['code'] === 200
            && isset($response['body']['success'])
            && $response['body']['success'] === true;
        
        echo "   Item removido: {$itemIdToDelete}\n";
        printResult($passed, $passed ? "Item removido com sucesso" : "HTTP {$response['code']}");
    } else {
        printResult(false, "Nenhum add-on encontrado para remover (mantendo item principal)");
    }
} else {
    printResult(false, "Não foi possível listar items para remoção");
}

// ============================================================================
// RESUMO FINAL
// ============================================================================
echo "\n\n{$blue}╔═══════════════════════════════════════════════════════════════╗{$reset}";
echo "\n{$blue}║                    RESUMO DOS TESTES                           ║{$reset}";
echo "\n{$blue}╚═══════════════════════════════════════════════════════════════╝{$reset}\n";

echo "Total de testes: {$testCount}\n";
echo "{$green}✓ Passou: {$passCount}{$reset}\n";
echo "{$red}✗ Falhou: {$failCount}{$reset}\n";

if ($failCount === 0) {
    echo "\n{$green}🎉 Todos os testes passaram!{$reset}\n";
} else {
    echo "\n{$yellow}⚠️  Alguns testes falharam. Verifique os detalhes acima.{$reset}\n";
}

echo "\n";


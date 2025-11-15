<?php

/**
 * Testes manuais para Invoice Items (itens de fatura)
 * 
 * Invoice Items são usados para adicionar cobranças únicas ou ajustes manuais a invoices.
 * Útil para fazer ajustes manuais frequentes, créditos, taxas adicionais, etc.
 * 
 * Execute: php tests/Manual/test_invoice_items.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

Config::load();

use App\Services\StripeService;

// Configurações
$baseUrl = 'http://localhost:8080';
$apiKey = Config::get('TEST_API_KEY'); // Use uma API key de teste do seu tenant

if (empty($apiKey)) {
    // Fallback: tenta usar uma API key hardcoded para testes (substitua pela sua)
    $apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086';
    echo "⚠️  Usando API key hardcoded. Configure TEST_API_KEY no .env para produção.\n\n";
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
$invoiceItemId = null;
$customerId = null;
$stripeCustomerId = null;

echo "\n{$blue}╔═══════════════════════════════════════════════════════════════╗{$reset}";
echo "\n{$blue}║     TESTES MANUAIS - INVOICE ITEMS                            ║{$reset}";
echo "\n{$blue}╚═══════════════════════════════════════════════════════════════╝{$reset}\n";

// ============================================================================
// PREPARAÇÃO: Criar customer necessário
// ============================================================================
echo "\n{$yellow}═══════════════════════════════════════════════════════════════{$reset}";
echo "\n{$yellow}PREPARAÇÃO: Criando customer necessário...{$reset}\n";
echo "{$yellow}═══════════════════════════════════════════════════════════════{$reset}\n";

echo "1. Criando customer...\n";
$customerData = [
    'email' => 'test_invoice_items_' . time() . '@example.com',
    'name' => 'Test Invoice Items Customer'
];
$response = makeRequest('POST', '/v1/customers', $customerData);
if ($response['code'] === 201 || $response['code'] === 200) {
    $customerId = $response['body']['data']['id'] ?? null;
    $stripeCustomerId = $response['body']['data']['stripe_customer_id'] ?? null;
    echo "   ✅ Customer criado: ID {$customerId}\n";
    echo "   Stripe Customer ID: {$stripeCustomerId}\n";
} else {
    die("   ❌ Erro ao criar customer: " . $response['raw'] . "\n");
}

echo "\n{$green}✅ Preparação concluída!{$reset}\n";

// ============================================================================
// TESTE 1: Criar Invoice Item básico via API
// ============================================================================
printTest(1, "Criar Invoice Item básico via API");

$invoiceItemData = [
    'customer_id' => $stripeCustomerId,
    'amount' => 5000, // R$ 50,00
    'currency' => 'brl',
    'description' => 'Item de teste - Taxa de setup',
    'quantity' => 1,
    'metadata' => [
        'test' => 'true',
        'type' => 'basic'
    ]
];

$response = makeRequest('POST', '/v1/invoice-items', $invoiceItemData);
$passed = ($response['code'] === 201 || $response['code'] === 200)
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data']['id']);

// Se retornou 200 mas não tem success, verifica se tem erro
if (!$passed && $response['code'] === 200 && isset($response['body']['error'])) {
    echo "   Erro na resposta: {$response['body']['error']}\n";
    if (isset($response['body']['message'])) {
        echo "   Mensagem: {$response['body']['message']}\n";
    }
}

if ($passed) {
    $invoiceItemId = $response['body']['data']['id'];
    echo "   Invoice Item criado: {$invoiceItemId}\n";
    echo "   Customer: {$response['body']['data']['customer']}\n";
    echo "   Amount: R$ " . number_format($response['body']['data']['amount'] / 100, 2, ',', '.') . "\n";
    echo "   Currency: {$response['body']['data']['currency']}\n";
    echo "   Description: {$response['body']['data']['description']}\n";
}

printResult($passed, $passed ? "Invoice Item criado com sucesso" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 2: Criar Invoice Item com quantidade (usando price one-time)
// ============================================================================
printTest(2, "Criar Invoice Item com quantidade > 1 (usando price one-time)");

// Primeiro, precisamos criar um price one-time para usar com quantity
// Invoice Items só aceitam prices do tipo one_time, não recurring
echo "   Criando produto e price one-time para teste de quantity...\n";
$productData = [
    'name' => 'Produto Test Quantity ' . time(),
    'description' => 'Para teste de invoice item com quantity'
];
$response = makeRequest('POST', '/v1/products', $productData);
if ($response['code'] === 201 || $response['code'] === 200) {
    $productId = $response['body']['data']['id'] ?? null;
    echo "   ✅ Produto criado: {$productId}\n";
    
    // Cria price one-time (sem recurring = one-time por padrão)
    $priceData = [
        'product' => $productId,
        'unit_amount' => 2000, // R$ 20,00
        'currency' => 'brl'
        // Sem 'recurring' = one-time por padrão
    ];
    $response = makeRequest('POST', '/v1/prices', $priceData);
    if ($response['code'] === 201 || $response['code'] === 200) {
        $priceId = $response['body']['data']['id'] ?? null;
        $priceType = $response['body']['data']['type'] ?? 'unknown';
        
        if (empty($priceId)) {
            echo "   ❌ Price ID não encontrado na resposta\n";
            printResult(false, "Price não foi criado corretamente");
        } else {
            echo "   ✅ Price one-time criado: {$priceId}\n";
            echo "   Price Type: {$priceType}\n";
            
            if ($priceType === 'one_time') {
                // Tenta criar via StripeService diretamente primeiro para testar
                echo "   Testando criação direta via StripeService...\n";
                try {
                    $invoiceItemDirect = $stripeService->createInvoiceItem([
                        'customer_id' => $stripeCustomerId,
                        'price' => $priceId,
                        'description' => 'Item de teste direto - Quantidade múltipla',
                        'quantity' => 3,
                        'metadata' => [
                            'test' => 'true',
                            'type' => 'quantity_direct'
                        ]
                    ]);
                    
                    echo "   ✅ Invoice Item criado diretamente via StripeService!\n";
                    echo "   ID: {$invoiceItemDirect->id}\n";
                    echo "   Amount: R$ " . number_format(($invoiceItemDirect->amount ?? 0) / 100, 2, ',', '.') . "\n";
                    echo "   Nota: Stripe não permite 'amount' e 'quantity' juntos.\n";
                    echo "   O amount total (unit_amount * quantity) foi calculado automaticamente.\n";
                    printResult(true, "Invoice Item com quantidade criado via StripeService");
                } catch (\Exception $e) {
                    echo "   ❌ Erro ao criar via StripeService: {$e->getMessage()}\n";
                    
                    // Se falhar via serviço, tenta via API
                    echo "   Tentando via API...\n";
                    $invoiceItemData2 = [
                        'customer_id' => $stripeCustomerId,
                        'price' => $priceId,
                        'description' => 'Item de teste - Quantidade múltipla',
                        'quantity' => 3,
                        'metadata' => [
                            'test' => 'true',
                            'type' => 'quantity'
                        ]
                    ];

                    $response = makeRequest('POST', '/v1/invoice-items', $invoiceItemData2);
                    $passed = ($response['code'] === 201 || $response['code'] === 200)
                        && isset($response['body']['success'])
                        && $response['body']['success'] === true
                        && isset($response['body']['data']['quantity'])
                        && $response['body']['data']['quantity'] === 3;

                    if ($passed) {
                        echo "   Invoice Item criado: {$response['body']['data']['id']}\n";
                        echo "   Quantity: {$response['body']['data']['quantity']}\n";
                    } else {
                        if (isset($response['body']['error'])) {
                            echo "   Erro: {$response['body']['error']}\n";
                        }
                        if (isset($response['body']['message'])) {
                            echo "   Mensagem: {$response['body']['message']}\n";
                        }
                        echo "   HTTP Code: {$response['code']}\n";
                    }
                    printResult($passed, $passed ? "Invoice Item com quantidade criado" : "HTTP {$response['code']}");
                }
            } else {
                echo "   ⚠️  Price criado não é one_time (tipo: {$priceType})\n";
                printResult(false, "Price não é do tipo one_time");
            }
        }
    } else {
        echo "   Erro ao criar price: HTTP {$response['code']}\n";
        if (isset($response['body']['error'])) {
            echo "   Erro: {$response['body']['error']}\n";
        }
        printResult(false, "Não foi possível criar price para teste");
    }
} else {
    echo "   Erro ao criar produto: HTTP {$response['code']}\n";
    printResult(false, "Não foi possível criar produto para teste");
}

// ============================================================================
// TESTE 3: Validação - Tentar criar sem customer_id
// ============================================================================
printTest(3, "Validação - Tentar criar sem customer_id");

$invalidData = [
    'amount' => 1000,
    'currency' => 'brl'
];

$response = makeRequest('POST', '/v1/invoice-items', $invalidData);
$passed = $response['code'] === 400
    || (isset($response['body']['error']) && stripos($response['body']['error'], 'customer_id') !== false);

printResult($passed, $passed ? "Validação funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 4: Validação - Tentar criar sem amount e sem price
// ============================================================================
printTest(4, "Validação - Tentar criar sem amount e sem price");

$invalidData2 = [
    'customer_id' => $stripeCustomerId,
    'currency' => 'brl'
];

$response = makeRequest('POST', '/v1/invoice-items', $invalidData2);
$passed = $response['code'] === 400
    || (isset($response['body']['error']) && stripos($response['body']['error'], 'amount') !== false);

printResult($passed, $passed ? "Validação funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 5: Listar Invoice Items
// ============================================================================
printTest(5, "Listar Invoice Items");

$response = makeRequest('GET', '/v1/invoice-items');
$passed = $response['code'] === 200
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data'])
    && is_array($response['body']['data']);

if ($passed) {
    echo "   Encontrado {$response['body']['count']} invoice item(s)\n";
    if (!empty($response['body']['data'])) {
        echo "   Primeiro: {$response['body']['data'][0]['description']} (R$ " . 
             number_format(($response['body']['data'][0]['amount'] ?? 0) / 100, 2, ',', '.') . ")\n";
    }
}

printResult($passed, $passed ? "Listagem retornou " . count($response['body']['data'] ?? []) . " invoice item(s)" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 6: Listar Invoice Items com filtros (customer, pending)
// ============================================================================
printTest(6, "Listar Invoice Items com filtro customer e pending");

$response = makeRequest('GET', "/v1/invoice-items?customer={$stripeCustomerId}&pending=true&limit=5");
$passed = $response['code'] === 200
    && isset($response['body']['success'])
    && $response['body']['success'] === true;

if ($passed) {
    echo "   Retornou " . count($response['body']['data'] ?? []) . " invoice item(s) pendente(s)\n";
}

printResult($passed, $passed ? "Filtros funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 7: Obter Invoice Item por ID
// ============================================================================
printTest(7, "Obter Invoice Item por ID");

if (empty($invoiceItemId)) {
    printResult(false, "Invoice Item ID não disponível (pule este teste)");
} else {
    $response = makeRequest('GET', "/v1/invoice-items/{$invoiceItemId}");
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data']['id'])
        && $response['body']['data']['id'] === $invoiceItemId;

    if ($passed) {
        echo "   ID: {$response['body']['data']['id']}\n";
        echo "   Customer: {$response['body']['data']['customer']}\n";
        echo "   Amount: R$ " . number_format(($response['body']['data']['amount'] ?? 0) / 100, 2, ',', '.') . "\n";
        echo "   Description: {$response['body']['data']['description']}\n";
        echo "   Quantity: {$response['body']['data']['quantity']}\n";
    }

    printResult($passed, $passed ? "Invoice Item obtido com sucesso" : "HTTP {$response['code']}");
}

// ============================================================================
// TESTE 8: Atualizar Invoice Item (description e amount)
// ============================================================================
printTest(8, "Atualizar Invoice Item (description e amount)");

if (empty($invoiceItemId)) {
    printResult(false, "Invoice Item ID não disponível (pule este teste)");
} else {
    $updateData = [
        'description' => 'Descrição atualizada ' . time(),
        'amount' => 6000, // R$ 60,00
        'currency' => 'brl',
        'metadata' => [
            'updated' => 'true',
            'test' => 'invoice_items'
        ]
    ];
    
    // Nota: Não podemos atualizar quantity quando usa amount (limitação do Stripe)

    $response = makeRequest('PUT', "/v1/invoice-items/{$invoiceItemId}", $updateData);
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data']['description']);

    if ($passed) {
        echo "   Description atualizada: {$response['body']['data']['description']}\n";
        echo "   Amount atualizado: R$ " . number_format(($response['body']['data']['amount'] ?? 0) / 100, 2, ',', '.') . "\n";
        echo "   Quantity: {$response['body']['data']['quantity']}\n";
    }

    printResult($passed, $passed ? "Invoice Item atualizado com sucesso" : "HTTP {$response['code']}");
}

// ============================================================================
// TESTE 9: Teste direto do StripeService - createInvoiceItem
// ============================================================================
printTest(9, "Teste direto do StripeService - createInvoiceItem");

try {
    $invoiceItem = $stripeService->createInvoiceItem([
        'customer_id' => $stripeCustomerId,
        'amount' => 3000, // R$ 30,00
        'currency' => 'brl',
        'description' => 'Item de teste direto via serviço',
        'metadata' => [
            'test' => 'direct_service'
        ]
    ]);
    
    $passed = !empty($invoiceItem->id) && $invoiceItem->customer === $stripeCustomerId;
    echo "   Invoice Item ID: {$invoiceItem->id}\n";
    echo "   Customer: {$invoiceItem->customer}\n";
    echo "   Amount: R$ " . number_format(($invoiceItem->amount ?? 0) / 100, 2, ',', '.') . "\n";
    echo "   Description: {$invoiceItem->description}\n";
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao criar");
} catch (\Exception $e) {
    printResult(false, "Erro: " . $e->getMessage());
}

// ============================================================================
// TESTE 10: Teste direto do StripeService - listInvoiceItems
// ============================================================================
printTest(10, "Teste direto do StripeService - listInvoiceItems");

try {
    $collection = $stripeService->listInvoiceItems([
        'customer' => $stripeCustomerId,
        'limit' => 10,
        'pending' => true
    ]);
    
    $passed = !empty($collection) && isset($collection->data);
    echo "   Encontrado " . count($collection->data) . " invoice item(s)\n";
    echo "   Has more: " . ($collection->has_more ? 'sim' : 'não') . "\n";
    
    if (!empty($collection->data)) {
        echo "   Primeiro: {$collection->data[0]->description} (R$ " . 
             number_format(($collection->data[0]->amount ?? 0) / 100, 2, ',', '.') . ")\n";
    }
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao listar");
} catch (\Exception $e) {
    printResult(false, "Erro: " . $e->getMessage());
}

// ============================================================================
// TESTE 11: Remover Invoice Item
// ============================================================================
printTest(11, "Remover Invoice Item (último teste)");

if (empty($invoiceItemId)) {
    printResult(false, "Invoice Item ID não disponível (pule este teste)");
} else {
    $response = makeRequest('DELETE', "/v1/invoice-items/{$invoiceItemId}");
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true;

    echo "   Item removido: {$invoiceItemId}\n";
    printResult($passed, $passed ? "Invoice Item removido com sucesso" : "HTTP {$response['code']}");
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


<?php

/**
 * Testes manuais para Tax Rates (taxas de imposto)
 * 
 * Tax Rates são usadas para calcular impostos automaticamente em invoices e subscriptions.
 * Útil para compliance fiscal (IVA, GST, ICMS, etc.).
 * 
 * Execute: php tests/Manual/test_tax_rates.php
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
$taxRateId = null;

echo "\n{$blue}╔═══════════════════════════════════════════════════════════════╗{$reset}";
echo "\n{$blue}║     TESTES MANUAIS - TAX RATES                                 ║{$reset}";
echo "\n{$blue}╚═══════════════════════════════════════════════════════════════╝{$reset}\n";

// ============================================================================
// TESTE 1: Criar Tax Rate básico (exclusivo) via API
// ============================================================================
printTest(1, "Criar Tax Rate básico (exclusivo) via API");

$taxRateData = [
    'display_name' => 'IVA Test ' . time(),
    'description' => 'Taxa de IVA para testes',
    'percentage' => 20.0,
    'inclusive' => false,
    'country' => 'BR',
    'metadata' => [
        'test' => 'true',
        'type' => 'basic'
    ]
];

$response = makeRequest('POST', '/v1/tax-rates', $taxRateData);
$passed = ($response['code'] === 201 || $response['code'] === 200)
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data']['id']);

if ($passed) {
    $taxRateId = $response['body']['data']['id'];
    echo "   Tax Rate criado: {$taxRateId}\n";
    echo "   Display Name: {$response['body']['data']['display_name']}\n";
    echo "   Percentage: {$response['body']['data']['percentage']}%\n";
    echo "   Inclusive: " . ($response['body']['data']['inclusive'] ? 'sim' : 'não') . "\n";
    echo "   Country: {$response['body']['data']['country']}\n";
}

printResult($passed, $passed ? "Tax Rate criado com sucesso" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 2: Criar Tax Rate inclusivo
// ============================================================================
printTest(2, "Criar Tax Rate inclusivo (imposto incluído no preço)");

$taxRateData2 = [
    'display_name' => 'GST Inclusivo ' . time(),
    'description' => 'GST incluído no preço',
    'percentage' => 10.0,
    'inclusive' => true,
    'country' => 'AU',
    'state' => 'NSW',
    'metadata' => [
        'test' => 'true',
        'type' => 'inclusive'
    ]
];

$response = makeRequest('POST', '/v1/tax-rates', $taxRateData2);
$passed = ($response['code'] === 201 || $response['code'] === 200)
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data']['id'])
    && $response['body']['data']['inclusive'] === true;

if ($passed) {
    echo "   Tax Rate criado: {$response['body']['data']['id']}\n";
    echo "   Inclusive: " . ($response['body']['data']['inclusive'] ? 'sim' : 'não') . "\n";
    echo "   State: {$response['body']['data']['state']}\n";
}

printResult($passed, $passed ? "Tax Rate inclusivo criado" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 3: Validação - Tentar criar sem display_name
// ============================================================================
printTest(3, "Validação - Tentar criar sem display_name");

$invalidData = [
    'percentage' => 15.0
];

$response = makeRequest('POST', '/v1/tax-rates', $invalidData);
$passed = $response['code'] === 400
    || (isset($response['body']['error']) && stripos($response['body']['error'], 'display_name') !== false);

printResult($passed, $passed ? "Validação funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 4: Validação - Tentar criar sem percentage
// ============================================================================
printTest(4, "Validação - Tentar criar sem percentage");

$invalidData2 = [
    'display_name' => 'Test Tax'
];

$response = makeRequest('POST', '/v1/tax-rates', $invalidData2);
$passed = $response['code'] === 400
    || (isset($response['body']['error']) && stripos($response['body']['error'], 'percentage') !== false);

printResult($passed, $passed ? "Validação funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 5: Listar Tax Rates
// ============================================================================
printTest(5, "Listar Tax Rates");

$response = makeRequest('GET', '/v1/tax-rates');
$passed = $response['code'] === 200
    && isset($response['body']['success'])
    && $response['body']['success'] === true
    && isset($response['body']['data'])
    && is_array($response['body']['data']);

if ($passed) {
    echo "   Encontrado {$response['body']['count']} tax rate(s)\n";
    if (!empty($response['body']['data'])) {
        echo "   Primeiro: {$response['body']['data'][0]['display_name']} ({$response['body']['data'][0]['percentage']}%)\n";
    }
}

printResult($passed, $passed ? "Listagem retornou " . count($response['body']['data'] ?? []) . " tax rate(s)" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 6: Listar Tax Rates com filtros (active)
// ============================================================================
printTest(6, "Listar Tax Rates com filtro active=true");

$response = makeRequest('GET', '/v1/tax-rates?active=true&limit=5');
$passed = $response['code'] === 200
    && isset($response['body']['success'])
    && $response['body']['success'] === true;

if ($passed) {
    echo "   Retornou " . count($response['body']['data'] ?? []) . " tax rate(s) ativo(s)\n";
}

printResult($passed, $passed ? "Filtro funcionando" : "HTTP {$response['code']}");

// ============================================================================
// TESTE 7: Obter Tax Rate por ID
// ============================================================================
printTest(7, "Obter Tax Rate por ID");

if (empty($taxRateId)) {
    printResult(false, "Tax Rate ID não disponível (pule este teste)");
} else {
    $response = makeRequest('GET', "/v1/tax-rates/{$taxRateId}");
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data']['id'])
        && $response['body']['data']['id'] === $taxRateId;

    if ($passed) {
        echo "   ID: {$response['body']['data']['id']}\n";
        echo "   Display Name: {$response['body']['data']['display_name']}\n";
        echo "   Percentage: {$response['body']['data']['percentage']}%\n";
        echo "   Active: " . ($response['body']['data']['active'] ? 'sim' : 'não') . "\n";
    }

    printResult($passed, $passed ? "Tax Rate obtido com sucesso" : "HTTP {$response['code']}");
}

// ============================================================================
// TESTE 8: Atualizar Tax Rate (display_name e active)
// ============================================================================
printTest(8, "Atualizar Tax Rate (display_name e active)");

if (empty($taxRateId)) {
    printResult(false, "Tax Rate ID não disponível (pule este teste)");
} else {
    $updateData = [
        'display_name' => 'IVA Atualizado ' . time(),
        'description' => 'Descrição atualizada',
        'active' => true,
        'metadata' => [
            'updated' => 'true',
            'test' => 'tax_rates'
        ]
    ];

    $response = makeRequest('PUT', "/v1/tax-rates/{$taxRateId}", $updateData);
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data']['display_name']);

    if ($passed) {
        echo "   Display Name atualizado: {$response['body']['data']['display_name']}\n";
        echo "   Description: {$response['body']['data']['description']}\n";
        echo "   Active: " . ($response['body']['data']['active'] ? 'sim' : 'não') . "\n";
    }

    printResult($passed, $passed ? "Tax Rate atualizado com sucesso" : "HTTP {$response['code']}");
}

// ============================================================================
// TESTE 9: Teste direto do StripeService - createTaxRate
// ============================================================================
printTest(9, "Teste direto do StripeService - createTaxRate");

try {
    $taxRate = $stripeService->createTaxRate([
        'display_name' => 'ICMS Test ' . time(),
        'description' => 'ICMS para teste direto',
        'percentage' => 18.0,
        'inclusive' => false,
        'country' => 'BR',
        'state' => 'SP',
        'metadata' => [
            'test' => 'direct_service'
        ]
    ]);
    
    $passed = !empty($taxRate->id) && $taxRate->percentage === 18.0;
    echo "   Tax Rate ID: {$taxRate->id}\n";
    echo "   Display Name: {$taxRate->display_name}\n";
    echo "   Percentage: {$taxRate->percentage}%\n";
    echo "   Country: {$taxRate->country}\n";
    echo "   State: {$taxRate->state}\n";
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao criar");
} catch (\Exception $e) {
    printResult(false, "Erro: " . $e->getMessage());
}

// ============================================================================
// TESTE 10: Teste direto do StripeService - listTaxRates
// ============================================================================
printTest(10, "Teste direto do StripeService - listTaxRates");

try {
    $collection = $stripeService->listTaxRates(['limit' => 10, 'active' => true]);
    
    $passed = !empty($collection) && isset($collection->data);
    echo "   Encontrado " . count($collection->data) . " tax rate(s)\n";
    echo "   Has more: " . ($collection->has_more ? 'sim' : 'não') . "\n";
    
    if (!empty($collection->data)) {
        echo "   Primeiro: {$collection->data[0]->display_name} ({$collection->data[0]->percentage}%)\n";
    }
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao listar");
} catch (\Exception $e) {
    printResult(false, "Erro: " . $e->getMessage());
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


<?php
/**
 * Teste Completo - API de Payouts (Saques)
 * 
 * Este script testa todos os endpoints de payouts implementados:
 * - GET /v1/payouts - Listar payouts
 * - GET /v1/payouts/:id - Obter payout específico
 * - POST /v1/payouts - Criar payout manual (opcional)
 * - POST /v1/payouts/:id/cancel - Cancelar payout pendente
 * 
 * Uso:
 *   php tests/Manual/test_payouts.php
 * 
 * Pré-requisitos:
 *   - Servidor rodando (php -S localhost:8080 -t public)
 *   - API Key válida configurada
 *   - Dados de teste no Stripe (payouts existentes, se houver)
 *   - Config.php carregado
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = Config::get('TEST_API_KEY', '2259e1ec9b69c26140000304940d58e7ee4ccd61c6a3771e3e5719d6e7c41035');
$baseUrl = Config::get('BASE_URL', 'http://localhost:8080');

// Cores para output
$colors = [
    'reset' => "\033[0m",
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'cyan' => "\033[36m"
];

function printHeader($text) {
    global $colors;
    echo "\n" . $colors['cyan'] . "=== $text ===" . $colors['reset'] . "\n";
}

function printSuccess($text) {
    global $colors;
    echo $colors['green'] . "✓ $text" . $colors['reset'] . "\n";
}

function printError($text) {
    global $colors;
    echo $colors['red'] . "✗ $text" . $colors['reset'] . "\n";
}

function printInfo($text) {
    global $colors;
    echo $colors['blue'] . "ℹ $text" . $colors['reset'] . "\n";
}

function makeRequest($method, $endpoint, $data = null) {
    global $baseUrl, $apiKey, $colors;
    
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => "Erro cURL: $error",
            'http_code' => 0
        ];
    }
    
    $data = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $data,
        'raw_response' => $response
    ];
}

function testEndpoint($name, $method, $endpoint, $data = null, $expectedFields = []) {
    printHeader("Testando: $name");
    printInfo("$method $endpoint" . ($data ? ' (com body)' : ''));
    
    $result = makeRequest($method, $endpoint, $data);
    
    if (!$result['success']) {
        printError("Falhou: HTTP {$result['http_code']}");
        if (isset($result['data']['error'])) {
            printError("Erro: " . $result['data']['error']);
            if (isset($result['data']['message'])) {
                printError("Mensagem: " . $result['data']['message']);
            }
        }
        echo "Resposta: " . substr($result['raw_response'], 0, 300) . "\n";
        return false;
    }
    
    if (!isset($result['data']['success']) || $result['data']['success'] !== true) {
        printError("Resposta não tem success=true");
        echo "Resposta: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
        return false;
    }
    
    if (!isset($result['data']['data'])) {
        printError("Resposta não tem campo 'data'");
        echo "Resposta: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
        return false;
    }
    
    $responseData = $result['data']['data'];
    
    // Verifica campos esperados (se for array, verifica primeiro item)
    if (!empty($expectedFields)) {
        $checkData = is_array($responseData) && isset($responseData[0]) ? $responseData[0] : $responseData;
        
        $missingFields = [];
        foreach ($expectedFields as $field) {
            if (!isset($checkData[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            printError("Campos faltando: " . implode(', ', $missingFields));
            echo "Dados: " . json_encode($checkData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            return false;
        }
    }
    
    printSuccess("Endpoint funcionando corretamente!");
    printInfo("Dados retornados:");
    echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    return true;
}

// Verifica se API Key foi configurada
if (empty($apiKey)) {
    printError("Por favor, configure TEST_API_KEY no config.php ou .env!");
    exit(1);
}

printHeader("TESTE DA API DE PAYOUTS (SAQUES)");
printInfo("Base URL: $baseUrl");
printInfo("API Key: " . substr($apiKey, 0, 10) . "...");

$testsPassed = 0;
$testsFailed = 0;

// Teste 1: Listar Payouts
printHeader("TESTE 1: Listar Payouts");
if (testEndpoint(
    'Listar payouts (padrão)',
    'GET',
    '/v1/payouts',
    null,
    ['id', 'amount', 'currency', 'status', 'created']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 2: Listar Payouts com filtro de status
if (testEndpoint(
    'Listar payouts com filtro status=paid',
    'GET',
    '/v1/payouts?status=paid',
    null,
    ['id', 'status']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 3: Listar Payouts com filtro de data
$startDate = strtotime('-30 days');
$endDate = time();
if (testEndpoint(
    'Listar payouts com filtro de data',
    'GET',
    "/v1/payouts?created_gte=$startDate&created_lte=$endDate",
    null,
    ['id']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 4: Listar Payouts com limite
if (testEndpoint(
    'Listar payouts com limite=5',
    'GET',
    '/v1/payouts?limit=5',
    null,
    ['id']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 5: Obter Payout específico (primeiro da lista)
printHeader("TESTE 2: Obter Payout Específico");
$listResult = makeRequest('GET', '/v1/payouts?limit=1');
if ($listResult['success'] && isset($listResult['data']['data'][0]['id'])) {
    $payoutId = $listResult['data']['data'][0]['id'];
    printInfo("Testando com payout ID: $payoutId");
    
    if (testEndpoint(
        'Obter payout específico',
        'GET',
        "/v1/payouts/$payoutId",
        null,
        ['id', 'amount', 'currency', 'status', 'created', 'arrival_date']
    )) {
        $testsPassed++;
    } else {
        $testsFailed++;
    }
} else {
    printError("Não foi possível obter um payout para teste");
    printInfo("Pulando teste de obter payout específico");
    $testsFailed++;
}

// Teste 6: Criar Payout (opcional - pode falhar se não tiver saldo)
printHeader("TESTE 3: Criar Payout (Manual)");
printInfo("⚠️  Nota: Este teste pode falhar se não houver saldo disponível no Stripe");
printInfo("⚠️  Este teste cria um payout real (pode ser cancelado depois)");

$createData = [
    'amount' => 100, // 1 real em centavos (valor mínimo)
    'currency' => 'brl',
    'description' => 'Teste de payout - ' . date('Y-m-d H:i:s')
];

if (testEndpoint(
    'Criar payout manual',
    'POST',
    '/v1/payouts',
    $createData,
    ['id', 'amount', 'currency', 'status']
)) {
    $testsPassed++;
    
    // Guarda o ID para tentar cancelar depois
    $createResult = makeRequest('POST', '/v1/payouts', $createData);
    if ($createResult['success'] && isset($createResult['data']['data']['id'])) {
        $newPayoutId = $createResult['data']['data']['id'];
        $newPayoutStatus = $createResult['data']['data']['status'] ?? null;
        
        printInfo("Payout criado com ID: $newPayoutId");
        printInfo("Status: $newPayoutStatus");
        
        // Teste 7: Cancelar Payout (apenas se for pendente)
        printHeader("TESTE 4: Cancelar Payout");
        if ($newPayoutStatus === 'pending') {
            printInfo("Tentando cancelar payout pendente...");
            
            if (testEndpoint(
                'Cancelar payout pendente',
                'POST',
                "/v1/payouts/$newPayoutId/cancel",
                null,
                ['id', 'status']
            )) {
                $testsPassed++;
            } else {
                $testsFailed++;
            }
        } else {
            printInfo("Payout não está pendente (status: $newPayoutStatus), não pode ser cancelado");
            printInfo("Pulando teste de cancelamento");
        }
    }
} else {
    printError("Não foi possível criar payout para teste");
    printInfo("Pulando teste de cancelamento");
    $testsFailed++;
}

// Teste 8: Tentar cancelar payout que não existe
printHeader("TESTE 5: Validações de Erro");
$fakeId = 'po_fake123';
$cancelResult = makeRequest('POST', "/v1/payouts/$fakeId/cancel");
if (!$cancelResult['success']) {
    if ($cancelResult['http_code'] === 404 || $cancelResult['http_code'] === 400) {
        printSuccess("Validação funcionando: payout inexistente retorna erro");
        $testsPassed++;
    } else {
        printError("Esperava erro 404 ou 400 para payout inexistente, recebeu: {$cancelResult['http_code']}");
        $testsFailed++;
    }
} else {
    printError("Não deveria ter sucesso ao cancelar payout inexistente");
    $testsFailed++;
}

// Teste 9: Tentar criar payout sem campos obrigatórios
$invalidData = ['currency' => 'brl']; // Sem amount
$createInvalidResult = makeRequest('POST', '/v1/payouts', $invalidData);
if (!$createInvalidResult['success']) {
    if ($createInvalidResult['http_code'] === 400) {
        printSuccess("Validação funcionando: campos obrigatórios validados");
        $testsPassed++;
    } else {
        printError("Esperava erro 400 para campos inválidos, recebeu: {$createInvalidResult['http_code']}");
        $testsFailed++;
    }
} else {
    printError("Não deveria ter sucesso ao criar payout sem campos obrigatórios");
    $testsFailed++;
}

// Resumo
printHeader("RESUMO DOS TESTES");
echo "Total de testes: " . ($testsPassed + $testsFailed) . "\n";
printSuccess("Testes passaram: $testsPassed");
if ($testsFailed > 0) {
    printError("Testes falharam: $testsFailed");
} else {
    printSuccess("Todos os testes passaram! 🎉");
}

echo "\n";


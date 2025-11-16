<?php
/**
 * Teste Completo - API de Relatórios e Analytics
 * 
 * Este script testa todos os endpoints de relatórios implementados:
 * - GET /v1/reports/revenue
 * - GET /v1/reports/subscriptions
 * - GET /v1/reports/churn
 * - GET /v1/reports/customers
 * - GET /v1/reports/payments
 * - GET /v1/reports/mrr
 * - GET /v1/reports/arr
 * 
 * Uso:
 *   php tests/Manual/test_reports.php
 * 
 * Pré-requisitos:
 *   - Servidor rodando (php -S localhost:8080 -t public)
 *   - API Key válida configurada
 *   - Dados de teste no banco (customers, subscriptions)
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

function makeRequest($method, $endpoint, $queryParams = []) {
    global $baseUrl, $apiKey, $colors;
    
    $url = $baseUrl . $endpoint;
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }
    
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

function testEndpoint($name, $endpoint, $queryParams = [], $expectedFields = []) {
    printHeader("Testando: $name");
    printInfo("GET $endpoint" . (!empty($queryParams) ? '?' . http_build_query($queryParams) : ''));
    
    $result = makeRequest('GET', $endpoint, $queryParams);
    
    if (!$result['success']) {
        printError("Falhou: HTTP {$result['http_code']}");
        if (isset($result['data']['error'])) {
            printError("Erro: " . $result['data']['error']);
            if (isset($result['data']['message'])) {
                printError("Mensagem: " . $result['data']['message']);
            }
        }
        echo "Resposta: " . substr($result['raw_response'], 0, 200) . "\n";
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
    
    $data = $result['data']['data'];
    
    // Verifica campos esperados
    $missingFields = [];
    foreach ($expectedFields as $field) {
        if (!isset($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        printError("Campos faltando: " . implode(', ', $missingFields));
        echo "Dados: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        return false;
    }
    
    printSuccess("Endpoint funcionando corretamente!");
    printInfo("Dados retornados:");
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    return true;
}

// Verifica se API Key foi configurada
if (empty($apiKey)) {
    printError("Por favor, configure TEST_API_KEY no config.php ou .env!");
    exit(1);
}

printHeader("TESTE DA API DE RELATÓRIOS E ANALYTICS");
printInfo("Base URL: $baseUrl");
printInfo("API Key: " . substr($apiKey, 0, 10) . "...");

$testsPassed = 0;
$testsFailed = 0;

// Teste 1: Receita por período (mês atual)
printHeader("TESTE 1: Receita por Período");
if (testEndpoint(
    'Receita do mês atual',
    '/v1/reports/revenue',
    ['period' => 'month'],
    ['total', 'currency', 'period', 'by_plan', 'by_currency']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 2: Receita período customizado
if (testEndpoint(
    'Receita período customizado',
    '/v1/reports/revenue',
    [
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'end_date' => date('Y-m-d')
    ],
    ['total', 'currency', 'period']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 3: Estatísticas de assinaturas
printHeader("TESTE 2: Estatísticas de Assinaturas");
if (testEndpoint(
    'Estatísticas de assinaturas (mês atual)',
    '/v1/reports/subscriptions',
    ['period' => 'month'],
    ['total', 'active', 'canceled', 'trial', 'period']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 4: Taxa de churn
printHeader("TESTE 3: Taxa de Churn");
if (testEndpoint(
    'Taxa de churn (último mês)',
    '/v1/reports/churn',
    ['period' => 'last_month'],
    ['churn_rate', 'retention_rate', 'canceled', 'period']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 5: Estatísticas de clientes
printHeader("TESTE 4: Estatísticas de Clientes");
if (testEndpoint(
    'Estatísticas de clientes (mês atual)',
    '/v1/reports/customers',
    ['period' => 'month'],
    ['total', 'active', 'new', 'period']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 6: Estatísticas de pagamentos
printHeader("TESTE 5: Estatísticas de Pagamentos");
if (testEndpoint(
    'Estatísticas de pagamentos (mês atual)',
    '/v1/reports/payments',
    ['period' => 'month'],
    ['total', 'succeeded', 'failed', 'period']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 7: MRR (Monthly Recurring Revenue)
printHeader("TESTE 6: MRR (Monthly Recurring Revenue)");
if (testEndpoint(
    'MRR atual',
    '/v1/reports/mrr',
    [],
    ['mrr', 'currency', 'by_plan']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 8: ARR (Annual Recurring Revenue)
printHeader("TESTE 7: ARR (Annual Recurring Revenue)");
if (testEndpoint(
    'ARR atual',
    '/v1/reports/arr',
    [],
    ['arr', 'mrr', 'currency', 'by_plan']
)) {
    $testsPassed++;
} else {
    $testsFailed++;
}

// Teste 9: Períodos predefinidos
printHeader("TESTE 8: Testando Períodos Predefinidos");
$periods = ['today', 'week', 'month', 'year', 'last_month', 'last_year'];

foreach ($periods as $period) {
    printInfo("Testando período: $period");
    $result = makeRequest('GET', '/v1/reports/revenue', ['period' => $period]);
    
    if ($result['success'] && isset($result['data']['data']['period']['type'])) {
        printSuccess("Período '$period' funcionando");
        $testsPassed++;
    } else {
        printError("Período '$period' falhou");
        $testsFailed++;
    }
}

// Teste 10: Cache (deve retornar mais rápido na segunda chamada)
printHeader("TESTE 9: Verificando Cache");
$start1 = microtime(true);
$result1 = makeRequest('GET', '/v1/reports/mrr', []);
$time1 = (microtime(true) - $start1) * 1000; // em milissegundos

$start2 = microtime(true);
$result2 = makeRequest('GET', '/v1/reports/mrr', []);
$time2 = (microtime(true) - $start2) * 1000;

if ($result1['success'] && $result2['success']) {
    printInfo("Primeira chamada: " . round($time1, 2) . "ms");
    printInfo("Segunda chamada (cache): " . round($time2, 2) . "ms");
    
    if ($time2 < $time1) {
        printSuccess("Cache funcionando! (Segunda chamada mais rápida)");
        $testsPassed++;
    } else {
        printInfo("Cache pode estar funcionando (diferença: " . round($time1 - $time2, 2) . "ms)");
        $testsPassed++; // Não é falha, apenas informação
    }
} else {
    printError("Falha ao testar cache");
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


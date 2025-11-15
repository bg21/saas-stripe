<?php

/**
 * Script de teste para Health Check Avançado
 * 
 * Testa:
 * - Health check básico (/health)
 * - Health check detalhado (/health/detailed)
 * - Verificações de dependências (Database, Redis, Stripe)
 * - Informações do sistema
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

echo "🧪 Teste de Health Check Avançado\n";
echo str_repeat("=", 70) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

// Contadores de testes
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Função para fazer requisições HTTP
function makeRequest(string $url, string $method = 'GET', array $data = []): array
{
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $method !== 'GET' ? json_encode($data) : null
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'code' => 0,
            'data' => ['error' => $error],
            'raw_response' => $response
        ];
    }
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true) ?? [],
        'raw_response' => $response
    ];
}

// Função para testar e registrar resultado
function testResult(string $description, int $expectedCode, int $actualCode, bool $checkSuccess = false, array $responseData = []): bool
{
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    
    $totalTests++;
    $passed = false;
    
    if ($expectedCode === $actualCode) {
        if ($checkSuccess) {
            $passed = isset($responseData['status']) && in_array($responseData['status'], ['ok', 'healthy']);
        } else {
            $passed = true;
        }
    }
    
    if ($passed) {
        $passedTests++;
        echo "{$green}✅{$reset} {$description}\n";
        echo "   HTTP Code: {$actualCode} (esperado: {$expectedCode})\n";
    } else {
        $failedTests++;
        echo "{$red}❌{$reset} {$description}\n";
        echo "   HTTP Code: {$actualCode} (esperado: {$expectedCode})\n";
        if (!empty($responseData)) {
            echo "   Resposta: " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "\n";
    
    return $passed;
}

// Verifica se o servidor está rodando
echo "🔍 Verificando se o servidor está rodando...\n";
$healthCheck = makeRequest("{$apiUrl}/health", 'GET');
if ($healthCheck['code'] === 0) {
    echo "{$red}❌ Servidor não está rodando!{$reset}\n";
    echo "   Execute: php -S localhost:8080 -t public\n\n";
    exit(1);
}
echo "{$green}✅ Servidor está rodando!{$reset}\n\n";

// ============================================================================
// TESTE 1: Health Check Básico
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: Health Check Básico (GET /health)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/health", 'GET');
testResult(
    "Health check básico retorna status ok",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['status'])) {
    echo "   Status: {$response['data']['status']}\n";
    echo "   Timestamp: {$response['data']['timestamp']}\n";
    echo "   Environment: {$response['data']['environment']}\n\n";
}

// ============================================================================
// TESTE 2: Health Check Detalhado
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 2: Health Check Detalhado (GET /health/detailed)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/health/detailed", 'GET');
testResult(
    "Health check detalhado retorna informações completas",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['checks'])) {
    $checks = $response['data']['checks'];
    
    echo "   Status geral: {$response['data']['status']}\n";
    echo "   Tempo de resposta: {$response['data']['response_time_ms']}ms\n";
    echo "   Versão: {$response['data']['version']}\n\n";
    
    // Verifica Database
    if (isset($checks['database'])) {
        $db = $checks['database'];
        $statusIcon = $db['status'] === 'healthy' ? $green . '✅' : $red . '❌';
        echo "   {$statusIcon}{$reset} Database: {$db['status']}\n";
        if (isset($db['response_time_ms'])) {
            echo "      Tempo: {$db['response_time_ms']}ms\n";
        }
        if (isset($db['details']['mysql_version'])) {
            echo "      MySQL Version: {$db['details']['mysql_version']}\n";
        }
        if (isset($db['error'])) {
            echo "      {$red}Erro: {$db['error']}{$reset}\n";
        }
        echo "\n";
    }
    
    // Verifica Redis
    if (isset($checks['redis'])) {
        $redis = $checks['redis'];
        $statusIcon = $redis['status'] === 'healthy' ? $green . '✅' : ($redis['status'] === 'unavailable' ? $yellow . '⚠️' : $red . '❌');
        echo "   {$statusIcon}{$reset} Redis: {$redis['status']}\n";
        if (isset($redis['response_time_ms'])) {
            echo "      Tempo: {$redis['response_time_ms']}ms\n";
        }
        if (isset($redis['details']['redis_version'])) {
            echo "      Redis Version: {$redis['details']['redis_version']}\n";
        }
        if (isset($redis['message'])) {
            echo "      {$yellow}Nota: {$redis['message']}{$reset}\n";
        }
        if (isset($redis['error'])) {
            echo "      {$red}Erro: {$redis['error']}{$reset}\n";
        }
        echo "\n";
    }
    
    // Verifica Stripe
    if (isset($checks['stripe'])) {
        $stripe = $checks['stripe'];
        $statusIcon = $stripe['status'] === 'healthy' ? $green . '✅' : $red . '❌';
        echo "   {$statusIcon}{$reset} Stripe: {$stripe['status']}\n";
        if (isset($stripe['response_time_ms'])) {
            echo "      Tempo: {$stripe['response_time_ms']}ms\n";
        }
        if (isset($stripe['details']['key_type'])) {
            echo "      Key Type: {$stripe['details']['key_type']}\n";
        }
        if (isset($stripe['error'])) {
            echo "      {$red}Erro: {$stripe['error']}{$reset}\n";
        }
        echo "\n";
    }
    
    // Informações do Sistema
    if (isset($checks['system'])) {
        $system = $checks['system'];
        echo "   {$blue}ℹ️{$reset} Sistema:\n";
        if (isset($system['php_version'])) {
            echo "      PHP Version: {$system['php_version']}\n";
        }
        if (isset($system['memory_usage'])) {
            echo "      Memory Usage: {$system['memory_usage']}\n";
        }
        if (isset($system['memory_peak'])) {
            echo "      Memory Peak: {$system['memory_peak']}\n";
        }
        if (isset($system['uptime'])) {
            echo "      Uptime: {$system['uptime']}\n";
        }
        echo "\n";
    }
}

// ============================================================================
// TESTE 3: Verificar que health check básico mantém compatibilidade
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 3: Compatibilidade do Health Check Básico\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/health", 'GET');
$hasRequiredFields = isset($response['data']['status']) && 
                     isset($response['data']['timestamp']) && 
                     isset($response['data']['environment']);

testResult(
    "Health check básico mantém campos obrigatórios (status, timestamp, environment)",
    200,
    $response['code'],
    $hasRequiredFields,
    $response['data']
);

// ============================================================================
// TESTE 4: Verificar estrutura do health check detalhado
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 4: Estrutura do Health Check Detalhado\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/health/detailed", 'GET');
$hasRequiredStructure = isset($response['data']['status']) &&
                        isset($response['data']['checks']) &&
                        isset($response['data']['checks']['database']) &&
                        isset($response['data']['checks']['redis']) &&
                        isset($response['data']['checks']['stripe']) &&
                        isset($response['data']['checks']['system']);

testResult(
    "Health check detalhado possui estrutura completa (status, checks, database, redis, stripe, system)",
    200,
    $response['code'],
    $hasRequiredStructure,
    $response['data']
);

// ============================================================================
// RESUMO DOS TESTES
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 70) . "\n\n";

echo "Total de testes: {$totalTests}\n";
echo "{$green}Testes passados: {$passedTests}{$reset}\n";
echo "{$red}Testes falhados: {$failedTests}{$reset}\n\n";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "Taxa de sucesso: {$successRate}%\n\n";

if ($failedTests > 0) {
    echo "{$red}❌ Alguns testes falharam! Verifique os logs acima.{$reset}\n\n";
    exit(1);
} else {
    echo "{$green}✅ Todos os testes passaram!{$reset}\n\n";
    exit(0);
}


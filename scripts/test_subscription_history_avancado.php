<?php

/**
 * Script de teste para Histórico de Mudanças de Assinatura (versão avançada)
 * 
 * Testa:
 * - Filtros avançados (tipo, data, changed_by, user_id)
 * - Estatísticas do histórico
 * - Rastreamento de user_id
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\SubscriptionHistory;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Tenant;

Config::load();

echo "🧪 Teste de Histórico de Mudanças de Assinatura (Avançado)\n";
echo str_repeat("=", 70) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';
$tenantId = 1;

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

// Contadores de testes
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Função para fazer requisições HTTP
function makeRequest(string $url, string $method = 'GET', array $data = [], ?string $token = null): array
{
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
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
            $passed = isset($responseData['success']) && $responseData['success'] === true;
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

// Função para fazer login e obter Session ID
function login(string $email, string $password, int $tenantId): ?string
{
    global $apiUrl;
    
    $loginData = [
        'email' => $email,
        'password' => $password,
        'tenant_id' => $tenantId
    ];
    
    $response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', $loginData);
    
    if ($response['code'] === 200 && isset($response['data']['data']['session_id'])) {
        return $response['data']['data']['session_id'];
    }
    
    return null;
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

// Obtém API Key do tenant
echo "🔍 Obtendo API Key do tenant...\n";
$tenantModel = new Tenant();
$tenant = $tenantModel->findById($tenantId);
if (!$tenant || empty($tenant['api_key'])) {
    echo "{$red}❌ Tenant não encontrado ou API Key não configurada!{$reset}\n";
    echo "   Execute: composer run seed\n\n";
    exit(1);
}
$apiKey = $tenant['api_key'];
echo "{$green}✅ API Key obtida!{$reset}\n\n";

// Busca uma assinatura existente
echo "🔍 Buscando assinatura para testar histórico...\n";
$subscriptionModel = new Subscription();
$subscriptions = $subscriptionModel->findByTenant($tenantId);
if (empty($subscriptions)) {
    echo "{$yellow}⚠️  Nenhuma assinatura encontrada. Criando uma para teste...{$reset}\n";
    // Aqui você poderia criar uma assinatura de teste, mas vamos usar o histórico diretamente
    echo "   Por favor, crie uma assinatura primeiro ou use uma existente.\n\n";
    exit(1);
}
$testSubscription = $subscriptions[0];
$testSubscriptionId = $testSubscription['id'];
echo "{$green}✅ Assinatura encontrada: ID {$testSubscriptionId}{$reset}\n\n";

// Login como admin
echo "🔐 Fazendo login como admin...\n";
$adminSessionId = login('admin@example.com', 'admin123', $tenantId);
if (!$adminSessionId) {
    echo "{$yellow}⚠️  Login como admin falhou (pode não existir){$reset}\n\n";
} else {
    echo "{$green}✅ Login como admin bem-sucedido!{$reset}\n\n";
}

// ============================================================================
// TESTE 1: Listar histórico completo
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: Listar histórico completo\n";
echo str_repeat("=", 70) . "\n\n";

$token = $adminSessionId ?? $apiKey;
$response = makeRequest("{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history", 'GET', [], $token);
testResult(
    "Listar histórico completo",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['data']) && count($response['data']['data']) > 0) {
    echo "   Total de registros: " . count($response['data']['data']) . "\n";
    echo "   Primeiro registro: " . ($response['data']['data'][0]['change_type'] ?? 'N/A') . "\n\n";
}

// ============================================================================
// TESTE 2: Filtrar por tipo de mudança
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 2: Filtrar por tipo de mudança (created)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest(
    "{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history?change_type=created",
    'GET',
    [],
    $token
);
testResult(
    "Filtrar por change_type=created",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['filters_applied']['change_type'])) {
    echo "   Filtro aplicado: {$response['data']['filters_applied']['change_type']}\n\n";
}

// ============================================================================
// TESTE 3: Filtrar por origem (changed_by)
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 3: Filtrar por origem (changed_by=api)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest(
    "{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history?changed_by=api",
    'GET',
    [],
    $token
);
testResult(
    "Filtrar por changed_by=api",
    200,
    $response['code'],
    true,
    $response['data']
);

// ============================================================================
// TESTE 4: Filtrar por data
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 4: Filtrar por data (últimos 30 dias)\n";
echo str_repeat("=", 70) . "\n\n";

$dateFrom = date('Y-m-d', strtotime('-30 days'));
$dateTo = date('Y-m-d');

$response = makeRequest(
    "{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history?date_from={$dateFrom}&date_to={$dateTo}",
    'GET',
    [],
    $token
);
testResult(
    "Filtrar por data (últimos 30 dias)",
    200,
    $response['code'],
    true,
    $response['data']
);

// ============================================================================
// TESTE 5: Obter estatísticas do histórico
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 5: Obter estatísticas do histórico\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest(
    "{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history/stats",
    'GET',
    [],
    $token
);
testResult(
    "Obter estatísticas do histórico",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['data']['total_changes'])) {
    echo "   Total de mudanças: {$response['data']['data']['total_changes']}\n";
    echo "   Tipos únicos: {$response['data']['data']['unique_change_types']}\n";
    echo "   Fontes únicas: {$response['data']['data']['unique_sources']}\n";
    if (isset($response['data']['data']['by_type'])) {
        echo "   Por tipo:\n";
        foreach ($response['data']['data']['by_type'] as $type => $count) {
            if ($count > 0) {
                echo "     - {$type}: {$count}\n";
            }
        }
    }
    echo "\n";
}

// ============================================================================
// TESTE 6: Paginação
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 6: Paginação (limit=5, offset=0)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest(
    "{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history?limit=5&offset=0",
    'GET',
    [],
    $token
);
testResult(
    "Paginação (limit=5, offset=0)",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['pagination'])) {
    echo "   Total: {$response['data']['pagination']['total']}\n";
    echo "   Limit: {$response['data']['pagination']['limit']}\n";
    echo "   Offset: {$response['data']['pagination']['offset']}\n";
    echo "   Has more: " . ($response['data']['pagination']['has_more'] ? 'Sim' : 'Não') . "\n\n";
}

// ============================================================================
// TESTE 7: Verificar se user_id está sendo rastreado
// ============================================================================
if ($adminSessionId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 7: Verificar rastreamento de user_id\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Busca histórico e verifica se tem user_email/user_name
    $response = makeRequest(
        "{$apiUrl}/v1/subscriptions/{$testSubscriptionId}/history?limit=10",
        'GET',
        [],
        $adminSessionId
    );
    
    $hasUserInfo = false;
    if (isset($response['data']['data']) && count($response['data']['data']) > 0) {
        foreach ($response['data']['data'] as $record) {
            if (isset($record['user_email']) || isset($record['user_name'])) {
                $hasUserInfo = true;
                echo "   ✅ Registro encontrado com informações de usuário:\n";
                if (isset($record['user_email'])) {
                    echo "      Email: {$record['user_email']}\n";
                }
                if (isset($record['user_name'])) {
                    echo "      Nome: {$record['user_name']}\n";
                }
                echo "\n";
                break;
            }
        }
    }
    
    if (!$hasUserInfo) {
        echo "   {$yellow}⚠️  Nenhum registro com user_id encontrado (pode ser normal se mudanças foram feitas via API Key){$reset}\n\n";
    }
}

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


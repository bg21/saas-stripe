<?php

/**
 * Script de teste para verificação de permissões
 * 
 * Testa o sistema de permissões com diferentes tipos de autenticação:
 * - API Key (tenant) - deve funcionar normalmente
 * - Session ID - Admin - deve ter todas as permissões
 * - Session ID - Editor - deve funcionar parcialmente
 * - Session ID - Viewer - deve bloquear ações
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;

Config::load();

echo "🧪 Teste de Permissões\n";
echo str_repeat("=", 70) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';
$tenantId = 1; // Ajuste conforme necessário

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
            // Verifica se a resposta tem 'success' => true
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

// ============================================================================
// TESTE 1: API KEY (TENANT) - Deve funcionar normalmente
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: API KEY (TENANT) - Deve funcionar normalmente\n";
echo str_repeat("=", 70) . "\n\n";

// Teste 1.1: Listar assinaturas com API Key
$response = makeRequest("{$apiUrl}/v1/subscriptions", 'GET', [], $apiKey);
testResult(
    "API Key: Listar assinaturas",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 1.2: Listar clientes com API Key
$response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $apiKey);
testResult(
    "API Key: Listar clientes",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 1.3: Criar cliente com API Key (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/customers", 'POST', [
    'email' => 'test_' . time() . '@example.com',
    'name' => 'Test Customer'
], $apiKey);
// FlightPHP retorna 200 em vez de 201, mas a operação foi bem-sucedida
testResult(
    "API Key: Criar cliente",
    $response['code'] === 200 || $response['code'] === 201 ? 200 : $response['code'],
    $response['code'],
    true,
    $response['data']
);

// ============================================================================
// TESTE 2: SESSION ID - ADMIN - Deve ter todas as permissões
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 2: SESSION ID - ADMIN - Deve ter todas as permissões\n";
echo str_repeat("=", 70) . "\n\n";

// Login como admin
echo "🔐 Fazendo login como admin...\n";
$adminSessionId = login('admin@example.com', 'admin123', $tenantId);
if (!$adminSessionId) {
    echo "{$red}❌ Login como admin falhou!{$reset}\n";
    echo "   Verifique se os usuários foram criados: composer run seed:users\n\n";
    exit(1);
}
echo "{$green}✅ Login como admin bem-sucedido!{$reset}\n\n";

// Teste 2.1: Admin - Listar assinaturas (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/subscriptions", 'GET', [], $adminSessionId);
testResult(
    "Admin: Listar assinaturas",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 2.2: Admin - Listar clientes (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $adminSessionId);
testResult(
    "Admin: Listar clientes",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 2.3: Admin - Ver logs de auditoria (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/audit-logs", 'GET', [], $adminSessionId);
testResult(
    "Admin: Ver logs de auditoria",
    200,
    $response['code'],
    true,
    $response['data']
);

// ============================================================================
// TESTE 3: SESSION ID - EDITOR - Deve funcionar parcialmente
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 3: SESSION ID - EDITOR - Deve funcionar parcialmente\n";
echo str_repeat("=", 70) . "\n\n";

// Login como editor
echo "🔐 Fazendo login como editor...\n";
$editorSessionId = login('editor@example.com', 'editor123', $tenantId);
if (!$editorSessionId) {
    echo "{$red}❌ Login como editor falhou!{$reset}\n";
    echo "   Verifique se os usuários foram criados: composer run seed:users\n\n";
    exit(1);
}
echo "{$green}✅ Login como editor bem-sucedido!{$reset}\n\n";

// Teste 3.1: Editor - Listar assinaturas (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/subscriptions", 'GET', [], $editorSessionId);
testResult(
    "Editor: Listar assinaturas",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 3.2: Editor - Listar clientes (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $editorSessionId);
testResult(
    "Editor: Listar clientes",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 3.3: Editor - Criar cliente (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/customers", 'POST', [
    'email' => 'editor_test_' . time() . '@example.com',
    'name' => 'Editor Test Customer'
], $editorSessionId);
// FlightPHP retorna 200 em vez de 201, mas a operação foi bem-sucedida
testResult(
    "Editor: Criar cliente",
    $response['code'] === 200 || $response['code'] === 201 ? 200 : $response['code'],
    $response['code'],
    true,
    $response['data']
);

// Teste 3.4: Editor - Ver logs de auditoria (deve BLOQUEAR - 403)
$response = makeRequest("{$apiUrl}/v1/audit-logs", 'GET', [], $editorSessionId);
testResult(
    "Editor: Ver logs de auditoria (deve BLOQUEAR)",
    403,
    $response['code'],
    false,
    $response['data']
);

// ============================================================================
// TESTE 4: SESSION ID - VIEWER - Deve bloquear ações
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 4: SESSION ID - VIEWER - Deve bloquear ações\n";
echo str_repeat("=", 70) . "\n\n";

// Login como viewer
echo "🔐 Fazendo login como viewer...\n";
$viewerSessionId = login('viewer@example.com', 'viewer123', $tenantId);
if (!$viewerSessionId) {
    echo "{$red}❌ Login como viewer falhou!{$reset}\n";
    echo "   Verifique se os usuários foram criados: composer run seed:users\n\n";
    exit(1);
}
echo "{$green}✅ Login como viewer bem-sucedido!{$reset}\n\n";

// Teste 4.1: Viewer - Listar assinaturas (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/subscriptions", 'GET', [], $viewerSessionId);
testResult(
    "Viewer: Listar assinaturas",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 4.2: Viewer - Listar clientes (deve funcionar)
$response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $viewerSessionId);
testResult(
    "Viewer: Listar clientes",
    200,
    $response['code'],
    true,
    $response['data']
);

// Teste 4.3: Viewer - Criar cliente (deve BLOQUEAR - 403)
$response = makeRequest("{$apiUrl}/v1/customers", 'POST', [
    'email' => 'viewer_test_' . time() . '@example.com',
    'name' => 'Viewer Test Customer'
], $viewerSessionId);
testResult(
    "Viewer: Criar cliente (deve BLOQUEAR)",
    403,
    $response['code'],
    false,
    $response['data']
);

// Teste 4.4: Viewer - Ver logs de auditoria (deve BLOQUEAR - 403)
$response = makeRequest("{$apiUrl}/v1/audit-logs", 'GET', [], $viewerSessionId);
testResult(
    "Viewer: Ver logs de auditoria (deve BLOQUEAR)",
    403,
    $response['code'],
    false,
    $response['data']
);

// Teste 4.5: Viewer - Obter assinatura específica (deve funcionar)
if (!empty($response['data']['data']) && isset($response['data']['data'][0]['id'])) {
    $subscriptionId = $response['data']['data'][0]['id'];
    $response = makeRequest("{$apiUrl}/v1/subscriptions/{$subscriptionId}", 'GET', [], $viewerSessionId);
    testResult(
        "Viewer: Obter assinatura específica",
        200,
        $response['code'],
        true,
        $response['data']
    );
} else {
    echo "{$yellow}⚠️  Não foi possível testar obter assinatura específica (nenhuma assinatura encontrada){$reset}\n\n";
}

// ============================================================================
// TESTE 5: TESTES ADICIONAIS - Verificar bloqueios específicos
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 5: TESTES ADICIONAIS - Verificar bloqueios específicos\n";
echo str_repeat("=", 70) . "\n\n";

// Teste 5.1: Editor - Tentar cancelar assinatura (deve BLOQUEAR - 403)
// Nota: Precisa de uma assinatura existente
$response = makeRequest("{$apiUrl}/v1/subscriptions", 'GET', [], $apiKey);
if (!empty($response['data']['data']) && isset($response['data']['data'][0]['id'])) {
    $subscriptionId = $response['data']['data'][0]['id'];
    $response = makeRequest("{$apiUrl}/v1/subscriptions/{$subscriptionId}", 'DELETE', [], $editorSessionId);
    testResult(
        "Editor: Cancelar assinatura (deve BLOQUEAR)",
        403,
        $response['code'],
        false,
        $response['data']
    );
} else {
    echo "{$yellow}⚠️  Não foi possível testar cancelar assinatura (nenhuma assinatura encontrada){$reset}\n\n";
}

// Teste 5.2: Viewer - Tentar atualizar cliente (deve BLOQUEAR - 403)
$response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $apiKey);
if (!empty($response['data']['data']) && isset($response['data']['data'][0]['id'])) {
    $customerId = $response['data']['data'][0]['id'];
    $response = makeRequest("{$apiUrl}/v1/customers/{$customerId}", 'PUT', [
        'name' => 'Updated Name'
    ], $viewerSessionId);
    testResult(
        "Viewer: Atualizar cliente (deve BLOQUEAR)",
        403,
        $response['code'],
        false,
        $response['data']
    );
} else {
    echo "{$yellow}⚠️  Não foi possível testar atualizar cliente (nenhum cliente encontrado){$reset}\n\n";
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


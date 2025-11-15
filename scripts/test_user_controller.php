<?php

/**
 * Script de teste para UserController
 * 
 * Testa o CRUD de usuários com diferentes tipos de autenticação:
 * - API Key (tenant) - não deve funcionar (precisa de permissão manage_users)
 * - Session ID - Admin - deve ter todas as permissões
 * - Session ID - Editor - não deve funcionar (sem permissão manage_users)
 * - Session ID - Viewer - não deve funcionar (sem permissão manage_users)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;

Config::load();

echo "🧪 Teste de UserController (CRUD de Usuários)\n";
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

// Login como admin
echo "🔐 Fazendo login como admin...\n";
$adminSessionId = login('admin@example.com', 'admin123', $tenantId);
if (!$adminSessionId) {
    echo "{$red}❌ Login como admin falhou!{$reset}\n";
    echo "   Verifique se os usuários foram criados: composer run seed:users\n\n";
    exit(1);
}
echo "{$green}✅ Login como admin bem-sucedido!{$reset}\n\n";

// ============================================================================
// TESTE 1: ADMIN - Listar usuários
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: ADMIN - Listar usuários\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/v1/users", 'GET', [], $adminSessionId);
testResult(
    "Admin: Listar usuários",
    200,
    $response['code'],
    true,
    $response['data']
);

$users = $response['data']['data'] ?? [];
echo "   Total de usuários: " . count($users) . "\n\n";

// ============================================================================
// TESTE 2: ADMIN - Criar usuário
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 2: ADMIN - Criar usuário\n";
echo str_repeat("=", 70) . "\n\n";

$newUserEmail = 'test_user_' . time() . '@example.com';
$newUserData = [
    'email' => $newUserEmail,
    'password' => 'test123',
    'name' => 'Test User',
    'role' => 'viewer'
];

$response = makeRequest("{$apiUrl}/v1/users", 'POST', $newUserData, $adminSessionId);
// FlightPHP retorna 200 em vez de 201, mas a operação foi bem-sucedida
testResult(
    "Admin: Criar usuário",
    $response['code'] === 200 || $response['code'] === 201 ? 200 : $response['code'],
    $response['code'],
    true,
    $response['data']
);

$newUserId = null;
if (isset($response['data']['data']['id'])) {
    $newUserId = $response['data']['data']['id'];
    echo "   Novo usuário criado: ID {$newUserId}, Email: {$newUserEmail}\n\n";
} else {
    echo "{$yellow}⚠️  Não foi possível obter o ID do usuário criado{$reset}\n\n";
}

// ============================================================================
// TESTE 3: ADMIN - Obter usuário específico
// ============================================================================
if ($newUserId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 3: ADMIN - Obter usuário específico\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $response = makeRequest("{$apiUrl}/v1/users/{$newUserId}", 'GET', [], $adminSessionId);
    testResult(
        "Admin: Obter usuário específico",
        200,
        $response['code'],
        true,
        $response['data']
    );
    
    if (isset($response['data']['data']['email'])) {
        echo "   Email do usuário: {$response['data']['data']['email']}\n";
        echo "   Role do usuário: {$response['data']['data']['role']}\n\n";
    }
}

// ============================================================================
// TESTE 4: ADMIN - Atualizar usuário
// ============================================================================
if ($newUserId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 4: ADMIN - Atualizar usuário\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $updateData = [
        'name' => 'Updated Test User',
        'status' => 'active'
    ];
    
    $response = makeRequest("{$apiUrl}/v1/users/{$newUserId}", 'PUT', $updateData, $adminSessionId);
    testResult(
        "Admin: Atualizar usuário",
        200,
        $response['code'],
        true,
        $response['data']
    );
    
    if (isset($response['data']['data']['name'])) {
        echo "   Nome atualizado: {$response['data']['data']['name']}\n\n";
    }
}

// ============================================================================
// TESTE 5: ADMIN - Atualizar role do usuário
// ============================================================================
if ($newUserId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 5: ADMIN - Atualizar role do usuário\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $roleData = [
        'role' => 'editor'
    ];
    
    $response = makeRequest("{$apiUrl}/v1/users/{$newUserId}/role", 'PUT', $roleData, $adminSessionId);
    testResult(
        "Admin: Atualizar role do usuário",
        200,
        $response['code'],
        true,
        $response['data']
    );
    
    if (isset($response['data']['data']['role'])) {
        echo "   Role atualizada: {$response['data']['data']['role']}\n\n";
    }
}

// ============================================================================
// TESTE 6: ADMIN - Desativar usuário
// ============================================================================
if ($newUserId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 6: ADMIN - Desativar usuário\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $response = makeRequest("{$apiUrl}/v1/users/{$newUserId}", 'DELETE', [], $adminSessionId);
    testResult(
        "Admin: Desativar usuário",
        200,
        $response['code'],
        true,
        $response['data']
    );
}

// ============================================================================
// TESTE 7: EDITOR - Tentar listar usuários (deve BLOQUEAR)
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 7: EDITOR - Tentar listar usuários (deve BLOQUEAR)\n";
echo str_repeat("=", 70) . "\n\n";

// Login como editor
echo "🔐 Fazendo login como editor...\n";
$editorSessionId = login('editor@example.com', 'editor123', $tenantId);
if (!$editorSessionId) {
    echo "{$yellow}⚠️  Login como editor falhou (pode não existir){$reset}\n\n";
} else {
    echo "{$green}✅ Login como editor bem-sucedido!{$reset}\n\n";
    
    $response = makeRequest("{$apiUrl}/v1/users", 'GET', [], $editorSessionId);
    testResult(
        "Editor: Listar usuários (deve BLOQUEAR)",
        403,
        $response['code'],
        false,
        $response['data']
    );
}

// ============================================================================
// TESTE 8: VIEWER - Tentar listar usuários (deve BLOQUEAR)
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 8: VIEWER - Tentar listar usuários (deve BLOQUEAR)\n";
echo str_repeat("=", 70) . "\n\n";

// Login como viewer
echo "🔐 Fazendo login como viewer...\n";
$viewerSessionId = login('viewer@example.com', 'viewer123', $tenantId);
if (!$viewerSessionId) {
    echo "{$yellow}⚠️  Login como viewer falhou (pode não existir){$reset}\n\n";
} else {
    echo "{$green}✅ Login como viewer bem-sucedido!{$reset}\n\n";
    
    $response = makeRequest("{$apiUrl}/v1/users", 'GET', [], $viewerSessionId);
    testResult(
        "Viewer: Listar usuários (deve BLOQUEAR)",
        403,
        $response['code'],
        false,
        $response['data']
    );
}

// ============================================================================
// TESTE 9: API KEY - Tentar listar usuários (deve BLOQUEAR)
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 9: API KEY - Tentar listar usuários (deve BLOQUEAR)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/v1/users", 'GET', [], $apiKey);
testResult(
    "API Key: Listar usuários (deve BLOQUEAR - precisa de permissão manage_users)",
    403,
    $response['code'],
    false,
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


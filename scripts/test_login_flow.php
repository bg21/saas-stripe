<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

echo "🧪 TESTE DE LOGIN: DONO E FUNCIONÁRIO\n";
echo "============================================================\n\n";

$baseUrl = 'http://localhost:8080';
$successCount = 0;
$errorCount = 0;

function runTest(string $description, callable $testFunction): ?array {
    global $successCount, $errorCount;
    echo "   " . $description . "... ";
    try {
        $result = $testFunction();
        echo "✅\n";
        if (isset($result['info'])) {
            echo "      ℹ️ " . $result['info'] . "\n";
        }
        $successCount++;
        return $result;
    } catch (\Throwable $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $errorCount++;
        return null;
    }
}

function makeRequest(string $method, string $url, array $data = [], ?string $sessionId = null): array {
    global $baseUrl;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($sessionId) {
        $headers[] = 'Authorization: Bearer ' . $sessionId;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro cURL: {$error}");
    }
    
    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Resposta JSON inválida: " . json_last_error_msg());
    }
    
    return [
        'http_code' => $httpCode,
        'data' => $responseData,
        'raw' => $response
    ];
}

// Primeiro, vamos criar uma clínica e usuários para testar
echo "📋 PREPARAÇÃO: Criando clínica e usuários para teste\n";
echo "============================================================\n";

$clinicName = 'Clínica Teste Login ' . time();
$clinicSlug = 'clinica-teste-login-' . time();
$ownerEmail = 'dono@testelogin' . time() . '.com';
$ownerPassword = 'MinhaSenhaForte@2024!';
$ownerName = 'João Silva (Dono)';

$employeeEmail = 'funcionario@testelogin' . time() . '.com';
$employeePassword = 'MinhaSenhaSegura@2024!';
$employeeName = 'Maria Santos (Funcionária)';

echo "   Clínica: {$clinicName}\n";
echo "   Slug: {$clinicSlug}\n";
echo "   Dono: {$ownerEmail}\n";
echo "   Funcionário: {$employeeEmail}\n\n";

// 1. Criar clínica e dono
$registerResult = runTest("Criar clínica e dono (POST /v1/auth/register)", function() use ($clinicName, $clinicSlug, $ownerEmail, $ownerPassword, $ownerName) {
    $response = makeRequest('POST', '/v1/auth/register', [
        'clinic_name' => $clinicName,
        'clinic_slug' => $clinicSlug,
        'email' => $ownerEmail,
        'password' => $ownerPassword,
        'name' => $ownerName
    ]);
    
    if ($response['http_code'] !== 201 && $response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    if (!isset($response['data']['success']) || !$response['data']['success']) {
        throw new Exception("Resposta não indica sucesso");
    }
    
    $data = $response['data']['data'];
    
    return [
        'session_id' => $data['session_id'],
        'tenant_id' => $data['tenant']['id'],
        'tenant_slug' => $data['tenant']['slug'],
        'info' => "Clínica criada: ID {$data['tenant']['id']}, Slug: {$data['tenant']['slug']}"
    ];
});

if (!$registerResult) {
    echo "\n❌ FALHA NA CRIAÇÃO DA CLÍNICA. Teste abortado.\n";
    exit(1);
}

$tenantSlug = $registerResult['tenant_slug'];

// 2. Criar funcionário
$employeeRegisterResult = runTest("Criar funcionário (POST /v1/auth/register-employee)", function() use ($tenantSlug, $employeeEmail, $employeePassword, $employeeName) {
    $response = makeRequest('POST', '/v1/auth/register-employee', [
        'tenant_slug' => $tenantSlug,
        'email' => $employeeEmail,
        'password' => $employeePassword,
        'name' => $employeeName
    ]);
    
    if ($response['http_code'] !== 201 && $response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    if (!isset($response['data']['success']) || !$response['data']['success']) {
        throw new Exception("Resposta não indica sucesso");
    }
    
    return ['info' => "Funcionário criado: {$employeeEmail}"];
});

if (!$employeeRegisterResult) {
    echo "\n❌ FALHA NA CRIAÇÃO DO FUNCIONÁRIO. Teste abortado.\n";
    exit(1);
}

echo "\n";

// ============================================
// TESTES DE LOGIN
// ============================================
echo "🔐 TESTES DE LOGIN\n";
echo "============================================================\n\n";

// 1. LOGIN DO DONO
echo "1️⃣ LOGIN DO DONO DA CLÍNICA\n";
echo "============================================================\n";

$ownerLoginResult = runTest("Login do dono usando tenant_slug (POST /v1/auth/login)", function() use ($ownerEmail, $ownerPassword, $tenantSlug) {
    $response = makeRequest('POST', '/v1/auth/login', [
        'email' => $ownerEmail,
        'password' => $ownerPassword,
        'tenant_slug' => $tenantSlug
    ]);
    
    if ($response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    if (!isset($response['data']['success']) || !$response['data']['success']) {
        throw new Exception("Resposta não indica sucesso");
    }
    
    $data = $response['data']['data'];
    
    if (!isset($data['session_id'])) {
        throw new Exception("Session ID não retornado");
    }
    
    if (!isset($data['user']['email']) || $data['user']['email'] !== $ownerEmail) {
        throw new Exception("Email do usuário incorreto");
    }
    
    if (!isset($data['user']['role']) || $data['user']['role'] !== 'admin') {
        throw new Exception("Role do usuário incorreto. Esperado: admin, Obtido: " . ($data['user']['role'] ?? 'NULL'));
    }
    
    if (!isset($data['tenant']['slug']) || $data['tenant']['slug'] !== $tenantSlug) {
        throw new Exception("Slug do tenant incorreto");
    }
    
    return [
        'session_id' => $data['session_id'],
        'info' => "Login bem-sucedido: {$data['user']['email']} (Admin) usando slug '{$tenantSlug}'"
    ];
});

if (!$ownerLoginResult) {
    echo "\n❌ FALHA NO LOGIN DO DONO. Teste abortado.\n";
    exit(1);
}

$ownerSessionId = $ownerLoginResult['session_id'];

echo "\n";

// 2. VERIFICAÇÃO DA SESSÃO DO DONO
runTest("Verificar sessão do dono (GET /v1/auth/me)", function() use ($ownerSessionId, $ownerEmail) {
    $response = makeRequest('GET', '/v1/auth/me', [], $ownerSessionId);
    
    if ($response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    $data = $response['data']['data'];
    
    if (!isset($data['user']['email']) || $data['user']['email'] !== $ownerEmail) {
        throw new Exception("Email do usuário incorreto");
    }
    
    if (!isset($data['user']['role']) || $data['user']['role'] !== 'admin') {
        throw new Exception("Role do usuário incorreto");
    }
    
    return ['info' => "Sessão válida: {$data['user']['email']} (Admin) no tenant {$data['tenant']['name']}"];
});

echo "\n";

// 3. LOGIN DO FUNCIONÁRIO
echo "2️⃣ LOGIN DO FUNCIONÁRIO\n";
echo "============================================================\n";

$employeeLoginResult = runTest("Login do funcionário usando tenant_slug (POST /v1/auth/login)", function() use ($employeeEmail, $employeePassword, $tenantSlug) {
    $response = makeRequest('POST', '/v1/auth/login', [
        'email' => $employeeEmail,
        'password' => $employeePassword,
        'tenant_slug' => $tenantSlug
    ]);
    
    if ($response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    if (!isset($response['data']['success']) || !$response['data']['success']) {
        throw new Exception("Resposta não indica sucesso");
    }
    
    $data = $response['data']['data'];
    
    if (!isset($data['session_id'])) {
        throw new Exception("Session ID não retornado");
    }
    
    if (!isset($data['user']['email']) || $data['user']['email'] !== $employeeEmail) {
        throw new Exception("Email do usuário incorreto");
    }
    
    if (!isset($data['user']['role']) || $data['user']['role'] !== 'viewer') {
        throw new Exception("Role do usuário incorreto. Esperado: viewer, Obtido: " . ($data['user']['role'] ?? 'NULL'));
    }
    
    if (!isset($data['tenant']['slug']) || $data['tenant']['slug'] !== $tenantSlug) {
        throw new Exception("Slug do tenant incorreto");
    }
    
    return [
        'session_id' => $data['session_id'],
        'info' => "Login bem-sucedido: {$data['user']['email']} (Viewer) usando slug '{$tenantSlug}'"
    ];
});

if (!$employeeLoginResult) {
    echo "\n❌ FALHA NO LOGIN DO FUNCIONÁRIO. Teste abortado.\n";
    exit(1);
}

$employeeSessionId = $employeeLoginResult['session_id'];

echo "\n";

// 4. VERIFICAÇÃO DA SESSÃO DO FUNCIONÁRIO
runTest("Verificar sessão do funcionário (GET /v1/auth/me)", function() use ($employeeSessionId, $employeeEmail) {
    $response = makeRequest('GET', '/v1/auth/me', [], $employeeSessionId);
    
    if ($response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    $data = $response['data']['data'];
    
    if (!isset($data['user']['email']) || $data['user']['email'] !== $employeeEmail) {
        throw new Exception("Email do usuário incorreto");
    }
    
    if (!isset($data['user']['role']) || $data['user']['role'] !== 'viewer') {
        throw new Exception("Role do usuário incorreto");
    }
    
    return ['info' => "Sessão válida: {$data['user']['email']} (Viewer) no tenant {$data['tenant']['name']}"];
});

echo "\n";

// 5. TESTES DE ERROS
echo "3️⃣ TESTES DE VALIDAÇÃO E ERROS\n";
echo "============================================================\n";

runTest("Tentar login com slug incorreto", function() use ($ownerEmail, $ownerPassword) {
    $response = makeRequest('POST', '/v1/auth/login', [
        'email' => $ownerEmail,
        'password' => $ownerPassword,
        'tenant_slug' => 'slug-inexistente-' . time()
    ]);
    
    if ($response['http_code'] !== 400 && $response['http_code'] !== 404) {
        throw new Exception("Deveria retornar 400 ou 404 para slug incorreto. Retornou: {$response['http_code']}");
    }
    
    return ['info' => "Erro retornado corretamente para slug incorreto"];
});

runTest("Tentar login com senha incorreta", function() use ($ownerEmail, $tenantSlug) {
    $response = makeRequest('POST', '/v1/auth/login', [
        'email' => $ownerEmail,
        'password' => 'SenhaErrada123!@#',
        'tenant_slug' => $tenantSlug
    ]);
    
    if ($response['http_code'] !== 401 && $response['http_code'] !== 403) {
        throw new Exception("Deveria retornar 401 ou 403 para senha incorreta. Retornou: {$response['http_code']}");
    }
    
    return ['info' => "Erro 401/403 retornado corretamente para senha incorreta"];
});

runTest("Tentar login com email inexistente", function() use ($tenantSlug) {
    $response = makeRequest('POST', '/v1/auth/login', [
        'email' => 'emailinexistente@teste.com',
        'password' => 'MinhaSenhaForte@2024!',
        'tenant_slug' => $tenantSlug
    ]);
    
    if ($response['http_code'] !== 401 && $response['http_code'] !== 403) {
        throw new Exception("Deveria retornar 401 ou 403 para email inexistente. Retornou: {$response['http_code']}");
    }
    
    return ['info' => "Erro 401/403 retornado corretamente para email inexistente"];
});

echo "\n";

// ============================================
// RESUMO FINAL
// ============================================
echo "============================================================\n";
echo "📊 RESUMO DO TESTE DE LOGIN\n";
echo "============================================================\n\n";
echo "✅ Testes bem-sucedidos: {$successCount}\n";
echo "❌ Testes com erro: {$errorCount}\n\n";

if ($errorCount === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM!\n\n";
    echo "✅ FLUXO DE LOGIN VALIDADO:\n";
    echo "   1. ✅ Login do dono usando tenant_slug\n";
    echo "   2. ✅ Verificação de sessão do dono\n";
    echo "   3. ✅ Login do funcionário usando tenant_slug\n";
    echo "   4. ✅ Verificação de sessão do funcionário\n";
    echo "   5. ✅ Validações de erros funcionando\n\n";
    echo "📝 DADOS DO TESTE:\n";
    echo "   Clínica: {$clinicName}\n";
    echo "   Slug: {$tenantSlug}\n";
    echo "   Dono: {$ownerEmail} (Admin)\n";
    echo "   Funcionário: {$employeeEmail} (Viewer)\n\n";
    echo "🔗 ENDPOINT TESTADO:\n";
    echo "   ✅ POST /v1/auth/login (com tenant_slug)\n";
    echo "   ✅ GET /v1/auth/me\n";
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM. Por favor, verifique os erros acima.\n";
    exit(1);
}


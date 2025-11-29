<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Models\UserSession;

echo "🧪 TESTE COMPLETO: FLUXO DE REGISTRO E LOGIN COM SLUG\n";
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
        throw new Exception("Resposta JSON inválida: " . json_last_error_msg() . " | Resposta: " . substr($response, 0, 200));
    }
    
    return [
        'http_code' => $httpCode,
        'data' => $responseData,
        'raw' => $response
    ];
}

$clinicName = 'Cão que Mia';
$clinicSlug = 'cao-que-mia-' . time(); // Slug único para teste
$ownerEmail = 'dono@caoquemia' . time() . '.com';
$ownerPassword = 'MinhaSenhaForte@2024!'; // Senha sem sequências simples
$ownerName = 'João Silva';

$employeeEmail = 'funcionario@caoquemia' . time() . '.com';
$employeePassword = 'MinhaSenhaSegura@2024!'; // Senha sem sequências simples
$employeeName = 'Maria Santos';

echo "📋 DADOS DO TESTE:\n";
echo "   Clínica: {$clinicName}\n";
echo "   Slug: {$clinicSlug}\n";
echo "   Dono: {$ownerEmail}\n";
echo "   Funcionário: {$employeeEmail}\n\n";

// ============================================
// 1. REGISTRO DO DONO DA CLÍNICA
// ============================================
echo "1️⃣ REGISTRO DO DONO DA CLÍNICA\n";
echo "============================================================\n";

$registerResult = runTest("Registrar dono da clínica (POST /v1/auth/register)", function() use ($clinicName, $clinicSlug, $ownerEmail, $ownerPassword, $ownerName) {
    $response = makeRequest('POST', '/v1/auth/register', [
        'clinic_name' => $clinicName,
        'clinic_slug' => $clinicSlug,
        'email' => $ownerEmail,
        'password' => $ownerPassword,
        'name' => $ownerName
    ]);
    
    // Aceita 200 ou 201 (alguns helpers podem retornar 200)
    if ($response['http_code'] !== 201 && $response['http_code'] !== 200) {
        $errorMsg = $response['data']['message'] ?? 'Erro desconhecido';
        if (isset($response['data']['errors'])) {
            $errorMsg .= " | Erros: " . json_encode($response['data']['errors']);
        }
        if (isset($response['data']['error'])) {
            $errorMsg .= " | Error: " . $response['data']['error'];
        }
        if (isset($response['data']['debug'])) {
            $errorMsg .= " | Debug: " . json_encode($response['data']['debug']);
        }
        // Mostra resposta completa em caso de erro 500
        if ($response['http_code'] === 500) {
            $errorMsg .= " | Resposta completa: " . substr($response['raw'], 0, 500);
        }
        throw new Exception("HTTP {$response['http_code']}: {$errorMsg}");
    }
    
    if (!isset($response['data']['success']) || !$response['data']['success']) {
        throw new Exception("Resposta não indica sucesso: " . json_encode($response['data']));
    }
    
    $data = $response['data']['data'];
    
    // Validações
    if (!isset($data['session_id'])) {
        throw new Exception("Session ID não retornado");
    }
    
    if (!isset($data['tenant']['id'])) {
        throw new Exception("Tenant ID não retornado");
    }
    
    // Slug pode ser o fornecido ou gerado automaticamente
    if (!isset($data['tenant']['slug']) || empty($data['tenant']['slug'])) {
        throw new Exception("Slug do tenant não retornado");
    }
    // Se slug foi fornecido, deve ser igual; se não, apenas verifica que existe
    if ($clinicSlug !== null && $data['tenant']['slug'] !== $clinicSlug) {
        throw new Exception("Slug do tenant incorreto. Esperado: {$clinicSlug}, Obtido: " . $data['tenant']['slug']);
    }
    
    if (!isset($data['user']['id'])) {
        throw new Exception("User ID não retornado");
    }
    
    if (!isset($data['user']['role']) || $data['user']['role'] !== 'admin') {
        throw new Exception("Usuário deveria ser admin. Obtido: " . ($data['user']['role'] ?? 'NULL'));
    }
    
    return [
        'session_id' => $data['session_id'],
        'tenant_id' => $data['tenant']['id'],
        'tenant_slug' => $data['tenant']['slug'],
        'user_id' => $data['user']['id'],
        'info' => "Tenant criado: ID {$data['tenant']['id']}, Slug: {$data['tenant']['slug']}, Usuário: {$data['user']['email']} (Admin)"
    ];
});

if (!$registerResult) {
    echo "\n❌ FALHA NO REGISTRO DO DONO. Teste abortado.\n";
    exit(1);
}

$ownerSessionId = $registerResult['session_id'];
$tenantId = $registerResult['tenant_id'];
$tenantSlug = $registerResult['tenant_slug'];

echo "\n";

// ============================================
// 2. VERIFICAÇÃO DA SESSÃO DO DONO
// ============================================
echo "2️⃣ VERIFICAÇÃO DA SESSÃO DO DONO\n";
echo "============================================================\n";

runTest("Verificar sessão do dono (GET /v1/auth/me)", function() use ($ownerSessionId, $ownerEmail, $tenantId) {
    $response = makeRequest('GET', '/v1/auth/me', [], $ownerSessionId);
    
    if ($response['http_code'] !== 200) {
        throw new Exception("HTTP {$response['http_code']}: " . ($response['data']['message'] ?? 'Erro desconhecido'));
    }
    
    $data = $response['data']['data'];
    
    if (!isset($data['user']['email']) || $data['user']['email'] !== $ownerEmail) {
        throw new Exception("Email do usuário incorreto");
    }
    
    if (!isset($data['tenant']['id']) || (int)$data['tenant']['id'] !== $tenantId) {
        throw new Exception("Tenant ID incorreto");
    }
    
    return ['info' => "Sessão válida: {$data['user']['email']} (Admin) no tenant {$data['tenant']['name']}"];
});

echo "\n";

// ============================================
// 3. LOGIN DO DONO COM TENANT_SLUG
// ============================================
echo "3️⃣ LOGIN DO DONO USANDO TENANT_SLUG\n";
echo "============================================================\n";

runTest("Login do dono usando tenant_slug (POST /v1/auth/login)", function() use ($ownerEmail, $ownerPassword, $tenantSlug) {
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
    
    if (!isset($data['tenant']['slug']) || $data['tenant']['slug'] !== $tenantSlug) {
        throw new Exception("Slug do tenant incorreto");
    }
    
    return ['info' => "Login bem-sucedido: {$data['user']['email']} usando slug '{$tenantSlug}'"];
});

echo "\n";

// ============================================
// 4. REGISTRO DE FUNCIONÁRIO
// ============================================
echo "4️⃣ REGISTRO DE FUNCIONÁRIO NA CLÍNICA\n";
echo "============================================================\n";

$employeeRegisterResult = runTest("Registrar funcionário (POST /v1/auth/register-employee)", function() use ($tenantSlug, $employeeEmail, $employeePassword, $employeeName, $tenantId) {
    $response = makeRequest('POST', '/v1/auth/register-employee', [
        'tenant_slug' => $tenantSlug,
        'email' => $employeeEmail,
        'password' => $employeePassword,
        'name' => $employeeName
    ]);
    
    // Aceita 200 ou 201
    if ($response['http_code'] !== 201 && $response['http_code'] !== 200) {
        $errorMsg = $response['data']['message'] ?? 'Erro desconhecido';
        if (isset($response['data']['errors'])) {
            $errorMsg .= " | Erros: " . json_encode($response['data']['errors']);
        }
        throw new Exception("HTTP {$response['http_code']}: {$errorMsg}");
    }
    
    if (!isset($response['data']['success']) || $response['data']['success'] !== true) {
        throw new Exception("Resposta não indica sucesso: " . json_encode($response['data']));
    }
    
    $data = $response['data']['data'];
    
    if (!isset($data['user']['id'])) {
        throw new Exception("User ID não retornado");
    }
    
    if (!isset($data['user']['email']) || $data['user']['email'] !== $employeeEmail) {
        throw new Exception("Email do funcionário incorreto");
    }
    
    if (!isset($data['user']['role']) || $data['user']['role'] !== 'viewer') {
        throw new Exception("Funcionário deveria ter role 'viewer'. Obtido: " . ($data['user']['role'] ?? 'NULL'));
    }
    
    if (!isset($data['tenant']['id']) || (int)$data['tenant']['id'] !== $tenantId) {
        throw new Exception("Tenant ID incorreto. Esperado: {$tenantId}, Obtido: " . ($data['tenant']['id'] ?? 'NULL'));
    }
    
    if (!isset($data['tenant']['slug']) || $data['tenant']['slug'] !== $tenantSlug) {
        throw new Exception("Slug do tenant incorreto");
    }
    
    return [
        'user_id' => $data['user']['id'],
        'info' => "Funcionário criado: {$data['user']['email']} (Viewer) no tenant {$data['tenant']['name']}"
    ];
});

if (!$employeeRegisterResult) {
    echo "\n❌ FALHA NO REGISTRO DO FUNCIONÁRIO. Teste abortado.\n";
    exit(1);
}

$employeeUserId = $employeeRegisterResult['user_id'];

echo "\n";

// ============================================
// 5. LOGIN DO FUNCIONÁRIO COM TENANT_SLUG
// ============================================
echo "5️⃣ LOGIN DO FUNCIONÁRIO USANDO TENANT_SLUG\n";
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

// ============================================
// 6. VERIFICAÇÃO DA SESSÃO DO FUNCIONÁRIO
// ============================================
echo "6️⃣ VERIFICAÇÃO DA SESSÃO DO FUNCIONÁRIO\n";
echo "============================================================\n";

runTest("Verificar sessão do funcionário (GET /v1/auth/me)", function() use ($employeeSessionId, $employeeEmail, $tenantId) {
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
    
    if (!isset($data['tenant']['id']) || (int)$data['tenant']['id'] !== $tenantId) {
        throw new Exception("Tenant ID incorreto");
    }
    
    return ['info' => "Sessão válida: {$data['user']['email']} (Viewer) no tenant {$data['tenant']['name']}"];
});

echo "\n";

// ============================================
// 7. TESTE DE ERROS
// ============================================
echo "7️⃣ TESTES DE VALIDAÇÃO E ERROS\n";
echo "============================================================\n";

runTest("Tentar registrar funcionário com slug inexistente", function() {
    $response = makeRequest('POST', '/v1/auth/register-employee', [
        'tenant_slug' => 'slug-inexistente-' . time(),
        'email' => 'teste@teste' . time() . '.com',
        'password' => 'MinhaSenhaForte@2024!'
    ]);
    
    // Pode retornar 400 (validação) ou 404 (não encontrado)
    if ($response['http_code'] !== 404 && $response['http_code'] !== 400) {
        throw new Exception("Deveria retornar 400 ou 404 para slug inexistente. Retornou: {$response['http_code']}");
    }
    
    return ['info' => "Erro {$response['http_code']} retornado corretamente para slug inexistente"];
});

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

runTest("Tentar registrar funcionário com email já existente", function() use ($tenantSlug, $ownerEmail) {
    $response = makeRequest('POST', '/v1/auth/register-employee', [
        'tenant_slug' => $tenantSlug,
        'email' => $ownerEmail, // Email do dono (já existe)
        'password' => 'MinhaSenhaForte@2024!'
    ]);
    
    if ($response['http_code'] !== 409) {
        throw new Exception("Deveria retornar 409 para email duplicado. Retornou: {$response['http_code']}");
    }
    
    return ['info' => "Erro 409 retornado corretamente para email duplicado"];
});

echo "\n";

// ============================================
// 8. VERIFICAÇÃO NO BANCO DE DADOS
// ============================================
echo "8️⃣ VERIFICAÇÃO NO BANCO DE DADOS\n";
echo "============================================================\n";

runTest("Verificar tenant no banco de dados", function() use ($tenantId, $tenantSlug, $clinicName) {
    $pdo = \App\Utils\Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = :id");
    $stmt->execute(['id' => $tenantId]);
    $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        throw new Exception("Tenant não encontrado no banco");
    }
    
    if ($tenant['slug'] !== $tenantSlug) {
        throw new Exception("Slug no banco incorreto. Esperado: {$tenantSlug}, Obtido: {$tenant['slug']}");
    }
    
    if ($tenant['name'] !== $clinicName) {
        throw new Exception("Nome no banco incorreto. Esperado: {$clinicName}, Obtido: {$tenant['name']}");
    }
    
    return ['info' => "Tenant confirmado no banco: ID {$tenant['id']}, Slug: {$tenant['slug']}, Nome: {$tenant['name']}"];
});

runTest("Verificar usuários no banco de dados", function() use ($tenantId, $ownerEmail, $employeeEmail) {
    $pdo = \App\Utils\Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = :tenant_id ORDER BY id");
    $stmt->execute(['tenant_id' => $tenantId]);
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($users) < 2) {
        throw new Exception("Deveria ter pelo menos 2 usuários. Encontrados: " . count($users));
    }
    
    $ownerFound = false;
    $employeeFound = false;
    
    foreach ($users as $user) {
        if ($user['email'] === $ownerEmail) {
            $ownerFound = true;
            if ($user['role'] !== 'admin') {
                throw new Exception("Dono deveria ser admin. Obtido: {$user['role']}");
            }
        }
        if ($user['email'] === $employeeEmail) {
            $employeeFound = true;
            if ($user['role'] !== 'viewer') {
                throw new Exception("Funcionário deveria ser viewer. Obtido: {$user['role']}");
            }
        }
    }
    
    if (!$ownerFound) {
        throw new Exception("Dono não encontrado no banco");
    }
    
    if (!$employeeFound) {
        throw new Exception("Funcionário não encontrado no banco");
    }
    
    return ['info' => "Usuários confirmados no banco: " . count($users) . " usuários (1 admin, 1 viewer)"];
});

echo "\n";

// ============================================
// RESUMO FINAL
// ============================================
echo "============================================================\n";
echo "📊 RESUMO DO TESTE COMPLETO\n";
echo "============================================================\n\n";
echo "✅ Testes bem-sucedidos: {$successCount}\n";
echo "❌ Testes com erro: {$errorCount}\n\n";

if ($errorCount === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM!\n\n";
    echo "✅ FLUXO COMPLETO VALIDADO:\n";
    echo "   1. ✅ Registro do dono da clínica com slug\n";
    echo "   2. ✅ Verificação de sessão do dono\n";
    echo "   3. ✅ Login do dono usando tenant_slug\n";
    echo "   4. ✅ Registro de funcionário usando tenant_slug\n";
    echo "   5. ✅ Login do funcionário usando tenant_slug\n";
    echo "   6. ✅ Verificação de sessão do funcionário\n";
    echo "   7. ✅ Validações de erros funcionando\n";
    echo "   8. ✅ Dados confirmados no banco de dados\n\n";
    echo "📝 DADOS CRIADOS:\n";
    echo "   Clínica: {$clinicName}\n";
    echo "   Slug: {$tenantSlug}\n";
    echo "   Tenant ID: {$tenantId}\n";
    echo "   Dono: {$ownerEmail} (Admin)\n";
    echo "   Funcionário: {$employeeEmail} (Viewer)\n\n";
    echo "🔗 ENDPOINTS TESTADOS:\n";
    echo "   ✅ POST /v1/auth/register\n";
    echo "   ✅ POST /v1/auth/register-employee\n";
    echo "   ✅ POST /v1/auth/login (com tenant_slug)\n";
    echo "   ✅ GET /v1/auth/me\n";
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM. Por favor, verifique os erros acima.\n";
    exit(1);
}


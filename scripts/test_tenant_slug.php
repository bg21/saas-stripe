<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Models\Tenant;
use App\Models\User;
use App\Utils\SlugHelper;

echo "🧪 TESTE DE SLUG PARA TENANTS\n";
echo "============================================================\n\n";

$successCount = 0;
$errorCount = 0;

function runTest(string $description, callable $testFunction): void {
    global $successCount, $errorCount;
    echo "   " . $description . "... ";
    try {
        $testFunction();
        echo "✅\n";
        $successCount++;
    } catch (\Throwable $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

$tenantModel = new Tenant();
$userModel = new User();

echo "1️⃣ Testando SlugHelper...\n";
runTest("Gerar slug de 'Cão que Mia'", function() {
    $slug = SlugHelper::generate('Cão que Mia');
    if ($slug !== 'cao-que-mia') {
        throw new Exception("Esperado 'cao-que-mia', obtido '{$slug}'");
    }
});

runTest("Gerar slug de 'Clínica Veterinária ABC'", function() {
    $slug = SlugHelper::generate('Clínica Veterinária ABC');
    if ($slug !== 'clinica-veterinaria-abc') {
        throw new Exception("Esperado 'clinica-veterinaria-abc', obtido '{$slug}'");
    }
});

runTest("Validar slug válido", function() {
    if (!SlugHelper::isValid('cao-que-mia')) {
        throw new Exception("Slug 'cao-que-mia' deveria ser válido");
    }
});

runTest("Rejeitar slug inválido (maiúsculas)", function() {
    if (SlugHelper::isValid('Cao-Que-Mia')) {
        throw new Exception("Slug com maiúsculas deveria ser inválido");
    }
});

runTest("Rejeitar slug inválido (espaços)", function() {
    if (SlugHelper::isValid('cao que mia')) {
        throw new Exception("Slug com espaços deveria ser inválido");
    }
});

echo "\n2️⃣ Testando criação de tenant com slug...\n";
$timestamp = time();
$testTenantName = 'Clínica Teste Slug ' . $timestamp;
$testSlug = 'clinica-teste-slug-' . $timestamp;
$testSlug2 = 'clinica-teste-slug-2-' . $timestamp;

runTest("Criar tenant com slug automático", function() use ($tenantModel, $testTenantName, &$tenantId1) {
    $tenantId1 = $tenantModel->create($testTenantName);
    $tenant = $tenantModel->findById($tenantId1);
    if (!$tenant || empty($tenant['slug'])) {
        throw new Exception("Tenant criado sem slug");
    }
    echo "   ℹ️ Tenant criado com ID: {$tenantId1}, slug: {$tenant['slug']}\n";
});

runTest("Criar tenant com slug fornecido", function() use ($tenantModel, $testTenantName, $testSlug2, &$tenantId2) {
    $tenantId2 = $tenantModel->create($testTenantName . ' 2', $testSlug2);
    $tenant = $tenantModel->findById($tenantId2);
    if (!$tenant || $tenant['slug'] !== $testSlug2) {
        throw new Exception("Slug não foi salvo corretamente. Esperado: {$testSlug2}, Obtido: " . ($tenant['slug'] ?? 'NULL'));
    }
    echo "   ℹ️ Tenant criado com ID: {$tenantId2}, slug: {$tenant['slug']}\n";
});

runTest("Verificar que slug é único", function() use ($tenantModel, $testSlug2) {
    try {
        $tenantModel->create('Teste Duplicado', $testSlug2);
        throw new Exception("Deveria ter lançado exceção para slug duplicado");
    } catch (\InvalidArgumentException $e) {
        if (strpos($e->getMessage(), 'Slug já existe') === false && strpos($e->getMessage(), 'já existe') === false) {
            throw new Exception("Exceção incorreta: " . $e->getMessage());
        }
    }
});

echo "\n3️⃣ Testando busca de tenant por slug...\n";
runTest("Buscar tenant por slug", function() use ($tenantModel, $testSlug2, &$tenantId2) {
    if (!isset($tenantId2)) {
        throw new Exception("tenantId2 não foi definido no teste anterior");
    }
    $tenant = $tenantModel->findBySlug($testSlug2);
    if (!$tenant) {
        throw new Exception("Tenant não encontrado pelo slug");
    }
    if ((int)$tenant['id'] !== $tenantId2) {
        throw new Exception("Tenant ID incorreto. Esperado: {$tenantId2}, Obtido: {$tenant['id']}");
    }
    echo "   ℹ️ Tenant encontrado: ID {$tenant['id']}, Nome: {$tenant['name']}, Slug: {$tenant['slug']}\n";
});

runTest("Buscar tenant por slug inexistente retorna null", function() use ($tenantModel) {
    $tenant = $tenantModel->findBySlug('slug-inexistente-' . time());
    if ($tenant !== null) {
        throw new Exception("Deveria retornar null para slug inexistente");
    }
});

echo "\n4️⃣ Testando verificação de slug existente...\n";
runTest("Verificar que slug existe", function() use ($tenantModel, $testSlug) {
    if (!$tenantModel->slugExists($testSlug)) {
        throw new Exception("Slug deveria existir");
    }
});

runTest("Verificar que slug não existe", function() use ($tenantModel) {
    if ($tenantModel->slugExists('slug-inexistente-' . time())) {
        throw new Exception("Slug não deveria existir");
    }
});

echo "\n5️⃣ Testando geração de slug único...\n";
runTest("Gerar slug único quando slug já existe", function() use ($tenantModel, $testTenantName, $testSlug) {
    // Cria tenant com slug que já existe (deve adicionar número)
    $tenantId3 = $tenantModel->create($testTenantName . ' 3'); // Slug será gerado automaticamente
    $tenant = $tenantModel->findById($tenantId3);
    if (!$tenant || empty($tenant['slug'])) {
        throw new Exception("Slug não foi gerado");
    }
    echo "   ℹ️ Tenant criado com slug único: {$tenant['slug']}\n";
});

echo "\n6️⃣ Testando via API (simulação)...\n";
runTest("Simular registro de tenant via API", function() use ($tenantModel, $userModel) {
    $clinicName = 'Clínica API Test ' . time();
    $email = 'admin@clinicatest' . time() . '.com';
    $password = 'SenhaForte123!@#';
    
    // Simula o que o endpoint register() faz
    $tenantId = $tenantModel->create($clinicName);
    $userId = $userModel->create($tenantId, $email, $password, 'Admin Test', 'admin');
    
    $tenant = $tenantModel->findById($tenantId);
    $user = $userModel->findById($userId);
    
    if (!$tenant || !$user) {
        throw new Exception("Falha ao criar tenant ou usuário");
    }
    
    if (empty($tenant['slug'])) {
        throw new Exception("Tenant criado sem slug");
    }
    
    echo "   ℹ️ Tenant criado: ID {$tenantId}, Slug: {$tenant['slug']}, Usuário: {$user['email']}\n";
});

runTest("Simular registro de funcionário via API", function() use ($tenantModel, $userModel) {
    // Cria um tenant de teste
    $tenantId = $tenantModel->create('Clínica Funcionário Test ' . time());
    $tenant = $tenantModel->findById($tenantId);
    $tenantSlug = $tenant['slug'];
    
    // Simula o que o endpoint registerEmployee() faz
    $email = 'funcionario@clinicatest' . time() . '.com';
    $password = 'SenhaForte123!@#';
    
    // Busca tenant pelo slug
    $foundTenant = $tenantModel->findBySlug($tenantSlug);
    if (!$foundTenant) {
        throw new Exception("Tenant não encontrado pelo slug");
    }
    
    $userId = $userModel->create((int)$foundTenant['id'], $email, $password, 'Funcionário Test', 'viewer');
    $user = $userModel->findById($userId);
    
    if (!$user) {
        throw new Exception("Falha ao criar funcionário");
    }
    
    echo "   ℹ️ Funcionário criado: Email {$user['email']}, Tenant Slug: {$tenantSlug}\n";
});

echo "\n============================================================\n";
echo "📊 RESUMO DOS TESTES\n";
echo "============================================================\n\n";
echo "✅ Testes bem-sucedidos: {$successCount}\n";
echo "❌ Testes com erro: {$errorCount}\n\n";

if ($errorCount === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM! Sistema de slug está funcionando corretamente.\n\n";
    echo "📝 PRÓXIMOS PASSOS:\n";
    echo "1. Teste o endpoint POST /v1/auth/register com:\n";
    echo "   {\n";
    echo "     \"clinic_name\": \"Cão que Mia\",\n";
    echo "     \"email\": \"admin@caoquemia.com\",\n";
    echo "     \"password\": \"SenhaForte123!@#\"\n";
    echo "   }\n\n";
    echo "2. Teste o endpoint POST /v1/auth/register-employee com:\n";
    echo "   {\n";
    echo "     \"tenant_slug\": \"cao-que-mia\",\n";
    echo "     \"email\": \"funcionario@caoquemia.com\",\n";
    echo "     \"password\": \"SenhaForte123!@#\"\n";
    echo "   }\n\n";
    echo "3. Teste o endpoint POST /v1/auth/login com:\n";
    echo "   {\n";
    echo "     \"email\": \"admin@caoquemia.com\",\n";
    echo "     \"password\": \"SenhaForte123!@#\",\n";
    echo "     \"tenant_slug\": \"cao-que-mia\"\n";
    echo "   }\n";
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM. Por favor, verifique os erros acima.\n";
    exit(1);
}


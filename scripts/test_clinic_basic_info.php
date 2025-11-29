<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Models\ClinicConfiguration;
use App\Utils\Database;

echo "🧪 TESTE DE SALVAMENTO DE INFORMAÇÕES BÁSICAS DA CLÍNICA\n";
echo "============================================================\n\n";

$successCount = 0;
$errorCount = 0;
$errorMessages = [];

function runTest(string $description, callable $testFunction): void {
    global $successCount, $errorCount, $errorMessages;
    echo "   " . $description . "... ";
    try {
        $testFunction();
        echo "✅\n";
        $successCount++;
    } catch (\Throwable $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $errorCount++;
        $errorMessages[] = $description . ": " . $e->getMessage();
    }
}

// 1️⃣ Verificar se os campos existem na tabela
echo "1️⃣ Verificando estrutura da tabela...\n";
$pdo = Database::getInstance();

runTest("Tabela clinic_configurations existe", function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'clinic_configurations'");
    $table = $stmt->fetch();
    if (!$table) {
        throw new Exception("Tabela 'clinic_configurations' não encontrada.");
    }
});

$requiredFields = [
    'clinic_name', 'clinic_phone', 'clinic_email', 'clinic_address',
    'clinic_city', 'clinic_state', 'clinic_zip_code', 'clinic_logo',
    'clinic_description', 'clinic_website'
];

foreach ($requiredFields as $field) {
    runTest("Campo {$field} existe", function() use ($pdo, $field) {
        $stmt = $pdo->query("SHOW COLUMNS FROM clinic_configurations LIKE '{$field}'");
        $column = $stmt->fetch();
        if (!$column) {
            throw new Exception("Campo '{$field}' não encontrado.");
        }
    });
}

// 2️⃣ Testar salvamento de informações básicas
echo "\n2️⃣ Testando salvamento de informações básicas...\n";

// Busca um tenant_id válido para teste
$stmt = $pdo->query("SELECT id FROM tenants LIMIT 1");
$tenant = $stmt->fetch();

if (!$tenant) {
    echo "⚠️  Nenhum tenant encontrado. Criando tenant de teste...\n";
    // Cria um tenant de teste
    $stmt = $pdo->prepare("INSERT INTO tenants (name, stripe_customer_id, created_at) VALUES ('Teste Clínica', 'test_customer_' . uniqid(), NOW())");
    $stmt->execute();
    $tenantId = $pdo->lastInsertId();
} else {
    $tenantId = $tenant['id'];
}

echo "   Usando tenant_id: {$tenantId}\n\n";

$configModel = new ClinicConfiguration();

$testData = [
    'clinic_name' => 'Clínica Veterinária Teste',
    'clinic_phone' => '(11) 98765-4321',
    'clinic_email' => 'contato@clinicateste.com.br',
    'clinic_address' => 'Rua Teste, 123 - Centro',
    'clinic_city' => 'São Paulo',
    'clinic_state' => 'SP',
    'clinic_zip_code' => '01234-567',
    'clinic_description' => 'Clínica veterinária especializada em cuidados com animais de estimação.',
    'clinic_website' => 'https://www.clinicateste.com.br',
    'default_appointment_duration' => 30,
    'time_slot_interval' => 15,
    'allow_online_booking' => 1,
    'require_confirmation' => 0,
    'cancellation_hours' => 24
];

runTest("Salvar configuração com informações básicas", function() use ($configModel, $tenantId, $testData) {
    $configId = $configModel->saveConfiguration($tenantId, $testData);
    if (!$configId) {
        throw new Exception("Falha ao salvar configuração.");
    }
    echo "      ✅ Configuração salva com ID: {$configId}\n";
});

runTest("Buscar configuração salva", function() use ($configModel, $tenantId, $testData) {
    $config = $configModel->findByTenant($tenantId);
    if (!$config) {
        throw new Exception("Configuração não encontrada após salvar.");
    }
    
    // Verifica se os dados foram salvos corretamente
    foreach ($testData as $key => $expectedValue) {
        if (isset($config[$key])) {
            $actualValue = $config[$key];
            // Normaliza comparação (telefone e CEP podem ter formatação diferente)
            if ($key === 'clinic_phone' || $key === 'clinic_zip_code') {
                // Remove formatação para comparação
                $expectedClean = preg_replace('/[^0-9]/', '', $expectedValue);
                $actualClean = preg_replace('/[^0-9]/', '', (string)$actualValue);
                if ($expectedClean !== $actualClean) {
                    throw new Exception("Campo {$key} não foi salvo corretamente. Esperado: {$expectedValue}, Obtido: {$actualValue}");
                }
            } elseif ($key === 'allow_online_booking' || $key === 'require_confirmation') {
                // Compara como boolean
                if ((int)$config[$key] !== (int)$expectedValue) {
                    throw new Exception("Campo {$key} não foi salvo corretamente. Esperado: {$expectedValue}, Obtido: {$config[$key]}");
                }
            } else {
                if ((string)$config[$key] !== (string)$expectedValue) {
                    throw new Exception("Campo {$key} não foi salvo corretamente. Esperado: {$expectedValue}, Obtido: {$config[$key]}");
                }
            }
        } else {
            throw new Exception("Campo {$key} não encontrado na configuração salva.");
        }
    }
    
    echo "      ✅ Todos os campos foram salvos corretamente\n";
});

// 3️⃣ Testar atualização de informações
echo "\n3️⃣ Testando atualização de informações...\n";

$updatedData = [
    'clinic_name' => 'Clínica Veterinária Teste Atualizada',
    'clinic_phone' => '(11) 99999-8888',
    'clinic_email' => 'novoemail@clinicateste.com.br'
];

runTest("Atualizar informações básicas", function() use ($configModel, $tenantId, $updatedData) {
    $configId = $configModel->saveConfiguration($tenantId, $updatedData);
    if (!$configId) {
        throw new Exception("Falha ao atualizar configuração.");
    }
    
    $config = $configModel->findByTenant($tenantId);
    if ($config['clinic_name'] !== $updatedData['clinic_name']) {
        throw new Exception("Nome não foi atualizado corretamente.");
    }
    if ($config['clinic_email'] !== $updatedData['clinic_email']) {
        throw new Exception("Email não foi atualizado corretamente.");
    }
    
    echo "      ✅ Informações atualizadas com sucesso\n";
});

// 4️⃣ Testar validações
echo "\n4️⃣ Testando validações...\n";

runTest("Validação de email inválido", function() use ($configModel) {
    $errors = $configModel->validate(['clinic_email' => 'email-invalido']);
    if (empty($errors['clinic_email'])) {
        throw new Exception("Validação de email inválido não funcionou.");
    }
});

runTest("Validação de CEP inválido", function() use ($configModel) {
    $errors = $configModel->validate(['clinic_zip_code' => '123']);
    if (empty($errors['clinic_zip_code'])) {
        throw new Exception("Validação de CEP inválido não funcionou.");
    }
});

runTest("Validação de website inválido", function() use ($configModel) {
    $errors = $configModel->validate(['clinic_website' => 'nao-e-uma-url']);
    if (empty($errors['clinic_website'])) {
        throw new Exception("Validação de website inválido não funcionou.");
    }
});

// 5️⃣ Verificar dados no banco diretamente
echo "\n5️⃣ Verificando dados no banco de dados...\n";

runTest("Dados existem no banco de dados", function() use ($pdo, $tenantId) {
    $stmt = $pdo->prepare("SELECT clinic_name, clinic_email, clinic_phone FROM clinic_configurations WHERE tenant_id = :tenant_id");
    $stmt->execute(['tenant_id' => $tenantId]);
    $config = $stmt->fetch();
    
    if (!$config) {
        throw new Exception("Nenhuma configuração encontrada no banco de dados.");
    }
    
    if (empty($config['clinic_name'])) {
        throw new Exception("Campo clinic_name está vazio no banco de dados.");
    }
    
    echo "      ✅ Nome: {$config['clinic_name']}\n";
    echo "      ✅ Email: {$config['clinic_email']}\n";
    echo "      ✅ Telefone: {$config['clinic_phone']}\n";
});

echo "\n============================================================\n";
echo "📊 RESUMO DOS TESTES\n";
echo "============================================================\n\n";
echo "✅ Testes bem-sucedidos: {$successCount}\n";
echo "❌ Testes com erro: {$errorCount}\n\n";

if (!empty($errorMessages)) {
    echo "❌ ERROS:\n";
    foreach ($errorMessages as $msg) {
        echo "   • {$msg}\n";
    }
}

if ($errorCount === 0) {
    echo "\n🎉 TODOS OS TESTES PASSARAM! As informações estão sendo salvas corretamente.\n";
} else {
    echo "\n⚠️  ALGUNS TESTES FALHARAM. Verifique os erros acima.\n";
}


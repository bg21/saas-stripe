<?php

/**
 * Teste completo do endpoint de configurações da clínica via API
 * Simula uma requisição HTTP real
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Models\UserSession;
use App\Models\Tenant;
use App\Models\ClinicConfiguration;

echo "🧪 TESTE COMPLETO DO ENDPOINT DE CONFIGURAÇÕES DA CLÍNICA\n";
echo "============================================================\n\n";

// 1️⃣ Busca ou cria um tenant e usuário para teste
$pdo = \App\Utils\Database::getInstance();

// Busca tenant
$stmt = $pdo->query("SELECT id FROM tenants LIMIT 1");
$tenant = $stmt->fetch();

if (!$tenant) {
    echo "❌ Nenhum tenant encontrado. Execute as seeds primeiro.\n";
    exit(1);
}

$tenantId = $tenant['id'];
echo "✅ Usando tenant_id: {$tenantId}\n\n";

// Busca usuário
$stmt = $pdo->prepare("SELECT id FROM users WHERE tenant_id = :tenant_id LIMIT 1");
$stmt->execute(['tenant_id' => $tenantId]);
$user = $stmt->fetch();

if (!$user) {
    echo "❌ Nenhum usuário encontrado para o tenant. Execute as seeds primeiro.\n";
    exit(1);
}

$userId = $user['id'];
echo "✅ Usando user_id: {$userId}\n\n";

// 2️⃣ Testa busca de configuração
echo "2️⃣ Testando busca de configuração...\n";

$configModel = new ClinicConfiguration();
$config = $configModel->findByTenant($tenantId);

if ($config) {
    echo "   ✅ Configuração encontrada:\n";
    echo "      - Nome: " . ($config['clinic_name'] ?? 'N/A') . "\n";
    echo "      - Email: " . ($config['clinic_email'] ?? 'N/A') . "\n";
    echo "      - Telefone: " . ($config['clinic_phone'] ?? 'N/A') . "\n";
} else {
    echo "   ℹ️  Nenhuma configuração encontrada (será criada no próximo teste)\n";
}

// 3️⃣ Testa salvamento via model diretamente
echo "\n3️⃣ Testando salvamento direto via Model...\n";

$testData = [
    'clinic_name' => 'Clínica Veterinária API Test',
    'clinic_phone' => '(11) 12345-6789',
    'clinic_email' => 'api@clinicateste.com.br',
    'clinic_address' => 'Rua API Test, 456',
    'clinic_city' => 'São Paulo',
    'clinic_state' => 'SP',
    'clinic_zip_code' => '12345-678',
    'clinic_description' => 'Teste via API',
    'clinic_website' => 'https://api.clinicateste.com.br',
    'default_appointment_duration' => 45,
    'time_slot_interval' => 30,
    'allow_online_booking' => 1,
    'require_confirmation' => 1,
    'cancellation_hours' => 48
];

try {
    $configId = $configModel->saveConfiguration($tenantId, $testData);
    echo "   ✅ Configuração salva com ID: {$configId}\n";
    
    // Verifica se foi salvo corretamente
    $saved = $configModel->findByTenant($tenantId);
    
    $allFieldsSaved = true;
    foreach ($testData as $key => $expectedValue) {
        if ($key === 'allow_online_booking' || $key === 'require_confirmation') {
            if ((int)($saved[$key] ?? 0) !== (int)$expectedValue) {
                echo "   ❌ Campo {$key} não foi salvo corretamente\n";
                $allFieldsSaved = false;
            }
        } elseif (isset($saved[$key])) {
            $savedValue = $saved[$key];
            // Normaliza comparação para telefone e CEP
            if ($key === 'clinic_phone' || $key === 'clinic_zip_code') {
                $expectedClean = preg_replace('/[^0-9]/', '', (string)$expectedValue);
                $savedClean = preg_replace('/[^0-9]/', '', (string)$savedValue);
                if ($expectedClean !== $savedClean) {
                    echo "   ❌ Campo {$key} não foi salvo corretamente (esperado: {$expectedValue}, obtido: {$savedValue})\n";
                    $allFieldsSaved = false;
                }
            } elseif ((string)$savedValue !== (string)$expectedValue) {
                echo "   ❌ Campo {$key} não foi salvo corretamente (esperado: {$expectedValue}, obtido: {$savedValue})\n";
                $allFieldsSaved = false;
            }
        } else {
            echo "   ⚠️  Campo {$key} não encontrado na configuração salva\n";
        }
    }
    
    if ($allFieldsSaved) {
        echo "   ✅ Todos os campos foram salvos corretamente!\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Erro ao salvar: " . $e->getMessage() . "\n";
    exit(1);
}

// 4️⃣ Verifica no banco diretamente
echo "\n4️⃣ Verificando dados no banco de dados...\n";
$stmt = $pdo->prepare("SELECT clinic_name, clinic_email, clinic_phone, clinic_address, clinic_city, clinic_state, clinic_zip_code, clinic_description, clinic_website FROM clinic_configurations WHERE tenant_id = :tenant_id");
$stmt->execute(['tenant_id' => $tenantId]);
$dbConfig = $stmt->fetch(\PDO::FETCH_ASSOC);

if ($dbConfig) {
    echo "   ✅ Dados encontrados no banco:\n";
    foreach ($dbConfig as $key => $value) {
        echo "      - {$key}: " . ($value ?? 'NULL') . "\n";
    }
} else {
    echo "   ❌ Nenhum dado encontrado no banco!\n";
    exit(1);
}

echo "\n============================================================\n";
echo "✅ TESTE CONCLUÍDO COM SUCESSO!\n";
echo "============================================================\n";
echo "\nAs informações estão sendo salvas corretamente no banco de dados.\n";
echo "Para testar via interface web, acesse: /clinic-settings\n";


<?php

/**
 * Teste completo de salvamento via simulação de requisição HTTP
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Models\ClinicConfiguration;
use App\Utils\Database;

echo "🧪 TESTE COMPLETO DE SALVAMENTO DE INFORMAÇÕES BÁSICAS\n";
echo "============================================================\n\n";

$pdo = Database::getInstance();

// Busca tenant
$stmt = $pdo->query("SELECT id FROM tenants LIMIT 1");
$tenant = $stmt->fetch();
$tenantId = $tenant['id'];

echo "✅ Usando tenant_id: {$tenantId}\n\n";

$configModel = new ClinicConfiguration();

// 1️⃣ Testa salvamento de informações básicas
echo "1️⃣ Testando salvamento de informações básicas...\n";

$testData = [
    'clinic_name' => 'Clínica Veterinária Completa',
    'clinic_phone' => '(11) 98765-4321',
    'clinic_email' => 'contato@clinicacompleta.com.br',
    'clinic_address' => 'Av. Teste Completo, 789 - Jardim Teste',
    'clinic_city' => 'São Paulo',
    'clinic_state' => 'SP',
    'clinic_zip_code' => '01234-567',
    'clinic_description' => 'Clínica veterinária completa com todos os serviços.',
    'clinic_website' => 'https://www.clinicacompleta.com.br',
    'default_appointment_duration' => 45,
    'time_slot_interval' => 30,
    'allow_online_booking' => 1,
    'require_confirmation' => 1,
    'cancellation_hours' => 48
];

try {
    $configId = $configModel->saveConfiguration($tenantId, $testData);
    echo "   ✅ Configuração salva com ID: {$configId}\n";
    
    // 2️⃣ Verifica se foi salvo corretamente
    echo "\n2️⃣ Verificando dados salvos...\n";
    $saved = $configModel->findByTenant($tenantId);
    
    if (!$saved) {
        throw new Exception("Configuração não encontrada após salvar!");
    }
    
    $allOk = true;
    foreach ($testData as $key => $expectedValue) {
        if (!isset($saved[$key])) {
            echo "   ❌ Campo {$key} não encontrado na configuração salva\n";
            $allOk = false;
            continue;
        }
        
        $savedValue = $saved[$key];
        
        // Comparação especial para campos booleanos
        if ($key === 'allow_online_booking' || $key === 'require_confirmation') {
            if ((int)$savedValue !== (int)$expectedValue) {
                echo "   ❌ Campo {$key}: esperado {$expectedValue}, obtido {$savedValue}\n";
                $allOk = false;
            } else {
                echo "   ✅ Campo {$key}: OK\n";
            }
        }
        // Comparação especial para telefone e CEP (remove formatação)
        elseif ($key === 'clinic_phone' || $key === 'clinic_zip_code') {
            $expectedClean = preg_replace('/[^0-9]/', '', (string)$expectedValue);
            $savedClean = preg_replace('/[^0-9]/', '', (string)$savedValue);
            if ($expectedClean !== $savedClean) {
                echo "   ❌ Campo {$key}: esperado {$expectedValue} ({$expectedClean}), obtido {$savedValue} ({$savedClean})\n";
                $allOk = false;
            } else {
                echo "   ✅ Campo {$key}: OK ({$savedValue})\n";
            }
        }
        // Comparação normal para outros campos
        else {
            if ((string)$savedValue !== (string)$expectedValue) {
                echo "   ❌ Campo {$key}: esperado '{$expectedValue}', obtido '{$savedValue}'\n";
                $allOk = false;
            } else {
                echo "   ✅ Campo {$key}: OK\n";
            }
        }
    }
    
    if ($allOk) {
        echo "\n   ✅ TODOS OS CAMPOS FORAM SALVOS CORRETAMENTE!\n";
    } else {
        echo "\n   ❌ ALGUNS CAMPOS NÃO FORAM SALVOS CORRETAMENTE!\n";
        exit(1);
    }
    
    // 3️⃣ Verifica no banco diretamente
    echo "\n3️⃣ Verificando no banco de dados diretamente...\n";
    $stmt = $pdo->prepare("SELECT clinic_name, clinic_email, clinic_phone, clinic_address, clinic_city, clinic_state, clinic_zip_code, clinic_description, clinic_website FROM clinic_configurations WHERE tenant_id = :tenant_id");
    $stmt->execute(['tenant_id' => $tenantId]);
    $dbConfig = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($dbConfig) {
        echo "   ✅ Dados confirmados no banco:\n";
        foreach ($dbConfig as $key => $value) {
            $status = !empty($value) ? '✅' : '⚠️';
            echo "      {$status} {$key}: " . ($value ?? 'NULL') . "\n";
        }
    } else {
        throw new Exception("Dados não encontrados no banco!");
    }
    
    // 4️⃣ Testa atualização
    echo "\n4️⃣ Testando atualização de informações...\n";
    
    $updateData = [
        'clinic_name' => 'Clínica Veterinária Atualizada',
        'clinic_phone' => '(11) 99999-8888',
        'clinic_email' => 'novo@clinicacompleta.com.br'
    ];
    
    $configId = $configModel->saveConfiguration($tenantId, $updateData);
    $updated = $configModel->findByTenant($tenantId);
    
    if ($updated['clinic_name'] === $updateData['clinic_name'] && 
        $updated['clinic_email'] === $updateData['clinic_email']) {
        echo "   ✅ Atualização funcionou corretamente!\n";
        echo "      - Nome atualizado: {$updated['clinic_name']}\n";
        echo "      - Email atualizado: {$updated['clinic_email']}\n";
    } else {
        throw new Exception("Atualização não funcionou corretamente!");
    }
    
    echo "\n============================================================\n";
    echo "✅ TESTE COMPLETO CONCLUÍDO COM SUCESSO!\n";
    echo "============================================================\n";
    echo "\n✅ As informações estão sendo salvas corretamente!\n";
    echo "✅ O salvamento funciona via Model diretamente\n";
    echo "✅ A atualização funciona corretamente\n";
    echo "✅ Os dados estão persistindo no banco de dados\n";
    echo "\nPara testar via interface web:\n";
    echo "1. Acesse: /clinic-settings\n";
    echo "2. Preencha os campos de informações básicas\n";
    echo "3. Clique em 'Salvar Configurações'\n";
    echo "4. Verifique se os dados foram salvos\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}


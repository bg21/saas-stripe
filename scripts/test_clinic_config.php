<?php
/**
 * Script de teste para verificar endpoint de configuração da clínica
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;
use App\Models\ClinicConfiguration;
use App\Models\UserPermission;

// Inicializa
$db = Database::getInstance();

echo "=== Teste de Configuração da Clínica ===\n\n";

// 1. Verifica se a tabela existe
echo "1. Verificando se a tabela clinic_configurations existe...\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'clinic_configurations'");
    $tableExists = $stmt->fetch() !== false;
    echo $tableExists ? "   ✅ Tabela existe\n" : "   ❌ Tabela NÃO existe\n";
    
    if ($tableExists) {
        // Verifica estrutura
        $stmt = $db->query("DESCRIBE clinic_configurations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Colunas encontradas: " . count($columns) . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao verificar tabela: " . $e->getMessage() . "\n";
}

// 2. Testa o model diretamente
echo "\n2. Testando ClinicConfiguration model...\n";
try {
    $model = new ClinicConfiguration();
    $tenantId = 1; // Assumindo tenant_id = 1
    
    echo "   Buscando configuração para tenant_id = {$tenantId}...\n";
    $config = $model->findByTenant($tenantId);
    
    if ($config) {
        echo "   ✅ Configuração encontrada (ID: {$config['id']})\n";
        echo "   - Duração padrão: {$config['default_appointment_duration']} min\n";
        echo "   - Intervalo: {$config['time_slot_interval']} min\n";
    } else {
        echo "   ⚠️  Nenhuma configuração encontrada (isso é normal se ainda não foi criada)\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao buscar configuração: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n";
}

// 3. Verifica permissão
echo "\n3. Verificando permissão 'manage_clinic_settings'...\n";
try {
    $permissionModel = new UserPermission();
    $userId = 1; // Assumindo user_id = 1
    
    echo "   Verificando se user_id = {$userId} tem permissão...\n";
    $hasPermission = $permissionModel->hasPermission($userId, 'manage_clinic_settings');
    
    echo $hasPermission ? "   ✅ Usuário tem permissão\n" : "   ⚠️  Usuário NÃO tem permissão\n";
    
    // Verifica role do usuário
    $userStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "   Role do usuário: {$user['role']}\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao verificar permissão: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n";
}

// 4. Testa criação de configuração padrão
echo "\n4. Testando criação de configuração padrão...\n";
try {
    $defaultConfig = [
        'tenant_id' => 1,
        'opening_time_monday' => '08:00:00',
        'closing_time_monday' => '18:00:00',
        'default_appointment_duration' => 30,
        'time_slot_interval' => 15,
        'allow_online_booking' => 1,
        'require_confirmation' => 0,
        'cancellation_hours' => 24
    ];
    
    echo "   Tentando criar configuração padrão...\n";
    $configId = $model->createOrUpdate(1, $defaultConfig);
    echo "   ✅ Configuração criada/atualizada (ID: {$configId})\n";
} catch (\Exception $e) {
    echo "   ❌ Erro ao criar configuração: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n";
}

echo "\n=== Fim do teste ===\n";


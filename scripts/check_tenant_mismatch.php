<?php

/**
 * Script para verificar incompatibilidade de tenant_id
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Professional;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Tenant;

echo "🔍 Verificando incompatibilidade de tenant_id...\n\n";

// Lista todos os tenants
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll([]);

echo "📋 Tenants encontrados:\n";
foreach ($tenants as $tenant) {
    echo "  - ID: {$tenant['id']}, Nome: {$tenant['name']}, Status: {$tenant['status']}\n";
}
echo "\n";

// Lista profissionais por tenant
$professionalModel = new Professional();
foreach ($tenants as $tenant) {
    $tenantId = (int)$tenant['id'];
    $professionals = $professionalModel->findByTenant($tenantId);
    echo "👨‍⚕️ Profissionais no tenant {$tenant['name']} (ID: {$tenantId}): " . count($professionals) . "\n";
    foreach ($professionals as $prof) {
        $user = (new User())->findById($prof['user_id']);
        echo "    - {$user['name']} (User ID: {$prof['user_id']}, Tenant ID do User: {$user['tenant_id']})\n";
    }
    echo "\n";
}

// Lista especialidades por tenant
$specialtyModel = new Specialty();
foreach ($tenants as $tenant) {
    $tenantId = (int)$tenant['id'];
    $specialties = $specialtyModel->findByTenant($tenantId);
    echo "📋 Especialidades no tenant {$tenant['name']} (ID: {$tenantId}): " . count($specialties) . "\n";
    foreach ($specialties as $spec) {
        echo "    - {$spec['name']} (ID: {$spec['id']})\n";
    }
    echo "\n";
}

// Lista usuários por tenant
$userModel = new User();
foreach ($tenants as $tenant) {
    $tenantId = (int)$tenant['id'];
    $users = $userModel->findByTenant($tenantId);
    echo "👤 Usuários no tenant {$tenant['name']} (ID: {$tenantId}): " . count($users) . "\n";
    foreach ($users as $user) {
        echo "    - {$user['name']} ({$user['email']}) - Role: {$user['role']}\n";
    }
    echo "\n";
}

echo "✅ Verificação concluída!\n";


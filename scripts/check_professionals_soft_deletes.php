<?php

/**
 * Script para verificar se profissionais estão marcados como deletados
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Professional;
use App\Models\Specialty;

echo "🔍 Verificando soft deletes em profissionais e especialidades...\n\n";

$professionalModel = new Professional();
$specialtyModel = new Specialty();

// Busca TODOS os profissionais (incluindo deletados)
$reflection = new ReflectionClass($professionalModel);
$dbProperty = $reflection->getProperty('db');
$dbProperty->setAccessible(true);
$pdo = $dbProperty->getValue($professionalModel);
$stmt = $pdo->query("SELECT id, tenant_id, user_id, crmv, status, deleted_at FROM professionals WHERE tenant_id = 3");
$allProfessionals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Profissionais no banco (tenant_id = 3):\n";
foreach ($allProfessionals as $prof) {
    $deleted = $prof['deleted_at'] ? 'DELETADO' : 'ATIVO';
    echo "  - ID: {$prof['id']}, CRMV: {$prof['crmv']}, Status: {$prof['status']}, Deleted: {$deleted}\n";
    if ($prof['deleted_at']) {
        echo "    ⚠️  Deleted_at: {$prof['deleted_at']}\n";
    }
}
echo "\n";

// Testa findByTenant
$professionals = $professionalModel->findByTenant(3);
echo "📋 Resultado de findByTenant(3): " . count($professionals) . " profissionais\n\n";

// Busca TODAS as especialidades (incluindo deletadas)
$reflection = new ReflectionClass($specialtyModel);
$dbProperty = $reflection->getProperty('db');
$dbProperty->setAccessible(true);
$pdo = $dbProperty->getValue($specialtyModel);
$stmt = $pdo->query("SELECT id, tenant_id, name, status, deleted_at FROM specialties WHERE tenant_id = 3");
$allSpecialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Especialidades no banco (tenant_id = 3):\n";
foreach ($allSpecialties as $spec) {
    $deleted = $spec['deleted_at'] ? 'DELETADO' : 'ATIVO';
    echo "  - ID: {$spec['id']}, Nome: {$spec['name']}, Status: {$spec['status']}, Deleted: {$deleted}\n";
    if ($spec['deleted_at']) {
        echo "    ⚠️  Deleted_at: {$spec['deleted_at']}\n";
    }
}
echo "\n";

// Testa findByTenant
$specialties = $specialtyModel->findByTenant(3);
echo "📋 Resultado de findByTenant(3): " . count($specialties) . " especialidades\n\n";

echo "✅ Verificação concluída!\n";


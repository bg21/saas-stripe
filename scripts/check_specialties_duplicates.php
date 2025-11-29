<?php

/**
 * Script para verificar especialidades duplicadas
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Specialty;

echo "🔍 Verificando especialidades duplicadas...\n\n";

$specialtyModel = new Specialty();

// Busca TODAS as especialidades (incluindo deletadas)
$reflection = new ReflectionClass($specialtyModel);
$dbProperty = $reflection->getProperty('db');
$dbProperty->setAccessible(true);
$pdo = $dbProperty->getValue($specialtyModel);

// Busca todas as especialidades do tenant 3
$stmt = $pdo->query("SELECT id, tenant_id, name, status, deleted_at FROM specialties WHERE tenant_id = 3 ORDER BY id");
$allSpecialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Todas as especialidades no banco (tenant_id = 3):\n";
$names = [];
foreach ($allSpecialties as $spec) {
    $deleted = $spec['deleted_at'] ? 'DELETADO' : 'ATIVO';
    echo "  - ID: {$spec['id']}, Nome: {$spec['name']}, Status: {$spec['status']}, Deleted: {$deleted}\n";
    
    if (!$spec['deleted_at']) {
        $names[] = $spec['name'];
    }
}
echo "\n";

// Verifica duplicatas
$duplicates = array_count_values($names);
$hasDuplicates = false;
foreach ($duplicates as $name => $count) {
    if ($count > 1) {
        $hasDuplicates = true;
        echo "⚠️  DUPLICATA encontrada: '{$name}' aparece {$count} vezes\n";
    }
}

if (!$hasDuplicates) {
    echo "✅ Nenhuma duplicata encontrada (por nome)\n";
}

echo "\n📋 Especialidades únicas (não deletadas): " . count(array_unique($names)) . "\n";
echo "📋 Total de especialidades (não deletadas): " . count($names) . "\n";

// Testa findByTenant
$specialties = $specialtyModel->findByTenant(3);
echo "📋 Resultado de findByTenant(3): " . count($specialties) . " especialidades\n\n";

// Mostra as especialidades retornadas
echo "Especialidades retornadas por findByTenant:\n";
foreach ($specialties as $spec) {
    echo "  - ID: {$spec['id']}, Nome: {$spec['name']}\n";
}

echo "\n✅ Verificação concluída!\n";


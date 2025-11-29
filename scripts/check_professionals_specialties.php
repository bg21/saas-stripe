<?php

/**
 * Script para verificar se há profissionais com especialidades que não existem
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Professional;
use App\Models\Specialty;

echo "🔍 Verificando profissionais e suas especialidades...\n\n";

$professionalModel = new Professional();
$specialtyModel = new Specialty();

// Busca todos os profissionais do tenant 3
$reflection = new ReflectionClass($professionalModel);
$dbProperty = $reflection->getProperty('db');
$dbProperty->setAccessible(true);
$pdo = $dbProperty->getValue($professionalModel);

$stmt = $pdo->query("SELECT id, tenant_id, user_id, crmv, specialties, status FROM professionals WHERE tenant_id = 3");
$professionals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca todas as especialidades do tenant 3
$specialties = $specialtyModel->findByTenant(3);
$specialtyIds = array_column($specialties, 'id');

echo "📋 Especialidades existentes (tenant_id = 3):\n";
foreach ($specialties as $spec) {
    echo "  - ID: {$spec['id']}, Nome: {$spec['name']}\n";
}
echo "\n";

echo "👨‍⚕️ Verificando profissionais:\n";
$hasInvalidSpecialties = false;

foreach ($professionals as $prof) {
    echo "  Profissional ID: {$prof['id']}, CRMV: {$prof['crmv']}\n";
    
    if (empty($prof['specialties'])) {
        echo "    ✅ Sem especialidades cadastradas\n";
        continue;
    }
    
    $profSpecialties = json_decode($prof['specialties'], true);
    
    if (!is_array($profSpecialties)) {
        echo "    ⚠️  Especialidades em formato inválido: {$prof['specialties']}\n";
        $hasInvalidSpecialties = true;
        continue;
    }
    
    echo "    Especialidades cadastradas: " . implode(', ', $profSpecialties) . "\n";
    
    foreach ($profSpecialties as $specId) {
        if (!in_array($specId, $specialtyIds)) {
            echo "    ❌ ERRO: Especialidade ID {$specId} não existe!\n";
            $hasInvalidSpecialties = true;
        } else {
            $specName = $specialties[array_search($specId, array_column($specialties, 'id'))]['name'];
            echo "    ✅ Especialidade ID {$specId} ({$specName}) existe\n";
        }
    }
    echo "\n";
}

if ($hasInvalidSpecialties) {
    echo "⚠️  PROBLEMA ENCONTRADO: Há profissionais com especialidades que não existem!\n";
    echo "💡 Solução: Remova as especialidades inválidas dos profissionais ou crie as especialidades faltantes.\n";
} else {
    echo "✅ Todos os profissionais têm especialidades válidas!\n";
}

echo "\n✅ Verificação concluída!\n";


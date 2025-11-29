<?php

/**
 * Script para corrigir especialidades inválidas dos profissionais
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Professional;
use App\Models\Specialty;

echo "🔧 Corrigindo especialidades dos profissionais...\n\n";

$professionalModel = new Professional();
$specialtyModel = new Specialty();

// Busca todas as especialidades do tenant 3
$specialties = $specialtyModel->findByTenant(3);
$specialtyMap = [];
foreach ($specialties as $spec) {
    $specialtyMap[$spec['id']] = $spec['name'];
}

echo "📋 Especialidades disponíveis:\n";
foreach ($specialtyMap as $id => $name) {
    echo "  - ID: {$id}, Nome: {$name}\n";
}
echo "\n";

// Mapeamento: IDs antigos -> IDs novos
// Profissional 1: tinha [6, 7] -> deve ter [1, 2] (Clínica Geral, Cirurgia)
// Profissional 2: tinha [6, 8] -> deve ter [1, 3] (Clínica Geral, Dermatologia)
// Profissional 3: tinha [9, 10] -> deve ter [4, 5] (Ortopedia, Cardiologia)

$corrections = [
    1 => [1, 2], // Dr. João Silva: Clínica Geral, Cirurgia
    2 => [1, 3], // Dra. Maria Santos: Clínica Geral, Dermatologia
    3 => [4, 5]  // Dr. Carlos Oliveira: Ortopedia, Cardiologia
];

// Busca todos os profissionais do tenant 3
$reflection = new ReflectionClass($professionalModel);
$dbProperty = $reflection->getProperty('db');
$dbProperty->setAccessible(true);
$pdo = $dbProperty->getValue($professionalModel);

foreach ($corrections as $profId => $newSpecialtyIds) {
    $stmt = $pdo->prepare("SELECT id, crmv, specialties FROM professionals WHERE id = ?");
    $stmt->execute([$profId]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prof) {
        echo "⚠️  Profissional ID {$profId} não encontrado\n";
        continue;
    }
    
    echo "👨‍⚕️ Corrigindo Profissional ID {$profId} (CRMV: {$prof['crmv']})\n";
    echo "  Especialidades antigas: {$prof['specialties']}\n";
    
    $newSpecialtiesJson = json_encode($newSpecialtyIds);
    echo "  Especialidades novas: {$newSpecialtiesJson}\n";
    
    $updateStmt = $pdo->prepare("UPDATE professionals SET specialties = ? WHERE id = ?");
    $updateStmt->execute([$newSpecialtiesJson, $profId]);
    
    echo "  ✅ Corrigido!\n\n";
}

echo "✅ Correção concluída!\n";
echo "\n💡 Agora os profissionais têm especialidades válidas.\n";


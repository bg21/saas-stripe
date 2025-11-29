<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\Professional;

echo "🧪 Teste Simples dos Models\n";
echo str_repeat("=", 60) . "\n\n";

// Busca tenant e profissional
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll([]);
$tenantId = (int)$tenants[0]['id'];

$professionalModel = new Professional();
$professionals = $professionalModel->findByTenant($tenantId);
$professionalId = (int)$professionals[0]['id'];

echo "Tenant ID: {$tenantId}\n";
echo "Professional ID: {$professionalId}\n\n";

// Testa ProfessionalSchedule
echo "📅 Testando ProfessionalSchedule...\n";
$scheduleModel = new ProfessionalSchedule();

try {
    // Testa findByProfessional
    $schedule = $scheduleModel->findByProfessional($tenantId, $professionalId);
    echo "✅ findByProfessional: " . count($schedule) . " registros\n";
    
    // Testa findByDay
    $daySchedule = $scheduleModel->findByDay($tenantId, $professionalId, 1); // Segunda-feira
    if ($daySchedule) {
        echo "✅ findByDay (segunda): Encontrado\n";
        echo "   Start: {$daySchedule['start_time']}, End: {$daySchedule['end_time']}\n";
    } else {
        echo "⚠️  findByDay (segunda): Não encontrado (normal se não configurado)\n";
    }
    
    // Testa saveSchedule
    $scheduleId = $scheduleModel->saveSchedule($tenantId, $professionalId, 1, '08:00:00', '18:00:00', true);
    echo "✅ saveSchedule: ID {$scheduleId}\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n";

// Testa ScheduleBlock
echo "🚫 Testando ScheduleBlock...\n";
$blockModel = new ScheduleBlock();

try {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    // Testa findByProfessionalAndPeriod
    $blocks = $blockModel->findByProfessionalAndPeriod($tenantId, $professionalId, $tomorrow, $tomorrow);
    echo "✅ findByProfessionalAndPeriod: " . count($blocks) . " bloqueios\n";
    
    // Testa hasBlock
    $hasBlock = $blockModel->hasBlock($tenantId, $professionalId, "{$tomorrow} 12:00:00");
    echo "✅ hasBlock: " . ($hasBlock ? "SIM" : "NÃO") . "\n";
    
    // Testa findFutureBlocks
    $futureBlocks = $blockModel->findFutureBlocks($tenantId, $professionalId);
    echo "✅ findFutureBlocks: " . count($futureBlocks) . " bloqueios futuros\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n✅ Teste concluído!\n";


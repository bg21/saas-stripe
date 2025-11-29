<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\Professional;

$tenantModel = new Tenant();
$tenants = $tenantModel->findAll([]);
$tenantId = (int)$tenants[0]['id'];

$professionalModel = new Professional();
$professionals = $professionalModel->findByTenant($tenantId);
$professionalId = (int)$professionals[0]['id'];

$blockModel = new ScheduleBlock();

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$datetime = "{$tomorrow} 12:00:00";

echo "Testando hasBlock...\n";
echo "Tenant ID: {$tenantId}\n";
echo "Professional ID: {$professionalId}\n";
echo "DateTime: {$datetime}\n\n";

try {
    $hasBlock = $blockModel->hasBlock($tenantId, $professionalId, $datetime);
    echo "✅ hasBlock retornou: " . ($hasBlock ? "true" : "false") . "\n";
} catch (\Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    echo "   Trace: " . substr($e->getTraceAsString(), 0, 500) . "\n";
}


<?php

/**
 * Script de teste para verificar profissionais na API
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Professional;
use App\Models\User;
use App\Models\Tenant;

echo "🔍 Testando profissionais...\n\n";

// Busca primeiro tenant
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll([]);
if (empty($tenants)) {
    echo "❌ Nenhum tenant encontrado!\n";
    exit(1);
}

$tenant = $tenants[0];
$tenantId = (int)$tenant['id'];
echo "✅ Tenant encontrado: {$tenant['name']} (ID: {$tenantId})\n\n";

// Busca profissionais
$professionalModel = new Professional();
$professionals = $professionalModel->findByTenant($tenantId);

echo "📊 Total de profissionais no banco: " . count($professionals) . "\n\n";

if (empty($professionals)) {
    echo "⚠️  Nenhum profissional encontrado no banco!\n";
    echo "💡 Execute o seed: php vendor/bin/phinx seed:run -s VeterinaryClinicSeed\n";
    exit(1);
}

echo "✅ Profissionais encontrados:\n";
foreach ($professionals as $prof) {
    $user = (new User())->findById($prof['user_id']);
    echo "  - ID: {$prof['id']}, User ID: {$prof['user_id']}, CRMV: {$prof['crmv']}, Status: {$prof['status']}\n";
    if ($user) {
        echo "    Usuário: {$user['name']} ({$user['email']}) - Role: {$user['role']}\n";
    } else {
        echo "    ⚠️  Usuário não encontrado!\n";
    }
    echo "\n";
}

echo "✅ Teste concluído!\n";


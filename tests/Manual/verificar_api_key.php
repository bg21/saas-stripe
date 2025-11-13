<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

Config::load();

$db = \App\Utils\Database::getInstance();
$stmt = $db->query("SELECT id, name, api_key, status FROM tenants");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Tenants no Banco ===\n\n";

foreach ($tenants as $tenant) {
    echo "ID: {$tenant['id']}\n";
    echo "Nome: {$tenant['name']}\n";
    echo "Status: {$tenant['status']}\n";
    echo "API Key: {$tenant['api_key']}\n";
    echo "Tamanho: " . strlen($tenant['api_key']) . " caracteres\n";
    echo "---\n\n";
}

echo "=== Teste de Autenticação ===\n\n";

$testApiKey = 'test_api_key_1234567890123456789012345678901234567890123456789012345678901234';
echo "API Key do teste: $testApiKey\n";
echo "Tamanho: " . strlen($testApiKey) . " caracteres\n\n";

$tenantModel = new \App\Models\Tenant();
$found = $tenantModel->findByApiKey($testApiKey);

if ($found) {
    echo "✅ API Key encontrada no banco!\n";
    echo "Tenant ID: {$found['id']}\n";
    echo "Nome: {$found['name']}\n";
} else {
    echo "❌ API Key NÃO encontrada no banco!\n";
    echo "\nUse esta API key do banco:\n";
    if (!empty($tenants)) {
        echo $tenants[0]['api_key'] . "\n";
    }
}


<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

Config::load();

$pdo = new PDO(
    'mysql:host=' . Config::get('DB_HOST') . ';dbname=' . Config::get('DB_NAME'),
    Config::get('DB_USER'),
    Config::get('DB_PASS')
);

$stmt = $pdo->query('SELECT id, tenant_id, name, is_active FROM professional_roles WHERE deleted_at IS NULL ORDER BY tenant_id, sort_order');
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de roles profissionais no banco: " . count($roles) . "\n\n";

if (count($roles) > 0) {
    echo "Roles encontradas:\n";
    foreach ($roles as $role) {
        echo sprintf(
            "  - ID: %d | Tenant: %d | Nome: %s | Ativa: %s\n",
            $role['id'],
            $role['tenant_id'],
            $role['name'],
            $role['is_active'] ? 'Sim' : 'Não'
        );
    }
} else {
    echo "⚠️  Nenhuma role profissional encontrada no banco de dados.\n";
    echo "Execute o seed: php vendor/bin/phinx seed:run -s ProfessionalRolesSeed\n";
}


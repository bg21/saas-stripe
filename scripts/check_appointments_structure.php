<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;

$db = Database::getInstance();
$stmt = $db->query("DESCRIBE appointments");
$columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Colunas da tabela appointments:\n";
$existingColumns = [];
foreach ($columns as $col) {
    $existingColumns[] = $col['Field'];
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nVerificando campos necessários:\n";
$requiredFields = ['confirmed_at', 'confirmed_by', 'completed_at', 'completed_by'];
foreach ($requiredFields as $field) {
    $exists = in_array($field, $existingColumns);
    echo "  " . ($exists ? "✅" : "❌") . " {$field}\n";
}


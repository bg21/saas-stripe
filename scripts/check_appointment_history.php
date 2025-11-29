<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;

$db = Database::getInstance();
$stmt = $db->query("SHOW TABLES LIKE 'appointment_history'");
$exists = $stmt->rowCount() > 0;

echo $exists ? "✅ Tabela appointment_history EXISTE\n" : "❌ Tabela appointment_history NÃO EXISTE\n";

if ($exists) {
    // Verifica estrutura
    $stmt = $db->query("DESCRIBE appointment_history");
    $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "\nColunas da tabela:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
}


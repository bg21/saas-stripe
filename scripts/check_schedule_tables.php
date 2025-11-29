<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;

$db = Database::getInstance();
$tables = ['professional_schedules', 'schedule_blocks'];

foreach ($tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $stmt->rowCount() > 0;
    echo ($exists ? "✅" : "❌") . " Tabela $table " . ($exists ? "EXISTE" : "NÃO EXISTE") . "\n";
}


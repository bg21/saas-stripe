<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;

$db = Database::getInstance();

echo "Estrutura da tabela professional_schedules:\n";
$stmt = $db->query('DESCRIBE professional_schedules');
$cols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nEstrutura da tabela schedule_blocks:\n";
$stmt = $db->query('DESCRIBE schedule_blocks');
$cols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}


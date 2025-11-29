<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;

$db = Database::getInstance();

try {
    $stmt = $db->query('DESCRIBE clinic_configurations');
    $cols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "✅ Tabela clinic_configurations EXISTE\n";
    echo "\nEstrutura:\n";
    foreach($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch(\Exception $e) {
    echo "❌ Tabela clinic_configurations NÃO EXISTE\n";
    echo "Erro: {$e->getMessage()}\n";
}


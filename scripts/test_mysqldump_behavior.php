<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use Ifsnop\Mysqldump\Mysqldump;

echo "🧪 Testando comportamento da biblioteca mysqldump-php\n";
echo str_repeat("=", 70) . "\n\n";

$host = Config::get('DB_HOST', '127.0.0.1');
$dbName = Config::get('DB_NAME', 'saas_payments');
$user = Config::get('DB_USER', 'root');
$pass = Config::get('DB_PASS', '');

// Teste 1: Com compressão, passando .sql
echo "Teste 1: Com compressão, passando 'test1.sql'\n";
$file1 = __DIR__ . '/../backups/test1.sql';
$dump1 = new Mysqldump(
    "mysql:host={$host};dbname={$dbName}",
    $user,
    $pass,
    ['compress' => Mysqldump::GZIP]
);
$dump1->start($file1);

if (file_exists($file1)) {
    echo "  ✅ Arquivo criado: test1.sql\n";
} elseif (file_exists($file1 . '.gz')) {
    echo "  ✅ Arquivo criado: test1.sql.gz (biblioteca adicionou .gz)\n";
} else {
    echo "  ❌ Nenhum arquivo encontrado\n";
}

// Teste 2: Com compressão, passando .sql.gz
echo "\nTeste 2: Com compressão, passando 'test2.sql.gz'\n";
$file2 = __DIR__ . '/../backups/test2.sql.gz';
$dump2 = new Mysqldump(
    "mysql:host={$host};dbname={$dbName}",
    $user,
    $pass,
    ['compress' => Mysqldump::GZIP]
);
$dump2->start($file2);

if (file_exists($file2)) {
    echo "  ✅ Arquivo criado: test2.sql.gz\n";
} elseif (file_exists($file2 . '.gz')) {
    echo "  ✅ Arquivo criado: test2.sql.gz.gz (biblioteca adicionou .gz novamente)\n";
} else {
    echo "  ❌ Nenhum arquivo encontrado\n";
}

// Teste 3: Sem compressão
echo "\nTeste 3: Sem compressão, passando 'test3.sql'\n";
$file3 = __DIR__ . '/../backups/test3.sql';
$dump3 = new Mysqldump(
    "mysql:host={$host};dbname={$dbName}",
    $user,
    $pass,
    ['compress' => Mysqldump::NONE]
);
$dump3->start($file3);

if (file_exists($file3)) {
    echo "  ✅ Arquivo criado: test3.sql\n";
} else {
    echo "  ❌ Arquivo não encontrado\n";
}

// Lista arquivos criados
echo "\n📁 Arquivos criados:\n";
$files = glob(__DIR__ . '/../backups/test*.sql*');
foreach ($files as $file) {
    echo "  - " . basename($file) . " (" . filesize($file) . " bytes)\n";
}


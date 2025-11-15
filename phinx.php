<?php

/**
 * Configuração do Phinx (Sistema de Migrations)
 * 
 * Este arquivo configura o Phinx para usar as mesmas configurações
 * do banco de dados definidas no arquivo .env
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use PDO;
use PDOException;

// Carrega configurações do ambiente
Config::load();

// Obtém configurações do banco de dados
$dbHost = Config::get('DB_HOST', '127.0.0.1');
$dbName = Config::get('DB_NAME', 'saas_payments');
$dbUser = Config::get('DB_USER', 'root');
$dbPass = Config::get('DB_PASS', '');
$dbCharset = Config::get('DB_CHARSET', 'utf8mb4');

// Cria o banco de dados se não existir
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};charset={$dbCharset}",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verifica se o banco existe
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($dbName));
    
    if ($stmt->rowCount() === 0) {
        // Cria o banco de dados
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$dbCharset} COLLATE {$dbCharset}_unicode_ci");
        echo "✅ Banco de dados '{$dbName}' criado automaticamente.\n";
    }
} catch (PDOException $e) {
    // Se não conseguir criar, deixa o Phinx tentar e mostrar o erro
    // Isso permite que o usuário veja o erro real de conexão
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'name' => $dbName,
            'user' => $dbUser,
            'pass' => $dbPass,
            'port' => 3306,
            'charset' => $dbCharset,
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'name' => $dbName,
            'user' => $dbUser,
            'pass' => $dbPass,
            'port' => 3306,
            'charset' => $dbCharset,
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];


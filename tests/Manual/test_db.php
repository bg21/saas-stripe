<?php

/**
 * Script para testar conexão com banco de dados
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

Config::load();

try {
    $host = Config::get('DB_HOST', '127.0.0.1');
    $dbName = Config::get('DB_NAME', 'saas_payments');
    $user = Config::get('DB_USER', 'root');
    $pass = Config::get('DB_PASS', '');
    $charset = Config::get('DB_CHARSET', 'utf8mb4');
    
    $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
    
    echo "Testando conexão com banco de dados...\n";
    echo "Host: $host\n";
    echo "Database: $dbName\n";
    echo "User: $user\n\n";
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Conexão com banco OK!\n\n";
    
    // Verifica se a tabela tenants existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'tenants'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'tenants' existe!\n\n";
        
        // Lista tenants
        $stmt = $pdo->query("SELECT id, name, api_key, status FROM tenants");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($tenants) > 0) {
            echo "✅ Tenants encontrados:\n";
            foreach ($tenants as $tenant) {
                echo "  - ID: {$tenant['id']}, Nome: {$tenant['name']}, Status: {$tenant['status']}\n";
                echo "    API Key: {$tenant['api_key']}\n\n";
            }
        } else {
            echo "⚠️  Nenhum tenant encontrado. Execute o seed_example.sql\n";
        }
    } else {
        echo "❌ Tabela 'tenants' não existe. Execute o schema.sql primeiro!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    echo "\nVerifique:\n";
    echo "1. MySQL está rodando?\n";
    echo "2. As credenciais no .env estão corretas?\n";
    echo "3. O banco 'saas_payments' foi criado?\n";
}


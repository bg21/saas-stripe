<?php

/**
 * Script para verificar se tudo está configurado corretamente
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

Config::load();

echo "=== Verificação de Configuração ===\n\n";

// 1. Verifica variáveis de ambiente
echo "1. Verificando variáveis de ambiente:\n";
$host = Config::get('DB_HOST', '127.0.0.1');
$dbName = Config::get('DB_NAME', 'saas_payments');
$user = Config::get('DB_USER', 'root');
$pass = Config::get('DB_PASS', '');
$charset = Config::get('DB_CHARSET', 'utf8mb4');
$stripeSecret = Config::get('STRIPE_SECRET');

echo "   DB_HOST: " . ($host ?: '❌ NÃO CONFIGURADO') . "\n";
echo "   DB_NAME: " . ($dbName ?: '❌ NÃO CONFIGURADO') . "\n";
echo "   DB_USER: " . ($user ?: '❌ NÃO CONFIGURADO') . "\n";
echo "   DB_PASS: " . ($pass !== null ? '***' : '❌ NÃO CONFIGURADO') . "\n";
echo "   DB_CHARSET: " . ($charset ?: '❌ NÃO CONFIGURADO') . "\n";
echo "   STRIPE_SECRET: " . ($stripeSecret && $stripeSecret !== 'sk_test_xxx' ? '✅ Configurado' : '⚠️  Não configurado ou padrão') . "\n\n";

// 2. Testa conexão com banco
echo "2. Testando conexão com banco de dados:\n";
try {
    $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Verifica se o banco existe
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Banco '$dbName' existe\n";
        
        // Seleciona o banco
        $pdo->exec("USE `$dbName`");
        
        // Verifica tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredTables = ['tenants', 'users', 'customers', 'subscriptions', 'stripe_events'];
        $missingTables = array_diff($requiredTables, $tables);
        
        if (empty($missingTables)) {
            echo "   ✅ Todas as tabelas necessárias existem\n";
        } else {
            echo "   ⚠️  Tabelas faltando: " . implode(', ', $missingTables) . "\n";
            echo "      Execute: mysql -u root -p < schema.sql\n";
        }
        
        // Verifica tenants
        if (in_array('tenants', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM tenants");
            $count = $stmt->fetch()['count'];
            if ($count > 0) {
                echo "   ✅ $count tenant(s) encontrado(s)\n";
            } else {
                echo "   ⚠️  Nenhum tenant encontrado. Execute: mysql -u root -p saas_payments < seed_example.sql\n";
            }
        }
    } else {
        echo "   ❌ Banco '$dbName' NÃO existe!\n";
        echo "      Execute: mysql -u root -p < schema.sql\n";
    }
    
} catch (PDOException $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
    echo "      Verifique se o MySQL está rodando e as credenciais estão corretas\n";
}

echo "\n3. Testando Database singleton:\n";
try {
    $db = \App\Utils\Database::getInstance();
    echo "   ✅ Database singleton funcionando\n";
    
    // Testa uma query simples
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result && $result['test'] == 1) {
        echo "   ✅ Query de teste funcionando\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n=== Verificação concluída ===\n";


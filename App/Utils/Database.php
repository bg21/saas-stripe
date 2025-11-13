<?php

namespace App\Utils;

use PDO;
use PDOException;
use Config;

/**
 * Classe singleton para gerenciar conexão com banco de dados
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Obtém instância única da conexão PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                // Obtém configurações do banco
                $host = Config::get('DB_HOST', '127.0.0.1');
                $dbName = Config::get('DB_NAME', 'saas_payments');
                $user = Config::get('DB_USER', 'root');
                $pass = Config::get('DB_PASS', '');
                $charset = Config::get('DB_CHARSET', 'utf8mb4');
                
                // Constrói DSN
                $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";

                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
                ]);
                
                // Garante que o banco está selecionado
                self::$instance->exec("USE `{$dbName}`");
            } catch (PDOException $e) {
                throw new \RuntimeException("Erro ao conectar ao banco de dados: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Previne clonagem
     */
    private function __clone() {}

    /**
     * Previne deserialização
     */
    public function __wakeup()
    {
        throw new \RuntimeException("Não é possível deserializar singleton");
    }
}


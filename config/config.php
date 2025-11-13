<?php

/**
 * Configurações do sistema
 * Carrega variáveis de ambiente do arquivo .env
 */

use Dotenv\Dotenv;

class Config
{
    private static ?array $env = null;

    /**
     * Carrega as variáveis de ambiente
     */
    public static function load(): void
    {
        if (self::$env === null) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            self::$env = $_ENV;
        }
    }

    /**
     * Obtém uma variável de ambiente
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return $_ENV[$key] ?? $default;
    }

    /**
     * Obtém o ambiente atual
     */
    public static function env(): string
    {
        return self::get('APP_ENV', 'production');
    }

    /**
     * Verifica se está em desenvolvimento
     */
    public static function isDevelopment(): bool
    {
        return self::env() === 'development';
    }
}


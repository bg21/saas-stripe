<?php

namespace App\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Config;

/**
 * Serviço de logging usando Monolog
 */
class Logger
{
    private static ?MonologLogger $instance = null;

    /**
     * Obtém instância única do logger
     */
    public static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            $logPath = Config::get('LOG_PATH', 'app.log');
            $logFile = __DIR__ . '/../../' . $logPath;

            self::$instance = new MonologLogger('saas_payments');

            $handler = new StreamHandler($logFile, MonologLogger::DEBUG);
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            );
            $handler->setFormatter($formatter);

            self::$instance->pushHandler($handler);
        }

        return self::$instance;
    }

    /**
     * Log de informação
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    /**
     * Log de erro
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    /**
     * Log de debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    /**
     * Log de warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }
}


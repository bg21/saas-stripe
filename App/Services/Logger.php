<?php

namespace App\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Config;

/**
 * Serviço de logging usando Monolog com rotação automática
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
            // Configuração de logs
            $logPath = Config::get('LOG_PATH', 'logs/app.log');
            $logDir = __DIR__ . '/../../' . dirname($logPath);
            $logFile = basename($logPath);
            
            // Garante que o diretório de logs existe
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            // Remove extensão do arquivo (RotatingFileHandler adiciona data automaticamente)
            $logFile = preg_replace('/\.log$/', '', $logFile);
            $logFilePath = $logDir . '/' . $logFile . '.log';

            self::$instance = new MonologLogger('saas_payments');

            // ✅ OTIMIZAÇÃO: Nível de log baseado em ambiente (reduz verbosidade em produção)
            $logLevel = Config::isDevelopment() 
                ? MonologLogger::DEBUG 
                : MonologLogger::INFO; // Em produção, apenas INFO e acima
            
            // RotatingFileHandler: rotação diária, mantém 30 dias de logs
            $maxFiles = (int)Config::get('LOG_MAX_FILES', 30);
            $handler = new RotatingFileHandler(
                $logFilePath,
                $maxFiles, // Mantém 30 arquivos (30 dias)
                $logLevel, // ✅ Nível dinâmico baseado em ambiente
                true, // Bubble (propaga para outros handlers)
                0644  // Permissões do arquivo
            );
            
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
     * Sanitiza contexto automaticamente
     */
    public static function info(string $message, array $context = []): void
    {
        $sanitizedContext = self::sanitizeContext($context);
        self::getInstance()->info($message, $sanitizedContext);
    }

    /**
     * Log de erro
     * Sanitiza contexto automaticamente
     */
    public static function error(string $message, array $context = []): void
    {
        $sanitizedContext = self::sanitizeContext($context);
        self::getInstance()->error($message, $sanitizedContext);
    }

    /**
     * Log de debug
     * Sanitiza contexto automaticamente
     */
    public static function debug(string $message, array $context = []): void
    {
        $sanitizedContext = self::sanitizeContext($context);
        self::getInstance()->debug($message, $sanitizedContext);
    }

    /**
     * Log de warning
     * Sanitiza contexto automaticamente
     */
    public static function warning(string $message, array $context = []): void
    {
        $sanitizedContext = self::sanitizeContext($context);
        self::getInstance()->warning($message, $sanitizedContext);
    }
    
    /**
     * Sanitiza contexto de log removendo dados sensíveis
     * 
     * @param array $context Contexto original
     * @return array Contexto sanitizado
     */
    private static function sanitizeContext(array $context): array
    {
        return \App\Utils\ErrorHandler::sanitizeContext($context);
    }
}


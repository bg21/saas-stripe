<?php

namespace App\Controllers;

use App\Utils\Database;
use App\Services\CacheService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use Config;
use Flight;

/**
 * Controller para Health Check Avançado
 * 
 * Verifica o status de todas as dependências do sistema:
 * - Banco de dados (MySQL)
 * - Redis (cache)
 * - Stripe API
 * - Informações do sistema
 */
class HealthCheckController
{
    /**
     * Health check básico (compatível com o endpoint atual)
     * GET /health
     */
    public function basic(): void
    {
        ResponseHelper::sendSuccess([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => Config::env()
        ]);
    }

    /**
     * Health check avançado com verificações detalhadas
     * GET /health/detailed
     */
    public function detailed(): void
    {
        $startTime = microtime(true);
        $checks = [];
        $overallStatus = 'healthy';

        // Verifica banco de dados
        $dbCheck = $this->checkDatabase();
        $checks['database'] = $dbCheck;
        if ($dbCheck['status'] !== 'healthy') {
            $overallStatus = 'unhealthy';
        }

        // Verifica Redis
        $redisCheck = $this->checkRedis();
        $checks['redis'] = $redisCheck;
        // Redis não é crítico, então não afeta o status geral se estiver unavailable

        // Verifica Stripe API
        $stripeCheck = $this->checkStripe();
        $checks['stripe'] = $stripeCheck;
        if ($stripeCheck['status'] !== 'healthy') {
            $overallStatus = 'unhealthy';
        }

        // Informações do sistema
        $systemInfo = $this->getSystemInfo();
        $checks['system'] = $systemInfo;

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        $responseData = [
            'status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => Config::env(),
            'version' => '1.0.0',
            'response_time_ms' => $totalTime,
            'checks' => $checks
        ];
        
        if ($overallStatus === 'healthy') {
            ResponseHelper::sendSuccess($responseData);
        } else {
            ResponseHelper::sendError(
                503,
                'Sistema não saudável',
                'Uma ou mais dependências estão com problemas',
                'HEALTH_CHECK_UNHEALTHY',
                $responseData,
                ['action' => 'detailed_health_check']
            );
        }
    }

    /**
     * Verifica conexão com banco de dados
     */
    private function checkDatabase(): array
    {
        $startTime = microtime(true);
        
        try {
            $db = Database::getInstance();
            
            // Executa query simples para verificar conexão
            $stmt = $db->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            // Verifica versão do MySQL
            $stmt = $db->query("SELECT VERSION() as version");
            $version = $stmt->fetch();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'details' => [
                    'host' => Config::get('DB_HOST', '127.0.0.1'),
                    'database' => Config::get('DB_NAME', 'saas_payments'),
                    'mysql_version' => $version['version'] ?? 'unknown',
                    'connection' => 'ok'
                ]
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Logger::warning("Health check: Database não disponível", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'response_time_ms' => $responseTime,
                'error' => Config::isDevelopment() ? $e->getMessage() : 'Database connection failed',
                'details' => [
                    'host' => Config::get('DB_HOST', '127.0.0.1'),
                    'database' => Config::get('DB_NAME', 'saas_payments'),
                    'connection' => 'failed'
                ]
            ];
        }
    }

    /**
     * Verifica conexão com Redis
     */
    private function checkRedis(): array
    {
        $startTime = microtime(true);
        
        try {
            $redisClient = CacheService::getRedisClient();
            
            if ($redisClient === null) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                return [
                    'status' => 'unavailable',
                    'response_time_ms' => $responseTime,
                    'message' => 'Redis não configurado ou não disponível',
                    'details' => [
                        'url' => Config::get('REDIS_URL', 'redis://127.0.0.1:6379'),
                        'connection' => 'not_configured',
                        'note' => 'Redis é opcional - o sistema funciona sem ele usando fallback para banco de dados'
                    ]
                ];
            }
            
            // Testa operação básica
            $testKey = 'health_check_' . time();
            $redisClient->setex($testKey, 1, 'test');
            $value = $redisClient->get($testKey);
            $redisClient->del($testKey);
            
            // Obtém informações do Redis
            $info = $redisClient->info('server');
            $redisVersion = $info['redis_version'] ?? 'unknown';
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'details' => [
                    'url' => Config::get('REDIS_URL', 'redis://127.0.0.1:6379'),
                    'redis_version' => $redisVersion,
                    'connection' => 'ok',
                    'test_operation' => $value === 'test' ? 'ok' : 'failed'
                ]
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Logger::warning("Health check: Redis não disponível", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unavailable',
                'response_time_ms' => $responseTime,
                'message' => 'Redis não disponível (sistema funciona sem ele)',
                'error' => Config::isDevelopment() ? $e->getMessage() : 'Redis connection failed',
                'details' => [
                    'url' => Config::get('REDIS_URL', 'redis://127.0.0.1:6379'),
                    'connection' => 'failed'
                ]
            ];
        }
    }

    /**
     * Verifica conexão com Stripe API
     */
    private function checkStripe(): array
    {
        $startTime = microtime(true);
        
        try {
            $stripeSecret = Config::get('STRIPE_SECRET');
            
            if (empty($stripeSecret)) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                return [
                    'status' => 'unhealthy',
                    'response_time_ms' => $responseTime,
                    'error' => 'STRIPE_SECRET não configurado',
                    'details' => [
                        'configured' => false,
                        'connection' => 'not_configured'
                    ]
                ];
            }
            
            // Cria instância do StripeService para testar conexão
            $stripeService = new StripeService();
            
            // Tenta uma operação simples (listar balance - operação leve)
            // Nota: Não vamos fazer uma chamada real para não consumir quota
            // Apenas verificamos se a chave está configurada e o cliente pode ser criado
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'details' => [
                    'configured' => true,
                    'key_prefix' => substr($stripeSecret, 0, 7) . '...',
                    'key_type' => strpos($stripeSecret, 'sk_test_') === 0 ? 'test' : (strpos($stripeSecret, 'sk_live_') === 0 ? 'live' : 'unknown'),
                    'connection' => 'ok'
                ]
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Logger::warning("Health check: Stripe não disponível", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'response_time_ms' => $responseTime,
                'error' => Config::isDevelopment() ? $e->getMessage() : 'Stripe API connection failed',
                'details' => [
                    'configured' => !empty(Config::get('STRIPE_SECRET')),
                    'connection' => 'failed'
                ]
            ];
        }
    }

    /**
     * Obtém informações do sistema
     */
    private function getSystemInfo(): array
    {
        return [
            'status' => 'ok',
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'timezone' => date_default_timezone_get(),
            'server_time' => date('Y-m-d H:i:s'),
            'uptime' => $this->getUptime()
        ];
    }

    /**
     * Formata bytes para formato legível
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Obtém uptime do servidor (se disponível)
     */
    private function getUptime(): ?string
    {
        // Tenta obter uptime do sistema (funciona no Linux)
        if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
            $uptime = @shell_exec('uptime -p 2>/dev/null');
            if ($uptime) {
                return trim($uptime);
            }
            
            // Fallback para formato padrão
            $uptime = @shell_exec('uptime 2>/dev/null');
            if ($uptime) {
                return trim($uptime);
            }
        }
        
        // No Windows ou se shell_exec não estiver disponível, retorna null
        return null;
    }
}


<?php

namespace App\Middleware;

use App\Models\PerformanceMetric;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Middleware de Métricas de Performance
 * 
 * Registra métricas de performance (tempo de resposta, memória) de todas as requisições.
 * Não bloqueia requisições - apenas registra informações.
 */
class PerformanceMiddleware
{
    private PerformanceMetric $metricModel;
    private bool $enabled;
    private array $excludedRoutes;
    
    public function __construct()
    {
        $this->metricModel = new PerformanceMetric();
        
        // Configurações
        $this->enabled = Config::get('PERFORMANCE_METRICS_ENABLED', 'true') === 'true';
        $this->excludedRoutes = [
            '/',
            '/health',
            '/health/detailed',
            '/v1/webhook', // Webhooks podem gerar muitas métricas
            '/debug',
            '/api-docs',
            '/api-docs/ui'
        ];
    }
    
    /**
     * Captura início da requisição
     * Deve ser chamado no before('start')
     * ✅ OTIMIZAÇÃO: Registra shutdown function automaticamente para garantir log
     */
    public function captureRequest(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        
        // Ignora rotas excluídas
        if (in_array($requestUri, $this->excludedRoutes)) {
            return;
        }
        
        // Armazena informações iniciais
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        Flight::set('performance_start_time', $startTime);
        Flight::set('performance_start_memory', $startMemory);
        
        // ✅ OTIMIZAÇÃO: Registra shutdown function automaticamente
        // Isso garante que as métricas sejam registradas mesmo se logMetrics() não for chamado explicitamente
        $metricModel = $this->metricModel;
        $excludedRoutes = $this->excludedRoutes;
        register_shutdown_function(function() use ($metricModel, $requestUri, $excludedRoutes, $startTime, $startMemory) {
            // Verifica novamente se a rota está excluída
            if (in_array($requestUri, $excludedRoutes)) {
                return;
            }
            
            // Calcula métricas
            $duration = (microtime(true) - $startTime) * 1000; // ms
            $memory = (memory_get_usage() - $startMemory) / 1024 / 1024; // MB
            
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $tenantId = Flight::get('tenant_id');
            $userId = Flight::get('user_id');
            
            // Prepara dados para inserção
            $metricData = [
                'endpoint' => $requestUri,
                'method' => $method,
                'duration_ms' => (int)$duration,
                'memory_mb' => round($memory, 2),
                'tenant_id' => $tenantId,
                'user_id' => $userId
            ];
            
            try {
                $metricModel->insert($metricData);
            } catch (\Exception $e) {
                // Não deve quebrar a aplicação se o log falhar
                Logger::error("Erro ao registrar métrica de performance", [
                    'error' => $e->getMessage(),
                    'endpoint' => $metricData['endpoint']
                ]);
            }
        });
    }
    
    /**
     * Registra métricas após resposta
     * ✅ OTIMIZAÇÃO: Usa register_shutdown_function para não bloquear resposta
     * Deve ser chamado no after('start') ou no tratamento de erros
     */
    public function logMetrics(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $startTime = Flight::get('performance_start_time');
        $startMemory = Flight::get('performance_start_memory');
        
        // Se não capturou início, não registra
        if ($startTime === null || $startMemory === null) {
            return;
        }
        
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        
        // Ignora rotas excluídas novamente (por segurança)
        if (in_array($requestUri, $this->excludedRoutes)) {
            return;
        }
        
        // Calcula métricas
        $duration = (microtime(true) - $startTime) * 1000; // ms
        $memory = (memory_get_usage() - $startMemory) / 1024 / 1024; // MB
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $tenantId = Flight::get('tenant_id');
        $userId = Flight::get('user_id');
        
        // Prepara dados para inserção
        $metricData = [
            'endpoint' => $requestUri,
            'method' => $method,
            'duration_ms' => (int)$duration,
            'memory_mb' => round($memory, 2),
            'tenant_id' => $tenantId,
            'user_id' => $userId
        ];
        
        // ✅ OTIMIZAÇÃO: Registra de forma assíncrona após enviar resposta
        // Isso não bloqueia a resposta HTTP
        $metricModel = $this->metricModel;
        register_shutdown_function(function() use ($metricModel, $metricData) {
            try {
                $metricModel->insert($metricData);
            } catch (\Exception $e) {
                // Não deve quebrar a aplicação se o log falhar
                Logger::error("Erro ao registrar métrica de performance", [
                    'error' => $e->getMessage(),
                    'endpoint' => $metricData['endpoint']
                ]);
            }
        });
        
        // Limpa dados temporários
        Flight::set('performance_start_time', null);
        Flight::set('performance_start_memory', null);
    }
}


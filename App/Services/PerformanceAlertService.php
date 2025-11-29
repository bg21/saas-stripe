<?php

namespace App\Services;

use App\Models\PerformanceMetric;
use App\Services\Logger as LoggerService;

/**
 * Service para gerenciar alertas de performance
 */
class PerformanceAlertService
{
    private PerformanceMetric $metricModel;
    private int $slowThreshold; // ms
    private int $verySlowThreshold; // ms
    
    public function __construct()
    {
        $this->metricModel = new PerformanceMetric();
        $this->slowThreshold = (int) \Config::get('PERFORMANCE_SLOW_THRESHOLD', '500'); // 500ms
        $this->verySlowThreshold = (int) \Config::get('PERFORMANCE_VERY_SLOW_THRESHOLD', '1000'); // 1000ms
    }
    
    /**
     * Verifica e registra alertas para endpoints lentos
     * 
     * @param int|null $tenantId ID do tenant (null para todos)
     * @param int $hours Últimas N horas para verificar (padrão: 1)
     * @return array Array com alertas encontrados
     */
    public function checkSlowEndpoints(?int $tenantId = null, int $hours = 1): array
    {
        $alerts = [];
        
        try {
            // Busca estatísticas agregadas das últimas N horas
            $dateFrom = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            $dateTo = date('Y-m-d H:i:s');
            
            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ];
            
            $stats = $this->metricModel->getAggregatedStats($tenantId, $filters);
            
            foreach ($stats as $stat) {
                $avgDuration = (float)($stat['avg_duration_ms'] ?? 0);
                $endpoint = $stat['endpoint'];
                $method = $stat['method'];
                $totalRequests = (int)($stat['total_requests'] ?? 0);
                
                // Ignora se não tiver requisições suficientes
                if ($totalRequests < 5) {
                    continue;
                }
                
                $severity = null;
                $message = null;
                
                if ($avgDuration >= $this->verySlowThreshold) {
                    $severity = 'critical';
                    $message = "Endpoint {$method} {$endpoint} está MUITO LENTO: média de {$avgDuration}ms (limite: {$this->verySlowThreshold}ms) em {$totalRequests} requisições";
                } elseif ($avgDuration >= $this->slowThreshold) {
                    $severity = 'warning';
                    $message = "Endpoint {$method} {$endpoint} está lento: média de {$avgDuration}ms (limite: {$this->slowThreshold}ms) em {$totalRequests} requisições";
                }
                
                if ($severity) {
                    $alerts[] = [
                        'severity' => $severity,
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'avg_duration_ms' => $avgDuration,
                        'total_requests' => $totalRequests,
                        'threshold' => $severity === 'critical' ? $this->verySlowThreshold : $this->slowThreshold,
                        'message' => $message,
                        'checked_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Registra alerta no log
                    LoggerService::warning("Alerta de Performance: {$message}", [
                        'severity' => $severity,
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'avg_duration_ms' => $avgDuration,
                        'total_requests' => $totalRequests,
                        'tenant_id' => $tenantId
                    ]);
                }
            }
        } catch (\Exception $e) {
            LoggerService::error("Erro ao verificar alertas de performance", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
        }
        
        return $alerts;
    }
    
    /**
     * Obtém endpoints mais lentos
     * 
     * @param int|null $tenantId ID do tenant
     * @param int $limit Limite de resultados
     * @param int $hours Últimas N horas
     * @return array
     */
    public function getSlowestEndpoints(?int $tenantId = null, int $limit = 10, int $hours = 24): array
    {
        try {
            $dateFrom = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            $dateTo = date('Y-m-d H:i:s');
            
            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ];
            
            $stats = $this->metricModel->getAggregatedStats($tenantId, $filters);
            
            // Ordena por tempo médio decrescente
            usort($stats, function($a, $b) {
                $avgA = (float)($a['avg_duration_ms'] ?? 0);
                $avgB = (float)($b['avg_duration_ms'] ?? 0);
                return $avgB <=> $avgA;
            });
            
            return array_slice($stats, 0, $limit);
        } catch (\Exception $e) {
            LoggerService::error("Erro ao obter endpoints mais lentos", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            return [];
        }
    }
}


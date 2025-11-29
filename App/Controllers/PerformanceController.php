<?php

namespace App\Controllers;

use App\Models\PerformanceMetric;
use App\Services\PerformanceAlertService;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;

/**
 * Controller para gerenciar métricas de performance
 */
class PerformanceController
{
    private PerformanceMetric $metricModel;
    private PerformanceAlertService $alertService;

    public function __construct()
    {
        $this->metricModel = new PerformanceMetric();
        $this->alertService = new PerformanceAlertService();
    }

    /**
     * Lista métricas de performance
     * GET /v1/metrics/performance
     * 
     * Query params opcionais:
     *   - endpoint: Filtrar por endpoint (busca parcial)
     *   - method: Filtrar por método HTTP (GET, POST, etc)
     *   - date_from: Data inicial (formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS)
     *   - date_to: Data final (formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS)
     *   - limit: Limite de resultados (padrão: 100, máximo: 500)
     *   - offset: Offset para paginação (padrão: 0)
     *   - aggregated: Se true, retorna estatísticas agregadas por endpoint (padrão: false)
     */
    public function list(): void
    {
        try {
            $isMaster = Flight::get('is_master') === true;
            
            // Master key não precisa de verificação de permissões
            // Usuários normais precisam de permissão para ver métricas
            if (!$isMaster) {
                PermissionHelper::require('view_performance_metrics');
            }
            
            $tenantId = Flight::get('tenant_id');
            
            if (!$isMaster && $tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_performance_metrics']);
                return;
            }

            $queryParams = Flight::request()->query;
            
            // Prepara filtros
            $filters = [];
            
            if (isset($queryParams['endpoint']) && !empty($queryParams['endpoint'])) {
                $filters['endpoint'] = $queryParams['endpoint'];
            }
            
            if (isset($queryParams['method']) && !empty($queryParams['method'])) {
                $filters['method'] = strtoupper($queryParams['method']);
            }
            
            if (isset($queryParams['date_from']) && !empty($queryParams['date_from'])) {
                $filters['date_from'] = $queryParams['date_from'];
            }
            
            if (isset($queryParams['date_to']) && !empty($queryParams['date_to'])) {
                $filters['date_to'] = $queryParams['date_to'];
            }

            // Verifica se deve retornar estatísticas agregadas
            $aggregated = isset($queryParams['aggregated']) && 
                         ($queryParams['aggregated'] === 'true' || $queryParams['aggregated'] === '1');
            
            if ($aggregated) {
                // Retorna estatísticas agregadas
                if ($isMaster) {
                    $stats = $this->metricModel->getAggregatedStats(null, $filters);
                } else {
                    $stats = $this->metricModel->getAggregatedStats($tenantId, $filters);
                }
                
                ResponseHelper::sendSuccess([
                    'stats' => $stats,
                    'filters' => $filters
                ]);
                return;
            }

            // Paginação
            $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 100;
            $limit = min(max($limit, 1), 500); // Entre 1 e 500
            $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
            $offset = max($offset, 0); // Não pode ser negativo

            // Master key vê todas as métricas, tenants normais só veem as suas
            if ($isMaster) {
                $metrics = $this->metricModel->findAllMetrics($filters, $limit, $offset);
                $total = $this->metricModel->countAllMetrics($filters);
            } else {
                $metrics = $this->metricModel->findByTenant($tenantId, $filters, $limit, $offset);
                $total = $this->metricModel->countByTenant($tenantId, $filters);
            }

            ResponseHelper::sendSuccess([
                'metrics' => $metrics,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ],
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar métricas de performance',
                'PERFORMANCE_METRICS_LIST_ERROR',
                ['action' => 'list_performance_metrics', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém alertas de performance
     * GET /v1/metrics/performance/alerts
     * 
     * Query params opcionais:
     *   - hours: Últimas N horas para verificar (padrão: 1)
     */
    public function alerts(): void
    {
        try {
            $isMaster = Flight::get('is_master') === true;
            
            if (!$isMaster) {
                PermissionHelper::require('view_performance_metrics');
            }
            
            $tenantId = Flight::get('tenant_id');
            
            if (!$isMaster && $tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_performance_alerts']);
                return;
            }

            $queryParams = Flight::request()->query;
            $hours = isset($queryParams['hours']) ? (int)$queryParams['hours'] : 1;
            $hours = max(1, min($hours, 168)); // Entre 1 e 168 horas (7 dias)
            
            $alerts = $this->alertService->checkSlowEndpoints($isMaster ? null : $tenantId, $hours);
            
            // Separa por severidade
            $critical = array_values(array_filter($alerts, fn($a) => $a['severity'] === 'critical'));
            $warnings = array_values(array_filter($alerts, fn($a) => $a['severity'] === 'warning'));
            
            ResponseHelper::sendSuccess([
                'alerts' => $alerts,
                'summary' => [
                    'total' => count($alerts),
                    'critical' => count($critical),
                    'warnings' => count($warnings)
                ],
                'critical' => $critical,
                'warnings' => $warnings,
                'period_hours' => $hours
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter alertas de performance',
                'PERFORMANCE_ALERTS_ERROR',
                ['action' => 'get_performance_alerts', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém endpoints mais lentos
     * GET /v1/metrics/performance/slowest
     * 
     * Query params opcionais:
     *   - limit: Limite de resultados (padrão: 10, máximo: 50)
     *   - hours: Últimas N horas (padrão: 24)
     */
    public function slowest(): void
    {
        try {
            $isMaster = Flight::get('is_master') === true;
            
            if (!$isMaster) {
                PermissionHelper::require('view_performance_metrics');
            }
            
            $tenantId = Flight::get('tenant_id');
            
            if (!$isMaster && $tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_slowest_endpoints']);
                return;
            }

            $queryParams = Flight::request()->query;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            $limit = max(1, min($limit, 50)); // Entre 1 e 50
            $hours = isset($queryParams['hours']) ? (int)$queryParams['hours'] : 24;
            $hours = max(1, min($hours, 720)); // Entre 1 e 720 horas (30 dias)
            
            $slowest = $this->alertService->getSlowestEndpoints(
                $isMaster ? null : $tenantId,
                $limit,
                $hours
            );
            
            ResponseHelper::sendSuccess([
                'endpoints' => $slowest,
                'count' => count($slowest),
                'period_hours' => $hours
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter endpoints mais lentos',
                'PERFORMANCE_SLOWEST_ERROR',
                ['action' => 'get_slowest_endpoints', 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


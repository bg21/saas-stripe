<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\ApplicationLog;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;

/**
 * Controller para rastreamento de requisições (Tracing)
 * 
 * Permite buscar todos os logs relacionados a uma requisição específica
 * através do request_id, facilitando debugging e correlação de logs.
 */
class TraceController
{
    private AuditLog $auditLogModel;
    private ApplicationLog $applicationLogModel;

    public function __construct()
    {
        $this->auditLogModel = new AuditLog();
        $this->applicationLogModel = new ApplicationLog();
    }

    /**
     * Obtém trace completo de uma requisição
     * GET /v1/traces/:request_id
     * 
     * Retorna todos os logs de auditoria relacionados ao request_id,
     * permitindo rastrear toda a jornada de uma requisição.
     */
    public function get(string $requestId): void
    {
        try {
            // Verifica permissão (apenas usuários autenticados podem ver traces)
            PermissionHelper::require('view_audit_logs');
            
            $tenantId = Flight::get('tenant_id');
            
            // Valida formato do request_id (32 caracteres hexadecimais)
            if (!preg_match('/^[a-f0-9]{32}$/i', $requestId)) {
                ResponseHelper::sendValidationError(
                    'Formato de request_id inválido',
                    ['request_id' => 'Request ID deve ter 32 caracteres hexadecimais'],
                    ['action' => 'get_trace', 'request_id' => $requestId]
                );
                return;
            }
            
            // Busca logs de auditoria relacionados ao request_id
            $auditLogs = $this->auditLogModel->findByRequestId($requestId, $tenantId);
            
            // Busca logs de aplicação (Monolog) relacionados ao request_id
            $applicationLogs = $this->applicationLogModel->findByRequestId($requestId, $tenantId);
            
            // Combina e ordena todos os logs por timestamp
            $allLogs = $this->mergeAndSortLogs($auditLogs, $applicationLogs);
            
            if (empty($allLogs)) {
                ResponseHelper::sendNotFoundError(
                    'Trace',
                    ['action' => 'get_trace', 'request_id' => $requestId, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Formata resposta
            $trace = [
                'request_id' => $requestId,
                'total_logs' => count($allLogs),
                'audit_logs_count' => count($auditLogs),
                'application_logs_count' => count($applicationLogs),
                'logs' => $allLogs,
                'timeline' => $this->generateTimeline($allLogs),
                'summary' => $this->generateSummary($auditLogs, $applicationLogs)
            ];
            
            ResponseHelper::sendSuccess($trace);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter trace',
                'TRACE_GET_ERROR',
                ['action' => 'get_trace', 'request_id' => $requestId ?? null, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
    
    /**
     * Busca traces por intervalo de tempo
     * GET /v1/traces/search?start_date=...&end_date=...
     */
    public function search(): void
    {
        try {
            PermissionHelper::require('view_audit_logs');
            
            $tenantId = Flight::get('tenant_id');
            
            // Obtém parâmetros da query string
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $level = $_GET['level'] ?? null;
            $requestId = $_GET['request_id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 1000);
            $offset = (int)($_GET['offset'] ?? 0);
            
            // Validações
            if (!$startDate || !$endDate) {
                ResponseHelper::sendValidationError(
                    'Datas obrigatórias',
                    [
                        'start_date' => 'Data inicial é obrigatória (formato: Y-m-d H:i:s)',
                        'end_date' => 'Data final é obrigatória (formato: Y-m-d H:i:s)'
                    ],
                    ['action' => 'search_traces']
                );
                return;
            }
            
            // Valida formato das datas
            $startTimestamp = strtotime($startDate);
            $endTimestamp = strtotime($endDate);
            
            if ($startTimestamp === false || $endTimestamp === false) {
                ResponseHelper::sendValidationError(
                    'Formato de data inválido',
                    [
                        'start_date' => 'Formato esperado: Y-m-d H:i:s',
                        'end_date' => 'Formato esperado: Y-m-d H:i:s'
                    ],
                    ['action' => 'search_traces']
                );
                return;
            }
            
            if ($startTimestamp > $endTimestamp) {
                ResponseHelper::sendValidationError(
                    'Data inicial maior que data final',
                    [],
                    ['action' => 'search_traces']
                );
                return;
            }
            
            // Prepara filtros
            $filters = [];
            if ($level) {
                $filters['level'] = $level;
            }
            if ($requestId) {
                $filters['request_id'] = $requestId;
            }
            
            // Busca logs de auditoria por intervalo de tempo
            // Nota: findByTenant já suporta date_from e date_to
            $auditFilters = array_merge($filters, [
                'date_from' => $startDate,
                'date_to' => $endDate
            ]);
            
            // Se request_id fornecido, busca diretamente por request_id e filtra por data
            if ($requestId && preg_match('/^[a-f0-9]{32}$/i', $requestId)) {
                $auditLogs = $this->auditLogModel->findByRequestId($requestId, $tenantId);
                // Aplica filtro de data manualmente
                $auditLogs = array_filter($auditLogs, function($log) use ($startDate, $endDate) {
                    $logDate = $log['created_at'] ?? '';
                    return $logDate >= $startDate && $logDate <= $endDate;
                });
                // Reindexa array após filtro
                $auditLogs = array_values($auditLogs);
            } else {
                $auditLogs = $this->auditLogModel->findByTenant($tenantId, $auditFilters, $limit, $offset);
            }
            
            // Busca logs de aplicação
            $applicationLogs = $this->applicationLogModel->findByDateRange(
                $startDate,
                $endDate,
                $tenantId,
                $filters,
                $limit,
                $offset
            );
            
            // Combina e ordena
            $allLogs = $this->mergeAndSortLogs($auditLogs, $applicationLogs);
            
            // Conta total
            $totalAudit = $this->auditLogModel->countByTenant($tenantId, array_merge($filters, [
                'date_from' => $startDate,
                'date_to' => $endDate
            ]));
            $totalApplication = $this->applicationLogModel->countByDateRange(
                $startDate,
                $endDate,
                $tenantId,
                $filters
            );
            
            ResponseHelper::sendSuccess([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_logs' => count($allLogs),
                'total_audit_logs' => $totalAudit,
                'total_application_logs' => $totalApplication,
                'logs' => $allLogs,
                'timeline' => $this->generateTimeline($allLogs)
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao buscar traces',
                'TRACE_SEARCH_ERROR',
                ['action' => 'search_traces']
            );
        }
    }
    
    /**
     * Combina e ordena logs de auditoria e aplicação por timestamp
     * 
     * @param array $auditLogs Logs de auditoria
     * @param array $applicationLogs Logs de aplicação
     * @return array Logs combinados e ordenados
     */
    private function mergeAndSortLogs(array $auditLogs, array $applicationLogs): array
    {
        $allLogs = [];
        
        // Adiciona tipo aos logs de auditoria
        foreach ($auditLogs as $log) {
            $log['log_type'] = 'audit';
            $allLogs[] = $log;
        }
        
        // Adiciona tipo aos logs de aplicação
        foreach ($applicationLogs as $log) {
            $log['log_type'] = 'application';
            $allLogs[] = $log;
        }
        
        // Ordena por timestamp
        usort($allLogs, function($a, $b) {
            $timeA = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
            $timeB = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
            
            if ($timeA === $timeB) {
                // Se mesmo timestamp, ordena por tipo (audit primeiro)
                if (($a['log_type'] ?? '') === 'audit') {
                    return -1;
                }
                return 1;
            }
            
            return $timeA <=> $timeB;
        });
        
        return $allLogs;
    }
    
    /**
     * Gera timeline de eventos
     * 
     * @param array $logs Lista de logs ordenados
     * @return array Timeline com eventos agrupados
     */
    private function generateTimeline(array $logs): array
    {
        $timeline = [];
        
        foreach ($logs as $log) {
            $timestamp = $log['created_at'] ?? null;
            if (!$timestamp) {
                continue;
            }
            
            $date = date('Y-m-d H:i:s', strtotime($timestamp));
            $hour = date('H:i:s', strtotime($timestamp));
            
            if (!isset($timeline[$date])) {
                $timeline[$date] = [];
            }
            
            $event = [
                'time' => $hour,
                'type' => $log['log_type'] ?? 'unknown',
                'level' => $log['level'] ?? ($log['response_status'] ?? 'N/A'),
                'message' => $this->getLogMessage($log),
                'data' => $log
            ];
            
            $timeline[$date][] = $event;
        }
        
        return $timeline;
    }
    
    /**
     * Extrai mensagem do log
     * 
     * @param array $log Log
     * @return string Mensagem
     */
    private function getLogMessage(array $log): string
    {
        if (isset($log['message'])) {
            return $log['message'];
        }
        
        if (isset($log['endpoint'])) {
            return sprintf(
                '%s %s - Status: %d',
                $log['method'] ?? 'UNKNOWN',
                $log['endpoint'],
                $log['response_status'] ?? 0
            );
        }
        
        return 'Log sem mensagem';
    }
    
    /**
     * Gera resumo do trace
     * 
     * @param array $auditLogs Logs de auditoria
     * @param array $applicationLogs Logs de aplicação
     * @return array Resumo com estatísticas
     */
    private function generateSummary(array $auditLogs, array $applicationLogs): array
    {
        $summary = [
            'total_audit_logs' => count($auditLogs),
            'total_application_logs' => count($applicationLogs),
            'total_logs' => count($auditLogs) + count($applicationLogs),
            'endpoints' => [],
            'methods' => [],
            'status_codes' => [],
            'log_levels' => [],
            'total_response_time' => 0,
            'average_response_time' => 0,
            'first_event' => null,
            'last_event' => null
        ];
        
        $allLogs = array_merge($auditLogs, $applicationLogs);
        
        if (empty($allLogs)) {
            return $summary;
        }
        
        // Ordena por timestamp
        usort($allLogs, function($a, $b) {
            $timeA = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
            $timeB = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
            return $timeA <=> $timeB;
        });
        
        $firstLog = $allLogs[0];
        $lastLog = $allLogs[count($allLogs) - 1];
        
        $summary['first_event'] = [
            'type' => isset($firstLog['endpoint']) ? 'audit' : 'application',
            'timestamp' => $firstLog['created_at'] ?? null,
            'message' => $this->getLogMessage($firstLog)
        ];
        
        $summary['last_event'] = [
            'type' => isset($lastLog['endpoint']) ? 'audit' : 'application',
            'timestamp' => $lastLog['created_at'] ?? null,
            'message' => $this->getLogMessage($lastLog)
        ];
        
        // Processa logs de auditoria
        foreach ($auditLogs as $log) {
            $endpoint = $log['endpoint'] ?? 'unknown';
            $summary['endpoints'][$endpoint] = ($summary['endpoints'][$endpoint] ?? 0) + 1;
            
            $method = $log['method'] ?? 'unknown';
            $summary['methods'][$method] = ($summary['methods'][$method] ?? 0) + 1;
            
            $status = $log['response_status'] ?? 0;
            $summary['status_codes'][$status] = ($summary['status_codes'][$status] ?? 0) + 1;
            
            $summary['total_response_time'] += (int)($log['response_time'] ?? 0);
        }
        
        // Processa logs de aplicação
        foreach ($applicationLogs as $log) {
            $level = $log['level'] ?? 'unknown';
            $summary['log_levels'][$level] = ($summary['log_levels'][$level] ?? 0) + 1;
        }
        
        // Calcula média de tempo de resposta (apenas para audit logs)
        if (count($auditLogs) > 0) {
            $summary['average_response_time'] = round($summary['total_response_time'] / count($auditLogs), 2);
        }
        
        return $summary;
    }
}


<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar logs de auditoria
 */
class AuditLogController
{
    private AuditLog $auditLogModel;

    public function __construct()
    {
        $this->auditLogModel = new AuditLog();
    }

    /**
     * Lista logs de auditoria do tenant
     * GET /v1/audit-logs
     * 
     * Query params opcionais:
     *   - endpoint: Filtrar por endpoint (busca parcial)
     *   - method: Filtrar por método HTTP (GET, POST, etc)
     *   - status: Filtrar por status HTTP (200, 404, 500, etc)
     *   - date_from: Data inicial (formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS)
     *   - date_to: Data final (formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS)
     *   - limit: Limite de resultados (padrão: 100, máximo: 500)
     *   - offset: Offset para paginação (padrão: 0)
     */
    public function list(): void
    {
        try {
            $isMaster = Flight::get('is_master') === true;
            
            // Master key não precisa de verificação de permissões
            // Usuários normais precisam de permissão para ver logs
            if (!$isMaster) {
                PermissionHelper::require('view_audit_logs');
            }
            
            $tenantId = Flight::get('tenant_id');
            
            if (!$isMaster && $tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
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
            
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $filters['status'] = (int) $queryParams['status'];
            }
            
            if (isset($queryParams['date_from']) && !empty($queryParams['date_from'])) {
                $filters['date_from'] = $queryParams['date_from'];
            }
            
            if (isset($queryParams['date_to']) && !empty($queryParams['date_to'])) {
                $filters['date_to'] = $queryParams['date_to'];
            }

            // Paginação
            $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 100;
            $limit = min(max($limit, 1), 500); // Entre 1 e 500
            $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
            $offset = max($offset, 0); // Não pode ser negativo

            // Master key vê todos os logs, tenants normais só veem os seus
            if ($isMaster) {
                $logs = $this->auditLogModel->findAllLogs($filters, $limit, $offset);
                $total = $this->auditLogModel->countAllLogs($filters);
            } else {
                $logs = $this->auditLogModel->findByTenant($tenantId, $filters, $limit, $offset);
                $total = $this->auditLogModel->countByTenant($tenantId, $filters);
            }

            Flight::json([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ],
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar logs de auditoria", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar logs de auditoria',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém um log de auditoria específico
     * GET /v1/audit-logs/:id
     */
    public function get(string $id): void
    {
        try {
            $isMaster = Flight::get('is_master') === true;
            
            // Master key não precisa de verificação de permissões
            // Usuários normais precisam de permissão para ver logs
            if (!$isMaster) {
                PermissionHelper::require('view_audit_logs');
            }
            
            $tenantId = Flight::get('tenant_id');
            $logId = $id;
            
            if (!$isMaster && $tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Busca o log
            $log = $this->auditLogModel->findById((int) $logId);

            if ($log === null) {
                http_response_code(404);
                Flight::json(['error' => 'Log não encontrado'], 404);
                return;
            }

            // Verifica se o log pertence ao tenant (a menos que seja master key)
            if (!$isMaster && (int) $log['tenant_id'] !== (int) $tenantId) {
                http_response_code(403);
                Flight::json(['error' => 'Acesso negado'], 403);
                return;
            }

            Flight::json([
                'success' => true,
                'data' => $log
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter log de auditoria", [
                'error' => $e->getMessage(),
                'log_id' => $logId ?? null,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter log de auditoria',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


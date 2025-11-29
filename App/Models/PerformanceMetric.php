<?php

namespace App\Models;

/**
 * Model para gerenciar métricas de performance
 */
class PerformanceMetric extends BaseModel
{
    protected string $table = 'performance_metrics';
    
    /**
     * Busca métricas por tenant com filtros
     * 
     * @param int|null $tenantId ID do tenant (null para master)
     * @param array $filters Filtros (endpoint, method, date_from, date_to)
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array
     */
    public function findByTenant(?int $tenantId, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        if (!empty($filters['endpoint'])) {
            $sql .= " AND endpoint LIKE :endpoint";
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }
        
        if (!empty($filters['method'])) {
            $sql .= " AND method = :method";
            $params['method'] = strtoupper($filters['method']);
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Conta métricas por tenant com filtros
     * 
     * @param int|null $tenantId ID do tenant (null para master)
     * @param array $filters Filtros
     * @return int
     */
    public function countByTenant(?int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        if (!empty($filters['endpoint'])) {
            $sql .= " AND endpoint LIKE :endpoint";
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }
        
        if (!empty($filters['method'])) {
            $sql .= " AND method = :method";
            $params['method'] = strtoupper($filters['method']);
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Busca todas as métricas (para master)
     * 
     * @param array $filters Filtros
     * @param int $limit Limite
     * @param int $offset Offset
     * @return array
     */
    public function findAllMetrics(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->findByTenant(null, $filters, $limit, $offset);
    }
    
    /**
     * Conta todas as métricas (para master)
     * 
     * @param array $filters Filtros
     * @return int
     */
    public function countAllMetrics(array $filters = []): int
    {
        return $this->countByTenant(null, $filters);
    }
    
    /**
     * Obtém estatísticas agregadas por endpoint
     * 
     * @param int|null $tenantId ID do tenant
     * @param array $filters Filtros
     * @return array
     */
    public function getAggregatedStats(?int $tenantId, array $filters = []): array
    {
        $sql = "SELECT 
                    endpoint,
                    method,
                    COUNT(*) as total_requests,
                    AVG(duration_ms) as avg_duration_ms,
                    MIN(duration_ms) as min_duration_ms,
                    MAX(duration_ms) as max_duration_ms,
                    AVG(memory_mb) as avg_memory_mb,
                    MIN(memory_mb) as min_memory_mb,
                    MAX(memory_mb) as max_memory_mb
                FROM {$this->table}
                WHERE 1=1";
        
        $params = [];
        
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        if (!empty($filters['endpoint'])) {
            $sql .= " AND endpoint LIKE :endpoint";
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }
        
        if (!empty($filters['method'])) {
            $sql .= " AND method = :method";
            $params['method'] = strtoupper($filters['method']);
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY endpoint, method ORDER BY total_requests DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}


<?php

namespace App\Models;

/**
 * Model para gerenciar logs da aplicação (Monolog)
 */
class ApplicationLog extends BaseModel
{
    protected string $table = 'application_logs';

    /**
     * Busca logs por request_id
     * 
     * @param string $requestId Request ID único
     * @param int|null $tenantId ID do tenant (opcional, para validação de segurança)
     * @return array Lista de logs relacionados ao request_id
     */
    public function findByRequestId(string $requestId, ?int $tenantId = null): array
    {
        $conditions = ['request_id = :request_id'];
        $params = ['request_id' => $requestId];
        
        // Se tenant_id fornecido, adiciona filtro de segurança
        if ($tenantId !== null) {
            $conditions[] = '(tenant_id = :tenant_id OR tenant_id IS NULL)';
            $params['tenant_id'] = $tenantId;
        }
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY created_at ASC, level_value ASC";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca logs por intervalo de tempo
     * 
     * @param string $startDate Data/hora inicial (Y-m-d H:i:s)
     * @param string $endDate Data/hora final (Y-m-d H:i:s)
     * @param int|null $tenantId ID do tenant (opcional)
     * @param array $filters Filtros adicionais (level, request_id, etc)
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de logs
     */
    public function findByDateRange(
        string $startDate,
        string $endDate,
        ?int $tenantId = null,
        array $filters = [],
        int $limit = 1000,
        int $offset = 0
    ): array {
        $conditions = [
            'created_at >= :start_date',
            'created_at <= :end_date'
        ];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Filtro de tenant
        if ($tenantId !== null) {
            $conditions[] = '(tenant_id = :tenant_id OR tenant_id IS NULL)';
            $params['tenant_id'] = $tenantId;
        }
        
        // Filtros adicionais
        if (isset($filters['level'])) {
            $conditions[] = 'level = :level';
            $params['level'] = $filters['level'];
        }
        
        if (isset($filters['request_id'])) {
            $conditions[] = 'request_id = :filter_request_id';
            $params['filter_request_id'] = $filters['request_id'];
        }
        
        if (isset($filters['min_level_value'])) {
            $conditions[] = 'level_value >= :min_level_value';
            $params['min_level_value'] = $filters['min_level_value'];
        }
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY created_at ASC, level_value ASC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Conta logs por intervalo de tempo
     * 
     * @param string $startDate Data/hora inicial
     * @param string $endDate Data/hora final
     * @param int|null $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return int Total de logs
     */
    public function countByDateRange(
        string $startDate,
        string $endDate,
        ?int $tenantId = null,
        array $filters = []
    ): int {
        $conditions = [
            'created_at >= :start_date',
            'created_at <= :end_date'
        ];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        if ($tenantId !== null) {
            $conditions[] = '(tenant_id = :tenant_id OR tenant_id IS NULL)';
            $params['tenant_id'] = $tenantId;
        }
        
        if (isset($filters['level'])) {
            $conditions[] = 'level = :level';
            $params['level'] = $filters['level'];
        }
        
        if (isset($filters['request_id'])) {
            $conditions[] = 'request_id = :filter_request_id';
            $params['filter_request_id'] = $filters['request_id'];
        }
        
        if (isset($filters['min_level_value'])) {
            $conditions[] = 'level_value >= :min_level_value';
            $params['min_level_value'] = $filters['min_level_value'];
        }
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE " . implode(' AND ', $conditions);
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Remove logs antigos (retenção configurável)
     * 
     * @param int $daysToKeep Dias para manter logs
     * @return int Número de logs removidos
     */
    public function deleteOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE created_at < :cutoff_date");
        $stmt->bindValue(':cutoff_date', $cutoffDate);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}


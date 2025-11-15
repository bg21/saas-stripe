<?php

namespace App\Models;

/**
 * Model para gerenciar logs de auditoria
 */
class AuditLog extends BaseModel
{
    protected string $table = 'audit_logs';

    /**
     * Cria um novo log de auditoria
     * 
     * @param array $data Dados do log
     * @return int ID do log criado
     */
    public function createLog(array $data): int
    {
        // Limita tamanho do request_body (10KB)
        if (isset($data['request_body']) && strlen($data['request_body']) > 10240) {
            $data['request_body'] = substr($data['request_body'], 0, 10240) . '... [truncated]';
        }

        return $this->insert($data);
    }

    /**
     * Busca logs por tenant
     * 
     * @param int|null $tenantId ID do tenant (null para master key)
     * @param array $filters Filtros adicionais (endpoint, method, status, etc)
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de logs
     */
    public function findByTenant(?int $tenantId, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $conditions = [];
        $params = [];

        if ($tenantId !== null) {
            $conditions[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        } else {
            $conditions[] = 'tenant_id IS NULL';
        }

        // Aplica filtros
        if (isset($filters['endpoint'])) {
            $conditions[] = 'endpoint LIKE :endpoint';
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }

        if (isset($filters['method'])) {
            $conditions[] = 'method = :method';
            $params['method'] = $filters['method'];
        }

        if (isset($filters['status'])) {
            $conditions[] = 'response_status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Conta total de logs por tenant (com filtros)
     * 
     * @param int|null $tenantId ID do tenant
     * @param array $filters Filtros
     * @return int Total de logs
     */
    public function countByTenant(?int $tenantId, array $filters = []): int
    {
        $conditions = [];
        $params = [];

        if ($tenantId !== null) {
            $conditions[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        } else {
            $conditions[] = 'tenant_id IS NULL';
        }

        // Aplica mesmos filtros do findByTenant
        if (isset($filters['endpoint'])) {
            $conditions[] = 'endpoint LIKE :endpoint';
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }

        if (isset($filters['method'])) {
            $conditions[] = 'method = :method';
            $params['method'] = $filters['method'];
        }

        if (isset($filters['status'])) {
            $conditions[] = 'response_status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Busca todos os logs (sem filtro de tenant) - para master key
     * 
     * @param array $filters Filtros adicionais (endpoint, method, status, etc)
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de logs
     */
    public function findAllLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $conditions = [];
        $params = [];

        // Aplica filtros (sem filtro de tenant)
        if (isset($filters['endpoint'])) {
            $conditions[] = 'endpoint LIKE :endpoint';
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }

        if (isset($filters['method'])) {
            $conditions[] = 'method = :method';
            $params['method'] = $filters['method'];
        }

        if (isset($filters['status'])) {
            $conditions[] = 'response_status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Conta total de logs (sem filtro de tenant) - para master key
     * 
     * @param array $filters Filtros
     * @return int Total de logs
     */
    public function countAllLogs(array $filters = []): int
    {
        $conditions = [];
        $params = [];

        // Aplica mesmos filtros do findAllLogs
        if (isset($filters['endpoint'])) {
            $conditions[] = 'endpoint LIKE :endpoint';
            $params['endpoint'] = '%' . $filters['endpoint'] . '%';
        }

        if (isset($filters['method'])) {
            $conditions[] = 'method = :method';
            $params['method'] = $filters['method'];
        }

        if (isset($filters['status'])) {
            $conditions[] = 'response_status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

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
    public function deleteOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE created_at < :cutoff_date");
        $stmt->bindValue(':cutoff_date', $cutoffDate);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}


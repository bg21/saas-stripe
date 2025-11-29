<?php

namespace App\Models;

/**
 * Model para gerenciar profissionais
 */
class Professional extends BaseModel
{
    protected string $table = 'professionals';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca profissional por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID do profissional
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $professional = $this->findById($id);
        return $professional && $professional['tenant_id'] == $tenantId ? $professional : null;
    }

    /**
     * Lista profissionais do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais (ex: ['status' => 'active'])
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }

    /**
     * Busca profissional por user_id e tenant
     * 
     * @param int $tenantId ID do tenant
     * @param int $userId ID do usuário
     * @return array|null
     */
    public function findByUserAndTenant(int $tenantId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE tenant_id = :tenant_id 
             AND user_id = :user_id 
             AND deleted_at IS NULL 
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Busca profissionais com dados do usuário relacionado
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenantWithUser(int $tenantId, array $filters = []): array
    {
        $sql = "SELECT p.*, u.name as user_name, u.email as user_email, u.role as user_role
                FROM {$this->table} p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.tenant_id = :tenant_id 
                AND p.deleted_at IS NULL";

        $params = ['tenant_id' => $tenantId];

        // Aplica filtros
        if (isset($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }

        // Nota: specialty_id será filtrado em PHP após buscar os dados
        // Isso é mais confiável que usar JSON_CONTAINS no SQL

        // Ordenação
        $sortBy = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'DESC';
        $allowedSorts = ['created_at', 'name', 'crmv', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'name') {
                $sql .= " ORDER BY u.name {$sortOrder}";
            } else {
                $sql .= " ORDER BY p.{$sortBy} {$sortOrder}";
            }
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Processa specialties (JSON) e aplica filtro de specialty_id se necessário
        $specialtyIdFilter = isset($filters['specialty_id']) ? (int)$filters['specialty_id'] : null;
        
        foreach ($results as &$result) {
            if (!empty($result['specialties'])) {
                $specialties = json_decode($result['specialties'], true);
                $result['specialties'] = is_array($specialties) ? $specialties : [];
            } else {
                $result['specialties'] = [];
            }

            // Adiciona dados do usuário em formato estruturado
            $result['user'] = [
                'id' => $result['user_id'],
                'name' => $result['user_name'] ?? null,
                'email' => $result['user_email'] ?? null,
                'role' => $result['user_role'] ?? null
            ];
        }
        
        // Filtra por specialty_id se fornecido
        if ($specialtyIdFilter !== null) {
            $results = array_filter($results, function($prof) use ($specialtyIdFilter) {
                $specialties = $prof['specialties'] ?? [];
                return in_array($specialtyIdFilter, $specialties, true);
            });
            $results = array_values($results); // Reindexa array
        }

        return $results;
    }
}


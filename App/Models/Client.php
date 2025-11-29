<?php

namespace App\Models;

/**
 * Model para gerenciar clientes (donos de pets)
 */
class Client extends BaseModel
{
    protected string $table = 'clients';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca cliente por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID do cliente
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $client = $this->findById($id);
        return $client && $client['tenant_id'] == $tenantId ? $client : null;
    }

    /**
     * Lista clientes do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }
}


<?php

namespace App\Models;

/**
 * Model para gerenciar pets
 */
class Pet extends BaseModel
{
    protected string $table = 'pets';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca pet por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID do pet
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $pet = $this->findById($id);
        return $pet && $pet['tenant_id'] == $tenantId ? $pet : null;
    }

    /**
     * Lista pets do tenant
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

    /**
     * Lista pets por cliente
     * 
     * @param int $tenantId ID do tenant
     * @param int $clientId ID do cliente
     * @return array
     */
    public function findByClient(int $tenantId, int $clientId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'client_id' => $clientId
        ]);
    }
}


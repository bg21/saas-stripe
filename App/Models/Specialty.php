<?php

namespace App\Models;

/**
 * Model para gerenciar especialidades
 */
class Specialty extends BaseModel
{
    protected string $table = 'specialties';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca especialidade por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID da especialidade
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $specialty = $this->findById($id);
        return $specialty && $specialty['tenant_id'] == $tenantId ? $specialty : null;
    }

    /**
     * Lista especialidades do tenant
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
}


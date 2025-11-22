<?php

namespace App\Models;

/**
 * Model para gerenciar especialidades veterinárias
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
     * Lista especialidades ativas do tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array
     */
    public function findActiveByTenant(int $tenantId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'status' => 'active'
        ]);
    }

    /**
     * Lista todas as especialidades do tenant (ativas e inativas)
     * 
     * @param int $tenantId ID do tenant
     * @return array
     */
    public function findByTenant(int $tenantId): array
    {
        return $this->findAll(['tenant_id' => $tenantId]);
    }
}


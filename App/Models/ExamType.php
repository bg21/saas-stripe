<?php

namespace App\Models;

/**
 * Model para gerenciar tipos de exames
 */
class ExamType extends BaseModel
{
    protected string $table = 'exam_types';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca tipo de exame por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID do tipo de exame
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $examType = $this->findById($id);
        return $examType && $examType['tenant_id'] == $tenantId ? $examType : null;
    }

    /**
     * Lista tipos de exames ativos do tenant
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
     * Lista todos os tipos de exames do tenant (ativos e inativos)
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais (ex: ['status' => 'active', 'category' => 'blood'])
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }
}


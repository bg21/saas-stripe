<?php

namespace App\Models;

/**
 * Model para gerenciar exames
 */
class Exam extends BaseModel
{
    protected string $table = 'exams';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca exame por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID do exame
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $exam = $this->findById($id);
        return $exam && $exam['tenant_id'] == $tenantId ? $exam : null;
    }

    /**
     * Lista exames do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais (ex: ['status' => 'pending', 'pet_id' => 1])
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }

    /**
     * Lista exames por pet
     * 
     * @param int $tenantId ID do tenant
     * @param int $petId ID do pet
     * @return array
     */
    public function findByPet(int $tenantId, int $petId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'pet_id' => $petId
        ]);
    }

    /**
     * Lista exames por profissional
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @return array
     */
    public function findByProfessional(int $tenantId, int $professionalId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ]);
    }
}


<?php

namespace App\Models;

/**
 * Model para gerenciar profissionais da clínica (veterinários, atendentes)
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
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }

    /**
     * Busca profissional por user_id
     * 
     * @param int $userId ID do usuário
     * @return array|null
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /**
     * Lista profissionais ativos do tenant
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
     * Cria ou atualiza profissional
     * Valida se tenant_id e user_id existem
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados do profissional
     * @return int ID do profissional
     * @throws \RuntimeException Se relacionamentos não existirem
     */
    public function createOrUpdate(int $tenantId, array $data): int
    {
        // Valida tenant
        $tenant = (new Tenant())->findById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant com ID {$tenantId} não encontrado");
        }

        // Valida user_id se fornecido
        if (isset($data['user_id'])) {
            $user = (new User())->findById($data['user_id']);
            if (!$user) {
                throw new \RuntimeException("Usuário com ID {$data['user_id']} não encontrado");
            }
            // Valida se user pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                throw new \RuntimeException("Usuário não pertence ao tenant");
            }
        }

        $data['tenant_id'] = $tenantId;

        if (isset($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
            $this->update($id, $data);
            return $id;
        }

        return $this->insert($data);
    }
}


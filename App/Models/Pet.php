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
     * Lista pets de um cliente
     * 
     * @param int $clientId ID do cliente
     * @return array
     */
    public function findByClient(int $clientId): array
    {
        return $this->findAll(['client_id' => $clientId]);
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
     * Calcula idade do pet em anos
     * 
     * @param string|null $birthDate Data de nascimento (Y-m-d)
     * @return int|null Idade em anos ou null se data não fornecida
     */
    public function calculateAge(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }

        try {
            $birth = new \DateTime($birthDate);
            $now = new \DateTime();
            return $now->diff($birth)->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Cria ou atualiza pet
     * Valida se tenant_id e client_id existem
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados do pet
     * @return int ID do pet
     * @throws \RuntimeException Se relacionamentos não existirem
     */
    public function createOrUpdate(int $tenantId, array $data): int
    {
        // Valida tenant
        $tenant = (new Tenant())->findById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant com ID {$tenantId} não encontrado");
        }

        // Valida client_id se fornecido
        if (isset($data['client_id'])) {
            $client = (new Client())->findById($data['client_id']);
            if (!$client) {
                throw new \RuntimeException("Cliente com ID {$data['client_id']} não encontrado");
            }
            // Valida se client pertence ao tenant
            if ($client['tenant_id'] != $tenantId) {
                throw new \RuntimeException("Cliente não pertence ao tenant");
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


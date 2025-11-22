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
     * Busca cliente por email
     * 
     * @param int $tenantId ID do tenant
     * @param string $email Email do cliente
     * @return array|null
     */
    public function findByEmail(int $tenantId, string $email): ?array
    {
        $clients = $this->findAll([
            'tenant_id' => $tenantId,
            'email' => $email
        ]);
        return $clients[0] ?? null;
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

    /**
     * Cria ou atualiza cliente
     * Valida se tenant_id existe
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados do cliente
     * @return int ID do cliente
     * @throws \RuntimeException Se tenant não existir
     */
    public function createOrUpdate(int $tenantId, array $data): int
    {
        // Valida tenant (usa o mesmo banco de dados)
        $tenantModel = new Tenant();
        $reflection = new \ReflectionClass($tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($tenantModel, $this->db);
        
        $tenant = $tenantModel->findById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant com ID {$tenantId} não encontrado");
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


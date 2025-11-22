<?php

namespace App\Models;

/**
 * Model para gerenciar configurações da clínica veterinária
 */
class ClinicConfiguration extends BaseModel
{
    protected string $table = 'clinic_configurations';

    /**
     * Busca configuração por tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array|null
     */
    public function findByTenant(int $tenantId): ?array
    {
        return $this->findBy('tenant_id', $tenantId);
    }

    /**
     * Cria ou atualiza configuração da clínica
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados da configuração
     * @return int ID da configuração
     * @throws \RuntimeException Se tenant não existir
     */
    public function createOrUpdate(int $tenantId, array $data): int
    {
        // Valida tenant
        $this->validateTenant($tenantId);
        
        $existing = $this->findByTenant($tenantId);
        
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        
        $data['tenant_id'] = $tenantId;
        return $this->insert($data);
    }

    /**
     * Valida se tenant existe antes de criar/atualizar
     * 
     * @param int $tenantId ID do tenant
     * @throws \RuntimeException Se tenant não existir
     */
    protected function validateTenant(int $tenantId): void
    {
        // Usa o mesmo banco de dados do modelo atual
        $tenantModel = new Tenant();
        $reflection = new \ReflectionClass($tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($tenantModel, $this->db);
        
        $tenant = $tenantModel->findById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant com ID {$tenantId} não encontrado");
        }
    }
}


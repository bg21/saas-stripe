<?php

namespace App\Models;

/**
 * Model para gerenciar tenants (clientes SaaS)
 */
class Tenant extends BaseModel
{
    protected string $table = 'tenants';
    protected bool $usesSoftDeletes = true; // ✅ Ativa soft deletes

    /**
     * Busca tenant por API key
     */
    public function findByApiKey(string $apiKey): ?array
    {
        return $this->findBy('api_key', $apiKey);
    }

    /**
     * Gera uma nova API key única
     */
    public function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Cria um novo tenant
     */
    public function create(string $name, ?string $apiKey = null): int
    {
        if ($apiKey === null) {
            $apiKey = $this->generateApiKey();
        }

        return $this->insert([
            'name' => $name,
            'api_key' => $apiKey,
            'status' => 'active'
        ]);
    }
}


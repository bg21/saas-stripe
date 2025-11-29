<?php

namespace App\Models;

use App\Utils\SlugHelper;

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
     * Busca tenant por slug
     * 
     * @param string $slug Slug do tenant (ex: "cao-que-mia")
     * @return array|null Dados do tenant ou null se não encontrado
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Verifica se um slug já existe
     * 
     * @param string $slug Slug a verificar
     * @param int|null $excludeId ID do tenant a excluir da verificação (útil para atualização)
     * @return bool True se slug existe, false caso contrário
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE slug = :slug AND deleted_at IS NULL";
        $params = ['slug' => $slug];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return ($result['count'] ?? 0) > 0;
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
     * 
     * @param string $name Nome do tenant
     * @param string|null $slug Slug do tenant (se não fornecido, será gerado automaticamente)
     * @param string|null $apiKey API key (se não fornecido, será gerada automaticamente)
     * @return int ID do tenant criado
     */
    public function create(string $name, ?string $slug = null, ?string $apiKey = null): int
    {
        if ($apiKey === null) {
            $apiKey = $this->generateApiKey();
        }
        
        // Gera slug se não fornecido
        if ($slug === null) {
            $slug = SlugHelper::generateUnique(
                $name,
                function($s) {
                    return $this->slugExists($s);
                }
            );
        } else {
            // Valida slug fornecido
            if (!SlugHelper::isValid($slug)) {
                throw new \InvalidArgumentException('Slug inválido');
            }
            
            // Verifica se slug já existe
            if ($this->slugExists($slug)) {
                throw new \InvalidArgumentException('Slug já existe');
            }
        }

        return $this->insert([
            'name' => $name,
            'slug' => $slug,
            'api_key' => $apiKey,
            'status' => 'active'
        ]);
    }
}


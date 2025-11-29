<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model para gerenciar roles específicas dos profissionais da clínica
 */
class ProfessionalRole extends BaseModel
{
    protected string $table = 'professional_roles';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca role por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID da role
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $role = $this->findById($id);
        return $role && $role['tenant_id'] == $tenantId ? $role : null;
    }

    /**
     * Lista roles do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        // Sempre começa com tenant_id
        $conditions = ['tenant_id' => $tenantId];
        
        // Adiciona filtros adicionais (evita duplicação)
        foreach ($filters as $key => $value) {
            // Não duplica tenant_id se já estiver em filters
            if ($key !== 'tenant_id') {
                $conditions[$key] = $value;
            }
        }
        
        $roles = $this->findAll($conditions);
        
        // Ordena por sort_order e depois por nome
        usort($roles, function($a, $b) {
            $sortOrderA = isset($a['sort_order']) ? (int)$a['sort_order'] : 0;
            $sortOrderB = isset($b['sort_order']) ? (int)$b['sort_order'] : 0;
            if ($sortOrderA != $sortOrderB) {
                return $sortOrderA <=> $sortOrderB;
            }
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        
        return $roles;
    }

    /**
     * Busca role por nome e tenant
     * 
     * @param int $tenantId ID do tenant
     * @param string $name Nome da role
     * @return array|null
     */
    public function findByTenantAndName(int $tenantId, string $name): ?array
    {
        $conditions = [
            'tenant_id' => $tenantId,
            'name' => $name
        ];
        $results = $this->findAll($conditions);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Cria uma nova role
     * 
     * @param array $data Dados da role
     * @return int ID da role criada
     */
    public function create(array $data): int
    {
        // Valida tenant_id
        if (empty($data['tenant_id'])) {
            throw new \InvalidArgumentException('tenant_id é obrigatório');
        }

        // Valida nome
        if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
            throw new \InvalidArgumentException('Nome da role deve ter pelo menos 2 caracteres');
        }

        // Verifica se já existe role com o mesmo nome no tenant
        $existing = $this->findByTenantAndName((int)$data['tenant_id'], trim($data['name']));
        if ($existing) {
            throw new \InvalidArgumentException('Já existe uma role com este nome para este tenant');
        }

        // Prepara dados
        $insertData = [
            'tenant_id' => (int)$data['tenant_id'],
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'permissions' => !empty($data['permissions']) ? json_encode($data['permissions']) : null,
            'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0
        ];

        return parent::create($insertData);
    }

    /**
     * Atualiza uma role
     * 
     * @param int $id ID da role
     * @param array $data Dados para atualizar
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = ['name', 'description', 'permissions', 'is_active', 'sort_order'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'permissions' && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } elseif ($field === 'is_active') {
                    $updateData[$field] = (bool)$data[$field];
                } elseif ($field === 'sort_order') {
                    $updateData[$field] = (int)$data[$field];
                } elseif ($field === 'name') {
                    $updateData[$field] = trim($data[$field]);
                    if (strlen($updateData[$field]) < 2) {
                        throw new \InvalidArgumentException('Nome da role deve ter pelo menos 2 caracteres');
                    }
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        // Se estiver atualizando o nome, verifica duplicatas
        if (isset($updateData['name'])) {
            $role = $this->findById($id);
            if ($role) {
                $existing = $this->findByTenantAndName((int)$role['tenant_id'], $updateData['name']);
                if ($existing && $existing['id'] != $id) {
                    throw new \InvalidArgumentException('Já existe uma role com este nome para este tenant');
                }
            }
        }

        if (empty($updateData)) {
            return false;
        }

        return parent::update($id, $updateData);
    }
}


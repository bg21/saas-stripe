<?php

namespace App\Models;

/**
 * Model para gerenciar usuários
 */
class User extends BaseModel
{
    protected string $table = 'users';

    /**
     * Busca usuário por email e tenant
     */
    public function findByEmailAndTenant(string $email, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE email = :email AND tenant_id = :tenant_id"
        );
        $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Cria hash de senha usando bcrypt
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verifica senha
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Cria um novo usuário
     */
    public function create(int $tenantId, string $email, string $password, ?string $name = null, string $role = 'viewer'): int
    {
        return $this->insert([
            'tenant_id' => $tenantId,
            'email' => $email,
            'password_hash' => $this->hashPassword($password),
            'name' => $name,
            'status' => 'active',
            'role' => $role
        ]);
    }

    /**
     * Busca usuários por tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array Lista de usuários
     */
    public function findByTenant(int $tenantId): array
    {
        return $this->findAll(['tenant_id' => $tenantId]);
    }

    /**
     * Atualiza role do usuário
     * 
     * @param int $userId ID do usuário
     * @param string $role Nova role (admin, editor, viewer)
     * @return bool Sucesso da operação
     */
    public function updateRole(int $userId, string $role): bool
    {
        if (!in_array($role, ['admin', 'editor', 'viewer'])) {
            return false;
        }

        return $this->update($userId, ['role' => $role]);
    }

    /**
     * Verifica se usuário é admin
     * 
     * @param int $userId ID do usuário
     * @return bool True se é admin
     */
    public function isAdmin(int $userId): bool
    {
        $user = $this->findById($userId);
        return $user && $user['role'] === 'admin';
    }
}


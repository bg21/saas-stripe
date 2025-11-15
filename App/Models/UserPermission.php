<?php

namespace App\Models;

/**
 * Model para gerenciar permissões específicas de usuários
 */
class UserPermission extends BaseModel
{
    protected string $table = 'user_permissions';

    /**
     * Verifica se usuário tem permissão
     * 
     * @param int $userId ID do usuário
     * @param string $permission Nome da permissão
     * @return bool True se tem permissão, false caso contrário
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        // Primeiro verifica role do usuário
        $userModel = new User();
        $user = $userModel->findById($userId);
        
        if (!$user) {
            return false;
        }

        // Admins têm todas as permissões
        if ($user['role'] === 'admin') {
            return true;
        }

        // Verifica permissão específica
        $stmt = $this->db->prepare(
            "SELECT granted FROM {$this->table} 
             WHERE user_id = :user_id AND permission = :permission"
        );
        $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
        $result = $stmt->fetch();

        // Se não tem permissão específica, verifica role padrão
        if (!$result) {
            return $this->checkRolePermission($user['role'], $permission);
        }

        return $result['granted'] === 1 || $result['granted'] === true;
    }

    /**
     * Verifica permissão baseada na role padrão
     * 
     * @param string $role Role do usuário
     * @param string $permission Nome da permissão
     * @return bool True se a role permite, false caso contrário
     */
    private function checkRolePermission(string $role, string $permission): bool
    {
        // Permissões padrão por role
        $rolePermissions = [
            'admin' => [
                // Admin tem todas as permissões
                'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
                'cancel_subscriptions', 'reactivate_subscriptions',
                'view_customers', 'create_customers', 'update_customers',
                'view_audit_logs', 'manage_users', 'manage_permissions'
            ],
            'editor' => [
                'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
                'view_customers', 'create_customers', 'update_customers'
            ],
            'viewer' => [
                'view_subscriptions', 'view_customers'
            ]
        ];

        if (!isset($rolePermissions[$role])) {
            return false;
        }

        return in_array($permission, $rolePermissions[$role]);
    }

    /**
     * Concede permissão a um usuário
     * 
     * @param int $userId ID do usuário
     * @param string $permission Nome da permissão
     * @return bool Sucesso da operação
     */
    public function grant(int $userId, string $permission): bool
    {
        // Verifica se já existe
        $existing = $this->findByUserAndPermission($userId, $permission);
        
        if ($existing) {
            // Atualiza
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET granted = :granted WHERE id = :id"
            );
            $stmt->execute(['granted' => true, 'id' => $existing['id']]);
            return $stmt->rowCount() > 0;
        }

        // Cria nova
        $this->insert([
            'user_id' => $userId,
            'permission' => $permission,
            'granted' => true
        ]);
        
        return true;
    }

    /**
     * Revoga permissão de um usuário
     * 
     * @param int $userId ID do usuário
     * @param string $permission Nome da permissão
     * @return bool Sucesso da operação
     */
    public function revoke(int $userId, string $permission): bool
    {
        $existing = $this->findByUserAndPermission($userId, $permission);
        
        if ($existing) {
            // Atualiza para negado
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET granted = :granted WHERE id = :id"
            );
            $stmt->execute(['granted' => false, 'id' => $existing['id']]);
            return $stmt->rowCount() > 0;
        }

        // Cria como negado
        $this->insert([
            'user_id' => $userId,
            'permission' => $permission,
            'granted' => false
        ]);
        
        return true;
    }

    /**
     * Busca permissão específica de um usuário
     * 
     * @param int $userId ID do usuário
     * @param string $permission Nome da permissão
     * @return array|null Dados da permissão ou null se não encontrada
     */
    public function findByUserAndPermission(int $userId, string $permission): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id AND permission = :permission"
        );
        $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Lista todas as permissões de um usuário
     * 
     * @param int $userId ID do usuário
     * @return array Lista de permissões
     */
    public function findByUser(int $userId): array
    {
        return $this->findAll(['user_id' => $userId]);
    }
}


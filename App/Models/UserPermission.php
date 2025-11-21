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
     * ✅ CORREÇÃO: Método estático centralizado para obter permissões por role
     * Facilita manutenção e permite reutilização
     * 
     * @return array Array associativo com permissões por role
     */
    private static function getRolePermissions(): array
    {
        return [
            'admin' => [
                // Admin tem todas as permissões
                'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
                'cancel_subscriptions', 'reactivate_subscriptions',
                'view_customers', 'create_customers', 'update_customers',
                'view_audit_logs', 'manage_users', 'manage_permissions',
                'view_disputes', 'manage_disputes',
                'view_balance_transactions',
                'view_charges', 'manage_charges',
                'view_reports',
                'view_payouts', 'manage_payouts'
            ],
            'editor' => [
                'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
                'view_customers', 'create_customers', 'update_customers',
                'view_disputes',
                'view_balance_transactions',
                'view_charges',
                'view_reports'
            ],
            'viewer' => [
                'view_subscriptions', 'view_customers',
                'view_charges'
            ]
        ];
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
        // ✅ CORREÇÃO: Usa método centralizado para obter permissões por role
        $rolePermissions = self::getRolePermissions();

        if (!isset($rolePermissions[$role])) {
            return false;
        }

        return in_array($permission, $rolePermissions[$role]);
    }

    /**
     * Concede permissão a um usuário
     * ✅ VALIDAÇÃO: Valida se user_id existe antes de conceder
     * 
     * @param int $userId ID do usuário
     * @param string $permission Nome da permissão
     * @return bool Sucesso da operação
     */
    public function grant(int $userId, string $permission): bool
    {
        // ✅ Validação de relacionamento: verifica se user existe
        $userModel = new User();
        $user = $userModel->findById($userId);
        if (!$user) {
            throw new \RuntimeException("Usuário com ID {$userId} não encontrado");
        }
        
        try {
            // ✅ CORREÇÃO: Verifica se já existe antes de inserir (simplifica lógica)
            $existing = $this->findByUserAndPermission($userId, $permission);
            
            if ($existing) {
                // Atualiza para concedido
                return $this->update($existing['id'], ['granted' => 1]);
            }

            // Cria nova permissão
            $id = $this->insert([
                'user_id' => $userId,
                'permission' => $permission,
                'granted' => 1
            ]);
            
            return $id > 0;
        } catch (\PDOException $e) {
            // ✅ CORREÇÃO: Tratamento simplificado de constraint única
            // Se for erro de constraint única (race condition), tenta atualizar
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                \App\Services\Logger::warning("Permissão já existe (race condition), atualizando", [
                    'user_id' => $userId,
                    'permission' => $permission
                ]);
                
                // Tenta atualizar
                $stmt = $this->db->prepare(
                    "UPDATE {$this->table} SET granted = 1 WHERE user_id = :user_id AND permission = :permission"
                );
                $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
                return $stmt->rowCount() > 0;
            }
            
            // Re-lança exceção se não for constraint única
            \App\Services\Logger::error("Erro ao conceder permissão", [
                'user_id' => $userId,
                'permission' => $permission,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            \App\Services\Logger::error("Exceção ao conceder permissão", [
                'user_id' => $userId,
                'permission' => $permission,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
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
        // ✅ CORREÇÃO: Verifica se existe antes de revogar
        $existing = $this->findByUserAndPermission($userId, $permission);
        
        if ($existing) {
            // Atualiza para negado
            return $this->update($existing['id'], ['granted' => 0]);
        }

        // ✅ CORREÇÃO: Se não existe, não cria registro - apenas retorna true
        // Não faz sentido criar registro negado se a permissão nunca foi concedida
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


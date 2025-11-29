<?php

namespace App\Middleware;

use App\Models\UserPermission;
use App\Services\Logger;
use Flight;

/**
 * Middleware de verificação de permissões
 * 
 * Verifica se o usuário autenticado tem permissão para executar uma ação
 */
class PermissionMiddleware
{
    private UserPermission $permissionModel;

    public function __construct()
    {
        $this->permissionModel = new UserPermission();
    }

    /**
     * Verifica se usuário tem permissão
     * 
     * @param string $permission Nome da permissão
     * @return bool True se tem permissão, false caso contrário
     */
    public function check(string $permission): bool
    {
        try {
            $userId = Flight::get('user_id');

            if (!$userId) {
                Logger::warning("Tentativa de verificar permissão sem usuário autenticado", [
                    'permission' => $permission
                ]);
                return false;
            }

            $hasPermission = $this->permissionModel->hasPermission($userId, $permission);

            if (!$hasPermission) {
                Logger::info("Acesso negado por falta de permissão", [
                    'user_id' => $userId,
                    'permission' => $permission
                ]);
            }

            return $hasPermission;
        } catch (\Exception $e) {
            Logger::error("Erro ao verificar permissão", [
                'permission' => $permission,
                'user_id' => Flight::get('user_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Middleware que bloqueia se não tiver permissão
     * 
     * @param string $permission Nome da permissão
     * @return bool True se tem permissão, false se bloqueou
     */
    public function require(string $permission): bool
    {
        if (!$this->check($permission)) {
            http_response_code(403);
            Flight::json([
                'error' => 'Acesso negado',
                'message' => "Você não tem permissão para: {$permission}"
            ], 403);
            Flight::stop();
            return false;
        }

        return true;
    }

    /**
     * Verifica múltiplas permissões (OR - precisa ter pelo menos uma)
     * 
     * @param array $permissions Lista de permissões
     * @return bool True se tem pelo menos uma permissão
     */
    public function checkAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->check($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica múltiplas permissões (AND - precisa ter todas)
     * 
     * @param array $permissions Lista de permissões
     * @return bool True se tem todas as permissões
     */
    public function checkAll(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->check($permission)) {
                return false;
            }
        }

        return true;
    }
}


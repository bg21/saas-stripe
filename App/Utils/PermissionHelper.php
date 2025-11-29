<?php

namespace App\Utils;

use App\Middleware\PermissionMiddleware;
use App\Services\Logger;
use Flight;

/**
 * Helper para verificação de permissões
 * 
 * Este helper verifica permissões apenas para autenticação de usuários (Session ID).
 * Autenticação via API Key (tenant) não requer verificação de permissões.
 * 
 * Uso:
 *   PermissionHelper::require('view_subscriptions');
 * 
 * Se for API Key, não verifica permissões e continua normalmente.
 * Se for Session ID (usuário), verifica permissões e bloqueia se necessário.
 */
class PermissionHelper
{
    /**
     * Verifica se deve verificar permissões
     * 
     * Permissões só devem ser verificadas quando:
     * - Autenticação é via Session ID (usuário logado)
     * - is_user_auth === true
     * 
     * Permissões NÃO devem ser verificadas quando:
     * - Autenticação é via API Key (tenant)
     * - is_master === true (master key)
     * 
     * @return bool True se deve verificar permissões, false caso contrário
     */
    private static function shouldCheckPermissions(): bool
    {
        $isUserAuth = Flight::get('is_user_auth');
        $isMaster = Flight::get('is_master');
        
        // Master key não precisa de verificação de permissões
        if ($isMaster === true) {
            return false;
        }
        
        // Apenas verifica permissões se for autenticação de usuário (Session ID)
        return $isUserAuth === true;
    }

    /**
     * Exige permissão (bloqueia se não tiver)
     * 
     * Se for autenticação via API Key, não verifica permissões e continua normalmente.
     * Se for autenticação via Session ID, verifica permissões e bloqueia se necessário.
     * 
     * @param string $permission Nome da permissão (ex: 'view_subscriptions')
     * @return void Retorna void se tiver permissão, ou bloqueia a requisição (403)
     */
    public static function require(string $permission): void
    {
        try {
            // Se não é autenticação de usuário, não verifica permissões
            if (!self::shouldCheckPermissions()) {
                Logger::debug("Permissões não verificadas (API Key ou Master Key)", [
                    'permission' => $permission,
                    'is_user_auth' => Flight::get('is_user_auth'),
                    'is_master' => Flight::get('is_master')
                ]);
                return;
            }

            // Verifica se é admin (admins têm todas as permissões)
            $userRole = Flight::get('user_role');
            if ($userRole === 'admin') {
                Logger::debug("Permissão concedida automaticamente para admin", [
                    'permission' => $permission,
                    'user_role' => $userRole
                ]);
                return;
            }

            // Verifica permissão usando o middleware
            $middleware = new PermissionMiddleware();
            $hasPermission = $middleware->check($permission);
            
            // Se não tem permissão, bloqueia a requisição
            if (!$hasPermission) {
                $userId = Flight::get('user_id');
                $userRole = Flight::get('user_role');
                
                Logger::warning("Acesso negado por falta de permissão", [
                    'user_id' => $userId,
                    'user_role' => $userRole,
                    'permission' => $permission,
                    'request_uri' => Flight::request()->url ?? 'N/A'
                ]);
                
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => "Você não tem permissão para realizar esta ação: {$permission}"
                ]));
                return;
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao verificar permissão no PermissionHelper", [
                'permission' => $permission,
                'user_id' => Flight::get('user_id'),
                'user_role' => Flight::get('user_role'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Em caso de erro, bloqueia por segurança
            Flight::halt(500, json_encode([
                'error' => 'Erro interno do servidor',
                'message' => 'Erro ao verificar permissões'
            ]));
        }
    }

    /**
     * Verifica se tem permissão (retorna bool)
     * 
     * Se for autenticação via API Key, sempre retorna true.
     * Se for autenticação via Session ID, verifica permissões e retorna bool.
     * 
     * @param string $permission Nome da permissão (ex: 'view_subscriptions')
     * @return bool True se tem permissão ou se não precisa verificar, false caso contrário
     */
    public static function check(string $permission): bool
    {
        // Se não é autenticação de usuário, sempre retorna true
        if (!self::shouldCheckPermissions()) {
            Logger::debug("Permissões não verificadas (API Key ou Master Key)", [
                'permission' => $permission,
                'is_user_auth' => Flight::get('is_user_auth'),
                'is_master' => Flight::get('is_master')
            ]);
            return true;
        }

        // Verifica permissão usando o middleware
        $middleware = new PermissionMiddleware();
        return $middleware->check($permission);
    }

    /**
     * Verifica múltiplas permissões (OR - precisa ter pelo menos uma)
     * 
     * @param array $permissions Lista de permissões
     * @return bool True se tem pelo menos uma permissão ou se não precisa verificar
     */
    public static function checkAny(array $permissions): bool
    {
        // Se não é autenticação de usuário, sempre retorna true
        if (!self::shouldCheckPermissions()) {
            return true;
        }

        $middleware = new PermissionMiddleware();
        return $middleware->checkAny($permissions);
    }

    /**
     * Verifica múltiplas permissões (AND - precisa ter todas)
     * 
     * @param array $permissions Lista de permissões
     * @return bool True se tem todas as permissões ou se não precisa verificar
     */
    public static function checkAll(array $permissions): bool
    {
        // Se não é autenticação de usuário, sempre retorna true
        if (!self::shouldCheckPermissions()) {
            return true;
        }

        $middleware = new PermissionMiddleware();
        return $middleware->checkAll($permissions);
    }

    /**
     * Verifica se é autenticação de usuário (Session ID)
     * 
     * @return bool True se é autenticação de usuário, false caso contrário
     */
    public static function isUserAuth(): bool
    {
        return Flight::get('is_user_auth') === true;
    }

    /**
     * Verifica se é autenticação via API Key (tenant)
     * 
     * @return bool True se é autenticação via API Key, false caso contrário
     */
    public static function isApiKeyAuth(): bool
    {
        return Flight::get('is_user_auth') === false && Flight::get('is_master') === false;
    }

    /**
     * Verifica se é master key
     * 
     * @return bool True se é master key, false caso contrário
     */
    public static function isMasterKey(): bool
    {
        return Flight::get('is_master') === true;
    }
}


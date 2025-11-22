<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar permissões de usuários
 */
class PermissionController
{
    private User $userModel;
    private UserPermission $permissionModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->permissionModel = new UserPermission();
    }
    
    /**
     * ✅ CORREÇÃO: Método centralizado para obter lista completa de permissões válidas
     * Garante que grant() e revoke() sempre usem a mesma lista
     * 
     * @return array Lista de permissões válidas
     */
    private static function getValidPermissions(): array
    {
        return [
            // Assinaturas
            'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
            'cancel_subscriptions', 'reactivate_subscriptions',
            // Clientes
            'view_customers', 'create_customers', 'update_customers',
            // Auditoria
            'view_audit_logs',
            // Disputas
            'view_disputes', 'manage_disputes',
            // Transações de Saldo
            'view_balance_transactions',
            // Cobranças
            'view_charges', 'manage_charges',
            // Relatórios
            'view_reports',
            // Payouts
            'view_payouts', 'manage_payouts',
            // Administrativas
            'manage_users', 'manage_permissions',
            // Clínica Veterinária - Profissionais
            'view_professionals', 'create_professionals', 'update_professionals', 'delete_professionals',
            // Clínica Veterinária - Clientes
            'view_clients', 'create_clients', 'update_clients', 'delete_clients',
            // Clínica Veterinária - Pets
            'view_pets', 'create_pets', 'update_pets', 'delete_pets',
            // Clínica Veterinária - Agendamentos
            'view_appointments', 'create_appointments', 'update_appointments', 'delete_appointments',
            'confirm_appointments', 'cancel_appointments',
            // Clínica Veterinária - Agenda
            'view_schedules', 'manage_schedules',
            // Clínica Veterinária - Especialidades
            'view_specialties', 'create_specialties', 'update_specialties', 'delete_specialties',
            // Clínica Veterinária - Configurações
            'manage_clinic_settings'
        ];
    }

    /**
     * Lista todas as permissões disponíveis no sistema
     * GET /v1/permissions
     */
    public function listAvailable(): void
    {
        try {
            // Endpoints de permissões requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'list_available_permissions']);
                return;
            }
            
            // Verifica permissão (apenas admin pode ver permissões)
            PermissionHelper::require('manage_permissions');
            
            // Lista todas as permissões disponíveis no sistema
            $permissions = [
                // Permissões de Assinaturas
                'view_subscriptions' => [
                    'name' => 'view_subscriptions',
                    'description' => 'Visualizar assinaturas',
                    'category' => 'subscriptions'
                ],
                'create_subscriptions' => [
                    'name' => 'create_subscriptions',
                    'description' => 'Criar assinaturas',
                    'category' => 'subscriptions'
                ],
                'update_subscriptions' => [
                    'name' => 'update_subscriptions',
                    'description' => 'Atualizar assinaturas',
                    'category' => 'subscriptions'
                ],
                'cancel_subscriptions' => [
                    'name' => 'cancel_subscriptions',
                    'description' => 'Cancelar assinaturas',
                    'category' => 'subscriptions'
                ],
                'reactivate_subscriptions' => [
                    'name' => 'reactivate_subscriptions',
                    'description' => 'Reativar assinaturas',
                    'category' => 'subscriptions'
                ],
                
                // Permissões de Clientes
                'view_customers' => [
                    'name' => 'view_customers',
                    'description' => 'Visualizar clientes',
                    'category' => 'customers'
                ],
                'create_customers' => [
                    'name' => 'create_customers',
                    'description' => 'Criar clientes',
                    'category' => 'customers'
                ],
                'update_customers' => [
                    'name' => 'update_customers',
                    'description' => 'Atualizar clientes',
                    'category' => 'customers'
                ],
                
                // Permissões de Auditoria
                'view_audit_logs' => [
                    'name' => 'view_audit_logs',
                    'description' => 'Visualizar logs de auditoria',
                    'category' => 'audit'
                ],
                
                // Permissões de Disputas
                'view_disputes' => [
                    'name' => 'view_disputes',
                    'description' => 'Visualizar disputas/chargebacks',
                    'category' => 'disputes'
                ],
                'manage_disputes' => [
                    'name' => 'manage_disputes',
                    'description' => 'Gerenciar disputas (adicionar evidências)',
                    'category' => 'disputes'
                ],
                
                // Permissões de Balance Transactions
                'view_balance_transactions' => [
                    'name' => 'view_balance_transactions',
                    'description' => 'Visualizar transações de saldo',
                    'category' => 'finance'
                ],
                
                // Permissões de Charges
                'view_charges' => [
                    'name' => 'view_charges',
                    'description' => 'Visualizar cobranças',
                    'category' => 'finance'
                ],
                'manage_charges' => [
                    'name' => 'manage_charges',
                    'description' => 'Gerenciar cobranças (atualizar metadata)',
                    'category' => 'finance'
                ],
                
                // Permissões de Relatórios e Analytics
                'view_reports' => [
                    'name' => 'view_reports',
                    'description' => 'Visualizar relatórios e analytics',
                    'category' => 'reports'
                ],
                
                // Permissões de Payouts
                'view_payouts' => [
                    'name' => 'view_payouts',
                    'description' => 'Visualizar saques/payouts',
                    'category' => 'finance'
                ],
                'manage_payouts' => [
                    'name' => 'manage_payouts',
                    'description' => 'Gerenciar saques/payouts (criar, cancelar)',
                    'category' => 'finance'
                ],
                
                // Permissões Administrativas
                'manage_users' => [
                    'name' => 'manage_users',
                    'description' => 'Gerenciar usuários',
                    'category' => 'admin'
                ],
                'manage_permissions' => [
                    'name' => 'manage_permissions',
                    'description' => 'Gerenciar permissões',
                    'category' => 'admin'
                ],
                
                // Permissões de Clínica Veterinária - Profissionais
                'view_professionals' => [
                    'name' => 'view_professionals',
                    'description' => 'Visualizar profissionais',
                    'category' => 'veterinary'
                ],
                'create_professionals' => [
                    'name' => 'create_professionals',
                    'description' => 'Criar profissionais',
                    'category' => 'veterinary'
                ],
                'update_professionals' => [
                    'name' => 'update_professionals',
                    'description' => 'Atualizar profissionais',
                    'category' => 'veterinary'
                ],
                'delete_professionals' => [
                    'name' => 'delete_professionals',
                    'description' => 'Deletar profissionais',
                    'category' => 'veterinary'
                ],
                
                // Permissões de Clínica Veterinária - Clientes
                'view_clients' => [
                    'name' => 'view_clients',
                    'description' => 'Visualizar clientes',
                    'category' => 'veterinary'
                ],
                'create_clients' => [
                    'name' => 'create_clients',
                    'description' => 'Criar clientes',
                    'category' => 'veterinary'
                ],
                'update_clients' => [
                    'name' => 'update_clients',
                    'description' => 'Atualizar clientes',
                    'category' => 'veterinary'
                ],
                'delete_clients' => [
                    'name' => 'delete_clients',
                    'description' => 'Deletar clientes',
                    'category' => 'veterinary'
                ],
                
                // Permissões de Clínica Veterinária - Pets
                'view_pets' => [
                    'name' => 'view_pets',
                    'description' => 'Visualizar pets',
                    'category' => 'veterinary'
                ],
                'create_pets' => [
                    'name' => 'create_pets',
                    'description' => 'Criar pets',
                    'category' => 'veterinary'
                ],
                'update_pets' => [
                    'name' => 'update_pets',
                    'description' => 'Atualizar pets',
                    'category' => 'veterinary'
                ],
                'delete_pets' => [
                    'name' => 'delete_pets',
                    'description' => 'Deletar pets',
                    'category' => 'veterinary'
                ],
                
                // Permissões de Clínica Veterinária - Agendamentos
                'view_appointments' => [
                    'name' => 'view_appointments',
                    'description' => 'Visualizar agendamentos',
                    'category' => 'veterinary'
                ],
                'create_appointments' => [
                    'name' => 'create_appointments',
                    'description' => 'Criar agendamentos',
                    'category' => 'veterinary'
                ],
                'update_appointments' => [
                    'name' => 'update_appointments',
                    'description' => 'Atualizar agendamentos',
                    'category' => 'veterinary'
                ],
                'delete_appointments' => [
                    'name' => 'delete_appointments',
                    'description' => 'Deletar agendamentos',
                    'category' => 'veterinary'
                ],
                'confirm_appointments' => [
                    'name' => 'confirm_appointments',
                    'description' => 'Confirmar agendamentos',
                    'category' => 'veterinary'
                ],
                'cancel_appointments' => [
                    'name' => 'cancel_appointments',
                    'description' => 'Cancelar agendamentos',
                    'category' => 'veterinary'
                ],
                
                // Permissões de Clínica Veterinária - Agenda
                'view_schedules' => [
                    'name' => 'view_schedules',
                    'description' => 'Visualizar agendas',
                    'category' => 'veterinary'
                ],
                'manage_schedules' => [
                    'name' => 'manage_schedules',
                    'description' => 'Gerenciar agendas (criar/editar bloqueios)',
                    'category' => 'veterinary'
                ],
                
                // Permissões de Clínica Veterinária - Especialidades
                'view_specialties' => [
                    'name' => 'view_specialties',
                    'description' => 'Visualizar especialidades',
                    'category' => 'veterinary'
                ],
                'create_specialties' => [
                    'name' => 'create_specialties',
                    'description' => 'Criar especialidades',
                    'category' => 'veterinary'
                ],
                'update_specialties' => [
                    'name' => 'update_specialties',
                    'description' => 'Atualizar especialidades',
                    'category' => 'veterinary'
                ],
                'delete_specialties' => [
                    'name' => 'delete_specialties',
                    'description' => 'Deletar especialidades',
                    'category' => 'veterinary'
                ],
                
                // Permissões de Clínica Veterinária - Configurações
                'manage_clinic_settings' => [
                    'name' => 'manage_clinic_settings',
                    'description' => 'Gerenciar configurações da clínica',
                    'category' => 'veterinary'
                ]
            ];

            ResponseHelper::sendSuccess([
                'permissions' => array_values($permissions),
                'count' => count($permissions),
                'categories' => [
                    'subscriptions' => 'Permissões de Assinaturas',
                    'customers' => 'Permissões de Clientes',
                    'audit' => 'Permissões de Auditoria',
                    'disputes' => 'Permissões de Disputas',
                    'finance' => 'Permissões Financeiras',
                    'reports' => 'Permissões de Relatórios',
                    'admin' => 'Permissões Administrativas',
                    'veterinary' => 'Permissões de Clínica Veterinária'
                ]
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar permissões disponíveis',
                'PERMISSION_LIST_ERROR',
                ['action' => 'list_available_permissions']
            );
        }
    }

    /**
     * Lista permissões de um usuário específico
     * GET /v1/users/:id/permissions
     */
    public function listUserPermissions(string $id): void
    {
        try {
            // Endpoints de permissões requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'list_available_permissions']);
                return;
            }
            
            // Verifica permissão (apenas admin pode ver permissões)
            PermissionHelper::require('manage_permissions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'list_user_permissions', 'user_id' => $id]);
                return;
            }

            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'list_user_permissions', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'list_user_permissions', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Busca permissões do usuário
            $permissions = $this->permissionModel->findByUser((int)$id);
            
            // ✅ CORREÇÃO: Filtra apenas permissões concedidas (granted = true)
            // Aceita diferentes formatos: 1, '1', true, 'true'
            $grantedPermissions = array_filter($permissions, function($perm) {
                $granted = $perm['granted'] ?? false;
                // Aceita: true, 1, '1', 'true'
                return $granted === true || $granted === 1 || $granted === '1' || $granted === 'true';
            });
            
            // Formata resposta
            $formattedPermissions = array_map(function($perm) {
                return [
                    'id' => $perm['id'],
                    'permission' => $perm['permission'],
                    'granted' => (bool)$perm['granted'],
                    'created_at' => $perm['created_at']
                ];
            }, $grantedPermissions);

            ResponseHelper::sendSuccess([
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ],
                'permissions' => $formattedPermissions,
                'count' => count($formattedPermissions)
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar permissões do usuário',
                'PERMISSION_LIST_USER_ERROR',
                ['action' => 'list_user_permissions', 'user_id' => $id]
            );
        }
    }

    /**
     * Concede permissão a um usuário
     * POST /v1/users/:id/permissions
     * 
     * Body JSON:
     * {
     *   "permission": "view_audit_logs"
     * }
     */
    public function grant(string $id): void
    {
        try {
            // Endpoints de permissões requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'list_available_permissions']);
                return;
            }
            
            // Verifica permissão (apenas admin pode gerenciar permissões)
            PermissionHelper::require('manage_permissions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'list_user_permissions', 'user_id' => $id]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'grant_permission', 'user_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            
            // Validações obrigatórias
            if (empty($data['permission'])) {
                ResponseHelper::sendValidationError(
                    'Campo permission é obrigatório',
                    ['permission' => 'Obrigatório'],
                    ['action' => 'grant_permission', 'user_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            $permission = $data['permission'];
            
            // ✅ CORREÇÃO: Usa método centralizado para obter lista de permissões válidas
            $validPermissions = self::getValidPermissions();
            
            if (!in_array($permission, $validPermissions)) {
                ResponseHelper::sendValidationError(
                    "A permissão '{$permission}' não é válida",
                    ['permission' => "Use GET /v1/permissions para ver todas as permissões disponíveis"],
                    ['action' => 'grant_permission', 'user_id' => $id, 'tenant_id' => $tenantId, 'invalid_permission' => $permission]
                );
                return;
            }
            
            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            Logger::info("Tentando conceder permissão", [
                'user_id' => $id,
                'permission' => $permission,
                'tenant_id' => $tenantId,
                'user_found' => !empty($user)
            ]);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'grant_permission', 'user_id' => $id, 'permission' => $permission, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'grant_permission', 'user_id' => $id, 'user_tenant_id' => $user['tenant_id'], 'request_tenant_id' => $tenantId, 'permission' => $permission]);
                return;
            }
            
            // ✅ CORREÇÃO: Admins têm todas as permissões por padrão
            // Não salvamos no banco, mas informamos claramente ao usuário
            if ($user['role'] === 'admin') {
                Logger::info("Tentativa de conceder permissão a admin (ignorada, admin tem todas)", [
                    'user_id' => $id,
                    'permission' => $permission,
                    'tenant_id' => $tenantId
                ]);
                ResponseHelper::sendSuccess([
                    'user_id' => $user['id'],
                    'user_email' => $user['email'],
                    'user_role' => $user['role'],
                    'permission' => $permission,
                    'granted' => true,
                    'warning' => true,
                    'skipped' => true,
                    'note' => 'Usuários com role "admin" já possuem todas as permissões automaticamente. A permissão não foi salva no banco de dados.'
                ], 200, 'Usuários admin já possuem todas as permissões automaticamente');
                return;
            }
            
            Logger::info("Chamando grant() para conceder permissão", [
                'user_id' => $id,
                'permission' => $permission,
                'user_role' => $user['role']
            ]);
            
            // Concede permissão
            $success = $this->permissionModel->grant((int)$id, $permission);
            
            Logger::info("Resultado do grant()", [
                'user_id' => $id,
                'permission' => $permission,
                'success' => $success
            ]);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \Exception('Não foi possível conceder a permissão'),
                    'Erro ao conceder permissão',
                    'PERMISSION_GRANT_ERROR',
                    ['action' => 'grant_permission', 'user_id' => $id, 'permission' => $permission, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Busca permissão concedida para confirmar
            $grantedPermission = $this->permissionModel->findByUserAndPermission((int)$id, $permission);
            
            if (!$grantedPermission) {
                ResponseHelper::sendGenericError(
                    new \Exception('Permissão não foi salva corretamente no banco de dados'),
                    'Erro ao conceder permissão',
                    'PERMISSION_GRANT_SAVE_ERROR',
                    ['action' => 'grant_permission', 'user_id' => $id, 'permission' => $permission, 'tenant_id' => $tenantId]
                );
                return;
            }

            ResponseHelper::sendCreated([
                'id' => $grantedPermission['id'],
                'user_id' => (int)$id,
                'permission' => $permission,
                'granted' => (bool)($grantedPermission['granted'] ?? true),
                'created_at' => $grantedPermission['created_at']
            ], 'Permissão concedida com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao conceder permissão',
                'PERMISSION_GRANT_ERROR',
                ['action' => 'grant_permission', 'user_id' => $id, 'permission' => $data['permission'] ?? null, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Revoga permissão de um usuário
     * DELETE /v1/users/:id/permissions/:permission
     */
    public function revoke(string $id, string $permission): void
    {
        try {
            // Endpoints de permissões requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'list_available_permissions']);
                return;
            }
            
            // Verifica permissão (apenas admin pode gerenciar permissões)
            PermissionHelper::require('manage_permissions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'list_user_permissions', 'user_id' => $id]);
                return;
            }

            // ✅ CORREÇÃO: Usa método centralizado para obter lista completa de permissões válidas
            $validPermissions = self::getValidPermissions();
            
            if (!in_array($permission, $validPermissions)) {
                ResponseHelper::sendValidationError(
                    "A permissão '{$permission}' não é válida",
                    ['permission' => "Use GET /v1/permissions para ver todas as permissões disponíveis"],
                    ['action' => 'revoke_permission', 'user_id' => $id, 'tenant_id' => $tenantId, 'invalid_permission' => $permission]
                );
                return;
            }
            
            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'revoke_permission', 'user_id' => $id, 'permission' => $permission, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'revoke_permission', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Admins têm todas as permissões por padrão
            // Não faz sentido revogar permissões de admin, mas podemos marcar como negado
            if ($user['role'] === 'admin') {
                ResponseHelper::sendSuccess([
                    'user_id' => $user['id'],
                    'permission' => $permission,
                    'granted' => false,
                    'warning' => true,
                    'note' => 'Admin possui todas as permissões por padrão. Permissão marcada como negada, mas admin ainda terá acesso.'
                ], 200, 'Admin possui todas as permissões por padrão');
                // Continua para marcar como negado no banco (para registro)
            }
            
            // Revoga permissão (marca como negado)
            $success = $this->permissionModel->revoke((int)$id, $permission);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \Exception('Não foi possível revogar a permissão'),
                    'Erro ao revogar permissão',
                    'PERMISSION_REVOKE_ERROR',
                    ['action' => 'revoke_permission', 'user_id' => $id, 'permission' => $permission, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Busca permissão revogada
            $revokedPermission = $this->permissionModel->findByUserAndPermission((int)$id, $permission);

            ResponseHelper::sendSuccess([
                'id' => $revokedPermission['id'] ?? null,
                'user_id' => (int)$id,
                'permission' => $permission,
                'granted' => false,
                'created_at' => $revokedPermission['created_at'] ?? null
            ], 200, 'Permissão revogada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao revogar permissão',
                'PERMISSION_REVOKE_ERROR',
                ['action' => 'revoke_permission', 'user_id' => $id, 'permission' => $permission, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


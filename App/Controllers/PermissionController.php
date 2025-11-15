<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use App\Services\Logger;
use App\Utils\PermissionHelper;
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
     * Lista todas as permissões disponíveis no sistema
     * GET /v1/permissions
     */
    public function listAvailable(): void
    {
        try {
            // Endpoints de permissões requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
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
                ]
            ];

            Flight::json([
                'success' => true,
                'data' => array_values($permissions),
                'count' => count($permissions),
                'categories' => [
                    'subscriptions' => 'Permissões de Assinaturas',
                    'customers' => 'Permissões de Clientes',
                    'audit' => 'Permissões de Auditoria',
                    'disputes' => 'Permissões de Disputas',
                    'finance' => 'Permissões Financeiras',
                    'admin' => 'Permissões Administrativas'
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar permissões disponíveis", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao listar permissões',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
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
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode ver permissões)
            PermissionHelper::require('manage_permissions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                Flight::halt(404, json_encode([
                    'error' => 'Usuário não encontrado',
                    'message' => 'O usuário especificado não existe'
                ]));
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este usuário'
                ]));
                return;
            }
            
            // Busca permissões do usuário
            $permissions = $this->permissionModel->findByUser((int)$id);
            
            // Formata resposta
            $formattedPermissions = array_map(function($perm) {
                return [
                    'id' => $perm['id'],
                    'permission' => $perm['permission'],
                    'granted' => (bool)$perm['granted'],
                    'created_at' => $perm['created_at']
                ];
            }, $permissions);

            Flight::json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                        'role' => $user['role']
                    ],
                    'permissions' => $formattedPermissions,
                    'count' => count($formattedPermissions)
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar permissões do usuário", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao listar permissões',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
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
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode gerenciar permissões)
            PermissionHelper::require('manage_permissions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Validações obrigatórias
            if (empty($data['permission'])) {
                Flight::halt(400, json_encode([
                    'error' => 'Dados inválidos',
                    'message' => 'Campo permission é obrigatório'
                ]));
                return;
            }
            
            $permission = $data['permission'];
            
            // Valida se a permissão é válida
            $validPermissions = [
                'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
                'cancel_subscriptions', 'reactivate_subscriptions',
                'view_customers', 'create_customers', 'update_customers',
                'view_audit_logs', 'manage_users', 'manage_permissions'
            ];
            
            if (!in_array($permission, $validPermissions)) {
                Flight::halt(400, json_encode([
                    'error' => 'Permissão inválida',
                    'message' => "A permissão '{$permission}' não é válida. Use GET /v1/permissions para ver todas as permissões disponíveis."
                ]));
                return;
            }
            
            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                Flight::halt(404, json_encode([
                    'error' => 'Usuário não encontrado',
                    'message' => 'O usuário especificado não existe'
                ]));
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este usuário'
                ]));
                return;
            }
            
            // Admins já têm todas as permissões por padrão
            if ($user['role'] === 'admin') {
                Flight::json([
                    'success' => true,
                    'message' => 'Admin já possui todas as permissões por padrão',
                    'data' => [
                        'user_id' => $user['id'],
                        'permission' => $permission,
                        'granted' => true,
                        'note' => 'Admins têm todas as permissões automaticamente'
                    ]
                ]);
                return;
            }
            
            // Concede permissão
            $success = $this->permissionModel->grant((int)$id, $permission);
            
            if (!$success) {
                Flight::halt(500, json_encode([
                    'error' => 'Erro ao conceder permissão',
                    'message' => 'Não foi possível conceder a permissão'
                ]));
                return;
            }
            
            // Busca permissão concedida
            $grantedPermission = $this->permissionModel->findByUserAndPermission((int)$id, $permission);
            
            Logger::info("Permissão concedida", [
                'user_id' => $id,
                'permission' => $permission,
                'tenant_id' => $tenantId
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Permissão concedida com sucesso',
                'data' => [
                    'id' => $grantedPermission['id'],
                    'user_id' => (int)$id,
                    'permission' => $permission,
                    'granted' => true,
                    'created_at' => $grantedPermission['created_at']
                ]
            ], 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao conceder permissão", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'permission' => $data['permission'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao conceder permissão',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
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
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode gerenciar permissões)
            PermissionHelper::require('manage_permissions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            // Valida se a permissão é válida
            $validPermissions = [
                'view_subscriptions', 'create_subscriptions', 'update_subscriptions',
                'cancel_subscriptions', 'reactivate_subscriptions',
                'view_customers', 'create_customers', 'update_customers',
                'view_audit_logs', 'manage_users', 'manage_permissions'
            ];
            
            if (!in_array($permission, $validPermissions)) {
                Flight::halt(400, json_encode([
                    'error' => 'Permissão inválida',
                    'message' => "A permissão '{$permission}' não é válida. Use GET /v1/permissions para ver todas as permissões disponíveis."
                ]));
                return;
            }
            
            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                Flight::halt(404, json_encode([
                    'error' => 'Usuário não encontrado',
                    'message' => 'O usuário especificado não existe'
                ]));
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este usuário'
                ]));
                return;
            }
            
            // Admins têm todas as permissões por padrão
            // Não faz sentido revogar permissões de admin, mas podemos marcar como negado
            if ($user['role'] === 'admin') {
                Flight::json([
                    'success' => true,
                    'message' => 'Admin possui todas as permissões por padrão. Permissão marcada como negada, mas admin ainda terá acesso.',
                    'data' => [
                        'user_id' => $user['id'],
                        'permission' => $permission,
                        'granted' => false,
                        'note' => 'Admins têm todas as permissões automaticamente, mas a permissão foi marcada como negada no banco'
                    ]
                ]);
                // Continua para marcar como negado no banco (para registro)
            }
            
            // Revoga permissão (marca como negado)
            $success = $this->permissionModel->revoke((int)$id, $permission);
            
            if (!$success) {
                Flight::halt(500, json_encode([
                    'error' => 'Erro ao revogar permissão',
                    'message' => 'Não foi possível revogar a permissão'
                ]));
                return;
            }
            
            // Busca permissão revogada
            $revokedPermission = $this->permissionModel->findByUserAndPermission((int)$id, $permission);
            
            Logger::info("Permissão revogada", [
                'user_id' => $id,
                'permission' => $permission,
                'tenant_id' => $tenantId
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Permissão revogada com sucesso',
                'data' => [
                    'id' => $revokedPermission['id'] ?? null,
                    'user_id' => (int)$id,
                    'permission' => $permission,
                    'granted' => false,
                    'created_at' => $revokedPermission['created_at'] ?? null
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao revogar permissão", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'permission' => $permission,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao revogar permissão',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }
}


<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\Validator;
use Flight;
use Config;

/**
 * Controller para gerenciar usuários
 */
class UserController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Lista usuários do tenant
     * GET /v1/users
     * 
     * Query params opcionais:
     *   - role: Filtrar por role (admin, editor, viewer)
     *   - status: Filtrar por status (active, inactive)
     */
    public function list(): void
    {
        try {
            // Endpoints de usuários requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode ver usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            $queryParams = Flight::request()->query;
            
            // Busca usuários do tenant
            $users = $this->userModel->findByTenant($tenantId);
            
            // Filtros opcionais
            if (isset($queryParams['role']) && !empty($queryParams['role'])) {
                $users = array_filter($users, function($user) use ($queryParams) {
                    return $user['role'] === $queryParams['role'];
                });
            }
            
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $users = array_filter($users, function($user) use ($queryParams) {
                    return $user['status'] === $queryParams['status'];
                });
            }
            
            // Remove senha do retorno
            $users = array_map(function($user) {
                unset($user['password_hash']);
                return $user;
            }, $users);
            
            // Reindexa array
            $users = array_values($users);

            Flight::json([
                'success' => true,
                'data' => $users,
                'count' => count($users)
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar usuários", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao listar usuários',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }

    /**
     * Obtém usuário específico
     * GET /v1/users/:id
     */
    public function get(string $id): void
    {
        try {
            // Endpoints de usuários requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode ver usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

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
            
            // Remove senha do retorno
            unset($user['password_hash']);

            Flight::json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter usuário", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao obter usuário',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }

    /**
     * Cria um novo usuário
     * POST /v1/users
     * 
     * Body JSON:
     * {
     *   "email": "user@example.com",
     *   "password": "senha123",
     *   "name": "Nome do Usuário",
     *   "role": "viewer"  // opcional, padrão: viewer
     * }
     */
    public function create(): void
    {
        try {
            // Endpoints de usuários requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode criar usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Flight::json(['error' => 'JSON inválido no corpo da requisição: ' . json_last_error_msg()], 400);
                    return;
                }
                $data = [];
            }
            
            // Validação usando Validator
            $errors = Validator::validateUserCreate($data);
            if (!empty($errors)) {
                Flight::halt(400, json_encode([
                    'error' => 'Dados inválidos',
                    'message' => 'Por favor, verifique os dados informados',
                    'errors' => $errors
                ]));
                return;
            }
            
            // Sanitiza após validação
            $role = $data['role'] ?? 'viewer';
            
            // Verifica se o email já existe no tenant
            $existingUser = $this->userModel->findByEmailAndTenant($data['email'], $tenantId);
            if ($existingUser) {
                Flight::halt(409, json_encode([
                    'error' => 'Usuário já existe',
                    'message' => 'Já existe um usuário com este email neste tenant'
                ]));
                return;
            }
            
            // Cria usuário
            $userId = $this->userModel->create(
                $tenantId,
                $data['email'],
                $data['password'],
                $data['name'] ?? null,
                $role
            );
            
            // Busca usuário criado
            $user = $this->userModel->findById($userId);
            
            // Remove senha do retorno
            unset($user['password_hash']);
            
            Logger::info("Usuário criado", [
                'user_id' => $userId,
                'email' => $data['email'],
                'role' => $role,
                'tenant_id' => $tenantId
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar usuário", [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao criar usuário',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }

    /**
     * Atualiza um usuário
     * PUT /v1/users/:id
     * 
     * Body JSON:
     * {
     *   "name": "Nome Atualizado",
     *   "email": "novoemail@example.com",  // opcional
     *   "password": "novasenha123",  // opcional
     *   "status": "active"  // opcional
     * }
     */
    public function update(string $id): void
    {
        try {
            // Endpoints de usuários requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode atualizar usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            $currentUserId = Flight::get('user_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Flight::json(['error' => 'JSON inválido no corpo da requisição: ' . json_last_error_msg()], 400);
                    return;
                }
                $data = [];
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
            
            // Prepara dados para atualização
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            
            if (isset($data['email'])) {
                // Valida formato de email
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    Flight::halt(400, json_encode([
                        'error' => 'Dados inválidos',
                        'message' => 'Email inválido'
                    ]));
                    return;
                }
                
                // Verifica se o email já existe em outro usuário do mesmo tenant
                $existingUser = $this->userModel->findByEmailAndTenant($data['email'], $tenantId);
                if ($existingUser && $existingUser['id'] != $id) {
                    Flight::halt(409, json_encode([
                        'error' => 'Email já existe',
                        'message' => 'Já existe um usuário com este email neste tenant'
                    ]));
                    return;
                }
                
                $updateData['email'] = $data['email'];
            }
            
            if (isset($data['password'])) {
                // Valida força da senha
                $passwordError = Validator::validatePasswordStrength($data['password']);
                if ($passwordError) {
                    Flight::halt(400, json_encode([
                        'error' => 'Dados inválidos',
                        'message' => $passwordError
                    ]));
                    return;
                }
                
                $updateData['password_hash'] = $this->userModel->hashPassword($data['password']);
            }
            
            if (isset($data['status'])) {
                if (!in_array($data['status'], ['active', 'inactive'])) {
                    Flight::halt(400, json_encode([
                        'error' => 'Dados inválidos',
                        'message' => 'Status inválido. Use: active ou inactive'
                    ]));
                    return;
                }
                $updateData['status'] = $data['status'];
            }
            
            // Verifica se há dados para atualizar
            if (empty($updateData)) {
                Flight::halt(400, json_encode([
                    'error' => 'Dados inválidos',
                    'message' => 'Nenhum campo válido para atualização fornecido'
                ]));
                return;
            }
            
            // Atualiza usuário
            $success = $this->userModel->update((int)$id, $updateData);
            
            if (!$success) {
                Flight::halt(500, json_encode([
                    'error' => 'Erro ao atualizar usuário',
                    'message' => 'Não foi possível atualizar o usuário'
                ]));
                return;
            }
            
            // Busca usuário atualizado
            $updatedUser = $this->userModel->findById((int)$id);
            
            // Remove senha do retorno
            unset($updatedUser['password_hash']);
            
            Logger::info("Usuário atualizado", [
                'user_id' => $id,
                'updated_fields' => array_keys($updateData),
                'tenant_id' => $tenantId
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso',
                'data' => $updatedUser
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar usuário", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao atualizar usuário',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }

    /**
     * Desativa um usuário (soft delete)
     * DELETE /v1/users/:id
     * 
     * Nota: Não remove o usuário do banco, apenas desativa (status = inactive)
     */
    public function delete(string $id): void
    {
        try {
            // Endpoints de usuários requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode desativar usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            $currentUserId = Flight::get('user_id');
            
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
            
            // Não permite que o usuário delete a si mesmo
            if ($currentUserId && (int)$currentUserId === (int)$id) {
                Flight::halt(400, json_encode([
                    'error' => 'Ação inválida',
                    'message' => 'Você não pode desativar sua própria conta'
                ]));
                return;
            }
            
            // Verifica se é o último admin
            if ($user['role'] === 'admin') {
                $allUsers = $this->userModel->findByTenant($tenantId);
                $admins = array_filter($allUsers, function($u) {
                    return $u['role'] === 'admin' && $u['status'] === 'active';
                });
                
                if (count($admins) <= 1) {
                    Flight::halt(400, json_encode([
                        'error' => 'Ação inválida',
                        'message' => 'Não é possível desativar o último admin do tenant'
                    ]));
                    return;
                }
            }
            
            // Desativa usuário (soft delete)
            $success = $this->userModel->update((int)$id, ['status' => 'inactive']);
            
            if (!$success) {
                Flight::halt(500, json_encode([
                    'error' => 'Erro ao desativar usuário',
                    'message' => 'Não foi possível desativar o usuário'
                ]));
                return;
            }
            
            Logger::info("Usuário desativado", [
                'user_id' => $id,
                'tenant_id' => $tenantId,
                'deactivated_by' => $currentUserId
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Usuário desativado com sucesso'
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao desativar usuário", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao desativar usuário',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }

    /**
     * Atualiza role de um usuário
     * PUT /v1/users/:id/role
     * 
     * Body JSON:
     * {
     *   "role": "editor"  // admin, editor ou viewer
     * }
     */
    public function updateRole(string $id): void
    {
        try {
            // Endpoints de usuários requerem autenticação de usuário (não API Key)
            if (!PermissionHelper::isUserAuth()) {
                Flight::halt(403, json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Este endpoint requer autenticação de usuário. API Key não é permitida.'
                ]));
                return;
            }
            
            // Verifica permissão (apenas admin pode atualizar roles)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            $currentUserId = Flight::get('user_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação inválido'
                ]));
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Flight::json(['error' => 'JSON inválido no corpo da requisição: ' . json_last_error_msg()], 400);
                    return;
                }
                $data = [];
            }
            
            // Valida role
            if (empty($data['role']) || !in_array($data['role'], ['admin', 'editor', 'viewer'])) {
                Flight::halt(400, json_encode([
                    'error' => 'Dados inválidos',
                    'message' => 'Role inválida. Use: admin, editor ou viewer'
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
            
            // Não permite que o usuário mude sua própria role de admin
            if ($currentUserId && (int)$currentUserId === (int)$id && $user['role'] === 'admin' && $data['role'] !== 'admin') {
                Flight::halt(400, json_encode([
                    'error' => 'Ação inválida',
                    'message' => 'Você não pode alterar sua própria role de admin'
                ]));
                return;
            }
            
            // Verifica se é o último admin e está tentando remover admin
            if ($user['role'] === 'admin' && $data['role'] !== 'admin') {
                $allUsers = $this->userModel->findByTenant($tenantId);
                $admins = array_filter($allUsers, function($u) {
                    return $u['role'] === 'admin' && $u['status'] === 'active';
                });
                
                if (count($admins) <= 1) {
                    Flight::halt(400, json_encode([
                        'error' => 'Ação inválida',
                        'message' => 'Não é possível remover o último admin do tenant'
                    ]));
                    return;
                }
            }
            
            // Atualiza role
            $success = $this->userModel->updateRole((int)$id, $data['role']);
            
            if (!$success) {
                Flight::halt(500, json_encode([
                    'error' => 'Erro ao atualizar role',
                    'message' => 'Não foi possível atualizar a role do usuário'
                ]));
                return;
            }
            
            // Busca usuário atualizado
            $updatedUser = $this->userModel->findById((int)$id);
            
            // Remove senha do retorno
            unset($updatedUser['password_hash']);
            
            Logger::info("Role de usuário atualizada", [
                'user_id' => $id,
                'old_role' => $user['role'],
                'new_role' => $data['role'],
                'tenant_id' => $tenantId
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Role atualizada com sucesso',
                'data' => $updatedUser
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar role", [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao atualizar role',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }
}


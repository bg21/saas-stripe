<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\Validator;
use App\Utils\ResponseHelper;
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
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'list_users']);
                return;
            }
            
            // Verifica permissão (apenas admin pode ver usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'list_users']);
                return;
            }

            $queryParams = Flight::request()->query;
            
            // Busca usuários do tenant
            $users = $this->userModel->findByTenant($tenantId);
            
            // Filtro de busca (nome ou email)
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $search = strtolower(trim($queryParams['search']));
                $users = array_filter($users, function($user) use ($search) {
                    $name = strtolower($user['name'] ?? '');
                    $email = strtolower($user['email'] ?? '');
                    return strpos($name, $search) !== false || strpos($email, $search) !== false;
                });
            }
            
            // Filtros opcionais
            if (isset($queryParams['role']) && !empty($queryParams['role'])) {
                $users = array_filter($users, function($user) use ($queryParams) {
                    return $user['role'] === $queryParams['role'];
                });
            }
            
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $users = array_filter($users, function($user) use ($queryParams) {
                    return ($user['status'] ?? 'active') === $queryParams['status'];
                });
            }
            
            // Ordenação
            $sortBy = $queryParams['sort'] ?? 'created_at';
            usort($users, function($a, $b) use ($sortBy) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
                
                if ($sortBy === 'created_at') {
                    return strtotime($bVal) - strtotime($aVal); // Mais recente primeiro
                }
                
                return strcmp($aVal, $bVal);
            });
            
            // Remove senha do retorno
            $users = array_map(function($user) {
                unset($user['password_hash']);
                return $user;
            }, $users);
            
            // Reindexa array
            $users = array_values($users);

            ResponseHelper::sendSuccess([
                'users' => $users,
                'count' => count($users)
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar usuários',
                'USER_LIST_ERROR',
                ['action' => 'list_users', 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'get_user', 'user_id' => $id]);
                return;
            }
            
            // Verifica permissão (apenas admin pode ver usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'get_user', 'user_id' => $id]);
                return;
            }

            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'get_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'get_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Remove senha do retorno
            unset($user['password_hash']);

            ResponseHelper::sendSuccess($user);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter usuário',
                'USER_GET_ERROR',
                ['action' => 'get_user', 'user_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'create_user']);
                return;
            }
            
            // Verifica permissão (apenas admin pode criar usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'create_user']);
                return;
            }
            
            // ✅ CORREÇÃO: Valida se o tenant existe e está ativo
            $tenantModel = new \App\Models\Tenant();
            $tenant = $tenantModel->findById($tenantId);
            if (!$tenant) {
                ResponseHelper::sendNotFoundError('Tenant', ['action' => 'create_user', 'tenant_id' => $tenantId]);
                return;
            }
            if ($tenant['status'] !== 'active') {
                ResponseHelper::sendForbiddenError('O tenant está inativo. Não é possível criar usuários.', ['action' => 'create_user', 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_user', 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            
            // Validação usando Validator
            $errors = Validator::validateUserCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_user', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Sanitiza após validação
            $role = $data['role'] ?? 'viewer';
            
            // Verifica se o email já existe no tenant
            $existingUser = $this->userModel->findByEmailAndTenant($data['email'], $tenantId);
            if ($existingUser) {
                ResponseHelper::sendError(
                    409,
                    'Usuário já existe',
                    'Já existe um usuário com este email neste tenant',
                    'USER_ALREADY_EXISTS',
                    [],
                    ['action' => 'create_user', 'tenant_id' => $tenantId, 'email' => $data['email']]
                );
                return;
            }
            
            // ✅ CORREÇÃO: Usa transação para prevenir race condition
            // Se outro processo criar o mesmo email entre a verificação e a inserção,
            // a constraint UNIQUE no banco ou a transação irá capturar o erro
            try {
                $this->userModel->db->beginTransaction();
                
                // Verifica novamente dentro da transação (double-check)
                $existingUserInTransaction = $this->userModel->findByEmailAndTenant($data['email'], $tenantId);
                if ($existingUserInTransaction) {
                    $this->userModel->db->rollBack();
                    ResponseHelper::sendError(
                        409,
                        'Usuário já existe',
                        'Já existe um usuário com este email neste tenant',
                        'USER_ALREADY_EXISTS',
                        [],
                        ['action' => 'create_user', 'tenant_id' => $tenantId, 'email' => $data['email']]
                    );
                    return;
                }
                
                // Cria usuário dentro da transação
                $userId = $this->userModel->create(
                    $tenantId,
                    $data['email'],
                    $data['password'],
                    $data['name'] ?? null,
                    $role
                );
                
                $this->userModel->db->commit();
            } catch (\PDOException $e) {
                // ✅ CORREÇÃO: Captura erro de constraint única (race condition)
                if ($this->userModel->db->inTransaction()) {
                    $this->userModel->db->rollBack();
                }
                
                // Verifica se é erro de constraint única (código 23000 = Integrity constraint violation)
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                    ResponseHelper::sendError(
                        409,
                        'Usuário já existe',
                        'Já existe um usuário com este email neste tenant',
                        'USER_ALREADY_EXISTS',
                        [],
                        ['action' => 'create_user', 'tenant_id' => $tenantId, 'email' => $data['email'] ?? null]
                    );
                    return;
                }
                
                // Re-lança exceção se não for constraint única
                throw $e;
            }
            
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

            ResponseHelper::sendCreated($user, 'Usuário criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar usuário',
                'USER_CREATE_ERROR',
                ['action' => 'create_user', 'tenant_id' => $tenantId ?? null, 'email' => $data['email'] ?? null]
            );
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
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'update_user']);
                return;
            }
            
            // Verifica permissão (apenas admin pode atualizar usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            $currentUserId = Flight::get('user_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'update_user']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            
            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Prepara dados para atualização
            $updateData = [];
            $errors = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            
            if (isset($data['email'])) {
                // Valida formato de email
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Email inválido';
                } else {
                    // Verifica se o email já existe em outro usuário do mesmo tenant
                    $existingUser = $this->userModel->findByEmailAndTenant($data['email'], $tenantId);
                    if ($existingUser && $existingUser['id'] != $id) {
                        ResponseHelper::sendError(409, 'Email já existe', 'Já existe um usuário com este email neste tenant', 'EMAIL_ALREADY_EXISTS', [], ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                        return;
                    }
                    $updateData['email'] = $data['email'];
                }
            }
            
            if (isset($data['password'])) {
                // ✅ CORREÇÃO: Usa validação completa de senha para update
                $passwordErrors = Validator::validatePasswordUpdate(['password' => $data['password']]);
                if (!empty($passwordErrors)) {
                    $errors = array_merge($errors, $passwordErrors);
                } else {
                    $updateData['password_hash'] = $this->userModel->hashPassword($data['password']);
                }
            }
            
            if (isset($data['status'])) {
                if (!in_array($data['status'], ['active', 'inactive'])) {
                    $errors['status'] = 'Status inválido. Use: active ou inactive';
                } else {
                    $updateData['status'] = $data['status'];
                }
            }
            
            // Se houver erros de validação, retorna
            if (!empty($errors)) {
                ResponseHelper::sendValidationError('Por favor, verifique os dados informados', $errors, ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se há dados para atualizar
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo válido para atualização fornecido', [], ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Atualiza usuário
            $success = $this->userModel->update((int)$id, $updateData);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \RuntimeException('Falha ao atualizar usuário no banco de dados'),
                    'Não foi possível atualizar o usuário',
                    'USER_UPDATE_DB_ERROR',
                    ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId]
                );
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

            ResponseHelper::sendSuccess($updatedUser, 200, 'Usuário atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar usuário',
                'USER_UPDATE_ERROR',
                ['action' => 'update_user', 'user_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'delete_user']);
                return;
            }
            
            // Verifica permissão (apenas admin pode desativar usuários)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            $currentUserId = Flight::get('user_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'delete_user']);
                return;
            }

            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'delete_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'delete_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Não permite que o usuário delete a si mesmo
            if ($currentUserId && (int)$currentUserId === (int)$id) {
                ResponseHelper::sendValidationError('Você não pode desativar sua própria conta', [], ['action' => 'delete_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se é o último admin
            if ($user['role'] === 'admin') {
                $allUsers = $this->userModel->findByTenant($tenantId);
                $admins = array_filter($allUsers, function($u) {
                    return $u['role'] === 'admin' && $u['status'] === 'active';
                });
                
                if (count($admins) <= 1) {
                    ResponseHelper::sendValidationError('Não é possível desativar o último admin do tenant', [], ['action' => 'delete_user', 'user_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
            }
            
            // Desativa usuário (soft delete)
            $success = $this->userModel->update((int)$id, ['status' => 'inactive']);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \RuntimeException('Falha ao desativar usuário no banco de dados'),
                    'Não foi possível desativar o usuário',
                    'USER_DELETE_DB_ERROR',
                    ['action' => 'delete_user', 'user_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            Logger::info("Usuário desativado", [
                'user_id' => $id,
                'tenant_id' => $tenantId,
                'deactivated_by' => $currentUserId
            ]);

            ResponseHelper::sendSuccess(null, 200, 'Usuário desativado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao desativar usuário',
                'USER_DELETE_ERROR',
                ['action' => 'delete_user', 'user_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendForbiddenError('Este endpoint requer autenticação de usuário. API Key não é permitida.', ['action' => 'update_user_role']);
                return;
            }
            
            // Verifica permissão (apenas admin pode atualizar roles)
            PermissionHelper::require('manage_users');
            
            $tenantId = Flight::get('tenant_id');
            $currentUserId = Flight::get('user_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Token de autenticação inválido', ['action' => 'update_user_role']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            
            // Valida role
            if (empty($data['role']) || !in_array($data['role'], ['admin', 'editor', 'viewer'])) {
                ResponseHelper::sendValidationError(
                    'Role inválida. Use: admin, editor ou viewer',
                    ['role' => 'Role inválida. Use: admin, editor ou viewer'],
                    ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Busca usuário
            $user = $this->userModel->findById((int)$id);
            
            if (!$user) {
                ResponseHelper::sendNotFoundError('Usuário', ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se o usuário pertence ao tenant
            if ($user['tenant_id'] != $tenantId) {
                ResponseHelper::sendForbiddenError('Você não tem permissão para acessar este usuário', ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Não permite que o usuário mude sua própria role de admin
            if ($currentUserId && (int)$currentUserId === (int)$id && $user['role'] === 'admin' && $data['role'] !== 'admin') {
                ResponseHelper::sendValidationError('Você não pode alterar sua própria role de admin', [], ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se é o último admin e está tentando remover admin
            if ($user['role'] === 'admin' && $data['role'] !== 'admin') {
                $allUsers = $this->userModel->findByTenant($tenantId);
                $admins = array_filter($allUsers, function($u) {
                    return $u['role'] === 'admin' && $u['status'] === 'active';
                });
                
                if (count($admins) <= 1) {
                    ResponseHelper::sendValidationError('Não é possível remover o último admin do tenant', [], ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
            }
            
            // Atualiza role
            $success = $this->userModel->updateRole((int)$id, $data['role']);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \RuntimeException('Falha ao atualizar role no banco de dados'),
                    'Não foi possível atualizar a role do usuário',
                    'USER_ROLE_UPDATE_DB_ERROR',
                    ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId]
                );
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

            ResponseHelper::sendSuccess($updatedUser, 200, 'Role atualizada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar role',
                'USER_ROLE_UPDATE_ERROR',
                ['action' => 'update_user_role', 'user_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


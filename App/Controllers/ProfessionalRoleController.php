<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProfessionalRole;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Config;
use Flight;

/**
 * Controller para gerenciar roles de profissionais
 */
class ProfessionalRoleController
{
    private ProfessionalRole $roleModel;

    public function __construct(ProfessionalRole $roleModel)
    {
        $this->roleModel = $roleModel;
    }

    /**
     * Lista todas as roles do tenant
     * 
     * GET /v1/professional-roles
     */
    public function list(): void
    {
        try {
            // Tenta obter tenant_id do Flight primeiro (mais confiável)
            $tenantId = Flight::get('tenant_id');
            
            // Se não estiver no Flight, tenta da query string
            if (!$tenantId && isset(Flight::request()->query['tenant_id'])) {
                $tenantId = (int)Flight::request()->query['tenant_id'];
            }

            // Valida tenant_id
            if (!$tenantId || !is_numeric($tenantId) || $tenantId <= 0) {
                ResponseHelper::sendError('Tenant ID não fornecido ou inválido', 400, 'TENANT_ID_REQUIRED');
                return;
            }
            
            $tenantId = (int)$tenantId;

            // Verifica permissão
            if (!PermissionHelper::check('view_professional_roles')) {
                ResponseHelper::sendError('Permissão negada', 403, 'PERMISSION_DENIED', [
                    'permission' => 'view_professional_roles',
                    'user_id' => Flight::get('user_id'),
                    'user_role' => Flight::get('user_role')
                ]);
                return;
            }

            $filters = [];
            
            // Filtro por status (is_active)
            if (isset(Flight::request()->query['is_active'])) {
                $filters['is_active'] = Flight::request()->query['is_active'] === 'true' || Flight::request()->query['is_active'] === '1';
            }

            $roles = $this->roleModel->findByTenant($tenantId, $filters);
            
            // Log para debug (apenas em desenvolvimento)
            if (Config::isDevelopment()) {
                \App\Services\Logger::debug('ProfessionalRoles list', [
                    'tenant_id' => $tenantId,
                    'filters' => $filters,
                    'roles_count' => count($roles)
                ]);
            }

            // Decodifica JSON de permissions
            foreach ($roles as &$role) {
                if ($role['permissions']) {
                    $role['permissions'] = json_decode($role['permissions'], true);
                }
            }

            ResponseHelper::sendSuccess($roles, 200, 'Roles listadas com sucesso');
        } catch (\Exception $e) {
            \App\Services\Logger::error('Erro ao listar professional roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError($e, 'Erro ao listar roles', 'PROFESSIONAL_ROLES_LIST_ERROR', ['action' => 'list_roles']);
        }
    }

    /**
     * Obtém uma role específica
     * 
     * GET /v1/professional-roles/{id}
     */
    public function get(int $id): void
    {
        try {
            $tenantId = PermissionHelper::getTenantId();
            if (!$tenantId) {
                ResponseHelper::sendError('Tenant ID não fornecido', 400, 'TENANT_ID_REQUIRED');
                return;
            }

            // Verifica permissão
            if (!PermissionHelper::check('view_professional_roles')) {
                ResponseHelper::sendError('Permissão negada', 403, 'PERMISSION_DENIED');
                return;
            }

            $role = $this->roleModel->findByTenantAndId($tenantId, $id);
            if (!$role) {
                ResponseHelper::sendError('Role não encontrada', 404, 'ROLE_NOT_FOUND');
                return;
            }

            // Decodifica JSON de permissions
            if ($role['permissions']) {
                $role['permissions'] = json_decode($role['permissions'], true);
            }

            ResponseHelper::sendSuccess($role, 200, 'Role obtida com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter role', 'PROFESSIONAL_ROLE_GET_ERROR', ['action' => 'get_role', 'role_id' => $id]);
        }
    }

    /**
     * Cria uma nova role
     * 
     * POST /v1/professional-roles
     */
    public function create(): void
    {
        try {
            $tenantId = PermissionHelper::getTenantId();
            if (!$tenantId) {
                ResponseHelper::sendError('Tenant ID não fornecido', 400, 'TENANT_ID_REQUIRED');
                return;
            }

            // Verifica permissão
            if (!PermissionHelper::hasPermission('create_professional_roles')) {
                ResponseHelper::sendError('Permissão negada', 403, 'PERMISSION_DENIED');
                return;
            }

            $data = json_decode(Flight::request()->getBody(), true) ?? [];
            $data['tenant_id'] = $tenantId;

            // Validação básica
            if (empty($data['name'])) {
                ResponseHelper::sendError('Nome da role é obrigatório', 400, 'VALIDATION_ERROR', ['field' => 'name']);
                return;
            }

            $id = $this->roleModel->create($data);
            $role = $this->roleModel->findById($id);

            // Decodifica JSON de permissions
            if ($role['permissions']) {
                $role['permissions'] = json_decode($role['permissions'], true);
            }

            ResponseHelper::sendSuccess($role, 201, 'Role criada com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendError($e->getMessage(), 400, 'VALIDATION_ERROR');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar role', 'PROFESSIONAL_ROLE_CREATE_ERROR', ['action' => 'create_role']);
        }
    }

    /**
     * Atualiza uma role
     * 
     * PUT /v1/professional-roles/{id}
     */
    public function update(int $id): void
    {
        try {
            $tenantId = PermissionHelper::getTenantId();
            if (!$tenantId) {
                ResponseHelper::sendError('Tenant ID não fornecido', 400, 'TENANT_ID_REQUIRED');
                return;
            }

            // Verifica permissão
            if (!PermissionHelper::hasPermission('update_professional_roles')) {
                ResponseHelper::sendError('Permissão negada', 403, 'PERMISSION_DENIED');
                return;
            }

            // Verifica se a role existe e pertence ao tenant
            $role = $this->roleModel->findByTenantAndId($tenantId, $id);
            if (!$role) {
                ResponseHelper::sendError('Role não encontrada', 404, 'ROLE_NOT_FOUND');
                return;
            }

            $data = json_decode(Flight::request()->getBody(), true) ?? [];

            $this->roleModel->update($id, $data);
            $updatedRole = $this->roleModel->findById($id);

            // Decodifica JSON de permissions
            if ($updatedRole['permissions']) {
                $updatedRole['permissions'] = json_decode($updatedRole['permissions'], true);
            }

            ResponseHelper::sendSuccess($updatedRole, 200, 'Role atualizada com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendError($e->getMessage(), 400, 'VALIDATION_ERROR');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar role', 'PROFESSIONAL_ROLE_UPDATE_ERROR', ['action' => 'update_role', 'role_id' => $id]);
        }
    }

    /**
     * Deleta uma role (soft delete)
     * 
     * DELETE /v1/professional-roles/{id}
     */
    public function delete(int $id): void
    {
        try {
            $tenantId = PermissionHelper::getTenantId();
            if (!$tenantId) {
                ResponseHelper::sendError('Tenant ID não fornecido', 400, 'TENANT_ID_REQUIRED');
                return;
            }

            // Verifica permissão
            if (!PermissionHelper::hasPermission('delete_professional_roles')) {
                ResponseHelper::sendError('Permissão negada', 403, 'PERMISSION_DENIED');
                return;
            }

            // Verifica se a role existe e pertence ao tenant
            $role = $this->roleModel->findByTenantAndId($tenantId, $id);
            if (!$role) {
                ResponseHelper::sendError('Role não encontrada', 404, 'ROLE_NOT_FOUND');
                return;
            }

            // Verifica se há profissionais usando esta role
            $professionalModel = new \App\Models\Professional();
            $professionals = $professionalModel->findByTenant($tenantId, ['professional_role_id' => $id]);
            if (!empty($professionals)) {
                ResponseHelper::sendError('Não é possível deletar esta role pois há profissionais vinculados a ela', 400, 'ROLE_IN_USE');
                return;
            }

            $this->roleModel->delete($id);
            ResponseHelper::sendSuccess(null, 200, 'Role deletada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao deletar role', 'PROFESSIONAL_ROLE_DELETE_ERROR', ['action' => 'delete_role', 'role_id' => $id]);
        }
    }
}


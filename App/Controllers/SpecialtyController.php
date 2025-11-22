<?php

namespace App\Controllers;

use App\Models\Specialty;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar especialidades veterinárias
 */
class SpecialtyController
{
    private Specialty $specialtyModel;

    public function __construct(Specialty $specialtyModel)
    {
        $this->specialtyModel = $specialtyModel;
    }

    /**
     * Lista especialidades do tenant
     * GET /v1/specialties
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_specialties']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            $status = $queryParams['status'] ?? null;
            
            $filters = [];
            if ($status) {
                $filters['status'] = $status;
            }
            
            $specialties = $this->specialtyModel->findByTenant($tenantId, $filters);
            
            ResponseHelper::sendSuccess($specialties, 'Especialidades listadas com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar especialidades', 'SPECIALTIES_LIST_ERROR', ['action' => 'list_specialties', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Cria uma nova especialidade
     * POST /v1/specialties
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_specialty']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_specialty']);
                return;
            }
            
            // Validação básica
            if (empty($data['name'])) {
                ResponseHelper::sendValidationError('Nome da especialidade é obrigatório', ['name' => 'Obrigatório'], ['action' => 'create_specialty']);
                return;
            }
            
            $specialtyData = [
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active'
            ];
            
            $specialtyId = $this->specialtyModel->insert($specialtyData);
            $specialty = $this->specialtyModel->findById($specialtyId);
            
            ResponseHelper::sendCreated($specialty, 'Especialidade criada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar especialidade', 'SPECIALTY_CREATE_ERROR', ['action' => 'create_specialty', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém uma especialidade
     * GET /v1/specialties/:id
     */
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_specialty']);
                return;
            }
            
            $specialty = $this->specialtyModel->findByTenantAndId($tenantId, $id);
            
            if (!$specialty) {
                ResponseHelper::sendNotFoundError('Especialidade não encontrada', ['action' => 'get_specialty', 'specialty_id' => $id]);
                return;
            }
            
            ResponseHelper::sendSuccess($specialty, 'Especialidade obtida com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter especialidade', 'SPECIALTY_GET_ERROR', ['action' => 'get_specialty', 'specialty_id' => $id]);
        }
    }

    /**
     * Atualiza uma especialidade
     * PUT /v1/specialties/:id
     */
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_specialty']);
                return;
            }
            
            $specialty = $this->specialtyModel->findByTenantAndId($tenantId, $id);
            
            if (!$specialty) {
                ResponseHelper::sendNotFoundError('Especialidade não encontrada', ['action' => 'update_specialty', 'specialty_id' => $id]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_specialty']);
                return;
            }
            
            $allowedFields = ['name', 'description', 'status'];
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo válido para atualização', [], ['action' => 'update_specialty']);
                return;
            }
            
            $this->specialtyModel->update($id, $updateData);
            $updated = $this->specialtyModel->findById($id);
            
            ResponseHelper::sendSuccess($updated, 'Especialidade atualizada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar especialidade', 'SPECIALTY_UPDATE_ERROR', ['action' => 'update_specialty', 'specialty_id' => $id]);
        }
    }

    /**
     * Deleta uma especialidade
     * DELETE /v1/specialties/:id
     */
    public function delete(int $id): void
    {
        try {
            PermissionHelper::require('delete_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_specialty']);
                return;
            }
            
            $specialty = $this->specialtyModel->findByTenantAndId($tenantId, $id);
            
            if (!$specialty) {
                ResponseHelper::sendNotFoundError('Especialidade não encontrada', ['action' => 'delete_specialty', 'specialty_id' => $id]);
                return;
            }
            
            $this->specialtyModel->delete($id);
            
            ResponseHelper::sendSuccess(null, 'Especialidade deletada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao deletar especialidade', 'SPECIALTY_DELETE_ERROR', ['action' => 'delete_specialty', 'specialty_id' => $id]);
        }
    }
}


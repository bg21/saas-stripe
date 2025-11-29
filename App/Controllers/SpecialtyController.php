<?php

namespace App\Controllers;

use App\Models\Specialty;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Controller para gerenciar especialidades
 */
class SpecialtyController
{
    private Specialty $specialtyModel;

    public function __construct()
    {
        $this->specialtyModel = new Specialty();
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
            
            $filters = [];
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            
            // Busca especialidades do tenant
            $specialties = $this->specialtyModel->findByTenant($tenantId, $filters);
            
            // Aplica busca textual se fornecida
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $searchLower = strtolower(trim($queryParams['search']));
                $specialties = array_filter($specialties, function($spec) use ($searchLower) {
                    $name = strtolower($spec['name'] ?? '');
                    $description = strtolower($spec['description'] ?? '');
                    return strpos($name, $searchLower) !== false || 
                           strpos($description, $searchLower) !== false;
                });
                $specialties = array_values($specialties); // Reindexa array
            }
            
            // Ordenação
            $sortBy = $queryParams['sort'] ?? 'created_at';
            usort($specialties, function($a, $b) use ($sortBy) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
                
                if ($sortBy === 'created_at') {
                    return strtotime($bVal) - strtotime($aVal); // Mais recente primeiro
                }
                
                return strcmp($aVal, $bVal);
            });
            
            ResponseHelper::sendSuccess([
                'specialties' => $specialties,
                'count' => count($specialties)
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar especialidades',
                'SPECIALTIES_LIST_ERROR',
                ['action' => 'list_specialties', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém especialidade específica
     * GET /v1/specialties/:id
     */
    public function get(string $id): void
    {
        try {
            PermissionHelper::require('view_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_specialty', 'specialty_id' => $id]);
                return;
            }

            $specialty = $this->specialtyModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$specialty) {
                ResponseHelper::sendNotFoundError('Especialidade', ['action' => 'get_specialty', 'specialty_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            ResponseHelper::sendSuccess($specialty);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter especialidade',
                'SPECIALTY_GET_ERROR',
                ['action' => 'get_specialty', 'specialty_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
            
            // Validações
            $errors = [];
            
            if (empty($data['name'])) {
                $errors['name'] = 'Nome é obrigatório';
            } elseif (strlen($data['name']) > 100) {
                $errors['name'] = 'Nome deve ter no máximo 100 caracteres';
            }
            
            if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
                $errors['status'] = 'Status inválido. Use: active ou inactive';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'create_specialty']);
                return;
            }
            
            // Prepara dados para inserção
            $insertData = [
                'tenant_id' => $tenantId,
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active'
            ];
            
            $specialtyId = $this->specialtyModel->insert($insertData);
            
            // Busca especialidade criada
            $specialty = $this->specialtyModel->findById($specialtyId);
            
            ResponseHelper::sendSuccess($specialty, 201, 'Especialidade criada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar especialidade',
                'SPECIALTY_CREATE_ERROR',
                ['action' => 'create_specialty', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza especialidade
     * PUT /v1/specialties/:id
     */
    public function update(string $id): void
    {
        try {
            PermissionHelper::require('update_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_specialty', 'specialty_id' => $id]);
                return;
            }
            
            $specialty = $this->specialtyModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$specialty) {
                ResponseHelper::sendNotFoundError('Especialidade', ['action' => 'update_specialty', 'specialty_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_specialty', 'specialty_id' => $id]);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (isset($data['name']) && empty($data['name'])) {
                $errors['name'] = 'Nome é obrigatório';
            } elseif (isset($data['name']) && strlen($data['name']) > 100) {
                $errors['name'] = 'Nome deve ter no máximo 100 caracteres';
            }
            
            if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
                $errors['status'] = 'Status inválido. Use: active ou inactive';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'update_specialty', 'specialty_id' => $id]);
                return;
            }
            
            // Prepara dados para atualização
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendError('Nenhum campo para atualizar', 400, 'NO_FIELDS_TO_UPDATE', ['action' => 'update_specialty', 'specialty_id' => $id]);
                return;
            }
            
            $this->specialtyModel->update((int)$id, $updateData);
            
            // Busca especialidade atualizada
            $updated = $this->specialtyModel->findById((int)$id);
            
            ResponseHelper::sendSuccess($updated, 200, 'Especialidade atualizada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar especialidade',
                'SPECIALTY_UPDATE_ERROR',
                ['action' => 'update_specialty', 'specialty_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Deleta especialidade (soft delete)
     * DELETE /v1/specialties/:id
     */
    public function delete(string $id): void
    {
        try {
            PermissionHelper::require('delete_specialties');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_specialty', 'specialty_id' => $id]);
                return;
            }
            
            $specialty = $this->specialtyModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$specialty) {
                ResponseHelper::sendNotFoundError('Especialidade', ['action' => 'delete_specialty', 'specialty_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Soft delete
            $this->specialtyModel->delete((int)$id);
            
            ResponseHelper::sendSuccess(null, 200, 'Especialidade deletada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao deletar especialidade',
                'SPECIALTY_DELETE_ERROR',
                ['action' => 'delete_specialty', 'specialty_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


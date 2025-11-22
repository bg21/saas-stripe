<?php

namespace App\Controllers;

use App\Models\Professional;
use App\Models\User;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar profissionais da clínica
 */
class ProfessionalController
{
    private Professional $professionalModel;

    public function __construct(Professional $professionalModel)
    {
        $this->professionalModel = $professionalModel;
    }

    /**
     * Lista profissionais do tenant
     * GET /v1/professionals
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_professionals']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            $status = $queryParams['status'] ?? null;
            $specialtyId = isset($queryParams['specialty_id']) ? (int)$queryParams['specialty_id'] : null;
            
            $filters = [];
            if ($status) {
                $filters['status'] = $status;
            }
            
            $professionals = $this->professionalModel->findByTenant($tenantId, $filters);
            
            // Filtra por especialidade se fornecida
            if ($specialtyId !== null) {
                $professionals = array_filter($professionals, function($prof) use ($specialtyId) {
                    if (empty($prof['specialties'])) {
                        return false;
                    }
                    $specialties = is_string($prof['specialties']) 
                        ? json_decode($prof['specialties'], true) 
                        : $prof['specialties'];
                    return is_array($specialties) && in_array($specialtyId, $specialties);
                });
                $professionals = array_values($professionals); // Reindexa array
            }
            
            // Enriquece com dados do usuário
            foreach ($professionals as &$professional) {
                $user = (new User())->findById($professional['user_id']);
                if ($user) {
                    $professional['user'] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ];
                }
            }
            
            ResponseHelper::sendSuccess($professionals, 200, 'Profissionais listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar profissionais', 'PROFESSIONALS_LIST_ERROR', ['action' => 'list_professionals', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Cria um novo profissional
     * POST /v1/professionals
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_professional']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_professional']);
                return;
            }
            
            // Validação
            if (empty($data['user_id'])) {
                ResponseHelper::sendValidationError('user_id é obrigatório', ['user_id' => 'Obrigatório'], ['action' => 'create_professional']);
                return;
            }
            
            $professionalData = [
                'tenant_id' => $tenantId,
                'user_id' => $data['user_id'],
                'crmv' => $data['crmv'] ?? null,
                'specialties' => isset($data['specialties']) ? json_encode($data['specialties']) : null,
                'default_consultation_duration' => $data['default_consultation_duration'] ?? 30,
                'status' => $data['status'] ?? 'active',
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ];
            
            $professionalId = $this->professionalModel->createOrUpdate($tenantId, $professionalData);
            $professional = $this->professionalModel->findById($professionalId);
            
            // Decodifica JSON
            if ($professional['specialties']) {
                $professional['specialties'] = json_decode($professional['specialties'], true);
            }
            if ($professional['metadata']) {
                $professional['metadata'] = json_decode($professional['metadata'], true);
            }
            
            ResponseHelper::sendCreated($professional, 'Profissional criado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_professional', 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar profissional', 'PROFESSIONAL_CREATE_ERROR', ['action' => 'create_professional', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém um profissional
     * GET /v1/professionals/:id
     */
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_professional']);
                return;
            }
            
            $professional = $this->professionalModel->findByTenantAndId($tenantId, $id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional não encontrado', ['action' => 'get_professional', 'professional_id' => $id]);
                return;
            }
            
            // Decodifica JSON
            if ($professional['specialties']) {
                $professional['specialties'] = json_decode($professional['specialties'], true);
            }
            if ($professional['metadata']) {
                $professional['metadata'] = json_decode($professional['metadata'], true);
            }
            
            // Enriquece com dados do usuário
            $user = (new User())->findById($professional['user_id']);
            if ($user) {
                $professional['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
            }
            
            ResponseHelper::sendSuccess($professional, 200, 'Profissional obtido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter profissional', 'PROFESSIONAL_GET_ERROR', ['action' => 'get_professional', 'professional_id' => $id]);
        }
    }

    /**
     * Atualiza um profissional
     * PUT /v1/professionals/:id
     */
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_professional']);
                return;
            }
            
            $professional = $this->professionalModel->findByTenantAndId($tenantId, $id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional não encontrado', ['action' => 'update_professional', 'professional_id' => $id]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_professional']);
                return;
            }
            
            $allowedFields = ['crmv', 'specialties', 'default_consultation_duration', 'status', 'metadata'];
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            // Converte arrays para JSON
            if (isset($updateData['specialties'])) {
                $updateData['specialties'] = json_encode($updateData['specialties']);
            }
            if (isset($updateData['metadata'])) {
                $updateData['metadata'] = json_encode($updateData['metadata']);
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo válido para atualização', [], ['action' => 'update_professional']);
                return;
            }
            
            $this->professionalModel->update($id, $updateData);
            $updated = $this->professionalModel->findById($id);
            
            // Decodifica JSON
            if ($updated['specialties']) {
                $updated['specialties'] = json_decode($updated['specialties'], true);
            }
            if ($updated['metadata']) {
                $updated['metadata'] = json_decode($updated['metadata'], true);
            }
            
            ResponseHelper::sendSuccess($updated, 200, 'Profissional atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar profissional', 'PROFESSIONAL_UPDATE_ERROR', ['action' => 'update_professional', 'professional_id' => $id]);
        }
    }

    /**
     * Deleta um profissional
     * DELETE /v1/professionals/:id
     */
    public function delete(int $id): void
    {
        try {
            PermissionHelper::require('delete_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_professional']);
                return;
            }
            
            $professional = $this->professionalModel->findByTenantAndId($tenantId, $id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional não encontrado', ['action' => 'delete_professional', 'professional_id' => $id]);
                return;
            }
            
            $this->professionalModel->delete($id);
            
            ResponseHelper::sendSuccess(null, 200, 'Profissional deletado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao deletar profissional', 'PROFESSIONAL_DELETE_ERROR', ['action' => 'delete_professional', 'professional_id' => $id]);
        }
    }
}


<?php

namespace App\Controllers;

use App\Models\Pet;
use App\Models\Appointment;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar pets
 */
class PetController
{
    private Pet $petModel;

    public function __construct(Pet $petModel)
    {
        $this->petModel = $petModel;
    }

    /**
     * Lista pets do tenant
     * GET /v1/pets
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_pets']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            
            $filters = ['tenant_id' => $tenantId];
            
            if (!empty($queryParams['client_id'])) {
                $filters['client_id'] = (int)$queryParams['client_id'];
            }
            
            if (!empty($queryParams['species'])) {
                $filters['species'] = $queryParams['species'];
            }
            
            if (!empty($queryParams['search'])) {
                $search = $queryParams['search'];
                $filters['OR'] = [
                    'name LIKE' => "%{$search}%"
                ];
            }
            
            $result = $this->petModel->findAllWithCount($filters, ['created_at' => 'DESC'], $limit, $offset);
            
            // Adiciona idade calculada
            foreach ($result['data'] as &$pet) {
                if ($pet['birth_date']) {
                    $pet['age_years'] = $this->petModel->calculateAge($pet['birth_date']);
                }
                // Decodifica JSON
                if ($pet['medical_history']) {
                    $pet['medical_history'] = json_decode($pet['medical_history'], true);
                }
                if ($pet['metadata']) {
                    $pet['metadata'] = json_decode($pet['metadata'], true);
                }
            }
            
            $responseData = [
                'pets' => $result['data'],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $result['total'],
                    'total_pages' => ceil($result['total'] / $limit)
                ]
            ];
            ResponseHelper::sendSuccess($responseData, 200, 'Pets listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar pets', 'PETS_LIST_ERROR', ['action' => 'list_pets', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Cria um novo pet
     * POST /v1/pets
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_pet']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_pet']);
                return;
            }
            
            // Validação básica
            if (empty($data['name'])) {
                ResponseHelper::sendValidationError('Nome é obrigatório', ['name' => 'Obrigatório'], ['action' => 'create_pet']);
                return;
            }
            
            if (empty($data['client_id'])) {
                ResponseHelper::sendValidationError('client_id é obrigatório', ['client_id' => 'Obrigatório'], ['action' => 'create_pet']);
                return;
            }
            
            if (empty($data['species'])) {
                ResponseHelper::sendValidationError('Espécie é obrigatória', ['species' => 'Obrigatório'], ['action' => 'create_pet']);
                return;
            }
            
            $petData = [
                'tenant_id' => $tenantId,
                'client_id' => $data['client_id'],
                'name' => $data['name'],
                'species' => $data['species'],
                'breed' => $data['breed'] ?? null,
                'gender' => $data['gender'] ?? 'unknown',
                'birth_date' => $data['birth_date'] ?? null,
                'weight' => $data['weight'] ?? null,
                'color' => $data['color'] ?? null,
                'microchip' => $data['microchip'] ?? null,
                'medical_history' => isset($data['medical_history']) ? json_encode($data['medical_history']) : null,
                'notes' => $data['notes'] ?? null,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ];
            
            $petId = $this->petModel->createOrUpdate($tenantId, $petData);
            $pet = $this->petModel->findById($petId);
            
            // Decodifica JSON e adiciona idade
            if ($pet['medical_history']) {
                $pet['medical_history'] = json_decode($pet['medical_history'], true);
            }
            if ($pet['metadata']) {
                $pet['metadata'] = json_decode($pet['metadata'], true);
            }
            if ($pet['birth_date']) {
                $pet['age_years'] = $this->petModel->calculateAge($pet['birth_date']);
            }
            
            ResponseHelper::sendCreated($pet, 'Pet criado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_pet', 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar pet', 'PET_CREATE_ERROR', ['action' => 'create_pet', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém um pet
     * GET /v1/pets/:id
     */
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_pet']);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, $id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet não encontrado', ['action' => 'get_pet', 'pet_id' => $id]);
                return;
            }
            
            // Decodifica JSON e adiciona idade
            if ($pet['medical_history']) {
                $pet['medical_history'] = json_decode($pet['medical_history'], true);
            }
            if ($pet['metadata']) {
                $pet['metadata'] = json_decode($pet['metadata'], true);
            }
            if ($pet['birth_date']) {
                $pet['age_years'] = $this->petModel->calculateAge($pet['birth_date']);
            }
            
            ResponseHelper::sendSuccess($pet, 'Pet obtido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter pet', 'PET_GET_ERROR', ['action' => 'get_pet', 'pet_id' => $id]);
        }
    }

    /**
     * Atualiza um pet
     * PUT /v1/pets/:id
     */
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_pet']);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, $id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet não encontrado', ['action' => 'update_pet', 'pet_id' => $id]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_pet']);
                return;
            }
            
            $allowedFields = ['name', 'species', 'breed', 'gender', 'birth_date', 'weight', 'color', 'microchip', 'medical_history', 'notes', 'metadata'];
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            // Converte para JSON
            if (isset($updateData['medical_history'])) {
                $updateData['medical_history'] = json_encode($updateData['medical_history']);
            }
            if (isset($updateData['metadata'])) {
                $updateData['metadata'] = json_encode($updateData['metadata']);
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo válido para atualização', [], ['action' => 'update_pet']);
                return;
            }
            
            $this->petModel->update($id, $updateData);
            $updated = $this->petModel->findById($id);
            
            // Decodifica JSON e adiciona idade
            if ($updated['medical_history']) {
                $updated['medical_history'] = json_decode($updated['medical_history'], true);
            }
            if ($updated['metadata']) {
                $updated['metadata'] = json_decode($updated['metadata'], true);
            }
            if ($updated['birth_date']) {
                $updated['age_years'] = $this->petModel->calculateAge($updated['birth_date']);
            }
            
            ResponseHelper::sendSuccess($updated, 'Pet atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar pet', 'PET_UPDATE_ERROR', ['action' => 'update_pet', 'pet_id' => $id]);
        }
    }

    /**
     * Deleta um pet
     * DELETE /v1/pets/:id
     */
    public function delete(int $id): void
    {
        try {
            PermissionHelper::require('delete_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_pet']);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, $id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet não encontrado', ['action' => 'delete_pet', 'pet_id' => $id]);
                return;
            }
            
            $this->petModel->delete($id);
            
            ResponseHelper::sendSuccess(null, 'Pet deletado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao deletar pet', 'PET_DELETE_ERROR', ['action' => 'delete_pet', 'pet_id' => $id]);
        }
    }

    /**
     * Lista agendamentos de um pet
     * GET /v1/pets/:id/appointments
     */
    public function listAppointments(int $id): void
    {
        try {
            PermissionHelper::require('view_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_pet_appointments']);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, $id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet não encontrado', ['action' => 'list_pet_appointments', 'pet_id' => $id]);
                return;
            }
            
            $appointments = (new Appointment())->findByPet($id);
            
            ResponseHelper::sendSuccess($appointments, 'Agendamentos do pet listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar agendamentos do pet', 'PET_APPOINTMENTS_LIST_ERROR', ['action' => 'list_pet_appointments', 'pet_id' => $id]);
        }
    }
}


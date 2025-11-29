<?php

namespace App\Controllers;

use App\Models\Pet;
use App\Models\Client;
use App\Models\Appointment;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Services\CacheService;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Controller para gerenciar pets
 */
class PetController
{
    private Pet $petModel;
    private Client $clientModel;
    private Appointment $appointmentModel;

    public function __construct()
    {
        $this->petModel = new Pet();
        $this->clientModel = new Client();
        $this->appointmentModel = new Appointment();
    }

    /**
     * Lista pets do tenant
     * GET /v1/pets
     */
    public function list(): void
    {
        try {
            // Log para debug
            \App\Services\Logger::debug('PetController::list chamado', [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'user_id' => Flight::get('user_id'),
                'tenant_id' => Flight::get('tenant_id'),
                'is_user_auth' => Flight::get('is_user_auth'),
                'is_master' => Flight::get('is_master')
            ]);
            
            PermissionHelper::require('view_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                \App\Services\Logger::error('Tenant ID é null no PetController::list', [
                    'user_id' => Flight::get('user_id'),
                    'is_user_auth' => Flight::get('is_user_auth'),
                    'is_master' => Flight::get('is_master')
                ]);
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_pets']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            
            $filters = [];
            if (isset($queryParams['client_id']) && !empty($queryParams['client_id'])) {
                $filters['client_id'] = (int)$queryParams['client_id'];
            }
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            
            // Verifica cache
            $cacheKey = 'pets_' . $tenantId . '_' . md5(json_encode($filters));
            $cached = CacheService::getJson($cacheKey);
            if ($cached !== null) {
                ResponseHelper::sendSuccess($cached);
                return;
            }
            
            \App\Services\Logger::debug('Buscando pets no banco', [
                'tenant_id' => $tenantId,
                'filters' => $filters
            ]);
            
            $pets = $this->petModel->findByTenant($tenantId, $filters);
            
            \App\Services\Logger::debug('Pets encontrados', [
                'count' => is_array($pets) ? count($pets) : 0
            ]);
            
            // Adiciona informações do cliente para cada pet
            if (is_array($pets)) {
                foreach ($pets as &$pet) {
                    if (isset($pet['client_id']) && !empty($pet['client_id'])) {
                        try {
                            $client = $this->clientModel->findByTenantAndId($tenantId, (int)$pet['client_id']);
                            $pet['client'] = $client ? [
                                'id' => $client['id'],
                                'name' => $client['name'],
                                'email' => $client['email'] ?? null
                            ] : null;
                        } catch (\Exception $e) {
                            // Se houver erro ao buscar cliente, apenas não adiciona a informação
                            Logger::warning("Erro ao buscar cliente para pet: " . $e->getMessage(), [
                                'pet_id' => $pet['id'] ?? null,
                                'client_id' => $pet['client_id'] ?? null,
                                'tenant_id' => $tenantId
                            ]);
                            $pet['client'] = null;
                        }
                    }
                }
            }
            
            $response = ['pets' => $pets];
            
            // Cache por 5 minutos
            CacheService::setJson($cacheKey, $response, 300);
            
            ResponseHelper::sendSuccess($response);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar pets: " . $e->getMessage(), [
                'action' => 'list_pets',
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar pets',
                'PET_LIST_ERROR',
                ['action' => 'list_pets', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém um pet específico
     * GET /v1/pets/:id
     */
    public function get(string $id): void
    {
        try {
            PermissionHelper::require('view_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_pet', 'pet_id' => $id]);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet', ['action' => 'get_pet', 'pet_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Adiciona informações do cliente
            if (isset($pet['client_id'])) {
                $client = $this->clientModel->findByTenantAndId($tenantId, (int)$pet['client_id']);
                $pet['client'] = $client ? [
                    'id' => $client['id'],
                    'name' => $client['name'],
                    'email' => $client['email'] ?? null
                ] : null;
            }
            
            ResponseHelper::sendSuccess($pet);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter pet: " . $e->getMessage(), [
                'action' => 'get_pet',
                'pet_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter pet',
                'PET_GET_ERROR',
                ['action' => 'get_pet', 'pet_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
            
            $data = Flight::request()->data->getData();
            
            // Validações básicas
            if (empty($data['name'])) {
                ResponseHelper::sendValidationError(['name' => 'Nome é obrigatório']);
                return;
            }
            
            if (empty($data['client_id'])) {
                ResponseHelper::sendValidationError(['client_id' => 'Cliente é obrigatório']);
                return;
            }
            
            // Verifica se cliente existe e pertence ao tenant
            $client = $this->clientModel->findByTenantAndId($tenantId, (int)$data['client_id']);
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'create_pet', 'client_id' => $data['client_id']]);
                return;
            }
            
            $petData = [
                'tenant_id' => $tenantId,
                'client_id' => (int)$data['client_id'],
                'name' => $data['name'],
                'species' => $data['species'] ?? null,
                'breed' => $data['breed'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'weight' => isset($data['weight']) ? (float)$data['weight'] : null,
                'color' => $data['color'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'active'
            ];
            
            $petId = $this->petModel->create($petData);
            
            if (!$petId) {
                ResponseHelper::sendGenericError(
                    new \Exception('Falha ao criar pet'),
                    'Erro ao criar pet',
                    'PET_CREATE_ERROR',
                    ['action' => 'create_pet', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Limpa cache (remove todas as chaves que começam com o padrão)
            // Nota: CacheService não tem método clear por padrão, então apenas não usa cache para esta operação
            
            $pet = $this->petModel->findByTenantAndId($tenantId, $petId);
            
            Logger::info("Pet criado com sucesso", [
                'action' => 'create_pet',
                'pet_id' => $petId,
                'tenant_id' => $tenantId
            ]);
            
            ResponseHelper::sendSuccess($pet, 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar pet: " . $e->getMessage(), [
                'action' => 'create_pet',
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar pet',
                'PET_CREATE_ERROR',
                ['action' => 'create_pet', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza um pet
     * PUT /v1/pets/:id
     */
    public function update(string $id): void
    {
        try {
            PermissionHelper::require('update_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_pet', 'pet_id' => $id]);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet', ['action' => 'update_pet', 'pet_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = Flight::request()->data->getData();
            
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['species'])) $updateData['species'] = $data['species'];
            if (isset($data['breed'])) $updateData['breed'] = $data['breed'];
            if (isset($data['gender'])) $updateData['gender'] = $data['gender'];
            if (isset($data['birth_date'])) $updateData['birth_date'] = $data['birth_date'];
            if (isset($data['weight'])) $updateData['weight'] = (float)$data['weight'];
            if (isset($data['color'])) $updateData['color'] = $data['color'];
            if (isset($data['notes'])) $updateData['notes'] = $data['notes'];
            if (isset($data['status'])) $updateData['status'] = $data['status'];
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError(['message' => 'Nenhum campo para atualizar']);
                return;
            }
            
            $success = $this->petModel->update((int)$id, $updateData);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \Exception('Falha ao atualizar pet'),
                    'Erro ao atualizar pet',
                    'PET_UPDATE_ERROR',
                    ['action' => 'update_pet', 'pet_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Limpa cache (remove todas as chaves que começam com o padrão)
            // Nota: CacheService não tem método clear por padrão, então apenas não usa cache para esta operação
            
            $updatedPet = $this->petModel->findByTenantAndId($tenantId, (int)$id);
            
            Logger::info("Pet atualizado com sucesso", [
                'action' => 'update_pet',
                'pet_id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            ResponseHelper::sendSuccess($updatedPet);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar pet: " . $e->getMessage(), [
                'action' => 'update_pet',
                'pet_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar pet',
                'PET_UPDATE_ERROR',
                ['action' => 'update_pet', 'pet_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Remove um pet (soft delete)
     * DELETE /v1/pets/:id
     */
    public function delete(string $id): void
    {
        try {
            PermissionHelper::require('delete_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_pet', 'pet_id' => $id]);
                return;
            }
            
            $pet = $this->petModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet', ['action' => 'delete_pet', 'pet_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $success = $this->petModel->delete((int)$id);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \Exception('Falha ao remover pet'),
                    'Erro ao remover pet',
                    'PET_DELETE_ERROR',
                    ['action' => 'delete_pet', 'pet_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Limpa cache (remove todas as chaves que começam com o padrão)
            // Nota: CacheService não tem método clear por padrão, então apenas não usa cache para esta operação
            
            Logger::info("Pet removido com sucesso", [
                'action' => 'delete_pet',
                'pet_id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            ResponseHelper::sendSuccess(['message' => 'Pet removido com sucesso']);
        } catch (\Exception $e) {
            Logger::error("Erro ao remover pet: " . $e->getMessage(), [
                'action' => 'delete_pet',
                'pet_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao remover pet',
                'PET_DELETE_ERROR',
                ['action' => 'delete_pet', 'pet_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Lista agendamentos de um pet
     * GET /v1/pets/:id/appointments
     */
    public function listAppointments(string $id): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_pet_appointments', 'pet_id' => $id]);
                return;
            }
            
            // Verifica se o pet existe e pertence ao tenant
            $pet = $this->petModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$pet) {
                ResponseHelper::sendNotFoundError('Pet', ['action' => 'list_pet_appointments', 'pet_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Busca agendamentos do pet
            $appointments = $this->appointmentModel->findByTenant($tenantId, ['pet_id' => (int)$id]);
            
            // Adiciona informações adicionais de cada agendamento
            if (is_array($appointments)) {
                foreach ($appointments as &$appointment) {
                    // Adiciona informações do profissional se disponível
                    if (isset($appointment['professional_id']) && !empty($appointment['professional_id'])) {
                        try {
                            $professionalModel = new \App\Models\Professional();
                            $professional = $professionalModel->findByTenantAndId($tenantId, (int)$appointment['professional_id']);
                            $appointment['professional'] = $professional ? [
                                'id' => $professional['id'],
                                'name' => $professional['name'] ?? null
                            ] : null;
                        } catch (\Exception $e) {
                            Logger::warning("Erro ao buscar profissional para agendamento: " . $e->getMessage(), [
                                'appointment_id' => $appointment['id'] ?? null,
                                'professional_id' => $appointment['professional_id'] ?? null
                            ]);
                            $appointment['professional'] = null;
                        }
                    }
                }
            }
            
            ResponseHelper::sendSuccess($appointments);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar agendamentos do pet: " . $e->getMessage(), [
                'action' => 'list_pet_appointments',
                'pet_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar agendamentos do pet',
                'PET_APPOINTMENTS_LIST_ERROR',
                ['action' => 'list_pet_appointments', 'pet_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


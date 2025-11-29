<?php

namespace App\Controllers;

use App\Models\Client;
use App\Models\Pet;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Services\CacheService;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Controller para gerenciar clientes da clínica
 */
class ClientController
{
    private Client $clientModel;
    private Pet $petModel;

    public function __construct()
    {
        $this->clientModel = new Client();
        $this->petModel = new Pet();
    }

    /**
     * Lista clientes do tenant
     * GET /v1/clients
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_clients']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            
            $filters = [];
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                // Busca por nome ou email
                $filters['search'] = $queryParams['search'];
            }
            
            // Verifica cache
            $cacheKey = 'clients_' . $tenantId . '_' . md5(json_encode($filters));
            $cached = CacheService::getJson($cacheKey);
            if ($cached !== null) {
                ResponseHelper::sendSuccess($cached);
                return;
            }
            
            $clients = $this->clientModel->findByTenant($tenantId, $filters);
            
            $response = ['clients' => $clients];
            
            // Cache por 5 minutos
            CacheService::setJson($cacheKey, $response, 300);
            
            ResponseHelper::sendSuccess($response);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar clientes: " . $e->getMessage(), [
                'action' => 'list_clients',
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar clientes',
                'CLIENT_LIST_ERROR',
                ['action' => 'list_clients', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém um cliente específico
     * GET /v1/clients/:id
     */
    public function get(string $id): void
    {
        try {
            PermissionHelper::require('view_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_client', 'client_id' => $id]);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'get_client', 'client_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            ResponseHelper::sendSuccess($client);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter cliente: " . $e->getMessage(), [
                'action' => 'get_client',
                'client_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter cliente',
                'CLIENT_GET_ERROR',
                ['action' => 'get_client', 'client_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Cria um novo cliente
     * POST /v1/clients
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_client']);
                return;
            }
            
            $data = Flight::request()->data->getData();
            
            // Validações básicas
            if (empty($data['name'])) {
                ResponseHelper::sendValidationError(['name' => 'Nome é obrigatório']);
                return;
            }
            
            $clientData = [
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'document' => $data['document'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'active'
            ];
            
            $clientId = $this->clientModel->create($clientData);
            
            if (!$clientId) {
                ResponseHelper::sendGenericError(
                    new \Exception('Falha ao criar cliente'),
                    'Erro ao criar cliente',
                    'CLIENT_CREATE_ERROR',
                    ['action' => 'create_client', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Limpa cache (remove todas as chaves que começam com o padrão)
            // Nota: CacheService não tem método clear por padrão, então apenas não usa cache para esta operação
            
            $client = $this->clientModel->findByTenantAndId($tenantId, $clientId);
            
            Logger::info("Cliente criado com sucesso", [
                'action' => 'create_client',
                'client_id' => $clientId,
                'tenant_id' => $tenantId
            ]);
            
            ResponseHelper::sendSuccess($client, 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar cliente: " . $e->getMessage(), [
                'action' => 'create_client',
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar cliente',
                'CLIENT_CREATE_ERROR',
                ['action' => 'create_client', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza um cliente
     * PUT /v1/clients/:id
     */
    public function update(string $id): void
    {
        try {
            PermissionHelper::require('update_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_client', 'client_id' => $id]);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'update_client', 'client_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = Flight::request()->data->getData();
            
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
            if (isset($data['document'])) $updateData['document'] = $data['document'];
            if (isset($data['address'])) $updateData['address'] = $data['address'];
            if (isset($data['city'])) $updateData['city'] = $data['city'];
            if (isset($data['state'])) $updateData['state'] = $data['state'];
            if (isset($data['zip_code'])) $updateData['zip_code'] = $data['zip_code'];
            if (isset($data['notes'])) $updateData['notes'] = $data['notes'];
            if (isset($data['status'])) $updateData['status'] = $data['status'];
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError(['message' => 'Nenhum campo para atualizar']);
                return;
            }
            
            $success = $this->clientModel->update((int)$id, $updateData);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \Exception('Falha ao atualizar cliente'),
                    'Erro ao atualizar cliente',
                    'CLIENT_UPDATE_ERROR',
                    ['action' => 'update_client', 'client_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Limpa cache (remove todas as chaves que começam com o padrão)
            // Nota: CacheService não tem método clear por padrão, então apenas não usa cache para esta operação
            
            $updatedClient = $this->clientModel->findByTenantAndId($tenantId, (int)$id);
            
            Logger::info("Cliente atualizado com sucesso", [
                'action' => 'update_client',
                'client_id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            ResponseHelper::sendSuccess($updatedClient);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar cliente: " . $e->getMessage(), [
                'action' => 'update_client',
                'client_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar cliente',
                'CLIENT_UPDATE_ERROR',
                ['action' => 'update_client', 'client_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Remove um cliente (soft delete)
     * DELETE /v1/clients/:id
     */
    public function delete(string $id): void
    {
        try {
            PermissionHelper::require('delete_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_client', 'client_id' => $id]);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'delete_client', 'client_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $success = $this->clientModel->delete((int)$id);
            
            if (!$success) {
                ResponseHelper::sendGenericError(
                    new \Exception('Falha ao remover cliente'),
                    'Erro ao remover cliente',
                    'CLIENT_DELETE_ERROR',
                    ['action' => 'delete_client', 'client_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Limpa cache (remove todas as chaves que começam com o padrão)
            // Nota: CacheService não tem método clear por padrão, então apenas não usa cache para esta operação
            
            Logger::info("Cliente removido com sucesso", [
                'action' => 'delete_client',
                'client_id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            ResponseHelper::sendSuccess(['message' => 'Cliente removido com sucesso']);
        } catch (\Exception $e) {
            Logger::error("Erro ao remover cliente: " . $e->getMessage(), [
                'action' => 'delete_client',
                'client_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao remover cliente',
                'CLIENT_DELETE_ERROR',
                ['action' => 'delete_client', 'client_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Lista pets de um cliente
     * GET /v1/clients/:id/pets
     */
    public function listPets(string $id): void
    {
        try {
            PermissionHelper::require('view_pets');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_client_pets', 'client_id' => $id]);
                return;
            }
            
            // Verifica se o cliente existe e pertence ao tenant
            $client = $this->clientModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'list_client_pets', 'client_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Busca pets do cliente
            $pets = $this->petModel->findByClient($tenantId, (int)$id);
            
            ResponseHelper::sendSuccess($pets);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar pets do cliente: " . $e->getMessage(), [
                'action' => 'list_client_pets',
                'client_id' => $id,
                'tenant_id' => $tenantId ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar pets do cliente',
                'CLIENT_PETS_LIST_ERROR',
                ['action' => 'list_client_pets', 'client_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


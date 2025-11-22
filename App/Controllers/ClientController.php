<?php

namespace App\Controllers;

use App\Models\Client;
use App\Models\Pet;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar clientes (donos de pets)
 */
class ClientController
{
    private Client $clientModel;

    public function __construct(Client $clientModel)
    {
        $this->clientModel = $clientModel;
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
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            
            $filters = [];
            if (!empty($queryParams['search'])) {
                // Busca por nome, email ou telefone
                $search = $queryParams['search'];
                $filters['OR'] = [
                    'name LIKE' => "%{$search}%",
                    'email LIKE' => "%{$search}%",
                    'phone LIKE' => "%{$search}%",
                    'phone_alt LIKE' => "%{$search}%"
                ];
            }
            
            $result = $this->clientModel->findAllWithCount($filters, ['created_at' => 'DESC'], $limit, $offset);
            
            $responseData = [
                'clients' => $result['data'],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $result['total'],
                    'total_pages' => ceil($result['total'] / $limit)
                ]
            ];
            ResponseHelper::sendSuccess($responseData, 200, 'Clientes listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar clientes', 'CLIENTS_LIST_ERROR', ['action' => 'list_clients', 'tenant_id' => Flight::get('tenant_id')]);
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
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_client']);
                return;
            }
            
            // Validação básica
            if (empty($data['name'])) {
                ResponseHelper::sendValidationError('Nome é obrigatório', ['name' => 'Obrigatório'], ['action' => 'create_client']);
                return;
            }
            
            $clientData = [
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'phone_alt' => $data['phone_alt'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ];
            
            $clientId = $this->clientModel->createOrUpdate($tenantId, $clientData);
            $client = $this->clientModel->findById($clientId);
            
            // Decodifica JSON
            if ($client['metadata']) {
                $client['metadata'] = json_decode($client['metadata'], true);
            }
            
            ResponseHelper::sendCreated($client, 'Cliente criado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_client', 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar cliente', 'CLIENT_CREATE_ERROR', ['action' => 'create_client', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém um cliente
     * GET /v1/clients/:id
     */
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_client']);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, $id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente não encontrado', ['action' => 'get_client', 'client_id' => $id]);
                return;
            }
            
            // Decodifica JSON
            if ($client['metadata']) {
                $client['metadata'] = json_decode($client['metadata'], true);
            }
            
            ResponseHelper::sendSuccess($client, 'Cliente obtido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter cliente', 'CLIENT_GET_ERROR', ['action' => 'get_client', 'client_id' => $id]);
        }
    }

    /**
     * Atualiza um cliente
     * PUT /v1/clients/:id
     */
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_client']);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, $id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente não encontrado', ['action' => 'update_client', 'client_id' => $id]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_client']);
                return;
            }
            
            $allowedFields = ['name', 'email', 'phone', 'phone_alt', 'address', 'city', 'state', 'postal_code', 'notes', 'metadata'];
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            // Converte metadata para JSON
            if (isset($updateData['metadata'])) {
                $updateData['metadata'] = json_encode($updateData['metadata']);
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo válido para atualização', [], ['action' => 'update_client']);
                return;
            }
            
            $this->clientModel->update($id, $updateData);
            $updated = $this->clientModel->findById($id);
            
            // Decodifica JSON
            if ($updated['metadata']) {
                $updated['metadata'] = json_decode($updated['metadata'], true);
            }
            
            ResponseHelper::sendSuccess($updated, 'Cliente atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar cliente', 'CLIENT_UPDATE_ERROR', ['action' => 'update_client', 'client_id' => $id]);
        }
    }

    /**
     * Deleta um cliente
     * DELETE /v1/clients/:id
     */
    public function delete(int $id): void
    {
        try {
            PermissionHelper::require('delete_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_client']);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, $id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente não encontrado', ['action' => 'delete_client', 'client_id' => $id]);
                return;
            }
            
            $this->clientModel->delete($id);
            
            ResponseHelper::sendSuccess(null, 'Cliente deletado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao deletar cliente', 'CLIENT_DELETE_ERROR', ['action' => 'delete_client', 'client_id' => $id]);
        }
    }

    /**
     * Lista pets de um cliente
     * GET /v1/clients/:id/pets
     */
    public function listPets(int $id): void
    {
        try {
            PermissionHelper::require('view_clients');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_client_pets']);
                return;
            }
            
            $client = $this->clientModel->findByTenantAndId($tenantId, $id);
            
            if (!$client) {
                ResponseHelper::sendNotFoundError('Cliente não encontrado', ['action' => 'list_client_pets', 'client_id' => $id]);
                return;
            }
            
            $pets = (new Pet())->findByClient($id);
            
            ResponseHelper::sendSuccess($pets, 'Pets do cliente listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar pets do cliente', 'CLIENT_PETS_LIST_ERROR', ['action' => 'list_client_pets', 'client_id' => $id]);
        }
    }
}


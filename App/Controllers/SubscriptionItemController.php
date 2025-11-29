<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\Validator;
use Flight;
use Config;

/**
 * Controller para gerenciar Subscription Items
 * 
 * Subscription Items representam produtos/preços individuais dentro de uma assinatura.
 * Útil para adicionar add-ons, upgrades, ou múltiplos produtos em uma assinatura.
 */
class SubscriptionItemController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo Subscription Item (adiciona item a uma assinatura)
     * POST /v1/subscriptions/:subscription_id/items
     * 
     * Body:
     *   - price_id (obrigatório): ID do preço no Stripe
     *   - quantity (opcional): Quantidade (padrão: 1)
     *   - metadata (opcional): Metadados
     */
    public function create(string $subscriptionId): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_subscription_item']);
                return;
            }
            
            // Valida subscription_id
            $subscriptionIdErrors = Validator::validateStripeId($subscriptionId, 'subscription_id');
            if (!empty($subscriptionIdErrors)) {
                ResponseHelper::sendValidationError(
                    'ID da assinatura inválido',
                    $subscriptionIdErrors,
                    ['action' => 'create_subscription_item', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId]
                );
                return;
            }

            // Valida se a assinatura existe e pertence ao tenant
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findByStripeId($subscriptionId);
            
            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'create_subscription_item', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_subscription_item', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            // ✅ Validação consistente
            $errors = [];
            if (empty($data['price_id'])) {
                $errors['price_id'] = 'Obrigatório';
            } else {
                $priceIdErrors = Validator::validateStripeId($data['price_id'], 'price_id');
                if (!empty($priceIdErrors)) {
                    $errors = array_merge($errors, $priceIdErrors);
                }
            }
            
            // Valida quantity se fornecido
            if (isset($data['quantity'])) {
                if (!is_numeric($data['quantity'])) {
                    $errors['quantity'] = 'Deve ser um número';
                } else {
                    $quantity = (int)$data['quantity'];
                    if ($quantity < 1 || $quantity > 10000) {
                        $errors['quantity'] = 'Deve estar entre 1 e 10000';
                    }
                }
            }
            
            // Valida metadata se fornecido
            if (isset($data['metadata'])) {
                $metadataErrors = Validator::validateMetadata($data['metadata'], 'metadata');
                if (!empty($metadataErrors)) {
                    $errors = array_merge($errors, $metadataErrors);
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_subscription_item', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId]
                );
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $subscriptionItem = $this->stripeService->createSubscriptionItem($subscriptionId, $data);

            ResponseHelper::sendCreated([
                'id' => $subscriptionItem->id,
                'subscription' => $subscriptionItem->subscription,
                'price' => $subscriptionItem->price->id,
                'quantity' => $subscriptionItem->quantity,
                'created' => date('Y-m-d H:i:s', $subscriptionItem->created),
                'metadata' => $subscriptionItem->metadata->toArray()
            ], 'Subscription item criado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar subscription item no Stripe',
                ['action' => 'create_subscription_item', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar subscription item',
                'SUBSCRIPTION_ITEM_CREATE_ERROR',
                ['action' => 'create_subscription_item', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Lista Subscription Items de uma assinatura
     * GET /v1/subscriptions/:subscription_id/items
     * 
     * Query params:
     *   - limit (opcional): Número de itens por página
     *   - starting_after (opcional): ID do item para paginação
     *   - ending_before (opcional): ID do item para paginação
     */
    public function list(string $subscriptionId): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_subscription_items', 'subscription_id' => $subscriptionId]);
                return;
            }

            // Valida se a assinatura existe e pertence ao tenant
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findByStripeId($subscriptionId);
            
            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'list_subscription_items', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId]);
                return;
            }

            $options = [];
            
            if (isset($_GET['limit'])) {
                $options['limit'] = (int)$_GET['limit'];
            }
            
            if (!empty($_GET['starting_after'])) {
                $options['starting_after'] = $_GET['starting_after'];
            }
            
            if (!empty($_GET['ending_before'])) {
                $options['ending_before'] = $_GET['ending_before'];
            }

            $collection = $this->stripeService->listSubscriptionItems($subscriptionId, $options);

            $items = [];
            foreach ($collection->data as $item) {
                $items[] = [
                    'id' => $item->id,
                    'subscription' => $item->subscription,
                    'price' => $item->price->id,
                    'quantity' => $item->quantity,
                    'created' => date('Y-m-d H:i:s', $item->created),
                    'metadata' => $item->metadata->toArray()
                ];
            }

            ResponseHelper::sendSuccess([
                'data' => $items,
                'count' => count($items),
                'has_more' => $collection->has_more
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao listar subscription items',
                ['action' => 'list_subscription_items', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar subscription items',
                'SUBSCRIPTION_ITEMS_LIST_ERROR',
                ['action' => 'list_subscription_items', 'subscription_id' => $subscriptionId, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém Subscription Item por ID
     * GET /v1/subscription-items/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_subscription_item', 'subscription_item_id' => $id]);
                return;
            }

            $subscriptionItem = $this->stripeService->getSubscriptionItem($id);

            // Valida se a assinatura pertence ao tenant
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findByStripeId($subscriptionItem->subscription);
            
            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Subscription item', ['action' => 'get_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            ResponseHelper::sendSuccess([
                'id' => $subscriptionItem->id,
                'subscription' => $subscriptionItem->subscription,
                'price' => $subscriptionItem->price->id,
                'quantity' => $subscriptionItem->quantity,
                'created' => date('Y-m-d H:i:s', $subscriptionItem->created),
                'metadata' => $subscriptionItem->metadata->toArray()
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Subscription item', ['action' => 'get_subscription_item', 'subscription_item_id' => $id]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter subscription item',
                    ['action' => 'get_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter subscription item',
                'SUBSCRIPTION_ITEM_GET_ERROR',
                ['action' => 'get_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza Subscription Item
     * PUT /v1/subscription-items/:id
     * 
     * Body:
     *   - price_id (opcional): Novo preço (migração de preço)
     *   - quantity (opcional): Nova quantidade
     *   - metadata (opcional): Metadados (merge com existentes)
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_subscription_item', 'subscription_item_id' => $id]);
                return;
            }

            // Primeiro, verifica se o item existe e pertence ao tenant
            $subscriptionItem = $this->stripeService->getSubscriptionItem($id);
            
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findByStripeId($subscriptionItem->subscription);
            
            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Subscription item', ['action' => 'update_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            $subscriptionItem = $this->stripeService->updateSubscriptionItem($id, $data);

            ResponseHelper::sendSuccess([
                'id' => $subscriptionItem->id,
                'subscription' => $subscriptionItem->subscription,
                'price' => $subscriptionItem->price->id,
                'quantity' => $subscriptionItem->quantity,
                'created' => date('Y-m-d H:i:s', $subscriptionItem->created),
                'metadata' => $subscriptionItem->metadata->toArray()
            ], 200, 'Subscription item atualizado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Subscription item', ['action' => 'update_subscription_item', 'subscription_item_id' => $id]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao atualizar subscription item',
                    ['action' => 'update_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar subscription item',
                'SUBSCRIPTION_ITEM_UPDATE_ERROR',
                ['action' => 'update_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Remove Subscription Item de uma assinatura
     * DELETE /v1/subscription-items/:id
     * 
     * Query params:
     *   - prorate (opcional): Se true, prorata o valor (padrão: true)
     */
    public function delete(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_subscription_item', 'subscription_item_id' => $id]);
                return;
            }

            // Primeiro, verifica se o item existe e pertence ao tenant
            $subscriptionItem = $this->stripeService->getSubscriptionItem($id);
            
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findByStripeId($subscriptionItem->subscription);
            
            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Subscription item', ['action' => 'delete_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            $options = [];
            
            if (isset($_GET['prorate'])) {
                $options['prorate'] = filter_var($_GET['prorate'], FILTER_VALIDATE_BOOLEAN);
            }

            $this->stripeService->deleteSubscriptionItem($id, $options);

            ResponseHelper::sendSuccess(null, 200, 'Subscription item removido com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Subscription item', ['action' => 'delete_subscription_item', 'subscription_item_id' => $id]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao remover subscription item',
                    ['action' => 'delete_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao remover subscription item',
                'SUBSCRIPTION_ITEM_DELETE_ERROR',
                ['action' => 'delete_subscription_item', 'subscription_item_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


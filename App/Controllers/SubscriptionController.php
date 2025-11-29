<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\Validator;
use App\Utils\ErrorHandler;
use App\Utils\ResponseHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar assinaturas
 */
class SubscriptionController
{
    private PaymentService $paymentService;
    private StripeService $stripeService;

    public function __construct(PaymentService $paymentService, StripeService $stripeService)
    {
        $this->paymentService = $paymentService;
        $this->stripeService = $stripeService;
    }

    /**
     * Cria uma nova assinatura
     * POST /v1/subscriptions
     * 
     * Body JSON:
     * {
     *   "customer_id": 1,
     *   "price_id": "price_xxx",
     *   "trial_period_days": 14,  // opcional
     *   "payment_behavior": "default_incomplete",  // opcional
     *   "metadata": {}  // opcional
     * }
     */
    public function create(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('create_subscriptions');
            
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_subscription']);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');
            
            // Validação rigorosa de inputs
            $errors = Validator::validateSubscriptionCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_subscription', 'tenant_id' => $tenantId]
                );
                return;
            }

            // Sanitiza e converte tipos após validação
            $subscription = $this->paymentService->createSubscription(
                $tenantId,
                (int)$data['customer_id'],
                $data['price_id'],
                $data['metadata'] ?? [],
                isset($data['trial_period_days']) ? (int)$data['trial_period_days'] : null,
                $data['payment_behavior'] ?? null
            );

            // ✅ Invalida cache de listagem
            \App\Services\CacheService::invalidateSubscriptionCache($tenantId);

            ResponseHelper::sendCreated($subscription, 'Assinatura criada com sucesso');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar assinatura no Stripe', ['action' => 'create_subscription', 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar assinatura', 'SUBSCRIPTION_CREATE_ERROR', ['action' => 'create_subscription', 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Lista assinaturas do tenant
     * GET /v1/subscriptions
     * 
     * Query params:
     *   - page: Número da página (padrão: 1)
     *   - limit: Itens por página (padrão: 20)
     *   - status: Filtrar por status
     *   - customer: Filtrar por customer_id
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_subscriptions');
            
            $tenantId = Flight::get('tenant_id');
            
            $queryParams = Flight::request()->query;
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20;
            
            $filters = [];
            if (!empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            if (!empty($queryParams['customer'])) {
                $filters['customer'] = $queryParams['customer'];
            }
            
            // ✅ CACHE: Gera chave única
            $cacheKey = sprintf(
                'subscriptions:list:%d:%d:%d:%s:%s',
                $tenantId,
                $page,
                $limit,
                $filters['status'] ?? '',
                $filters['customer'] ?? ''
            );
            
            // ✅ Tenta obter do cache
            $cached = \App\Services\CacheService::getJson($cacheKey);
            if ($cached !== null) {
                // ✅ CORREÇÃO: Se cache tem formato antigo {data: [...], meta: {...}}, converte
                if (isset($cached['data']) && isset($cached['meta'])) {
                    Flight::json([
                        'success' => true,
                        'data' => $cached['data'],
                        'meta' => $cached['meta']
                    ]);
                } else {
                    // Formato novo (já é array direto)
                    Flight::json([
                        'success' => true,
                        'data' => $cached,
                        'meta' => []
                    ]);
                }
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            $result = $subscriptionModel->findByTenant($tenantId, $page, $limit, $filters);

            // ✅ Calcula estatísticas precisas por status
            $stats = $subscriptionModel->getStatsByTenant($tenantId, $filters);

            // ✅ CORREÇÃO: Passa apenas o array de dados para sendSuccess
            // O meta será adicionado como propriedade separada na resposta
            $responseData = $result['data'];
            $meta = [
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'total_pages' => $result['total_pages'],
                'stats' => $stats // ✅ Estatísticas precisas por status
            ];
            
            // ✅ Salva no cache (formato completo para compatibilidade)
            $cacheData = [
                'data' => $responseData,
                'meta' => $meta
            ];
            \App\Services\CacheService::setJson($cacheKey, $cacheData, 60);
            
            // ✅ Envia resposta com data como array e meta separado
            Flight::json([
                'success' => true,
                'data' => $responseData,
                'meta' => $meta
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar assinaturas', 'SUBSCRIPTION_LIST_ERROR', ['action' => 'list_subscriptions', 'tenant_id' => $tenantId ?? null]);
        }
    }

    /**
     * Obtém assinatura por ID
     * GET /v1/subscriptions/:id
     */
    public function get(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_subscriptions');
            
            // Valida ID
            $idErrors = Validator::validateId($id, 'id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID inválido',
                    $idErrors,
                    ['action' => 'cancel_subscription', 'subscription_id' => $id]
                );
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_subscription', 'subscription_id' => $id]);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'get_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ CACHE: Verifica se há cache válido (TTL: 5 minutos)
            $cacheKey = "subscriptions:get:{$tenantId}:{$id}";
            $cached = \App\Services\CacheService::getJson($cacheKey);
            
            if ($cached !== null) {
                ResponseHelper::sendSuccess($cached);
                return;
            }

            // ✅ Sincronização condicional: apenas se cache expirou
            // Busca dados atualizados no Stripe
            $stripeSubscription = $this->stripeService->getSubscription($subscription['stripe_subscription_id']);

            // Atualiza no banco apenas se houver mudanças significativas
            $needsUpdate = false;
            if (($stripeSubscription->status ?? null) !== ($subscription['status'] ?? null) ||
                ($stripeSubscription->cancel_at_period_end ?? false) !== ($subscription['cancel_at_period_end'] ?? false)) {
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $subscriptionModel->createOrUpdate(
                    $tenantId,
                    $subscription['customer_id'],
                    $stripeSubscription->toArray()
                );
            }

            // ✅ CORREÇÃO: Extrai amount e currency dos items da assinatura
            $amount = 0;
            $currency = 'BRL';
            $priceId = null;
            
            if (!empty($stripeSubscription->items->data)) {
                $firstItem = $stripeSubscription->items->data[0];
                $amount = ($firstItem->price->unit_amount ?? 0) / 100; // Converte de centavos para reais
                $currency = strtoupper($firstItem->price->currency ?? 'brl');
                $priceId = $firstItem->price->id ?? null;
            }

            // Prepara resposta
            $responseData = [
                'id' => $subscription['id'],
                'stripe_subscription_id' => $stripeSubscription->id,
                'customer_id' => $subscription['customer_id'],
                'status' => $stripeSubscription->status,
                'price_id' => $priceId,
                'amount' => $amount,
                'currency' => $currency,
                'current_period_start' => $stripeSubscription->current_period_start ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start) : null,
                'current_period_end' => isset($stripeSubscription->current_period_end) && $stripeSubscription->current_period_end 
                    ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) 
                    : (isset($subscription['current_period_end']) ? $subscription['current_period_end'] : null),
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d H:i:s', $stripeSubscription->canceled_at) : null,
                'trial_start' => $stripeSubscription->trial_start ? date('Y-m-d H:i:s', $stripeSubscription->trial_start) : null,
                'trial_end' => $stripeSubscription->trial_end ? date('Y-m-d H:i:s', $stripeSubscription->trial_end) : null,
                'items' => array_map(function($item) {
                    return [
                        'id' => $item->id,
                        'price_id' => $item->price->id,
                        'quantity' => $item->quantity
                    ];
                }, $stripeSubscription->items->data),
                'metadata' => $stripeSubscription->metadata->toArray(),
                'created_at' => date('Y-m-d H:i:s', $stripeSubscription->created), // ✅ CORREÇÃO: Mudado de 'created' para 'created_at'
                'created' => date('Y-m-d H:i:s', $stripeSubscription->created) // Mantém para compatibilidade
            ];

            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $responseData, 300); // 5 minutos

            ResponseHelper::sendSuccess($responseData);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::warning("Assinatura não encontrada no Stripe", ['subscription_id' => (int)$id]);
            ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'get_subscription', 'subscription_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter assinatura', 'SUBSCRIPTION_GET_ERROR', ['action' => 'get_subscription', 'subscription_id' => $id]);
        }
    }

    /**
     * Atualiza uma assinatura
     * PUT /v1/subscriptions/:id
     * 
     * Body JSON:
     * {
     *   "price_id": "price_xxx",  // opcional - para upgrade/downgrade
     *   "quantity": 2,  // opcional - nova quantidade
     *   "metadata": {},  // opcional - metadados atualizados
     *   "proration_behavior": "create_prorations",  // opcional
     *   "cancel_at_period_end": false  // opcional
     * }
     */
    public function update(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('update_subscriptions');
            
            // Valida ID
            $idErrors = Validator::validateId($id, 'id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID inválido',
                    $idErrors,
                    ['action' => 'cancel_subscription', 'subscription_id' => $id]
                );
                return;
            }
            
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_subscription', 'subscription_id' => $id]);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');
            
            // Validação rigorosa de inputs
            $errors = Validator::validateSubscriptionUpdate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'update_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Verifica se há dados para atualizar
            $allowedFields = ['price_id', 'quantity', 'metadata', 'proration_behavior', 'cancel_at_period_end', 'trial_end'];
            $hasUpdates = false;
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $hasUpdates = true;
                    break;
                }
            }

            if (!$hasUpdates) {
                ResponseHelper::sendValidationError(
                    'Nenhum campo válido para atualização fornecido',
                    [],
                    ['action' => 'update_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_subscription', 'subscription_id' => $id]);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'get_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Prepara dados antigos para histórico
            $oldData = [
                'status' => $subscription['status'],
                'plan_id' => $subscription['plan_id'],
                'amount' => $subscription['amount'],
                'currency' => $subscription['currency'],
                'current_period_end' => $subscription['current_period_end'],
                'cancel_at_period_end' => $subscription['cancel_at_period_end'],
                'metadata' => $subscription['metadata'] ? json_decode($subscription['metadata'], true) : null
            ];

            // Atualiza no Stripe
            $stripeSubscription = $this->stripeService->updateSubscription(
                $subscription['stripe_subscription_id'],
                $data
            );

            // Atualiza no banco
            $subscriptionModel->createOrUpdate(
                $tenantId,
                $subscription['customer_id'],
                $stripeSubscription->toArray()
            );

            // Busca assinatura atualizada no banco
            $updatedSubscription = $subscriptionModel->findById((int)$id);

            // Prepara dados novos para histórico
            $newData = [
                'status' => $updatedSubscription['status'],
                'plan_id' => $updatedSubscription['plan_id'],
                'amount' => $updatedSubscription['amount'],
                'currency' => $updatedSubscription['currency'],
                'current_period_end' => $updatedSubscription['current_period_end'],
                'cancel_at_period_end' => $updatedSubscription['cancel_at_period_end'],
                'metadata' => $updatedSubscription['metadata'] ? json_decode($updatedSubscription['metadata'], true) : null
            ];

            // Determina tipo de mudança
            $changeType = \App\Models\SubscriptionHistory::CHANGE_TYPE_UPDATED;
            if (isset($data['price_id']) && $oldData['plan_id'] !== $newData['plan_id']) {
                $changeType = \App\Models\SubscriptionHistory::CHANGE_TYPE_PLAN_CHANGED;
            } elseif ($oldData['status'] !== $newData['status']) {
                $changeType = \App\Models\SubscriptionHistory::CHANGE_TYPE_STATUS_CHANGED;
            }

            // Registra no histórico
            $userId = Flight::get('user_id'); // Pode ser null se for API Key
            $historyModel = new \App\Models\SubscriptionHistory();
            $historyModel->recordChange(
                (int)$id,
                $tenantId,
                $changeType,
                $oldData,
                $newData,
                \App\Models\SubscriptionHistory::CHANGED_BY_API,
                isset($data['price_id']) ? "Plano alterado para {$newData['plan_id']}" : "Assinatura atualizada via API",
                $userId
            );

            // ✅ Invalida cache de listagem e cache específico
            \App\Services\CacheService::invalidateSubscriptionCache($tenantId, (int)$id);

            ResponseHelper::sendSuccess([
                'id' => $updatedSubscription['id'],
                'stripe_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'current_period_start' => $stripeSubscription->current_period_start ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start) : null,
                'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) : null,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d H:i:s', $stripeSubscription->canceled_at) : null,
                'items' => array_map(function($item) {
                    return [
                        'id' => $item->id,
                        'price_id' => $item->price->id,
                        'quantity' => $item->quantity
                    ];
                }, $stripeSubscription->items->data),
                'metadata' => $stripeSubscription->metadata->toArray()
            ], 200, 'Assinatura atualizada com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao atualizar assinatura no Stripe', ['action' => 'update_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar assinatura', 'SUBSCRIPTION_UPDATE_ERROR', ['action' => 'update_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Cancela uma assinatura
     * DELETE /v1/subscriptions/:id
     */
    public function cancel(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('cancel_subscriptions');
            
            // Valida ID
            $idErrors = Validator::validateId($id, 'id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID inválido',
                    $idErrors,
                    ['action' => 'cancel_subscription', 'subscription_id' => $id]
                );
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_subscription', 'subscription_id' => $id]);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'get_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            $immediately = (Flight::request()->query['immediately'] ?? 'false') === 'true';
            $stripeSubscription = $this->stripeService->cancelSubscription(
                $subscription['stripe_subscription_id'],
                $immediately
            );

            // Prepara dados antigos para histórico
            $oldData = [
                'status' => $subscription['status'],
                'cancel_at_period_end' => $subscription['cancel_at_period_end']
            ];

            // Atualiza no banco
            $subscriptionModel->createOrUpdate(
                $tenantId,
                $subscription['customer_id'],
                $stripeSubscription->toArray()
            );

            // Busca assinatura atualizada no banco
            $updatedSubscription = $subscriptionModel->findById((int)$id);

            // Prepara dados novos para histórico
            $newData = [
                'status' => $updatedSubscription['status'],
                'cancel_at_period_end' => $updatedSubscription['cancel_at_period_end']
            ];

            // Registra no histórico
            $userId = Flight::get('user_id'); // Pode ser null se for API Key
            $historyModel = new \App\Models\SubscriptionHistory();
            $historyModel->recordChange(
                (int)$id,
                $tenantId,
                \App\Models\SubscriptionHistory::CHANGE_TYPE_CANCELED,
                $oldData,
                $newData,
                \App\Models\SubscriptionHistory::CHANGED_BY_API,
                $immediately ? 'Assinatura cancelada imediatamente' : 'Assinatura marcada para cancelar no final do período',
                $userId
            );

            // ✅ Invalida cache de listagem e cache específico
            \App\Services\CacheService::invalidateSubscriptionCache($tenantId, (int)$id);

            ResponseHelper::sendSuccess([
                'id' => $updatedSubscription['id'],
                'stripe_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d H:i:s', $stripeSubscription->canceled_at) : null,
                'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) : null
            ], 200, $immediately 
                ? 'Assinatura cancelada imediatamente' 
                : 'Assinatura será cancelada no final do período');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao cancelar assinatura', 'SUBSCRIPTION_CANCEL_ERROR', ['action' => 'cancel_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Reativa uma assinatura cancelada
     * POST /v1/subscriptions/:id/reactivate
     * 
     * Remove a flag cancel_at_period_end para reativar uma assinatura que estava
     * marcada para cancelar no final do período.
     * 
     * Nota: Assinaturas já canceladas (status = 'canceled') não podem ser reativadas.
     */
    public function reactivate(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('reactivate_subscriptions');
            
            // Valida ID
            $idErrors = Validator::validateId($id, 'id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID inválido',
                    $idErrors,
                    ['action' => 'cancel_subscription', 'subscription_id' => $id]
                );
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_subscription', 'subscription_id' => $id]);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                ResponseHelper::sendNotFoundError('Assinatura', ['action' => 'get_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Obtém assinatura atual do Stripe para verificar status
            $currentStripeSubscription = $this->stripeService->getSubscription($subscription['stripe_subscription_id']);
            
            // Valida se pode ser reativada
            if ($currentStripeSubscription->status === 'canceled') {
                ResponseHelper::sendValidationError(
                    'Assinaturas canceladas não podem ser reativadas. Crie uma nova assinatura.',
                    ['status' => 'Assinatura já está cancelada'],
                    ['action' => 'reactivate_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId, 'current_status' => 'canceled']
                );
                return;
            }

            if (!$currentStripeSubscription->cancel_at_period_end) {
                ResponseHelper::sendSuccess([
                    'id' => $subscription['id'],
                    'stripe_subscription_id' => $currentStripeSubscription->id,
                    'status' => $currentStripeSubscription->status,
                    'cancel_at_period_end' => $currentStripeSubscription->cancel_at_period_end
                ], 200, 'Assinatura já está ativa e não estava marcada para cancelar');
                return;
            }

            // Prepara dados antigos para histórico
            $oldData = [
                'status' => $subscription['status'],
                'cancel_at_period_end' => $subscription['cancel_at_period_end']
            ];

            // Reativa a assinatura
            $stripeSubscription = $this->stripeService->reactivateSubscription($subscription['stripe_subscription_id']);

            // Atualiza no banco
            $subscriptionModel->createOrUpdate(
                $tenantId,
                $subscription['customer_id'],
                $stripeSubscription->toArray()
            );

            // Busca assinatura atualizada no banco
            $updatedSubscription = $subscriptionModel->findById((int)$id);

            // Prepara dados novos para histórico
            $newData = [
                'status' => $updatedSubscription['status'],
                'cancel_at_period_end' => $updatedSubscription['cancel_at_period_end']
            ];

            // Registra no histórico
            $userId = Flight::get('user_id'); // Pode ser null se for API Key
            $historyModel = new \App\Models\SubscriptionHistory();
            $historyModel->recordChange(
                (int)$id,
                $tenantId,
                \App\Models\SubscriptionHistory::CHANGE_TYPE_REACTIVATED,
                $oldData,
                $newData,
                \App\Models\SubscriptionHistory::CHANGED_BY_API,
                'Assinatura reativada - cancelamento no final do período removido',
                $userId
            );

            ResponseHelper::sendSuccess([
                'id' => $updatedSubscription['id'],
                'stripe_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                'current_period_start' => $stripeSubscription->current_period_start ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start) : null,
                'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) : null,
                'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d H:i:s', $stripeSubscription->canceled_at) : null
            ], 200, 'Assinatura reativada com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError(
                $e->getMessage(),
                [],
                ['action' => 'reactivate_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao reativar assinatura no Stripe',
                ['action' => 'reactivate_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao reativar assinatura',
                'SUBSCRIPTION_REACTIVATE_ERROR',
                ['action' => 'reactivate_subscription', 'subscription_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Lista histórico de mudanças de uma assinatura
     * GET /v1/subscriptions/:id/history
     * 
     * Query params opcionais:
     *   - limit: Limite de resultados (padrão: 100, máximo: 500)
     *   - offset: Offset para paginação (padrão: 0)
     *   - change_type: Filtrar por tipo de mudança (created, updated, canceled, reactivated, plan_changed, status_changed)
     *   - changed_by: Filtrar por origem (api, webhook, admin)
     *   - user_id: Filtrar por ID do usuário que fez a mudança
     *   - date_from: Data inicial (Y-m-d ou Y-m-d H:i:s)
     *   - date_to: Data final (Y-m-d ou Y-m-d H:i:s)
     */
    public function history(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_subscriptions');
            
            // Valida ID
            $idErrors = Validator::validateId($id, 'id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID inválido',
                    $idErrors,
                    ['action' => 'cancel_subscription', 'subscription_id' => $id]
                );
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode(['error' => 'Não autenticado']));
                return;
            }

            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                Flight::halt(404, json_encode(['error' => 'Assinatura não encontrada']));
                return;
            }

            // ✅ CORREÇÃO: Flight::request()->query retorna Collection, precisa converter para array
            // Segue o mesmo padrão usado em PayoutController e ReportController
            try {
                $queryParams = Flight::request()->query->getData();
                if (!is_array($queryParams)) {
                    $queryParams = [];
                }
            } catch (\Exception $e) {
                // Se houver erro ao obter query params, usa array vazio
                error_log("Erro ao obter query params: " . $e->getMessage());
                $queryParams = [];
            }
            
            // ✅ CORREÇÃO: Valida paginação com tratamento de erro
            try {
                $pagination = Validator::validatePagination($queryParams);
                if (!empty($pagination['errors'])) {
                    ResponseHelper::sendValidationError(
                        'Parâmetros de paginação inválidos',
                        $pagination['errors'],
                        ['action' => 'subscription_history', 'subscription_id' => $id, 'tenant_id' => $tenantId]
                    );
                    return;
                }
                
                // Usa valores validados (limite máximo de 500 para histórico)
                $limit = isset($pagination['limit']) ? min($pagination['limit'], 500) : 100;
                $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
                $offset = max($offset, 0); // Não pode ser negativo
            } catch (\Exception $e) {
                // ✅ Se houver erro na validação, usa valores padrão
                error_log("Erro ao validar paginação: " . $e->getMessage());
                $limit = 100;
                $offset = 0;
            }

            // Filtros opcionais
            $filters = [];
            
            if (!empty($queryParams['change_type'])) {
                $validTypes = [
                    \App\Models\SubscriptionHistory::CHANGE_TYPE_CREATED,
                    \App\Models\SubscriptionHistory::CHANGE_TYPE_UPDATED,
                    \App\Models\SubscriptionHistory::CHANGE_TYPE_CANCELED,
                    \App\Models\SubscriptionHistory::CHANGE_TYPE_REACTIVATED,
                    \App\Models\SubscriptionHistory::CHANGE_TYPE_PLAN_CHANGED,
                    \App\Models\SubscriptionHistory::CHANGE_TYPE_STATUS_CHANGED
                ];
                if (in_array($queryParams['change_type'], $validTypes)) {
                    $filters['change_type'] = $queryParams['change_type'];
                }
            }
            
            if (!empty($queryParams['changed_by'])) {
                $validSources = [
                    \App\Models\SubscriptionHistory::CHANGED_BY_API,
                    \App\Models\SubscriptionHistory::CHANGED_BY_WEBHOOK,
                    \App\Models\SubscriptionHistory::CHANGED_BY_ADMIN
                ];
                if (in_array($queryParams['changed_by'], $validSources)) {
                    $filters['changed_by'] = $queryParams['changed_by'];
                }
            }
            
            if (isset($queryParams['user_id']) && $queryParams['user_id'] !== '') {
                $filters['user_id'] = (int) $queryParams['user_id'];
            }
            
            if (!empty($queryParams['date_from'])) {
                // Se não tiver hora, adiciona 00:00:00
                $dateFrom = $queryParams['date_from'];
                if (strlen($dateFrom) === 10) {
                    $dateFrom .= ' 00:00:00';
                }
                $filters['date_from'] = $dateFrom;
            }
            
            if (!empty($queryParams['date_to'])) {
                // Se não tiver hora, adiciona 23:59:59
                $dateTo = $queryParams['date_to'];
                if (strlen($dateTo) === 10) {
                    $dateTo .= ' 23:59:59';
                }
                $filters['date_to'] = $dateTo;
            }

            // Busca histórico
            // ✅ CORREÇÃO: Tratamento robusto - se a tabela não existir ou houver erro, retorna vazio
            $history = [];
            $total = 0;
            try {
                $historyModel = new \App\Models\SubscriptionHistory();
                $history = $historyModel->findBySubscription((int)$id, $tenantId, $limit, $offset, $filters);
                $total = $historyModel->countBySubscription((int)$id, $tenantId, $filters);
            } catch (\PDOException $e) {
                // ✅ Erro de banco de dados (tabela não existe, etc)
                error_log("Erro PDO ao buscar histórico de assinatura {$id}: " . $e->getMessage());
                $history = [];
                $total = 0;
            } catch (\Exception $e) {
                // ✅ Qualquer outro erro
                error_log("Erro ao buscar histórico de assinatura {$id}: " . $e->getMessage());
                $history = [];
                $total = 0;
            }

            // ✅ CORREÇÃO: Retorna array diretamente em data, meta separado
            Flight::json([
                'success' => true,
                'data' => $history,
                'meta' => [
                    'filters_applied' => $filters,
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            // ✅ CORREÇÃO: Em caso de erro crítico, retorna resposta vazia em vez de 500
            error_log("Erro crítico ao obter histórico de assinatura: " . $e->getMessage());
            Flight::json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'filters_applied' => [],
                    'pagination' => [
                        'total' => 0,
                        'limit' => 100,
                        'offset' => 0,
                        'has_more' => false
                    ],
                    'warning' => 'Histórico não disponível no momento'
                ]
            ]);
        }
    }

    /**
     * Obtém estatísticas do histórico de uma assinatura
     * GET /v1/subscriptions/:id/history/stats
     */
    public function historyStats(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_subscriptions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode(['error' => 'Não autenticado']));
                return;
            }

            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                Flight::halt(404, json_encode(['error' => 'Assinatura não encontrada']));
                return;
            }

            // Busca estatísticas
            $historyModel = new \App\Models\SubscriptionHistory();
            $stats = $historyModel->getStatistics((int)$id, $tenantId);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter estatísticas do histórico',
                'SUBSCRIPTION_HISTORY_STATS_ERROR',
                ['action' => 'subscription_history_stats', 'subscription_id' => $id, 'tenant_id' => $tenantId]
            );
        }
    }
}


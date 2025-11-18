<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\Validator;
use App\Utils\ErrorHandler;
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
                    Flight::json(['error' => 'JSON inválido no corpo da requisição: ' . json_last_error_msg()], 400);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');
            
            // Validação rigorosa de inputs
            $errors = Validator::validateSubscriptionCreate($data);
            if (!empty($errors)) {
                Flight::json([
                    'error' => 'Dados inválidos',
                    'message' => 'Por favor, verifique os dados informados',
                    'errors' => $errors
                ], 400);
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

            Flight::json([
                'success' => true,
                'data' => $subscription
            ], 201);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $response = ErrorHandler::prepareStripeErrorResponse($e, 'Erro ao criar assinatura no Stripe');
            Flight::json($response, 500);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao criar assinatura', 'SUBSCRIPTION_CREATE_ERROR');
            Flight::json($response, 500);
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
                Flight::json($cached);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            $result = $subscriptionModel->findByTenant($tenantId, $page, $limit, $filters);

            $response = [
                'success' => true,
                'data' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total_pages' => $result['total_pages']
                ]
            ];
            
            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $response, 60);
            
            Flight::json($response);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao listar assinaturas', 'SUBSCRIPTION_LIST_ERROR');
            Flight::json($response, 500);
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
                Flight::json([
                    'error' => 'ID inválido',
                    'errors' => $idErrors
                ], 400);
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                Flight::json(['error' => 'Assinatura não encontrada'], 404);
                return;
            }

            // ✅ CACHE: Verifica se há cache válido (TTL: 5 minutos)
            $cacheKey = "subscriptions:get:{$tenantId}:{$id}";
            $cached = \App\Services\CacheService::getJson($cacheKey);
            
            if ($cached !== null) {
                Flight::json([
                    'success' => true,
                    'data' => $cached
                ]);
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

            // Prepara resposta
            $responseData = [
                'id' => $subscription['id'],
                'stripe_subscription_id' => $stripeSubscription->id,
                'customer_id' => $subscription['customer_id'],
                'status' => $stripeSubscription->status,
                'current_period_start' => $stripeSubscription->current_period_start ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start) : null,
                'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) : null,
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
                'created' => date('Y-m-d H:i:s', $stripeSubscription->created)
            ];

            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $responseData, 300); // 5 minutos

            Flight::json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::warning("Assinatura não encontrada no Stripe", ['subscription_id' => (int)$id]);
            Flight::json(['error' => 'Assinatura não encontrada'], 404);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao obter assinatura', 'SUBSCRIPTION_GET_ERROR');
            Flight::json($response, 500);
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
                Flight::json([
                    'error' => 'ID inválido',
                    'errors' => $idErrors
                ], 400);
                return;
            }
            
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Flight::json(['error' => 'JSON inválido no corpo da requisição: ' . json_last_error_msg()], 400);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');
            
            // Validação rigorosa de inputs
            $errors = Validator::validateSubscriptionUpdate($data);
            if (!empty($errors)) {
                Flight::json([
                    'error' => 'Dados inválidos',
                    'message' => 'Por favor, verifique os dados informados',
                    'errors' => $errors
                ], 400);
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
                Flight::json(['error' => 'Nenhum campo válido para atualização fornecido'], 400);
                return;
            }
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                Flight::json(['error' => 'Assinatura não encontrada'], 404);
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

            Flight::json([
                'success' => true,
                'message' => 'Assinatura atualizada com sucesso',
                'data' => [
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
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $response = ErrorHandler::prepareStripeErrorResponse($e, 'Erro ao atualizar assinatura no Stripe');
            Flight::json($response, 400);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao atualizar assinatura', 'SUBSCRIPTION_UPDATE_ERROR');
            Flight::json($response, 500);
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
                Flight::json([
                    'error' => 'ID inválido',
                    'errors' => $idErrors
                ], 400);
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                Flight::json(['error' => 'Assinatura não encontrada'], 404);
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

            Flight::json([
                'success' => true,
                'message' => $immediately 
                    ? 'Assinatura cancelada imediatamente' 
                    : 'Assinatura será cancelada no final do período',
                'data' => [
                    'id' => $updatedSubscription['id'],
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'status' => $stripeSubscription->status,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                    'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d H:i:s', $stripeSubscription->canceled_at) : null,
                    'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) : null
                ]
            ]);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao cancelar assinatura', 'SUBSCRIPTION_CANCEL_ERROR');
            Flight::json($response, 500);
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
                Flight::json([
                    'error' => 'ID inválido',
                    'errors' => $idErrors
                ], 400);
                return;
            }
            
            $tenantId = Flight::get('tenant_id');
            
            // VALIDAÇÃO RIGOROSA: tenant_id não pode ser null
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            $subscriptionModel = new \App\Models\Subscription();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $subscription = $subscriptionModel->findByTenantAndId($tenantId, (int)$id);

            if (!$subscription) {
                Flight::json(['error' => 'Assinatura não encontrada'], 404);
                return;
            }

            // Obtém assinatura atual do Stripe para verificar status
            $currentStripeSubscription = $this->stripeService->getSubscription($subscription['stripe_subscription_id']);
            
            // Valida se pode ser reativada
            if ($currentStripeSubscription->status === 'canceled') {
                http_response_code(400);
                Flight::json([
                    'error' => 'Assinatura já está cancelada',
                    'message' => 'Assinaturas canceladas não podem ser reativadas. Crie uma nova assinatura.'
                ], 400);
                return;
            }

            if (!$currentStripeSubscription->cancel_at_period_end) {
                Flight::json([
                    'success' => true,
                    'message' => 'Assinatura já está ativa e não estava marcada para cancelar',
                    'data' => [
                        'id' => $subscription['id'],
                        'stripe_subscription_id' => $currentStripeSubscription->id,
                        'status' => $currentStripeSubscription->status,
                        'cancel_at_period_end' => $currentStripeSubscription->cancel_at_period_end
                    ]
                ]);
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

            Flight::json([
                'success' => true,
                'message' => 'Assinatura reativada com sucesso',
                'data' => [
                    'id' => $updatedSubscription['id'],
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'status' => $stripeSubscription->status,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                    'current_period_start' => $stripeSubscription->current_period_start ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start) : null,
                    'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end) : null,
                    'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d H:i:s', $stripeSubscription->canceled_at) : null
                ]
            ]);
        } catch (\RuntimeException $e) {
            $response = ErrorHandler::prepareErrorResponse($e, $e->getMessage(), 'SUBSCRIPTION_REACTIVATE_ERROR');
            Flight::json($response, 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $response = ErrorHandler::prepareStripeErrorResponse($e, 'Erro ao reativar assinatura no Stripe');
            Flight::json($response, 400);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao reativar assinatura', 'SUBSCRIPTION_REACTIVATE_ERROR');
            Flight::json($response, 500);
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
                Flight::json([
                    'error' => 'ID inválido',
                    'errors' => $idErrors
                ], 400);
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

            $queryParams = Flight::request()->query;
            
            // Valida paginação
            $pagination = Validator::validatePagination($queryParams);
            if (!empty($pagination['errors'])) {
                Flight::json([
                    'error' => 'Parâmetros de paginação inválidos',
                    'errors' => $pagination['errors']
                ], 400);
                return;
            }
            
            // Usa valores validados (limite máximo de 500 para histórico)
            $limit = min($pagination['limit'], 500);
            $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
            $offset = max($offset, 0); // Não pode ser negativo

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
            $historyModel = new \App\Models\SubscriptionHistory();
            $history = $historyModel->findBySubscription((int)$id, $tenantId, $limit, $offset, $filters);
            $total = $historyModel->countBySubscription((int)$id, $tenantId, $filters);

            Flight::json([
                'success' => true,
                'data' => $history,
                'filters_applied' => $filters,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao obter histórico de assinatura', 'SUBSCRIPTION_HISTORY_ERROR');
            Flight::halt(500, json_encode($response));
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

            Flight::json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            $response = ErrorHandler::prepareErrorResponse($e, 'Erro ao obter estatísticas do histórico', 'SUBSCRIPTION_HISTORY_STATS_ERROR');
            Flight::halt(500, json_encode($response));
        }
    }
}


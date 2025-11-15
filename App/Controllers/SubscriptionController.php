<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
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
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tenantId = Flight::get('tenant_id');

            if (empty($data['customer_id']) || empty($data['price_id'])) {
                Flight::json(['error' => 'customer_id e price_id são obrigatórios'], 400);
                return;
            }

            $subscription = $this->paymentService->createSubscription(
                $tenantId,
                $data['customer_id'],
                $data['price_id'],
                $data['metadata'] ?? [],
                $data['trial_period_days'] ?? null,
                $data['payment_behavior'] ?? null
            );

            Flight::json([
                'success' => true,
                'data' => $subscription
            ], 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar assinatura", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao criar assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Lista assinaturas do tenant
     * GET /v1/subscriptions
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_subscriptions');
            
            $tenantId = Flight::get('tenant_id');
            $subscriptionModel = new \App\Models\Subscription();
            $subscriptions = $subscriptionModel->findByTenant($tenantId);

            Flight::json([
                'success' => true,
                'data' => $subscriptions,
                'count' => count($subscriptions)
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar assinaturas", ['error' => $e->getMessage()]);
            Flight::json(['error' => 'Erro ao listar assinaturas'], 500);
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
            
            $tenantId = Flight::get('tenant_id');
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findById((int)$id);

            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Assinatura não encontrada'], 404);
                return;
            }

            // Busca dados atualizados no Stripe
            $stripeSubscription = $this->stripeService->getSubscription($subscription['stripe_subscription_id']);

            // Atualiza no banco com dados do Stripe
            $subscriptionModel->createOrUpdate(
                $tenantId,
                $subscription['customer_id'],
                $stripeSubscription->toArray()
            );

            // Busca assinatura atualizada no banco
            $updatedSubscription = $subscriptionModel->findById((int)$id);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $updatedSubscription['id'],
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'customer_id' => $updatedSubscription['customer_id'],
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
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Assinatura não encontrada no Stripe", ['subscription_id' => $id]);
            http_response_code(404);
            Flight::json(['error' => 'Assinatura não encontrada'], 404);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter assinatura", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao obter assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tenantId = Flight::get('tenant_id');
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findById((int)$id);

            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Assinatura não encontrada'], 404);
                return;
            }

            // Valida se há dados para atualizar
            $allowedFields = ['price_id', 'quantity', 'metadata', 'proration_behavior', 'cancel_at_period_end', 'trial_end'];
            $hasUpdates = false;
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $hasUpdates = true;
                    break;
                }
            }

            if (!$hasUpdates) {
                http_response_code(400);
                Flight::json(['error' => 'Nenhum campo válido para atualização fornecido'], 400);
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
            Logger::error("Erro ao atualizar assinatura no Stripe", ['error' => $e->getMessage()]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao atualizar assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar assinatura", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao atualizar assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
            
            $tenantId = Flight::get('tenant_id');
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findById((int)$id);

            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
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
            Logger::error("Erro ao cancelar assinatura", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao cancelar assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
            
            $tenantId = Flight::get('tenant_id');
            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findById((int)$id);

            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                http_response_code(404);
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
            Logger::error("Erro ao reativar assinatura", ['error' => $e->getMessage()]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao reativar assinatura',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao reativar assinatura no Stripe", ['error' => $e->getMessage()]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao reativar assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao reativar assinatura", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao reativar assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::halt(401, json_encode(['error' => 'Não autenticado']));
                return;
            }

            $subscriptionModel = new \App\Models\Subscription();
            $subscription = $subscriptionModel->findById((int)$id);

            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
                Flight::halt(404, json_encode(['error' => 'Assinatura não encontrada']));
                return;
            }

            $queryParams = Flight::request()->query;
            
            // Paginação
            $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 100;
            $limit = min(max($limit, 1), 500); // Entre 1 e 500
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
            Logger::error("Erro ao obter histórico de assinatura", [
                'error' => $e->getMessage(),
                'subscription_id' => $id
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao obter histórico de assinatura',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
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
            $subscription = $subscriptionModel->findById((int)$id);

            if (!$subscription || $subscription['tenant_id'] != $tenantId) {
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
            Logger::error("Erro ao obter estatísticas do histórico", [
                'error' => $e->getMessage(),
                'subscription_id' => $id
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao obter estatísticas do histórico',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }
}


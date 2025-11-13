<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
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

            // Atualiza no banco
            $subscriptionModel->createOrUpdate(
                $tenantId,
                $subscription['customer_id'],
                $stripeSubscription->toArray()
            );

            // Busca assinatura atualizada no banco
            $updatedSubscription = $subscriptionModel->findById((int)$id);

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
}


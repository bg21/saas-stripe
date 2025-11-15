<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar Setup Intents
 * 
 * Setup Intents permitem salvar métodos de pagamento sem processar um pagamento.
 * Útil para trial periods, pré-cadastro de cartões e upgrades futuros.
 */
class SetupIntentController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo Setup Intent
     * POST /v1/setup-intents
     * 
     * Body:
     *   - customer_id (opcional): ID do customer no Stripe
     *   - payment_method_types (opcional): Tipos de pagamento (padrão: ['card'])
     *   - description (opcional): Descrição
     *   - metadata (opcional): Metadados
     *   - usage (opcional): 'off_session' ou 'on_session' (padrão: 'off_session')
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if ($data === null) {
                $data = [];
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $setupIntent = $this->stripeService->createSetupIntent($data);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $setupIntent->id,
                    'client_secret' => $setupIntent->client_secret,
                    'status' => $setupIntent->status,
                    'customer' => $setupIntent->customer ?? null,
                    'payment_method' => $setupIntent->payment_method ?? null,
                    'payment_method_types' => $setupIntent->payment_method_types,
                    'usage' => $setupIntent->usage ?? 'off_session',
                    'description' => $setupIntent->description ?? null,
                    'created' => date('Y-m-d H:i:s', $setupIntent->created),
                    'metadata' => $setupIntent->metadata->toArray()
                ]
            ], 201);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao criar setup intent no Stripe", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar setup intent',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar setup intent", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar setup intent',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém Setup Intent por ID
     * GET /v1/setup-intents/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $setupIntent = $this->stripeService->getSetupIntent($id);

            // Valida se pertence ao tenant (via metadata)
            if (isset($setupIntent->metadata->tenant_id) && 
                (string)$setupIntent->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Setup intent não encontrado'], 404);
                return;
            }

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $setupIntent->id,
                    'client_secret' => $setupIntent->client_secret,
                    'status' => $setupIntent->status,
                    'customer' => $setupIntent->customer ?? null,
                    'payment_method' => $setupIntent->payment_method ?? null,
                    'payment_method_types' => $setupIntent->payment_method_types,
                    'usage' => $setupIntent->usage ?? 'off_session',
                    'description' => $setupIntent->description ?? null,
                    'created' => date('Y-m-d H:i:s', $setupIntent->created),
                    'metadata' => $setupIntent->metadata->toArray()
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Logger::error("Setup intent não encontrado", ['setup_intent_id' => $id]);
                Flight::json([
                    'error' => 'Setup intent não encontrado',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 404);
            } else {
                Logger::error("Erro ao obter setup intent", [
                    'error' => $e->getMessage(),
                    'setup_intent_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao obter setup intent',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao obter setup intent", [
                'error' => $e->getMessage(),
                'setup_intent_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter setup intent',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Confirma Setup Intent
     * POST /v1/setup-intents/:id/confirm
     * 
     * Body:
     *   - payment_method (opcional): ID do método de pagamento
     *   - return_url (opcional): URL de retorno após confirmação
     */
    public function confirm(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Primeiro, verifica se o setup intent existe e pertence ao tenant
            $setupIntent = $this->stripeService->getSetupIntent($id);
            
            if (isset($setupIntent->metadata->tenant_id) && 
                (string)$setupIntent->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Setup intent não encontrado'], 404);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if ($data === null) {
                $data = [];
            }

            $setupIntent = $this->stripeService->confirmSetupIntent($id, $data);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $setupIntent->id,
                    'client_secret' => $setupIntent->client_secret,
                    'status' => $setupIntent->status,
                    'customer' => $setupIntent->customer ?? null,
                    'payment_method' => $setupIntent->payment_method ?? null,
                    'payment_method_types' => $setupIntent->payment_method_types,
                    'usage' => $setupIntent->usage ?? 'off_session',
                    'created' => date('Y-m-d H:i:s', $setupIntent->created),
                    'metadata' => $setupIntent->metadata->toArray()
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Setup intent não encontrado'], 404);
            } else {
                Logger::error("Erro ao confirmar setup intent", [
                    'error' => $e->getMessage(),
                    'setup_intent_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao confirmar setup intent',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao confirmar setup intent", [
                'error' => $e->getMessage(),
                'setup_intent_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


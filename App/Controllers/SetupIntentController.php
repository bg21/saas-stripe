<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_setup_intent']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_setup_intent']);
                    return;
                }
                $data = [];
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $setupIntent = $this->stripeService->createSetupIntent($data);

            ResponseHelper::sendCreated([
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
            ], 'Setup intent criado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar setup intent', ['action' => 'create_setup_intent', 'tenant_id' => $tenantId ?? null]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar setup intent', 'SETUP_INTENT_CREATE_ERROR', ['action' => 'create_setup_intent', 'tenant_id' => $tenantId ?? null]);
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_setup_intent']);
                return;
            }

            $setupIntent = $this->stripeService->getSetupIntent($id);

            // Valida se pertence ao tenant (via metadata)
            if (isset($setupIntent->metadata->tenant_id) && 
                (string)$setupIntent->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Setup intent', ['action' => 'get_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            ResponseHelper::sendSuccess([
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
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Setup intent', ['action' => 'get_setup_intent', 'setup_intent_id' => $id]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter setup intent',
                    ['action' => 'get_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter setup intent',
                'SETUP_INTENT_GET_ERROR',
                ['action' => 'get_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_setup_intent']);
                return;
            }

            // Primeiro, verifica se o setup intent existe e pertence ao tenant
            $setupIntent = $this->stripeService->getSetupIntent($id);
            
            if (isset($setupIntent->metadata->tenant_id) && 
                (string)$setupIntent->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Setup intent', ['action' => 'confirm_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'confirm_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            $setupIntent = $this->stripeService->confirmSetupIntent($id, $data);

            ResponseHelper::sendSuccess([
                'id' => $setupIntent->id,
                'client_secret' => $setupIntent->client_secret,
                'status' => $setupIntent->status,
                'customer' => $setupIntent->customer ?? null,
                'payment_method' => $setupIntent->payment_method ?? null,
                'payment_method_types' => $setupIntent->payment_method_types,
                'usage' => $setupIntent->usage ?? 'off_session',
                'created' => date('Y-m-d H:i:s', $setupIntent->created),
                'metadata' => $setupIntent->metadata->toArray()
            ], 200, 'Setup intent confirmado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Setup intent', ['action' => 'confirm_setup_intent', 'setup_intent_id' => $id]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao confirmar setup intent',
                    ['action' => 'confirm_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao confirmar setup intent',
                'SETUP_INTENT_CONFIRM_ERROR',
                ['action' => 'confirm_setup_intent', 'setup_intent_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


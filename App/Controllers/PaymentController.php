<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar pagamentos únicos e reembolsos
 */
class PaymentController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria Payment Intent para pagamento único
     * POST /v1/payment-intents
     * 
     * Body:
     *   - amount (obrigatório): Valor em centavos
     *   - currency (obrigatório): Moeda (ex: 'brl', 'usd')
     *   - customer_id (opcional): ID do customer no Stripe
     *   - payment_method (opcional): ID do método de pagamento
     *   - description (opcional): Descrição do pagamento
     *   - metadata (opcional): Metadados
     *   - confirm (opcional): Se true, confirma o pagamento imediatamente
     *   - capture_method (opcional): 'automatic' ou 'manual'
     */
    public function createPaymentIntent(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Inicializa $data se for null
            if ($data === null) {
                $data = [];
            }

            // Validações obrigatórias
            if (empty($data['amount']) || !is_numeric($data['amount'])) {
                Flight::json(['error' => 'Campo amount é obrigatório e deve ser numérico'], 400);
                return;
            }

            if (empty($data['currency'])) {
                Flight::json(['error' => 'Campo currency é obrigatório'], 400);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $paymentIntent = $this->stripeService->createPaymentIntent($data);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'amount' => $paymentIntent->amount,
                    'currency' => strtoupper($paymentIntent->currency),
                    'status' => $paymentIntent->status,
                    'description' => $paymentIntent->description,
                    'customer' => $paymentIntent->customer,
                    'payment_method' => $paymentIntent->payment_method,
                    'created' => date('Y-m-d H:i:s', $paymentIntent->created),
                    'metadata' => $paymentIntent->metadata->toArray()
                ]
            ], 201);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao criar payment intent", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar payment intent',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar payment intent", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar payment intent',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reembolsa um pagamento
     * POST /v1/refunds
     * 
     * Body:
     *   - payment_intent_id (obrigatório): ID do Payment Intent a ser reembolsado
     *   - amount (opcional): Valor em centavos para reembolso parcial
     *   - reason (opcional): Motivo do reembolso ('duplicate', 'fraudulent', 'requested_by_customer')
     *   - metadata (opcional): Metadados do reembolso
     */
    public function createRefund(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Inicializa $data se for null
            if ($data === null) {
                $data = [];
            }

            // Validações obrigatórias
            if (empty($data['payment_intent_id'])) {
                Flight::json(['error' => 'Campo payment_intent_id é obrigatório'], 400);
                return;
            }

            // Prepara opções de reembolso
            $options = [];
            
            if (isset($data['amount'])) {
                $options['amount'] = (int)$data['amount'];
            }

            if (!empty($data['reason'])) {
                $options['reason'] = $data['reason'];
            }

            if (isset($data['metadata'])) {
                $options['metadata'] = $data['metadata'];
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($options['metadata'])) {
                $options['metadata'] = [];
            }
            $options['metadata']['tenant_id'] = $tenantId;

            $refund = $this->stripeService->refundPayment($data['payment_intent_id'], $options);

            Flight::json([
                'success' => true,
                'message' => 'Reembolso criado com sucesso',
                'data' => [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'currency' => strtoupper($refund->currency),
                    'status' => $refund->status,
                    'reason' => $refund->reason,
                    'payment_intent' => $refund->payment_intent,
                    'created' => date('Y-m-d H:i:s', $refund->created),
                    'metadata' => $refund->metadata->toArray()
                ]
            ], 201);
        } catch (\RuntimeException $e) {
            Logger::error("Erro ao criar reembolso", [
                'error' => $e->getMessage(),
                'payment_intent_id' => $data['payment_intent_id'] ?? null,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar reembolso',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao criar reembolso no Stripe", [
                'error' => $e->getMessage(),
                'payment_intent_id' => $data['payment_intent_id'] ?? null,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar reembolso',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar reembolso", [
                'error' => $e->getMessage(),
                'payment_intent_id' => $data['payment_intent_id'] ?? null,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar reembolso',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


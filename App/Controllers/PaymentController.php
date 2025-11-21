<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_payment_intent']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_payment_intent']);
                    return;
                }
                $data = [];
            }

            // ✅ Validação consistente usando Validator
            $errors = \App\Utils\Validator::validatePaymentIntentCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_payment_intent', 'tenant_id' => $tenantId]
                );
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $paymentIntent = $this->stripeService->createPaymentIntent($data);

            ResponseHelper::sendCreated([
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
            ], 'Payment intent criado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar payment intent', ['action' => 'create_payment_intent', 'tenant_id' => $tenantId ?? null]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar payment intent', 'PAYMENT_INTENT_CREATE_ERROR', ['action' => 'create_payment_intent', 'tenant_id' => $tenantId ?? null]);
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_payment_intent']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_payment_intent']);
                    return;
                }
                $data = [];
            }

            // ✅ Validação consistente
            $errors = [];
            
            // payment_intent_id: obrigatório, deve ser ID Stripe válido
            if (empty($data['payment_intent_id'])) {
                $errors['payment_intent_id'] = 'Obrigatório';
            } else {
                $paymentIntentErrors = \App\Utils\Validator::validateStripeId($data['payment_intent_id'], 'payment_intent_id');
                if (!empty($paymentIntentErrors)) {
                    $errors = array_merge($errors, $paymentIntentErrors);
                }
            }
            
            // amount: opcional, mas se presente deve ser válido
            if (isset($data['amount'])) {
                if (!is_numeric($data['amount'])) {
                    $errors['amount'] = 'Deve ser um número';
                } else {
                    $amount = (int)$data['amount'];
                    if ($amount < 1) {
                        $errors['amount'] = 'Deve ser maior que zero';
                    }
                }
            }
            
            // reason: opcional, valores permitidos
            if (isset($data['reason'])) {
                $allowedReasons = ['duplicate', 'fraudulent', 'requested_by_customer'];
                if (!is_string($data['reason']) || !in_array($data['reason'], $allowedReasons, true)) {
                    $errors['reason'] = 'Deve ser "duplicate", "fraudulent" ou "requested_by_customer"';
                }
            }
            
            // metadata: validação padrão
            if (isset($data['metadata'])) {
                $metadataError = \App\Utils\Validator::validateMetadata($data['metadata'], 'metadata');
                if (!empty($metadataError)) {
                    $errors = array_merge($errors, $metadataError);
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_refund', 'tenant_id' => $tenantId]
                );
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

            ResponseHelper::sendCreated([
                'id' => $refund->id,
                'amount' => $refund->amount,
                'currency' => strtoupper($refund->currency),
                'status' => $refund->status,
                'reason' => $refund->reason,
                'payment_intent' => $refund->payment_intent,
                'created' => date('Y-m-d H:i:s', $refund->created),
                'metadata' => $refund->metadata->toArray()
            ], 'Reembolso criado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError(
                $e->getMessage(),
                [],
                ['action' => 'create_refund', 'payment_intent_id' => $data['payment_intent_id'] ?? null, 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar reembolso no Stripe',
                ['action' => 'create_refund', 'payment_intent_id' => $data['payment_intent_id'] ?? null, 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar reembolso',
                'REFUND_CREATE_ERROR',
                ['action' => 'create_refund', 'payment_intent_id' => $data['payment_intent_id'] ?? null, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


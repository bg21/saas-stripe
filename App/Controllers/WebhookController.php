<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para receber webhooks do Stripe
 */
class WebhookController
{
    private PaymentService $paymentService;
    private StripeService $stripeService;

    public function __construct(PaymentService $paymentService, StripeService $stripeService)
    {
        $this->paymentService = $paymentService;
        $this->stripeService = $stripeService;
    }

    /**
     * Recebe e processa webhook do Stripe
     * POST /v1/webhook
     */
    public function handle(): void
    {
        try {
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $payload = \App\Utils\RequestCache::getInput();
            $payloadSize = strlen($payload);
            
            Logger::info("=== WEBHOOK RECEBIDO ===", [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'payload_size' => $payloadSize,
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
            ]);
            
            // Obtém header Stripe-Signature
            $signature = null;
            $headers = [];
            
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            } else {
                // Fallback para CLI ou quando getallheaders não está disponível
                foreach ($_SERVER as $key => $value) {
                    if (strpos($key, 'HTTP_') === 0) {
                        $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                        $headers[$headerName] = $value;
                    }
                }
            }
            
            // Tenta obter Stripe-Signature de várias formas
            $signature = $headers['Stripe-Signature'] ?? 
                        $headers['stripe-signature'] ?? 
                        $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? 
                        null;

            if (empty($signature)) {
                Logger::error("Webhook sem signature", [
                    'headers_available' => array_keys($headers),
                    'has_stripe_signature' => isset($headers['Stripe-Signature']) || isset($headers['stripe-signature'])
                ]);
                Flight::json(['error' => 'Signature não fornecida'], 400);
                return;
            }

            Logger::info("Validando signature do webhook", [
                'signature_length' => strlen($signature),
                'signature_prefix' => substr($signature, 0, 10) . '...'
            ]);

            // Valida signature
            $event = $this->stripeService->validateWebhook($payload, $signature);

            Logger::info("Webhook validado e recebido", [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'event_created' => $event->created ?? 'N/A'
            ]);

            // Verifica idempotência ANTES de processar (proteção contra replay attacks)
            $eventModel = new \App\Models\StripeEvent();
            if ($eventModel->isProcessed($event->id)) {
                Logger::info("Webhook já processado anteriormente (idempotência)", [
                    'event_id' => $event->id,
                    'event_type' => $event->type
                ]);
                
                // Retorna sucesso para evitar que o Stripe reenvie
                Flight::json([
                    'success' => true,
                    'message' => 'Evento já processado anteriormente',
                    'event_id' => $event->id
                ], 200);
                return;
            }

            // Processa webhook
            $this->paymentService->processWebhook($event);

            Logger::info("Webhook processado com sucesso", [
                'event_id' => $event->id,
                'event_type' => $event->type
            ]);

            Flight::json(['success' => true, 'received' => true]);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Logger::error("Webhook signature inválida", ['error' => $e->getMessage()]);
            Flight::json(['error' => 'Signature inválida'], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao processar webhook", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao processar webhook',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


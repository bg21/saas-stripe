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
            $payload = file_get_contents('php://input');
            $signature = Flight::request()->getHeader('Stripe-Signature');

            if (empty($signature)) {
                Logger::error("Webhook sem signature");
                Flight::json(['error' => 'Signature não fornecida'], 400);
                return;
            }

            // Valida signature
            $event = $this->stripeService->validateWebhook($payload, $signature);

            Logger::info("Webhook recebido", [
                'event_id' => $event->id,
                'event_type' => $event->type
            ]);

            // Processa webhook
            $this->paymentService->processWebhook($event);

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


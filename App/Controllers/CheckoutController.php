<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar sessões de checkout
 */
class CheckoutController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria sessão de checkout
     * POST /v1/checkout
     */
    public function create(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tenantId = Flight::get('tenant_id');

            // Validações
            if (empty($data['line_items']) || !is_array($data['line_items'])) {
                Flight::json(['error' => 'line_items é obrigatório'], 400);
                return;
            }

            if (empty($data['success_url']) || empty($data['cancel_url'])) {
                Flight::json(['error' => 'success_url e cancel_url são obrigatórios'], 400);
                return;
            }

            // Adiciona metadata do tenant
            $data['metadata'] = array_merge($data['metadata'] ?? [], [
                'tenant_id' => $tenantId
            ]);

            $session = $this->stripeService->createCheckoutSession($data);

            Flight::json([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                    'url' => $session->url
                ]
            ], 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar sessão de checkout", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao criar sessão de checkout',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


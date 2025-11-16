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
     * 
     * Aceita dois formatos:
     * 1. Formato completo (Stripe): line_items, mode, etc.
     * 2. Formato simplificado: customer_id, price_id (converte automaticamente)
     */
    public function create(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tenantId = Flight::get('tenant_id');

            // Validações básicas
            if (empty($data['success_url']) || empty($data['cancel_url'])) {
                Flight::json(['error' => 'success_url e cancel_url são obrigatórios'], 400);
                return;
            }

            // Se não tem line_items, mas tem price_id, converte para formato Stripe
            if (empty($data['line_items']) && !empty($data['price_id'])) {
                // Formato simplificado: converte para line_items
                $data['line_items'] = [
                    [
                        'price' => $data['price_id'],
                        'quantity' => $data['quantity'] ?? 1
                    ]
                ];
                
                // Define mode como subscription por padrão (pode ser sobrescrito)
                if (empty($data['mode'])) {
                    $data['mode'] = 'subscription';
                }
                
                // Remove price_id do array (não é usado pelo Stripe diretamente)
                unset($data['price_id']);
            }

            // Validação: precisa ter line_items agora
            if (empty($data['line_items']) || !is_array($data['line_items'])) {
                Flight::json(['error' => 'line_items ou price_id é obrigatório'], 400);
                return;
            }

            // Se tem customer_id mas é ID do nosso banco, precisa buscar o stripe_customer_id
            if (!empty($data['customer_id']) && is_numeric($data['customer_id'])) {
                // É ID do nosso banco, precisa buscar o stripe_customer_id
                $customerModel = new \App\Models\Customer();
                $customer = $customerModel->findById((int)$data['customer_id']);
                
                if (!$customer) {
                    Flight::json(['error' => 'Cliente não encontrado'], 404);
                    return;
                }
                
                // Verifica se pertence ao tenant
                if ($customer['tenant_id'] != $tenantId) {
                    Flight::json(['error' => 'Cliente não pertence ao tenant'], 403);
                    return;
                }
                
                // Substitui pelo stripe_customer_id
                $data['customer_id'] = $customer['stripe_customer_id'];
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

    /**
     * Obtém sessão de checkout por ID
     * GET /v1/checkout/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                http_response_code(401);
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Obtém sessão do Stripe
            $session = $this->stripeService->getCheckoutSession($id);

            // Valida se a sessão pertence ao tenant (via metadata)
            if (isset($session->metadata->tenant_id) && (int)$session->metadata->tenant_id !== $tenantId) {
                http_response_code(403);
                Flight::json(['error' => 'Sessão não pertence ao tenant'], 403);
                return;
            }

            // Prepara resposta com dados completos
            $responseData = [
                'id' => $session->id,
                'url' => $session->url,
                'status' => $session->status,
                'mode' => $session->mode,
                'customer' => $session->customer,
                'customer_email' => $session->customer_email,
                'payment_status' => $session->payment_status,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
                'created' => date('Y-m-d H:i:s', $session->created),
                'expires_at' => $session->expires_at ? date('Y-m-d H:i:s', $session->expires_at) : null,
                'metadata' => $session->metadata->toArray()
            ];

            // Adiciona payment_intent se existir
            if ($session->payment_intent) {
                $responseData['payment_intent'] = [
                    'id' => $session->payment_intent->id,
                    'status' => $session->payment_intent->status,
                    'amount' => $session->payment_intent->amount,
                    'currency' => $session->payment_intent->currency
                ];
            }

            // Adiciona subscription se existir
            if ($session->subscription) {
                $responseData['subscription'] = [
                    'id' => $session->subscription->id,
                    'status' => $session->subscription->status
                ];
            }

            Flight::json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Sessão de checkout não encontrada", ['session_id' => $id]);
            http_response_code(404);
            Flight::json([
                'error' => 'Sessão de checkout não encontrada',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 404);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter sessão de checkout", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao obter sessão de checkout',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


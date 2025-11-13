<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar portal de cobrança
 */
class BillingPortalController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria sessão do portal de cobrança
     * POST /v1/billing-portal
     */
    public function create(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tenantId = Flight::get('tenant_id');

            if (empty($data['customer_id'])) {
                http_response_code(400);
                Flight::json(['error' => 'customer_id é obrigatório'], 400);
                return;
            }

            if (empty($data['return_url'])) {
                http_response_code(400);
                Flight::json(['error' => 'return_url é obrigatório'], 400);
                return;
            }

            // Busca customer para validar tenant
            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$data['customer_id']);

            if (!$customer || $customer['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Cliente não encontrado'], 404);
                return;
            }

            // Prepara opções opcionais conforme documentação do Stripe
            $options = [];
            
            // configuration: ID da configuração do portal (opcional)
            if (!empty($data['configuration'])) {
                $options['configuration'] = $data['configuration'];
            }
            
            // locale: Idioma do portal (ex: 'pt-BR', 'en', 'es') (opcional)
            if (!empty($data['locale'])) {
                $options['locale'] = $data['locale'];
            }
            
            // on_behalf_of: ID da conta conectada (opcional, para contas conectadas)
            if (!empty($data['on_behalf_of'])) {
                $options['on_behalf_of'] = $data['on_behalf_of'];
            }

            $session = $this->stripeService->createBillingPortalSession(
                $customer['stripe_customer_id'],
                $data['return_url'],
                $options
            );

            $responseData = [
                'session_id' => $session->id,
                'url' => $session->url,
                'customer' => $customer['stripe_customer_id'],
                'return_url' => $data['return_url'],
                'created' => date('Y-m-d H:i:s', $session->created)
            ];

            // Adiciona informações opcionais se foram fornecidas
            if (!empty($options['configuration'])) {
                $responseData['configuration'] = $options['configuration'];
            }
            if (!empty($options['locale'])) {
                $responseData['locale'] = $options['locale'];
            }

            Flight::json([
                'success' => true,
                'data' => $responseData
            ], 201);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao criar sessão de portal", ['error' => $e->getMessage()]);
            
            // Mensagem específica para configuração não encontrada
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'No configuration provided') !== false) {
                http_response_code(400);
                Flight::json([
                    'error' => 'Billing Portal não configurado',
                    'message' => 'O Billing Portal precisa ser configurado no Stripe Dashboard. Acesse: https://dashboard.stripe.com/test/settings/billing/portal',
                    'stripe_error' => Config::isDevelopment() ? $errorMessage : null
                ], 400);
            } else {
                http_response_code(500);
                Flight::json([
                    'error' => 'Erro ao criar sessão de portal',
                    'message' => Config::isDevelopment() ? $errorMessage : null
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao criar sessão de portal", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao criar sessão de portal',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


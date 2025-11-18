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
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Flight::json(['error' => 'JSON inválido no corpo da requisição: ' . json_last_error_msg()], 400);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');

            // Validações básicas
            if (empty($data['success_url']) || empty($data['cancel_url'])) {
                Flight::json(['error' => 'success_url e cancel_url são obrigatórios'], 400);
                return;
            }
            
            // Valida URLs para prevenir SSRF e Open Redirect
            if (!$this->validateRedirectUrl($data['success_url'], $tenantId)) {
                Flight::json(['error' => 'success_url inválida ou não permitida'], 400);
                return;
            }
            
            if (!$this->validateRedirectUrl($data['cancel_url'], $tenantId)) {
                Flight::json(['error' => 'cancel_url inválida ou não permitida'], 400);
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
            
            // ✅ SEGURANÇA: Valida tamanho máximo de line_items (prevenção de DoS)
            $lineItemsErrors = \App\Utils\Validator::validateArraySize($data['line_items'], 'line_items', 100);
            if (!empty($lineItemsErrors)) {
                Flight::json(['error' => 'Dados inválidos', 'errors' => $lineItemsErrors], 400);
                return;
            }
            
            // ✅ SEGURANÇA: Valida payment_method_types se fornecido
            if (isset($data['payment_method_types']) && is_array($data['payment_method_types'])) {
                $paymentMethodsErrors = \App\Utils\Validator::validateArraySize($data['payment_method_types'], 'payment_method_types', 10);
                if (!empty($paymentMethodsErrors)) {
                    Flight::json(['error' => 'Dados inválidos', 'errors' => $paymentMethodsErrors], 400);
                    return;
                }
            }

            // Se tem customer_id mas é ID do nosso banco, precisa buscar o stripe_customer_id
            if (!empty($data['customer_id']) && is_numeric($data['customer_id'])) {
                // É ID do nosso banco, precisa buscar o stripe_customer_id
                $customerModel = new \App\Models\Customer();
                
                // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
                $customer = $customerModel->findByTenantAndId($tenantId, (int)$data['customer_id']);
                
                if (!$customer) {
                    Flight::json(['error' => 'Cliente não encontrado'], 404);
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
    
    /**
     * Valida URL de redirecionamento para prevenir SSRF e Open Redirect
     * 
     * @param string $url URL a validar
     * @param int $tenantId ID do tenant
     * @return bool True se válida, false caso contrário
     */
    private function validateRedirectUrl(string $url, int $tenantId): bool
    {
        // Parse URL
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['scheme'])) {
            return false;
        }
        
        // Apenas HTTPS permitido (exceto em desenvolvimento)
        if ($parsed['scheme'] !== 'https' && !Config::isDevelopment()) {
            return false;
        }
        
        // Em desenvolvimento, permite HTTP apenas para localhost
        if (Config::isDevelopment() && $parsed['scheme'] === 'http') {
            $host = $parsed['host'] ?? '';
            if ($host !== 'localhost' && $host !== '127.0.0.1' && strpos($host, 'localhost:') !== 0) {
                return false;
            }
        }
        
        // Bloqueia esquemas perigosos
        $dangerousSchemes = ['file', 'ftp', 'gopher', 'javascript', 'data', 'vbscript'];
        if (in_array(strtolower($parsed['scheme']), $dangerousSchemes, true)) {
            return false;
        }
        
        // Bloqueia IPs privados e localhost (SSRF protection) - exceto em desenvolvimento
        $host = $parsed['host'] ?? '';
        if (!Config::isDevelopment() && filter_var($host, FILTER_VALIDATE_IP)) {
            // Bloqueia IPs privados e de loopback
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        
        // Valida formato básico de URL
        if (empty($host)) {
            return false;
        }
        
        // Valida comprimento máximo da URL
        if (strlen($url) > 2048) {
            return false;
        }
        
        return true;
    }
}


<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
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
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_checkout']);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');

            // ✅ Validação consistente usando Validator
            $errors = \App\Utils\Validator::validateCheckoutCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['tenant_id' => $tenantId, 'action' => 'create_checkout']
                );
                return;
            }
            
            // ✅ Valida URLs para prevenir SSRF e Open Redirect (validação adicional de segurança)
            if (!$this->validateRedirectUrl($data['success_url'], $tenantId)) {
                ResponseHelper::sendValidationError(
                    'A URL success_url é inválida ou não permitida',
                    ['success_url' => 'URL inválida ou não permitida (verificação de segurança)'],
                    ['tenant_id' => $tenantId, 'url' => $data['success_url']]
                );
                return;
            }
            
            if (!$this->validateRedirectUrl($data['cancel_url'], $tenantId)) {
                ResponseHelper::sendValidationError(
                    'A URL cancel_url é inválida ou não permitida',
                    ['cancel_url' => 'URL inválida ou não permitida (verificação de segurança)'],
                    ['tenant_id' => $tenantId, 'url' => $data['cancel_url']]
                );
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
                ResponseHelper::sendValidationError(
                    'O campo line_items ou price_id é obrigatório',
                    ['line_items' => 'Campo obrigatório'],
                    ['tenant_id' => $tenantId, 'action' => 'create_checkout']
                );
                return;
            }
            
            // ✅ SEGURANÇA: Valida tamanho máximo de line_items (prevenção de DoS)
            $lineItemsErrors = \App\Utils\Validator::validateArraySize($data['line_items'], 'line_items', 100);
            if (!empty($lineItemsErrors)) {
                ResponseHelper::sendValidationError(
                    'Dados inválidos',
                    $lineItemsErrors,
                    ['tenant_id' => $tenantId, 'field' => 'line_items']
                );
                return;
            }
            
            // ✅ SEGURANÇA: Valida payment_method_types se fornecido
            if (isset($data['payment_method_types']) && is_array($data['payment_method_types'])) {
                $paymentMethodsErrors = \App\Utils\Validator::validateArraySize($data['payment_method_types'], 'payment_method_types', 10);
                if (!empty($paymentMethodsErrors)) {
                    ResponseHelper::sendValidationError(
                        'Dados inválidos',
                        $paymentMethodsErrors,
                        ['tenant_id' => $tenantId, 'field' => 'payment_method_types']
                    );
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
                    ResponseHelper::sendNotFoundError('Cliente', [
                        'customer_id' => $data['customer_id'],
                        'tenant_id' => $tenantId,
                        'action' => 'create_checkout'
                    ]);
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

            ResponseHelper::sendCreated([
                'session_id' => $session->id,
                'url' => $session->url
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar sessão de checkout',
                ['tenant_id' => $tenantId ?? null, 'action' => 'create_checkout']
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar sessão de checkout',
                'CHECKOUT_CREATE_ERROR',
                ['tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['session_id' => $id, 'action' => 'get_checkout']);
                return;
            }

            // Obtém sessão do Stripe
            $session = $this->stripeService->getCheckoutSession($id);

            // Valida se a sessão pertence ao tenant (via metadata)
            if (isset($session->metadata->tenant_id) && (int)$session->metadata->tenant_id !== $tenantId) {
                ResponseHelper::sendForbiddenError(
                    'Sessão não pertence ao tenant',
                    ['session_id' => $id, 'tenant_id' => $tenantId, 'session_tenant_id' => $session->metadata->tenant_id]
                );
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

            ResponseHelper::sendSuccess($responseData);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Sessão de checkout', ['session_id' => $id, 'tenant_id' => $tenantId ?? null]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter sessão de checkout',
                    ['session_id' => $id, 'tenant_id' => $tenantId ?? null, 'action' => 'get_checkout']
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter sessão de checkout',
                'CHECKOUT_GET_ERROR',
                ['session_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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


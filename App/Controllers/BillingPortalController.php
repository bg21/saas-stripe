<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
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
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_billing_portal', 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');

            if (empty($data['customer_id'])) {
                ResponseHelper::sendValidationError(
                    'customer_id é obrigatório',
                    ['customer_id' => 'Campo obrigatório'],
                    ['action' => 'create_billing_portal', 'tenant_id' => $tenantId]
                );
                return;
            }

            if (empty($data['return_url'])) {
                ResponseHelper::sendValidationError(
                    'return_url é obrigatório',
                    ['return_url' => 'Campo obrigatório'],
                    ['action' => 'create_billing_portal', 'tenant_id' => $tenantId]
                );
                return;
            }

            // Busca customer para validar tenant
            $customerModel = new \App\Models\Customer();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $customer = $customerModel->findByTenantAndId($tenantId, (int)$data['customer_id']);

            if (!$customer) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'create_billing_portal', 'customer_id' => $data['customer_id'], 'tenant_id' => $tenantId]);
                return;
            }
            
            // Valida return_url para prevenir SSRF e Open Redirect
            if (!$this->validateRedirectUrl($data['return_url'], $tenantId)) {
                ResponseHelper::sendValidationError(
                    'return_url inválida ou não permitida',
                    ['return_url' => 'URL inválida ou não permitida'],
                    ['action' => 'create_billing_portal', 'tenant_id' => $tenantId, 'url' => $data['return_url']]
                );
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

            ResponseHelper::sendCreated($responseData, 'Sessão de portal criada com sucesso');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Mensagem específica para configuração não encontrada
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'No configuration provided') !== false) {
                ResponseHelper::sendValidationError(
                    'O Billing Portal precisa ser configurado no Stripe Dashboard. Acesse: https://dashboard.stripe.com/test/settings/billing/portal',
                    [],
                    [
                        'action' => 'create_billing_portal',
                        'tenant_id' => $tenantId ?? null,
                        'stripe_error' => Config::isDevelopment() ? $errorMessage : null
                    ]
                );
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao criar sessão de portal',
                    ['action' => 'create_billing_portal', 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar sessão de portal',
                'BILLING_PORTAL_CREATE_ERROR',
                ['action' => 'create_billing_portal', 'tenant_id' => $tenantId ?? null]
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


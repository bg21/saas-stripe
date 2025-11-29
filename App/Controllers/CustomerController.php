<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\ErrorHandler;
use App\Utils\ResponseHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar clientes Stripe
 */
class CustomerController
{
    private PaymentService $paymentService;
    private StripeService $stripeService;

    public function __construct(PaymentService $paymentService, StripeService $stripeService)
    {
        $this->paymentService = $paymentService;
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo cliente
     * POST /v1/customers
     */
    public function create(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('create_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null || $tenantId === '') {
                $context = Config::isDevelopment() ? [
                    'tenant_id' => $tenantId,
                    'type' => gettype($tenantId),
                    'all_flight_data' => [
                        'tenant_id' => Flight::get('tenant_id'),
                        'is_master' => Flight::get('is_master'),
                        'tenant' => Flight::get('tenant')
                    ]
                ] : [];
                ResponseHelper::sendUnauthorizedError('Não autenticado', array_merge($context, ['action' => 'create_customer']));
                return;
            }
            
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_customer', 'customer_id' => $id]);
                    return;
                }
                $data = [];
            }
            
            // Validação rigorosa de inputs
            $errors = \App\Utils\Validator::validateCustomerCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_customer', 'tenant_id' => $tenantId]
                );
                return;
            }

            $customer = $this->paymentService->createCustomer($tenantId, $data);

            // ✅ Invalida cache de listagem
            \App\Services\CacheService::invalidateCustomerCache($tenantId);

            ResponseHelper::sendCreated($customer, 'Cliente criado com sucesso');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar cliente no Stripe', ['action' => 'create_customer', 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar cliente', 'CUSTOMER_CREATE_ERROR', ['action' => 'create_customer', 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Lista clientes do tenant
     * GET /v1/customers
     * 
     * Query params:
     *   - page: Número da página (padrão: 1)
     *   - limit: Itens por página (padrão: 20)
     *   - search: Busca por email ou nome
     *   - status: Filtrar por status
     *   - sort: Campo para ordenação
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_customers']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20;
            
            $filters = [];
            if (!empty($queryParams['search'])) {
                $filters['search'] = $queryParams['search'];
            }
            if (!empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            if (!empty($queryParams['sort'])) {
                $filters['sort'] = $queryParams['sort'];
            }
            
            // ✅ CACHE: Gera chave única baseada em parâmetros
            $cacheKey = sprintf(
                'customers:list:%d:%d:%d:%s:%s:%s',
                $tenantId,
                $page,
                $limit,
                md5($filters['search'] ?? ''),
                $filters['status'] ?? '',
                $filters['sort'] ?? 'created_at'
            );
            
            // ✅ Tenta obter do cache (TTL: 60 segundos)
            $cached = \App\Services\CacheService::getJson($cacheKey);
            if ($cached !== null) {
                // Se o cache já tem formato ResponseHelper, usa diretamente
                if (isset($cached['success']) && isset($cached['data'])) {
                    ResponseHelper::sendSuccess($cached['data'], 200, null, $cached['meta'] ?? null);
                } else {
                    // Formato antigo, converte
                    ResponseHelper::sendSuccess($cached);
                }
                return;
            }
            
            $customerModel = new \App\Models\Customer();
            $result = $customerModel->findByTenant($tenantId, $page, $limit, $filters);

            $responseData = [
                'customers' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total_pages' => $result['total_pages']
                ]
            ];
            
            // ✅ Salva no cache (formato ResponseHelper)
            $cacheResponse = [
                'success' => true,
                'data' => $responseData,
                'meta' => $responseData['meta']
            ];
            \App\Services\CacheService::setJson($cacheKey, $cacheResponse, 60);
            
            // Envia resposta com meta
            ResponseHelper::sendSuccess($responseData['customers'], 200, null, $responseData['meta']);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar clientes', 'CUSTOMER_LIST_ERROR', ['action' => 'list_customers', 'tenant_id' => $tenantId ?? null]);
        }
    }

    /**
     * Obtém cliente por ID
     * GET /v1/customers/:id
     */
    public function get(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_customer', 'customer_id' => $id]);
                return;
            }

            $customerModel = new \App\Models\Customer();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $customer = $customerModel->findByTenantAndId($tenantId, (int)$id);

            if (!$customer) {
                error_log("Cliente não encontrado no banco: customer_id={$id}, tenant_id={$tenantId}");
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'get_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ CACHE: Verifica se há cache válido (TTL: 5 minutos)
            $cacheKey = "customers:get:{$tenantId}:{$id}";
            $cached = \App\Services\CacheService::getJson($cacheKey);
            
            if ($cached !== null) {
                ResponseHelper::sendSuccess($cached);
                return;
            }

            // ✅ CORREÇÃO: Valida se tem stripe_customer_id
            if (empty($customer['stripe_customer_id'])) {
                error_log("Cliente sem stripe_customer_id: {$id} (tenant_id: {$tenantId})");
                // Retorna dados do banco mesmo sem stripe_customer_id
                $responseData = [
                    'id' => $customer['id'],
                    'stripe_customer_id' => null,
                    'email' => $customer['email'] ?? null,
                    'name' => $customer['name'] ?? null,
                    'phone' => null,
                    'description' => null,
                    'metadata' => [],
                    'created' => $customer['created_at'] ?? date('Y-m-d H:i:s')
                ];
                
                \App\Services\CacheService::setJson($cacheKey, $responseData, 300);
                ResponseHelper::sendSuccess($responseData);
                return;
            }

            // ✅ Sincronização condicional: apenas se cache expirou
            // Busca dados atualizados no Stripe
            try {
                $stripeCustomer = $this->stripeService->getCustomer($customer['stripe_customer_id']);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Se o customer não existe no Stripe, retorna dados do banco
                if ($e->getStripeCode() === 'resource_missing') {
                    error_log("Cliente não encontrado no Stripe: {$customer['stripe_customer_id']} (customer_id: {$id}, tenant_id: {$tenantId})");
                    $responseData = [
                        'id' => $customer['id'],
                        'stripe_customer_id' => $customer['stripe_customer_id'],
                        'email' => $customer['email'] ?? null,
                        'name' => $customer['name'] ?? null,
                        'phone' => null,
                        'description' => null,
                        'metadata' => [],
                        'created' => $customer['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                    
                    \App\Services\CacheService::setJson($cacheKey, $responseData, 300);
                    ResponseHelper::sendSuccess($responseData);
                    return;
                }
                throw $e; // Re-lança outras exceções
            }

            // Atualiza banco apenas se houver mudanças significativas
            $needsUpdate = false;
            if (($stripeCustomer->email ?? null) !== ($customer['email'] ?? null) || 
                ($stripeCustomer->name ?? null) !== ($customer['name'] ?? null)) {
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $customerModel->createOrUpdate(
                    $tenantId,
                    $customer['stripe_customer_id'],
                    [
                        'email' => $stripeCustomer->email,
                        'name' => $stripeCustomer->name,
                        'metadata' => $stripeCustomer->metadata->toArray()
                    ]
                );
            }

            // Prepara resposta com dados completos
            $responseData = [
                'id' => $customer['id'],
                'stripe_customer_id' => $stripeCustomer->id,
                'email' => $stripeCustomer->email ?? null,
                'name' => $stripeCustomer->name ?? null,
                'phone' => $stripeCustomer->phone ?? null,
                'description' => $stripeCustomer->description ?? null,
                'metadata' => $stripeCustomer->metadata->toArray(),
                'created' => date('Y-m-d H:i:s', $stripeCustomer->created)
            ];

            // Adiciona endereço se existir
            if ($stripeCustomer->address) {
                $responseData['address'] = [
                    'line1' => $stripeCustomer->address->line1 ?? null,
                    'line2' => $stripeCustomer->address->line2 ?? null,
                    'city' => $stripeCustomer->address->city ?? null,
                    'state' => $stripeCustomer->address->state ?? null,
                    'postal_code' => $stripeCustomer->address->postal_code ?? null,
                    'country' => $stripeCustomer->address->country ?? null
                ];
            }

            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $responseData, 300); // 5 minutos

            ResponseHelper::sendSuccess($responseData);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::warning("Cliente não encontrado no Stripe", ['customer_id' => (int)$id]);
            ResponseHelper::sendNotFoundError('Cliente', ['action' => 'get_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter cliente', 'CUSTOMER_GET_ERROR', ['action' => 'get_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Atualiza cliente
     * PUT /v1/customers/:id
     * 
     * Body JSON:
     * {
     *   "email": "novo@email.com",  // opcional
     *   "name": "Novo Nome",  // opcional
     *   "phone": "+5511999999999",  // opcional
     *   "description": "Descrição",  // opcional
     *   "metadata": {"key": "value"},  // opcional
     *   "address": {  // opcional
     *     "line1": "Rua Exemplo",
     *     "line2": "Apto 123",
     *     "city": "São Paulo",
     *     "state": "SP",
     *     "postal_code": "01234-567",
     *     "country": "BR"
     *   }
     * }
     */
    public function update(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('update_customers');
            
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_customer', 'customer_id' => $id]);
                    return;
                }
                $data = [];
            }
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_customer', 'customer_id' => $id]);
                return;
            }

            $customerModel = new \App\Models\Customer();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $customer = $customerModel->findByTenantAndId($tenantId, (int)$id);

            if (!$customer) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'get_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Valida se há dados para atualizar
            $allowedFields = ['email', 'name', 'phone', 'description', 'metadata', 'address'];
            $hasUpdates = false;
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $hasUpdates = true;
                    break;
                }
            }

            if (!$hasUpdates) {
                ResponseHelper::sendValidationError(
                    'Nenhum campo válido para atualização fornecido',
                    [],
                    ['action' => 'update_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }

            // ✅ Validação consistente usando Validator
            $errors = \App\Utils\Validator::validateCustomerUpdate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'update_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }

            // Atualiza no Stripe
            $stripeCustomer = $this->stripeService->updateCustomer($customer['stripe_customer_id'], $data);

            // Atualiza no banco
            $customerModel->createOrUpdate(
                $tenantId,
                $customer['stripe_customer_id'],
                [
                    'email' => $stripeCustomer->email,
                    'name' => $stripeCustomer->name,
                    'metadata' => $stripeCustomer->metadata->toArray()
                ]
            );

            // Busca customer atualizado no banco
            $updatedCustomer = $customerModel->findById((int)$id);

            // ✅ Invalida cache de listagem e cache específico
            \App\Services\CacheService::invalidateCustomerCache($tenantId, (int)$id);

            // Prepara resposta
            $responseData = [
                'id' => $updatedCustomer['id'],
                'stripe_customer_id' => $stripeCustomer->id,
                'email' => $stripeCustomer->email,
                'name' => $stripeCustomer->name,
                'phone' => $stripeCustomer->phone,
                'description' => $stripeCustomer->description,
                'metadata' => $stripeCustomer->metadata->toArray()
            ];

            // Adiciona endereço se existir
            if ($stripeCustomer->address) {
                $responseData['address'] = [
                    'line1' => $stripeCustomer->address->line1,
                    'line2' => $stripeCustomer->address->line2,
                    'city' => $stripeCustomer->address->city,
                    'state' => $stripeCustomer->address->state,
                    'postal_code' => $stripeCustomer->address->postal_code,
                    'country' => $stripeCustomer->address->country
                ];
            }

            ResponseHelper::sendSuccess($responseData, 200, 'Cliente atualizado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao atualizar cliente no Stripe', ['action' => 'update_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar cliente', 'CUSTOMER_UPDATE_ERROR', ['action' => 'update_customer', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Lista faturas de um cliente
     * GET /v1/customers/:id/invoices
     * 
     * Query parameters opcionais:
     *   - limit: Número máximo de resultados (padrão: 10)
     *   - status: Filtrar por status (draft, open, paid, uncollectible, void)
     */
    public function listInvoices(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_customer', 'customer_id' => $id]);
                return;
            }

            $customerModel = new \App\Models\Customer();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $customer = $customerModel->findByTenantAndId($tenantId, (int)$id);

            if (!$customer) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'list_invoices', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Obtém parâmetros de query
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $status = $_GET['status'] ?? null;
            $startingAfter = $_GET['starting_after'] ?? null;
            $endingBefore = $_GET['ending_before'] ?? null;

            $options = [
                'limit' => $limit
            ];

            if ($status) {
                $options['status'] = $status;
            }

            if ($startingAfter) {
                $options['starting_after'] = $startingAfter;
            }

            if ($endingBefore) {
                $options['ending_before'] = $endingBefore;
            }

            // ✅ CORREÇÃO: Valida se tem stripe_customer_id
            if (empty($customer['stripe_customer_id'])) {
                error_log("Cliente sem stripe_customer_id: {$id} (tenant_id: {$tenantId})");
                Flight::json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'count' => 0,
                        'has_more' => false
                    ]
                ]);
                return;
            }

            // Lista faturas do Stripe
            try {
                $invoices = $this->stripeService->listInvoices($customer['stripe_customer_id'], $options);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Se o customer não existe no Stripe ou não tem faturas, retorna array vazio
                error_log("Erro ao listar faturas do Stripe: {$e->getMessage()} (customer_id: {$id}, stripe_customer_id: {$customer['stripe_customer_id']})");
                Flight::json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'count' => 0,
                        'has_more' => false
                    ]
                ]);
                return;
            }

            // Formata resposta
            $invoicesData = [];
            foreach ($invoices->data as $invoice) {
                $invoicesData[] = [
                    'id' => $invoice->id,
                    'customer' => $invoice->customer,
                    'amount_due' => $invoice->amount_due,
                    'amount_paid' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'billing_reason' => $invoice->billing_reason,
                    'created' => date('Y-m-d H:i:s', $invoice->created),
                    'due_date' => $invoice->due_date ? date('Y-m-d H:i:s', $invoice->due_date) : null,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'paid' => $invoice->paid,
                    'metadata' => $invoice->metadata->toArray()
                ];
            }

            // ✅ CORREÇÃO: Retorna array diretamente, meta separado
            Flight::json([
                'success' => true,
                'data' => $invoicesData,
                'meta' => [
                    'count' => count($invoicesData),
                    'has_more' => $invoices->has_more
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao listar faturas', ['action' => 'list_customer_invoices', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar faturas', 'CUSTOMER_INVOICES_ERROR', ['action' => 'list_customer_invoices', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Lista assinaturas de um cliente
     * GET /v1/customers/:id/subscriptions
     * 
     * Query parameters opcionais:
     *   - limit: Número máximo de resultados (padrão: 20)
     *   - status: Filtrar por status
     */
    public function listSubscriptions(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_customer_subscriptions', 'customer_id' => $id]);
                return;
            }

            $customerModel = new \App\Models\Customer();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $customer = $customerModel->findByTenantAndId($tenantId, (int)$id);

            if (!$customer) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'list_customer_subscriptions', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ Usa SubscriptionController logic via Subscription model
            $subscriptionModel = new \App\Models\Subscription();
            $filters = ['customer' => (int)$id];
            
            // Obtém parâmetros de query
            $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }

            $result = $subscriptionModel->findByTenant($tenantId, $page, $limit, $filters);

            // ✅ Retorna array diretamente, meta separado
            Flight::json([
                'success' => true,
                'data' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total_pages' => $result['total_pages']
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao listar assinaturas do cliente: {$e->getMessage()} (customer_id: {$id}, tenant_id: {$tenantId})");
            ResponseHelper::sendGenericError($e, 'Erro ao listar assinaturas do cliente', 'CUSTOMER_SUBSCRIPTIONS_ERROR', ['action' => 'list_customer_subscriptions', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Lista métodos de pagamento de um cliente
     * GET /v1/customers/:id/payment-methods
     * 
     * Query parameters opcionais:
     *   - limit: Número máximo de resultados (padrão: 10)
     *   - type: Filtrar por tipo (card, us_bank_account, etc)
     */
    public function listPaymentMethods(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_customer', 'customer_id' => $id]);
                return;
            }

            $customerModel = new \App\Models\Customer();
            
            // Buscar diretamente com filtro de tenant (mais seguro - proteção IDOR)
            $customer = $customerModel->findByTenantAndId($tenantId, (int)$id);

            if (!$customer) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'list_payment_methods', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Obtém parâmetros de query
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $type = $_GET['type'] ?? null;
            $startingAfter = $_GET['starting_after'] ?? null;
            $endingBefore = $_GET['ending_before'] ?? null;

            $options = [
                'limit' => $limit
            ];

            if ($type) {
                $options['type'] = $type;
            }

            if ($startingAfter) {
                $options['starting_after'] = $startingAfter;
            }

            if ($endingBefore) {
                $options['ending_before'] = $endingBefore;
            }

            // ✅ CACHE: Gera chave única baseada em parâmetros
            $cacheKey = sprintf(
                'customers:payment-methods:%d:%d:%s:%s:%s',
                $tenantId,
                (int)$id,
                $type ?? '',
                $startingAfter ?? '',
                $endingBefore ?? ''
            );
            
            // ✅ Tenta obter do cache (TTL: 60 segundos)
            $cached = \App\Services\CacheService::getJson($cacheKey);
            if ($cached !== null) {
                // ✅ CORREÇÃO: Se cache tem formato antigo {data: [...], meta}, converte
                if (isset($cached['data']) && isset($cached['meta'])) {
                    Flight::json([
                        'success' => true,
                        'data' => $cached['data'],
                        'meta' => $cached['meta']
                    ]);
                } else {
                    // Formato novo (já é array direto)
                    Flight::json([
                        'success' => true,
                        'data' => $cached,
                        'meta' => []
                    ]);
                }
                return;
            }

            // ✅ CORREÇÃO: Valida se tem stripe_customer_id
            if (empty($customer['stripe_customer_id'])) {
                error_log("Cliente sem stripe_customer_id: {$id} (tenant_id: {$tenantId})");
                Flight::json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'count' => 0,
                        'has_more' => false
                    ]
                ]);
                return;
            }

            // Lista métodos de pagamento do Stripe
            try {
                $paymentMethods = $this->stripeService->listPaymentMethods($customer['stripe_customer_id'], $options);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Se o customer não existe no Stripe ou não tem métodos de pagamento, retorna array vazio
                error_log("Erro ao listar métodos de pagamento do Stripe: {$e->getMessage()} (customer_id: {$id}, stripe_customer_id: {$customer['stripe_customer_id']})");
                Flight::json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'count' => 0,
                        'has_more' => false
                    ]
                ]);
                return;
            }

            // Formata resposta
            $paymentMethodsData = [];
            foreach ($paymentMethods->data as $paymentMethod) {
                $paymentMethodData = [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'customer' => $paymentMethod->customer,
                    'created' => date('Y-m-d H:i:s', $paymentMethod->created),
                    'metadata' => $paymentMethod->metadata->toArray()
                ];

                // Adiciona dados específicos do tipo
                if ($paymentMethod->type === 'card' && isset($paymentMethod->card)) {
                    $paymentMethodData['card'] = [
                        'brand' => $paymentMethod->card->brand,
                        'last4' => $paymentMethod->card->last4,
                        'exp_month' => $paymentMethod->card->exp_month,
                        'exp_year' => $paymentMethod->card->exp_year,
                        'country' => $paymentMethod->card->country
                    ];
                }

                $paymentMethodsData[] = $paymentMethodData;
            }

            // ✅ CORREÇÃO: Retorna array diretamente, meta separado
            $meta = [
                'count' => count($paymentMethodsData),
                'has_more' => $paymentMethods->has_more
            ];
            
            // ✅ Salva no cache (formato completo para compatibilidade)
            $cacheData = [
                'data' => $paymentMethodsData,
                'meta' => $meta
            ];
            \App\Services\CacheService::setJson($cacheKey, $cacheData, 60);
            
            // ✅ Retorna array diretamente em data
            Flight::json([
                'success' => true,
                'data' => $paymentMethodsData,
                'meta' => $meta
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao listar métodos de pagamento', ['action' => 'list_payment_methods', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar métodos de pagamento', 'PAYMENT_METHODS_LIST_ERROR', ['action' => 'list_payment_methods', 'customer_id' => $id, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Atualiza método de pagamento de um cliente
     * PUT /v1/customers/:id/payment-methods/:pm_id
     * 
     * Body JSON:
     *   - billing_details (opcional): { address, email, name, phone }
     *   - metadata (opcional): Metadados
     */
    public function updatePaymentMethod(string $id, string $pmId): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('update_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_customers']);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'update_payment_method', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_customer', 'customer_id' => $id]);
                    return;
                }
                $data = [];
            }
            
            if ($data === null) {
                $data = [];
            }

            // Valida que o payment method pertence ao customer
            $paymentMethods = $this->stripeService->listPaymentMethods($customer['stripe_customer_id'], ['limit' => 100]);
            $paymentMethodExists = false;
            foreach ($paymentMethods->data as $pm) {
                if ($pm->id === $pmId) {
                    $paymentMethodExists = true;
                    break;
                }
            }

            if (!$paymentMethodExists) {
                ResponseHelper::sendNotFoundError('Método de pagamento', ['action' => 'update_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
                return;
            }

            // Atualiza payment method
            $paymentMethod = $this->stripeService->updatePaymentMethod($pmId, $data);

            // Formata resposta
            $paymentMethodData = [
                'id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
                'customer' => $paymentMethod->customer,
                'created' => date('Y-m-d H:i:s', $paymentMethod->created),
                'metadata' => $paymentMethod->metadata->toArray()
            ];

            // Adiciona dados específicos do tipo
            if ($paymentMethod->type === 'card' && isset($paymentMethod->card)) {
                $paymentMethodData['card'] = [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'exp_month' => $paymentMethod->card->exp_month,
                    'exp_year' => $paymentMethod->card->exp_year,
                    'country' => $paymentMethod->card->country
                ];
            }

            // Adiciona billing details se existir
            if (isset($paymentMethod->billing_details)) {
                $paymentMethodData['billing_details'] = [
                    'name' => $paymentMethod->billing_details->name ?? null,
                    'email' => $paymentMethod->billing_details->email ?? null,
                    'phone' => $paymentMethod->billing_details->phone ?? null,
                    'address' => $paymentMethod->billing_details->address ? [
                        'line1' => $paymentMethod->billing_details->address->line1 ?? null,
                        'line2' => $paymentMethod->billing_details->address->line2 ?? null,
                        'city' => $paymentMethod->billing_details->address->city ?? null,
                        'state' => $paymentMethod->billing_details->address->state ?? null,
                        'postal_code' => $paymentMethod->billing_details->address->postal_code ?? null,
                        'country' => $paymentMethod->billing_details->address->country ?? null,
                    ] : null
                ];
            }

            ResponseHelper::sendSuccess($paymentMethodData);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao atualizar método de pagamento', ['action' => 'update_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar método de pagamento', 'PAYMENT_METHOD_UPDATE_ERROR', ['action' => 'update_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Deleta método de pagamento de um cliente
     * DELETE /v1/customers/:id/payment-methods/:pm_id
     */
    public function deletePaymentMethod(string $id, string $pmId): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('update_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_customers']);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'update_payment_method', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Valida que o payment method pertence ao customer
            $paymentMethods = $this->stripeService->listPaymentMethods($customer['stripe_customer_id'], ['limit' => 100]);
            $paymentMethodExists = false;
            foreach ($paymentMethods->data as $pm) {
                if ($pm->id === $pmId) {
                    $paymentMethodExists = true;
                    break;
                }
            }

            if (!$paymentMethodExists) {
                ResponseHelper::sendNotFoundError('Método de pagamento', ['action' => 'update_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
                return;
            }

            // Deleta payment method
            $paymentMethod = $this->stripeService->deletePaymentMethod($pmId);

            ResponseHelper::sendSuccess([
                'id' => $paymentMethod->id,
                'customer' => $paymentMethod->customer ?? null
            ], 200, 'Método de pagamento deletado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao deletar método de pagamento', ['action' => 'delete_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao deletar método de pagamento', 'PAYMENT_METHOD_DELETE_ERROR', ['action' => 'delete_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
        }
    }

    /**
     * Define método de pagamento como padrão para um cliente
     * POST /v1/customers/:id/payment-methods/:pm_id/set-default
     */
    public function setDefaultPaymentMethod(string $id, string $pmId): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('update_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_customers']);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Cliente', ['action' => 'update_payment_method', 'customer_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // Valida que o payment method pertence ao customer
            $paymentMethods = $this->stripeService->listPaymentMethods($customer['stripe_customer_id'], ['limit' => 100]);
            $paymentMethodExists = false;
            foreach ($paymentMethods->data as $pm) {
                if ($pm->id === $pmId) {
                    $paymentMethodExists = true;
                    break;
                }
            }

            if (!$paymentMethodExists) {
                ResponseHelper::sendNotFoundError('Método de pagamento', ['action' => 'update_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
                return;
            }

            // Define como padrão
            $this->stripeService->setDefaultPaymentMethod($pmId, $customer['stripe_customer_id']);

            // Obtém o customer atualizado para confirmar
            $stripeCustomer = $this->stripeService->getCustomer($customer['stripe_customer_id']);

            ResponseHelper::sendSuccess([
                'payment_method_id' => $pmId,
                'customer_id' => $customer['stripe_customer_id'],
                'default_payment_method' => $stripeCustomer->invoice_settings->default_payment_method ?? null
            ], 200, 'Método de pagamento definido como padrão');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError(
                $e->getMessage(),
                [],
                ['action' => 'set_default_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao definir método de pagamento como padrão', ['action' => 'set_default_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao definir método de pagamento como padrão', 'PAYMENT_METHOD_SET_DEFAULT_ERROR', ['action' => 'set_default_payment_method', 'customer_id' => $id, 'payment_method_id' => $pmId, 'tenant_id' => $tenantId]);
        }
    }
}


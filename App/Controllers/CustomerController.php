<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
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
                Flight::json([
                    'error' => 'Não autenticado',
                    'debug' => Config::isDevelopment() ? [
                        'tenant_id' => $tenantId,
                        'type' => gettype($tenantId),
                        'all_flight_data' => [
                            'tenant_id' => Flight::get('tenant_id'),
                            'is_master' => Flight::get('is_master'),
                            'tenant' => Flight::get('tenant')
                        ]
                    ] : null
                ], 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            if (empty($data['email'])) {
                Flight::json(['error' => 'Email é obrigatório'], 400);
                return;
            }

            $customer = $this->paymentService->createCustomer($tenantId, $data);

            Flight::json([
                'success' => true,
                'data' => $customer
            ], 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar cliente", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao criar cliente',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Lista clientes do tenant
     * GET /v1/customers
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_customers');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            $customerModel = new \App\Models\Customer();
            $customers = $customerModel->findByTenant($tenantId);

            Flight::json([
                'success' => true,
                'data' => $customers,
                'count' => count($customers)
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar clientes", ['error' => $e->getMessage()]);
            Flight::json(['error' => 'Erro ao listar clientes'], 500);
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
                http_response_code(401);
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Cliente não encontrado'], 404);
                return;
            }

            // Busca dados atualizados no Stripe para sincronização
            $stripeCustomer = $this->stripeService->getCustomer($customer['stripe_customer_id']);

            // Atualiza banco com dados do Stripe (sincronização)
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

            // Prepara resposta com dados completos
            $responseData = [
                'id' => $updatedCustomer['id'],
                'stripe_customer_id' => $stripeCustomer->id,
                'email' => $stripeCustomer->email,
                'name' => $stripeCustomer->name,
                'phone' => $stripeCustomer->phone,
                'description' => $stripeCustomer->description,
                'metadata' => $stripeCustomer->metadata->toArray(),
                'created' => date('Y-m-d H:i:s', $stripeCustomer->created)
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

            Flight::json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Cliente não encontrado no Stripe", ['customer_id' => $id]);
            http_response_code(404);
            Flight::json(['error' => 'Cliente não encontrado'], 404);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter cliente", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao obter cliente',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                http_response_code(401);
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Cliente não encontrado'], 404);
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
                http_response_code(400);
                Flight::json(['error' => 'Nenhum campo válido para atualização fornecido'], 400);
                return;
            }

            // Valida email se fornecido
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                Flight::json(['error' => 'Email inválido'], 400);
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

            Flight::json([
                'success' => true,
                'message' => 'Cliente atualizado com sucesso',
                'data' => $responseData
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao atualizar cliente no Stripe", ['error' => $e->getMessage()]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao atualizar cliente',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar cliente", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao atualizar cliente',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                http_response_code(401);
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Cliente não encontrado'], 404);
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

            // Lista faturas do Stripe
            $invoices = $this->stripeService->listInvoices($customer['stripe_customer_id'], $options);

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

            Flight::json([
                'success' => true,
                'data' => $invoicesData,
                'count' => count($invoicesData),
                'has_more' => $invoices->has_more
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao listar faturas", ['error' => $e->getMessage()]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao listar faturas',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar faturas", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao listar faturas',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                http_response_code(401);
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                http_response_code(404);
                Flight::json(['error' => 'Cliente não encontrado'], 404);
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

            // Lista métodos de pagamento do Stripe
            $paymentMethods = $this->stripeService->listPaymentMethods($customer['stripe_customer_id'], $options);

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

            Flight::json([
                'success' => true,
                'data' => $paymentMethodsData,
                'count' => count($paymentMethodsData),
                'has_more' => $paymentMethods->has_more
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao listar métodos de pagamento", ['error' => $e->getMessage()]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao listar métodos de pagamento',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar métodos de pagamento", ['error' => $e->getMessage()]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao listar métodos de pagamento',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                Flight::json(['error' => 'Cliente não encontrado'], 404);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
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
                Flight::json(['error' => 'Método de pagamento não encontrado'], 404);
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

            Flight::json([
                'success' => true,
                'data' => $paymentMethodData
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao atualizar método de pagamento", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao atualizar método de pagamento',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar método de pagamento", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                Flight::json(['error' => 'Cliente não encontrado'], 404);
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
                Flight::json(['error' => 'Método de pagamento não encontrado'], 404);
                return;
            }

            // Deleta payment method
            $paymentMethod = $this->stripeService->deletePaymentMethod($pmId);

            Flight::json([
                'success' => true,
                'message' => 'Método de pagamento deletado com sucesso',
                'data' => [
                    'id' => $paymentMethod->id,
                    'customer' => $paymentMethod->customer ?? null
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao deletar método de pagamento", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao deletar método de pagamento',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao deletar método de pagamento", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findById((int)$id);

            // Valida se customer existe e pertence ao tenant
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                Flight::json(['error' => 'Cliente não encontrado'], 404);
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
                Flight::json(['error' => 'Método de pagamento não encontrado'], 404);
                return;
            }

            // Define como padrão
            $this->stripeService->setDefaultPaymentMethod($pmId, $customer['stripe_customer_id']);

            // Obtém o customer atualizado para confirmar
            $stripeCustomer = $this->stripeService->getCustomer($customer['stripe_customer_id']);

            Flight::json([
                'success' => true,
                'message' => 'Método de pagamento definido como padrão',
                'data' => [
                    'payment_method_id' => $pmId,
                    'customer_id' => $customer['stripe_customer_id'],
                    'default_payment_method' => $stripeCustomer->invoice_settings->default_payment_method ?? null
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro de validação ao definir método padrão", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro de validação',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao definir método de pagamento como padrão", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao definir método de pagamento como padrão',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao definir método de pagamento como padrão", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


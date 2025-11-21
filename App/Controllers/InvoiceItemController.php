<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
use Flight;
use Config;

/**
 * Controller para gerenciar Invoice Items (itens de fatura)
 * 
 * Invoice Items são usados para adicionar cobranças únicas ou ajustes manuais a invoices.
 * Útil para fazer ajustes manuais frequentes, créditos, taxas adicionais, etc.
 */
class InvoiceItemController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo Invoice Item
     * POST /v1/invoice-items
     * 
     * Body:
     *   - customer_id (obrigatório): ID do customer no Stripe
     *   - amount (obrigatório se price não fornecido): Valor em centavos
     *   - currency (obrigatório se amount fornecido): Moeda (ex: 'brl', 'usd')
     *   - price (opcional): ID do preço (alternativa a amount/currency)
     *   - description (opcional): Descrição do item
     *   - invoice (opcional): ID da invoice existente
     *   - subscription (opcional): ID da subscription
     *   - quantity (opcional): Quantidade (padrão: 1)
     *   - tax_rates (opcional): Array de IDs de tax rates
     *   - metadata (opcional): Metadados
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_invoice_item']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_invoice_item']);
                    return;
                }
                $data = [];
            }

            // Validações obrigatórias
            $errors = [];
            if (empty($data['customer_id'])) {
                $errors['customer_id'] = 'Campo customer_id é obrigatório';
            }

            // Valida amount/currency ou price
            if (empty($data['price'])) {
                if (empty($data['amount']) || !is_numeric($data['amount'])) {
                    $errors['amount'] = 'Campo amount é obrigatório quando price não é fornecido';
                }
                if (empty($data['currency'])) {
                    $errors['currency'] = 'Campo currency é obrigatório quando amount é fornecido';
                }
            }
            
            // ✅ SEGURANÇA: Valida tamanho máximo de arrays (prevenção de DoS)
            if (isset($data['tax_rates']) && is_array($data['tax_rates'])) {
                $taxRatesErrors = \App\Utils\Validator::validateArraySize($data['tax_rates'], 'tax_rates', 50);
                if (!empty($taxRatesErrors)) {
                    $errors = array_merge($errors, $taxRatesErrors);
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError('Dados inválidos', $errors, ['action' => 'create_invoice_item', 'tenant_id' => $tenantId]);
                return;
            }

            // Valida se customer existe e pertence ao tenant
            $customerModel = new \App\Models\Customer();
            $customer = $customerModel->findByStripeId($data['customer_id']);
            
            if (!$customer || $customer['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Customer', ['action' => 'create_invoice_item', 'customer_id' => $data['customer_id'], 'tenant_id' => $tenantId]);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $invoiceItem = $this->stripeService->createInvoiceItem($data);

            ResponseHelper::sendCreated([
                'id' => $invoiceItem->id,
                'customer' => $invoiceItem->customer,
                'amount' => $invoiceItem->amount ?? null,
                'currency' => $invoiceItem->currency ?? null,
                'description' => $invoiceItem->description ?? null,
                'invoice' => $invoiceItem->invoice ?? null,
                'subscription' => $invoiceItem->subscription ?? null,
                'price' => $invoiceItem->price->id ?? null,
                'quantity' => $invoiceItem->quantity,
                'tax_rates' => array_map(function($tr) { return $tr->id; }, $invoiceItem->tax_rates ?? []),
                'created' => date('Y-m-d H:i:s', $invoiceItem->created),
                'metadata' => $invoiceItem->metadata->toArray()
            ], 'Invoice item criado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar invoice item', ['action' => 'create_invoice_item', 'tenant_id' => $tenantId ?? null]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar invoice item', 'INVOICE_ITEM_CREATE_ERROR', ['action' => 'create_invoice_item', 'tenant_id' => $tenantId ?? null]);
        }
    }

    /**
     * Lista Invoice Items
     * GET /v1/invoice-items
     * 
     * Query params:
     *   - limit (opcional): Número de invoice items por página
     *   - customer (opcional): Filtrar por ID do customer (Stripe)
     *   - invoice (opcional): Filtrar por ID da invoice
     *   - pending (opcional): Filtrar por itens pendentes (true/false)
     *   - starting_after (opcional): ID do invoice item para paginação
     *   - ending_before (opcional): ID do invoice item para paginação
     */
    public function list(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_invoice_item']);
                return;
            }

            $options = [];
            
            if (isset($_GET['limit'])) {
                $options['limit'] = (int)$_GET['limit'];
            }
            
            if (!empty($_GET['customer'])) {
                $options['customer'] = $_GET['customer'];
            }
            
            if (!empty($_GET['invoice'])) {
                $options['invoice'] = $_GET['invoice'];
            }
            
            if (isset($_GET['pending'])) {
                $options['pending'] = filter_var($_GET['pending'], FILTER_VALIDATE_BOOLEAN);
            }
            
            if (!empty($_GET['starting_after'])) {
                $options['starting_after'] = $_GET['starting_after'];
            }
            
            if (!empty($_GET['ending_before'])) {
                $options['ending_before'] = $_GET['ending_before'];
            }

            $collection = $this->stripeService->listInvoiceItems($options);

            // ✅ OTIMIZAÇÃO: Busca todos os customers de uma vez (elimina N+1)
            $customerModel = new \App\Models\Customer();
            $stripeCustomerIds = array_unique(array_filter(
                array_map(function($item) {
                    return $item->customer ?? null;
                }, $collection->data)
            ));
            
            // Busca todos os customers em uma única query
            $customersByStripeId = [];
            if (!empty($stripeCustomerIds)) {
                $db = \App\Utils\Database::getInstance();
                $placeholders = implode(',', array_fill(0, count($stripeCustomerIds), '?'));
                $stmt = $db->prepare(
                    "SELECT id, tenant_id, stripe_customer_id 
                     FROM customers 
                     WHERE stripe_customer_id IN ({$placeholders})"
                );
                $stmt->execute($stripeCustomerIds);
                $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($customers as $customer) {
                    $customersByStripeId[$customer['stripe_customer_id']] = $customer;
                }
            }

            $invoiceItems = [];
            foreach ($collection->data as $item) {
                // Filtra apenas invoice items do tenant (via metadata ou customer)
                $isTenantItem = false;
                
                // Verifica metadata primeiro (mais rápido)
                if (isset($item->metadata->tenant_id) && 
                    (string)$item->metadata->tenant_id === (string)$tenantId) {
                    $isTenantItem = true;
                } elseif (!empty($item->customer)) {
                    // ✅ Usa cache de customers já carregados (elimina N+1)
                    $customer = $customersByStripeId[$item->customer] ?? null;
                    if ($customer && $customer['tenant_id'] == $tenantId) {
                        $isTenantItem = true;
                    }
                }
                
                if ($isTenantItem) {
                    $invoiceItems[] = [
                        'id' => $item->id,
                        'customer' => $item->customer,
                        'amount' => $item->amount ?? null,
                        'currency' => $item->currency ?? null,
                        'description' => $item->description ?? null,
                        'invoice' => $item->invoice ?? null,
                        'subscription' => $item->subscription ?? null,
                        'price' => $item->price->id ?? null,
                        'quantity' => $item->quantity,
                        'tax_rates' => array_map(function($tr) { return $tr->id; }, $item->tax_rates ?? []),
                        'created' => date('Y-m-d H:i:s', $item->created),
                        'metadata' => $item->metadata->toArray()
                    ];
                }
            }

            Flight::json([
                'success' => true,
                'data' => $invoiceItems,
                'count' => count($invoiceItems),
                'has_more' => $collection->has_more
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar invoice items", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar invoice items',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém Invoice Item por ID
     * GET /v1/invoice-items/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_invoice_item']);
                return;
            }

            $invoiceItem = $this->stripeService->getInvoiceItem($id);

            // Valida se pertence ao tenant (via metadata ou customer)
            $isTenantItem = false;
            
            if (isset($invoiceItem->metadata->tenant_id) && 
                (string)$invoiceItem->metadata->tenant_id === (string)$tenantId) {
                $isTenantItem = true;
            } else {
                // Fallback: verifica via customer
                if (!empty($invoiceItem->customer)) {
                    $customerModel = new \App\Models\Customer();
                    $customer = $customerModel->findByStripeId($invoiceItem->customer);
                    if ($customer && $customer['tenant_id'] == $tenantId) {
                        $isTenantItem = true;
                    }
                }
            }
            
            if (!$isTenantItem) {
                Flight::json(['error' => 'Invoice item não encontrado'], 404);
                return;
            }

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $invoiceItem->id,
                    'customer' => $invoiceItem->customer,
                    'amount' => $invoiceItem->amount ?? null,
                    'currency' => $invoiceItem->currency ?? null,
                    'description' => $invoiceItem->description ?? null,
                    'invoice' => $invoiceItem->invoice ?? null,
                    'subscription' => $invoiceItem->subscription ?? null,
                    'price' => $invoiceItem->price->id ?? null,
                    'quantity' => $invoiceItem->quantity,
                    'tax_rates' => array_map(function($tr) { return $tr->id; }, $invoiceItem->tax_rates ?? []),
                    'created' => date('Y-m-d H:i:s', $invoiceItem->created),
                    'metadata' => $invoiceItem->metadata->toArray()
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Logger::error("Invoice item não encontrado", ['invoice_item_id' => $id]);
                Flight::json([
                    'error' => 'Invoice item não encontrado',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 404);
            } else {
                Logger::error("Erro ao obter invoice item", [
                    'error' => $e->getMessage(),
                    'invoice_item_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao obter invoice item',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao obter invoice item", [
                'error' => $e->getMessage(),
                'invoice_item_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter invoice item',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Atualiza Invoice Item
     * PUT /v1/invoice-items/:id
     * 
     * Body:
     *   - amount (opcional): Novo valor em centavos
     *   - currency (opcional): Nova moeda (deve ser fornecido junto com amount)
     *   - description (opcional): Nova descrição
     *   - quantity (opcional): Nova quantidade
     *   - tax_rates (opcional): Array de IDs de tax rates
     *   - metadata (opcional): Metadados (merge com existentes)
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_invoice_item']);
                return;
            }

            // Primeiro, verifica se o invoice item existe e pertence ao tenant
            $invoiceItem = $this->stripeService->getInvoiceItem($id);
            
            $isTenantItem = false;
            if (isset($invoiceItem->metadata->tenant_id) && 
                (string)$invoiceItem->metadata->tenant_id === (string)$tenantId) {
                $isTenantItem = true;
            } else {
                if (!empty($invoiceItem->customer)) {
                    $customerModel = new \App\Models\Customer();
                    $customer = $customerModel->findByStripeId($invoiceItem->customer);
                    if ($customer && $customer['tenant_id'] == $tenantId) {
                        $isTenantItem = true;
                    }
                }
            }
            
            if (!$isTenantItem) {
                Flight::json(['error' => 'Invoice item não encontrado'], 404);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_invoice_item']);
                    return;
                }
                $data = [];
            }
            
            // ✅ SEGURANÇA: Valida tamanho máximo de arrays (prevenção de DoS)
            if (isset($data['tax_rates']) && is_array($data['tax_rates'])) {
                $taxRatesErrors = \App\Utils\Validator::validateArraySize($data['tax_rates'], 'tax_rates', 50);
                if (!empty($taxRatesErrors)) {
                    Flight::json(['error' => 'Dados inválidos', 'errors' => $taxRatesErrors], 400);
                    return;
                }
            }

            $invoiceItem = $this->stripeService->updateInvoiceItem($id, $data);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $invoiceItem->id,
                    'customer' => $invoiceItem->customer,
                    'amount' => $invoiceItem->amount ?? null,
                    'currency' => $invoiceItem->currency ?? null,
                    'description' => $invoiceItem->description ?? null,
                    'invoice' => $invoiceItem->invoice ?? null,
                    'subscription' => $invoiceItem->subscription ?? null,
                    'price' => $invoiceItem->price->id ?? null,
                    'quantity' => $invoiceItem->quantity,
                    'tax_rates' => array_map(function($tr) { return $tr->id; }, $invoiceItem->tax_rates ?? []),
                    'created' => date('Y-m-d H:i:s', $invoiceItem->created),
                    'metadata' => $invoiceItem->metadata->toArray()
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Invoice item não encontrado'], 404);
            } else {
                Logger::error("Erro ao atualizar invoice item", [
                    'error' => $e->getMessage(),
                    'invoice_item_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao atualizar invoice item',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar invoice item", [
                'error' => $e->getMessage(),
                'invoice_item_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao atualizar invoice item',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove Invoice Item
     * DELETE /v1/invoice-items/:id
     */
    public function delete(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_invoice_item']);
                return;
            }

            // Primeiro, verifica se o invoice item existe e pertence ao tenant
            $invoiceItem = $this->stripeService->getInvoiceItem($id);
            
            $isTenantItem = false;
            if (isset($invoiceItem->metadata->tenant_id) && 
                (string)$invoiceItem->metadata->tenant_id === (string)$tenantId) {
                $isTenantItem = true;
            } else {
                if (!empty($invoiceItem->customer)) {
                    $customerModel = new \App\Models\Customer();
                    $customer = $customerModel->findByStripeId($invoiceItem->customer);
                    if ($customer && $customer['tenant_id'] == $tenantId) {
                        $isTenantItem = true;
                    }
                }
            }
            
            if (!$isTenantItem) {
                Flight::json(['error' => 'Invoice item não encontrado'], 404);
                return;
            }

            $this->stripeService->deleteInvoiceItem($id);

            Flight::json([
                'success' => true,
                'message' => 'Invoice item removido com sucesso'
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Invoice item não encontrado'], 404);
            } else {
                Logger::error("Erro ao remover invoice item", [
                    'error' => $e->getMessage(),
                    'invoice_item_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao remover invoice item',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao remover invoice item", [
                'error' => $e->getMessage(),
                'invoice_item_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao remover invoice item',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


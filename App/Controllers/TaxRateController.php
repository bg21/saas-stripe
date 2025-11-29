<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\Validator;
use Flight;
use Config;

/**
 * Controller para gerenciar Tax Rates (taxas de imposto)
 * 
 * Tax Rates são usadas para calcular impostos automaticamente em invoices e subscriptions.
 * Útil para compliance fiscal (IVA, GST, ICMS, etc.).
 */
class TaxRateController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo Tax Rate
     * POST /v1/tax-rates
     * 
     * Body:
     *   - display_name (obrigatório): Nome exibido (ex: "IVA", "GST", "ICMS")
     *   - description (opcional): Descrição
     *   - percentage (obrigatório): Percentual de imposto (ex: 20.0 para 20%)
     *   - inclusive (opcional): Se true, o imposto está incluído no preço (padrão: false)
     *   - country (opcional): Código do país (ISO 3166-1 alpha-2, ex: 'BR', 'US')
     *   - state (opcional): Estado/região (ex: 'SP', 'CA')
     *   - jurisdiction (opcional): Jurisdição fiscal
     *   - metadata (opcional): Metadados
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_tax_rate']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_tax_rate', 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            // ✅ Validação consistente usando Validator
            $errors = Validator::validateTaxRateCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_tax_rate', 'tenant_id' => $tenantId]
                );
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $taxRate = $this->stripeService->createTaxRate($data);

            ResponseHelper::sendCreated([
                'id' => $taxRate->id,
                'display_name' => $taxRate->display_name,
                'description' => $taxRate->description ?? null,
                'percentage' => $taxRate->percentage,
                'inclusive' => $taxRate->inclusive,
                'active' => $taxRate->active,
                'country' => $taxRate->country ?? null,
                'state' => $taxRate->state ?? null,
                'jurisdiction' => $taxRate->jurisdiction ?? null,
                'created' => date('Y-m-d H:i:s', $taxRate->created),
                'metadata' => $taxRate->metadata->toArray()
            ], 'Tax rate criado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar tax rate no Stripe',
                ['action' => 'create_tax_rate', 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar tax rate',
                'TAX_RATE_CREATE_ERROR',
                ['action' => 'create_tax_rate', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Lista Tax Rates
     * GET /v1/tax-rates
     * 
     * Query params:
     *   - limit (opcional): Número de tax rates por página
     *   - active (opcional): Filtrar por status ativo (true/false)
     *   - inclusive (opcional): Filtrar por tipo inclusivo (true/false)
     *   - starting_after (opcional): ID do tax rate para paginação
     *   - ending_before (opcional): ID do tax rate para paginação
     */
    public function list(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_tax_rates']);
                return;
            }

            $options = [];
            
            if (isset($_GET['limit'])) {
                $options['limit'] = (int)$_GET['limit'];
            }
            
            if (isset($_GET['active'])) {
                $options['active'] = filter_var($_GET['active'], FILTER_VALIDATE_BOOLEAN);
            }
            
            if (isset($_GET['inclusive'])) {
                $options['inclusive'] = filter_var($_GET['inclusive'], FILTER_VALIDATE_BOOLEAN);
            }
            
            if (!empty($_GET['starting_after'])) {
                $options['starting_after'] = $_GET['starting_after'];
            }
            
            if (!empty($_GET['ending_before'])) {
                $options['ending_before'] = $_GET['ending_before'];
            }

            $collection = $this->stripeService->listTaxRates($options);

            $taxRates = [];
            foreach ($collection->data as $taxRate) {
                // Filtra apenas tax rates do tenant (via metadata)
                if (isset($taxRate->metadata->tenant_id) && 
                    (string)$taxRate->metadata->tenant_id === (string)$tenantId) {
                    $taxRates[] = [
                        'id' => $taxRate->id,
                        'display_name' => $taxRate->display_name,
                        'description' => $taxRate->description ?? null,
                        'percentage' => $taxRate->percentage,
                        'inclusive' => $taxRate->inclusive,
                        'active' => $taxRate->active,
                        'country' => $taxRate->country ?? null,
                        'state' => $taxRate->state ?? null,
                        'jurisdiction' => $taxRate->jurisdiction ?? null,
                        'created' => date('Y-m-d H:i:s', $taxRate->created),
                        'metadata' => $taxRate->metadata->toArray()
                    ];
                }
            }

            // ✅ CORREÇÃO: Retorna array diretamente, meta separado
            Flight::json([
                'success' => true,
                'data' => $taxRates,
                'meta' => [
                    'count' => count($taxRates),
                    'has_more' => $collection->has_more
                ]
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar tax rates',
                'TAX_RATE_LIST_ERROR',
                ['action' => 'list_tax_rates', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém Tax Rate por ID
     * GET /v1/tax-rates/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_tax_rates']);
                return;
            }

            $taxRate = $this->stripeService->getTaxRate($id);

            // Valida se pertence ao tenant (via metadata)
            if (isset($taxRate->metadata->tenant_id) && 
                (string)$taxRate->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Tax rate', ['action' => 'get_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            ResponseHelper::sendSuccess([
                'id' => $taxRate->id,
                'display_name' => $taxRate->display_name,
                'description' => $taxRate->description ?? null,
                'percentage' => $taxRate->percentage,
                'inclusive' => $taxRate->inclusive,
                'active' => $taxRate->active,
                'country' => $taxRate->country ?? null,
                'state' => $taxRate->state ?? null,
                'jurisdiction' => $taxRate->jurisdiction ?? null,
                'created' => date('Y-m-d H:i:s', $taxRate->created),
                'metadata' => $taxRate->metadata->toArray()
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Tax rate', ['action' => 'get_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter tax rate',
                    ['action' => 'get_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter tax rate',
                'TAX_RATE_GET_ERROR',
                ['action' => 'get_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza Tax Rate
     * PUT /v1/tax-rates/:id
     * 
     * Body:
     *   - display_name (opcional): Novo nome
     *   - description (opcional): Nova descrição
     *   - active (opcional): Status ativo/inativo
     *   - metadata (opcional): Metadados (merge com existentes)
     * 
     * Nota: percentage, inclusive, country, state e jurisdiction não podem ser alterados após criação.
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_tax_rates']);
                return;
            }

            // Primeiro, verifica se o tax rate existe e pertence ao tenant
            $taxRate = $this->stripeService->getTaxRate($id);
            
            if (isset($taxRate->metadata->tenant_id) && 
                (string)$taxRate->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Tax rate', ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            
            // Valida que há dados para atualizar
            if (empty($data)) {
                ResponseHelper::sendValidationError(
                    'Nenhum dado fornecido para atualização',
                    [],
                    ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Valida campos permitidos (apenas display_name, description, active, metadata)
            $allowedFields = ['display_name', 'description', 'active', 'metadata'];
            $invalidFields = array_diff(array_keys($data), $allowedFields);
            if (!empty($invalidFields)) {
                ResponseHelper::sendValidationError(
                    'Campos inválidos para atualização',
                    ['fields' => 'Apenas display_name, description, active e metadata podem ser atualizados. Campos inválidos: ' . implode(', ', $invalidFields)],
                    ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId, 'invalid_fields' => $invalidFields]
                );
                return;
            }
            
            // Valida display_name se fornecido
            if (isset($data['display_name'])) {
                if (!is_string($data['display_name']) || strlen(trim($data['display_name'])) === 0) {
                    ResponseHelper::sendValidationError(
                        'display_name inválido',
                        ['display_name' => 'Deve ser uma string não vazia'],
                        ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                    );
                    return;
                }
                if (strlen($data['display_name']) > 255) {
                    ResponseHelper::sendValidationError(
                        'display_name muito longo',
                        ['display_name' => 'Máximo 255 caracteres'],
                        ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                    );
                    return;
                }
            }
            
            // Valida description se fornecido
            if (isset($data['description']) && strlen($data['description']) > 500) {
                ResponseHelper::sendValidationError(
                    'description muito longo',
                    ['description' => 'Máximo 500 caracteres'],
                    ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Valida active se fornecido
            if (isset($data['active']) && !is_bool($data['active'])) {
                ResponseHelper::sendValidationError(
                    'active inválido',
                    ['active' => 'Deve ser true ou false'],
                    ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Valida metadata se fornecido
            if (isset($data['metadata'])) {
                $metadataErrors = Validator::validateMetadata($data['metadata'], 'metadata');
                if (!empty($metadataErrors)) {
                    ResponseHelper::sendValidationError(
                        'metadata inválido',
                        $metadataErrors,
                        ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                    );
                    return;
                }
            }

            $taxRate = $this->stripeService->updateTaxRate($id, $data);

            ResponseHelper::sendSuccess([
                'id' => $taxRate->id,
                'display_name' => $taxRate->display_name,
                'description' => $taxRate->description ?? null,
                'percentage' => $taxRate->percentage,
                'inclusive' => $taxRate->inclusive,
                'active' => $taxRate->active,
                'country' => $taxRate->country ?? null,
                'state' => $taxRate->state ?? null,
                'jurisdiction' => $taxRate->jurisdiction ?? null,
                'created' => date('Y-m-d H:i:s', $taxRate->created),
                'metadata' => $taxRate->metadata->toArray()
            ], 200, 'Tax rate atualizado com sucesso');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Tax rate', ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao atualizar tax rate',
                    ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar tax rate',
                'TAX_RATE_UPDATE_ERROR',
                ['action' => 'update_tax_rate', 'tax_rate_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


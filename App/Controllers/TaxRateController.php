<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

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

            // Validações obrigatórias
            if (empty($data['display_name'])) {
                Flight::json(['error' => 'Campo display_name é obrigatório'], 400);
                return;
            }

            if (!isset($data['percentage']) || !is_numeric($data['percentage'])) {
                Flight::json(['error' => 'Campo percentage é obrigatório e deve ser numérico'], 400);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $taxRate = $this->stripeService->createTaxRate($data);

            Flight::json([
                'success' => true,
                'data' => [
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
                ]
            ], 201);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao criar tax rate no Stripe", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar tax rate',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar tax rate", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar tax rate',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
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

            Flight::json([
                'success' => true,
                'data' => $taxRates,
                'count' => count($taxRates),
                'has_more' => $collection->has_more
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar tax rates", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar tax rates',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $taxRate = $this->stripeService->getTaxRate($id);

            // Valida se pertence ao tenant (via metadata)
            if (isset($taxRate->metadata->tenant_id) && 
                (string)$taxRate->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Tax rate não encontrado'], 404);
                return;
            }

            Flight::json([
                'success' => true,
                'data' => [
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
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Logger::error("Tax rate não encontrado", ['tax_rate_id' => $id]);
                Flight::json([
                    'error' => 'Tax rate não encontrado',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 404);
            } else {
                Logger::error("Erro ao obter tax rate", [
                    'error' => $e->getMessage(),
                    'tax_rate_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao obter tax rate',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao obter tax rate", [
                'error' => $e->getMessage(),
                'tax_rate_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter tax rate',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Primeiro, verifica se o tax rate existe e pertence ao tenant
            $taxRate = $this->stripeService->getTaxRate($id);
            
            if (isset($taxRate->metadata->tenant_id) && 
                (string)$taxRate->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Tax rate não encontrado'], 404);
                return;
            }

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

            $taxRate = $this->stripeService->updateTaxRate($id, $data);

            Flight::json([
                'success' => true,
                'data' => [
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
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Tax rate não encontrado'], 404);
            } else {
                Logger::error("Erro ao atualizar tax rate", [
                    'error' => $e->getMessage(),
                    'tax_rate_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao atualizar tax rate',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar tax rate", [
                'error' => $e->getMessage(),
                'tax_rate_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao atualizar tax rate',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


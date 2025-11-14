<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar preços (prices) do Stripe
 */
class PriceController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Lista preços disponíveis
     * GET /v1/prices
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados (padrão: 10)
     *   - starting_after: ID do preço para paginação
     *   - ending_before: ID do preço para paginação reversa
     *   - active: true/false para filtrar apenas preços ativos/inativos
     *   - type: 'one_time' ou 'recurring' para filtrar por tipo
     *   - product: ID do produto para filtrar preços de um produto específico
     *   - currency: Código da moeda (ex: 'brl', 'usd')
     */
    public function list(): void
    {
        try {
            $queryParams = Flight::request()->query;
            
            $options = [];
            
            // Processa query params
            if (isset($queryParams['limit'])) {
                $options['limit'] = (int)$queryParams['limit'];
            }
            
            if (!empty($queryParams['starting_after'])) {
                $options['starting_after'] = $queryParams['starting_after'];
            }
            
            if (!empty($queryParams['ending_before'])) {
                $options['ending_before'] = $queryParams['ending_before'];
            }
            
            if (isset($queryParams['active'])) {
                $options['active'] = filter_var($queryParams['active'], FILTER_VALIDATE_BOOLEAN);
            }
            
            if (!empty($queryParams['type'])) {
                $options['type'] = $queryParams['type'];
            }
            
            if (!empty($queryParams['product'])) {
                $options['product'] = $queryParams['product'];
            }
            
            if (!empty($queryParams['currency'])) {
                $options['currency'] = $queryParams['currency'];
            }
            
            $prices = $this->stripeService->listPrices($options);
            
            // Formata resposta
            $formattedPrices = [];
            foreach ($prices->data as $price) {
                $priceData = [
                    'id' => $price->id,
                    'active' => $price->active,
                    'currency' => strtoupper($price->currency),
                    'type' => $price->type,
                    'unit_amount' => $price->unit_amount,
                    'unit_amount_decimal' => $price->unit_amount_decimal,
                    'formatted_amount' => number_format($price->unit_amount / 100, 2, ',', '.'),
                    'created' => date('Y-m-d H:i:s', $price->created),
                    'metadata' => $price->metadata->toArray()
                ];
                
                // Adiciona informações de recorrência se for recurring
                if ($price->type === 'recurring' && isset($price->recurring)) {
                    $priceData['recurring'] = [
                        'interval' => $price->recurring->interval,
                        'interval_count' => $price->recurring->interval_count,
                        'trial_period_days' => $price->recurring->trial_period_days ?? null
                    ];
                }
                
                // Adiciona informações do produto se expandido
                if (isset($price->product) && is_object($price->product)) {
                    $priceData['product'] = [
                        'id' => $price->product->id,
                        'name' => $price->product->name ?? null,
                        'description' => $price->product->description ?? null,
                        'active' => $price->product->active ?? null
                    ];
                } elseif (is_string($price->product)) {
                    $priceData['product_id'] = $price->product;
                }
                
                $formattedPrices[] = $priceData;
            }
            
            Flight::json([
                'success' => true,
                'data' => $formattedPrices,
                'has_more' => $prices->has_more,
                'count' => count($formattedPrices)
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao listar preços", [
                'error' => $e->getMessage(),
                'query_params' => $queryParams ?? []
            ]);
            http_response_code(400);
            Flight::json([
                'error' => 'Erro ao listar preços',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar preços", [
                'error' => $e->getMessage(),
                'query_params' => $queryParams ?? []
            ]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro ao listar preços',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cria um novo preço
     * POST /v1/prices
     * 
     * Body:
     *   - product (obrigatório): ID do produto
     *   - unit_amount (obrigatório): Valor em centavos (ex: 2000 = $20.00)
     *   - currency (obrigatório): Código da moeda (ex: 'brl', 'usd')
     *   - recurring (opcional): Para preços recorrentes { interval: 'month'|'year'|'week'|'day', interval_count: int, trial_period_days: int }
     *   - active (opcional): Se o preço está ativo (padrão: true)
     *   - metadata (opcional): Metadados
     *   - nickname (opcional): Apelido do preço
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if ($data === null) {
                $data = [];
            }

            // Validações obrigatórias
            if (empty($data['product'])) {
                Flight::json(['error' => 'Campo product é obrigatório'], 400);
                return;
            }

            if (!isset($data['unit_amount'])) {
                Flight::json(['error' => 'Campo unit_amount é obrigatório'], 400);
                return;
            }

            if (empty($data['currency'])) {
                Flight::json(['error' => 'Campo currency é obrigatório'], 400);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $price = $this->stripeService->createPrice($data);

            // Formata resposta
            $priceData = [
                'id' => $price->id,
                'active' => $price->active,
                'currency' => strtoupper($price->currency),
                'type' => $price->type,
                'unit_amount' => $price->unit_amount,
                'unit_amount_decimal' => $price->unit_amount_decimal,
                'formatted_amount' => number_format($price->unit_amount / 100, 2, ',', '.'),
                'nickname' => $price->nickname ?? null,
                'created' => date('Y-m-d H:i:s', $price->created),
                'metadata' => $price->metadata->toArray()
            ];

            // Adiciona informações de recorrência se for recurring
            if ($price->type === 'recurring' && isset($price->recurring)) {
                $priceData['recurring'] = [
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'trial_period_days' => $price->recurring->trial_period_days ?? null
                ];
            }

            // Adiciona informações do produto
            if (is_string($price->product)) {
                $priceData['product_id'] = $price->product;
            } elseif (is_object($price->product)) {
                $priceData['product'] = [
                    'id' => $price->product->id,
                    'name' => $price->product->name ?? null,
                    'description' => $price->product->description ?? null,
                    'active' => $price->product->active ?? null
                ];
            }

            Flight::json([
                'success' => true,
                'data' => $priceData
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro de validação ao criar preço", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro de validação',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao criar preço no Stripe", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar preço',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao criar preço", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém preço específico
     * GET /v1/prices/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $price = $this->stripeService->getPrice($id);

            // Valida se o preço pertence ao tenant (via metadata)
            if (isset($price->metadata->tenant_id) && (string)$price->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Preço não encontrado'], 404);
                return;
            }

            // Formata resposta
            $priceData = [
                'id' => $price->id,
                'active' => $price->active,
                'currency' => strtoupper($price->currency),
                'type' => $price->type,
                'unit_amount' => $price->unit_amount,
                'unit_amount_decimal' => $price->unit_amount_decimal,
                'formatted_amount' => number_format($price->unit_amount / 100, 2, ',', '.'),
                'nickname' => $price->nickname ?? null,
                'created' => date('Y-m-d H:i:s', $price->created),
                'metadata' => $price->metadata->toArray()
            ];

            // Adiciona informações de recorrência se for recurring
            if ($price->type === 'recurring' && isset($price->recurring)) {
                $priceData['recurring'] = [
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'trial_period_days' => $price->recurring->trial_period_days ?? null
                ];
            }

            // Adiciona informações do produto
            if (is_string($price->product)) {
                $priceData['product_id'] = $price->product;
            } elseif (is_object($price->product)) {
                $priceData['product'] = [
                    'id' => $price->product->id,
                    'name' => $price->product->name ?? null,
                    'description' => $price->product->description ?? null,
                    'active' => $price->product->active ?? null
                ];
            }

            Flight::json([
                'success' => true,
                'data' => $priceData
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Preço não encontrado'], 404);
            } else {
                Logger::error("Erro ao obter preço", ['error' => $e->getMessage()]);
                Flight::json([
                    'error' => 'Erro ao obter preço',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao obter preço", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Atualiza preço
     * PUT /v1/prices/:id
     * 
     * Nota: O Stripe não permite alterar o valor (unit_amount) ou moeda de um preço existente.
     * Apenas é possível atualizar: active, metadata, nickname.
     * 
     * Body:
     *   - active (opcional): Se o preço está ativo
     *   - metadata (opcional): Metadados
     *   - nickname (opcional): Apelido do preço
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Primeiro, verifica se o preço existe e pertence ao tenant
            $price = $this->stripeService->getPrice($id);
            
            if (isset($price->metadata->tenant_id) && (string)$price->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Preço não encontrado'], 404);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if ($data === null) {
                $data = [];
            }

            // Preserva tenant_id nos metadados se metadata for atualizado
            if (isset($data['metadata'])) {
                $data['metadata']['tenant_id'] = $tenantId;
            }

            $price = $this->stripeService->updatePrice($id, $data);

            // Formata resposta
            $priceData = [
                'id' => $price->id,
                'active' => $price->active,
                'currency' => strtoupper($price->currency),
                'type' => $price->type,
                'unit_amount' => $price->unit_amount,
                'unit_amount_decimal' => $price->unit_amount_decimal,
                'formatted_amount' => number_format($price->unit_amount / 100, 2, ',', '.'),
                'nickname' => $price->nickname ?? null,
                'created' => date('Y-m-d H:i:s', $price->created),
                'metadata' => $price->metadata->toArray()
            ];

            // Adiciona informações de recorrência se for recurring
            if ($price->type === 'recurring' && isset($price->recurring)) {
                $priceData['recurring'] = [
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'trial_period_days' => $price->recurring->trial_period_days ?? null
                ];
            }

            // Adiciona informações do produto
            if (is_string($price->product)) {
                $priceData['product_id'] = $price->product;
            } elseif (is_object($price->product)) {
                $priceData['product'] = [
                    'id' => $price->product->id,
                    'name' => $price->product->name ?? null,
                    'description' => $price->product->description ?? null,
                    'active' => $price->product->active ?? null
                ];
            }

            Flight::json([
                'success' => true,
                'data' => $priceData
            ]);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro de validação ao atualizar preço", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro de validação',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Preço não encontrado'], 404);
            } else {
                Logger::error("Erro ao atualizar preço", ['error' => $e->getMessage()]);
                Flight::json([
                    'error' => 'Erro ao atualizar preço',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar preço", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


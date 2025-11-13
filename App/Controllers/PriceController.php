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
}


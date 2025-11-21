<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
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
            
            // ✅ CACHE: Gera chave única baseada em parâmetros
            $cacheKey = sprintf(
                'prices:list:%d:%s:%s:%s:%s:%s:%s',
                $options['limit'] ?? 10,
                md5($options['starting_after'] ?? ''),
                md5($options['ending_before'] ?? ''),
                ($options['active'] ?? '') === true ? '1' : (($options['active'] ?? '') === false ? '0' : ''),
                $options['type'] ?? '',
                $options['product'] ?? '',
                $options['currency'] ?? ''
            );
            
            // ✅ Tenta obter do cache (TTL: 60 segundos)
            $cached = \App\Services\CacheService::getJson($cacheKey);
            if ($cached !== null) {
                Flight::json($cached);
                return;
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
            
            $response = [
                'success' => true,
                'data' => $formattedPrices,
                'has_more' => $prices->has_more,
                'count' => count($formattedPrices)
            ];
            
            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $response, 60);
            
            Flight::json($response);
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_price']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_price']);
                    return;
                }
                $data = [];
            }

            // Validações obrigatórias
            $errors = [];
            if (empty($data['product'])) {
                $errors['product'] = 'Campo product é obrigatório';
            }

            if (!isset($data['unit_amount'])) {
                $errors['unit_amount'] = 'Campo unit_amount é obrigatório';
            }

            if (empty($data['currency'])) {
                $errors['currency'] = 'Campo currency é obrigatório';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError('Dados inválidos', $errors, ['action' => 'create_price', 'tenant_id' => $tenantId]);
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

            ResponseHelper::sendCreated($priceData, 'Preço criado com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_price', 'tenant_id' => $tenantId ?? null]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar preço', ['action' => 'create_price', 'tenant_id' => $tenantId ?? null]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar preço', 'PRICE_CREATE_ERROR', ['action' => 'create_price', 'tenant_id' => $tenantId ?? null]);
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_price']);
                return;
            }

            $price = $this->stripeService->getPrice($id);

            // Valida se o preço pertence ao tenant (via metadata)
            if (isset($price->metadata->tenant_id) && (string)$price->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Preço', ['action' => 'get_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
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

            ResponseHelper::sendSuccess($priceData);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Preço', ['action' => 'get_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
            } else {
                ResponseHelper::sendStripeError($e, 'Erro ao obter preço', ['action' => 'get_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter preço', 'PRICE_GET_ERROR', ['action' => 'get_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_price', 'price_id' => $id]);
                return;
            }

            // Primeiro, verifica se o preço existe e pertence ao tenant
            $price = $this->stripeService->getPrice($id);
            
            if (isset($price->metadata->tenant_id) && (string)$price->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Preço', ['action' => 'update_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_price']);
                    return;
                }
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

            ResponseHelper::sendSuccess($priceData, 200, 'Preço atualizado com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'update_price', 'price_id' => $id, 'tenant_id' => $tenantId ?? null]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Preço', ['action' => 'update_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
            } else {
                ResponseHelper::sendStripeError($e, 'Erro ao atualizar preço', ['action' => 'update_price', 'price_id' => $id, 'tenant_id' => $tenantId]);
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar preço', 'PRICE_UPDATE_ERROR', ['action' => 'update_price', 'price_id' => $id, 'tenant_id' => $tenantId ?? null]);
        }
    }
}


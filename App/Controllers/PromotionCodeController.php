<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar códigos promocionais (Promotion Codes)
 * 
 * Promotion Codes são códigos que os clientes podem digitar no checkout
 * para aplicar cupons de desconto. Eles sempre referenciam um Coupon existente.
 */
class PromotionCodeController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo código promocional
     * POST /v1/promotion-codes
     * 
     * Body:
     *   - coupon (obrigatório): ID do cupom existente
     *   - code (opcional): Código customizado (ex: "BLACK50", "SUMMER2024")
     *   - active (opcional): Se o código está ativo (padrão: true)
     *   - customer (opcional): ID do customer que pode usar este código
     *   - expires_at (opcional): Data de expiração (timestamp ou string)
     *   - first_time_transaction (opcional): Se true, só pode ser usado na primeira transação
     *   - max_redemptions (opcional): Número máximo de resgates
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
            if (empty($data['coupon'])) {
                Flight::json(['error' => 'Campo coupon é obrigatório'], 400);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $promotionCode = $this->stripeService->createPromotionCode($data);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $promotionCode->id,
                    'code' => $promotionCode->code,
                    'coupon' => [
                        'id' => $promotionCode->coupon->id,
                        'name' => $promotionCode->coupon->name ?? null,
                        'percent_off' => $promotionCode->coupon->percent_off ?? null,
                        'amount_off' => $promotionCode->coupon->amount_off ?? null,
                        'currency' => $promotionCode->coupon->currency ?? null,
                        'duration' => $promotionCode->coupon->duration ?? null
                    ],
                    'active' => $promotionCode->active,
                    'customer' => $promotionCode->customer ?? null,
                    'expires_at' => $promotionCode->expires_at ? date('Y-m-d H:i:s', $promotionCode->expires_at) : null,
                    'first_time_transaction' => $promotionCode->first_time_transaction ?? false,
                    'max_redemptions' => $promotionCode->max_redemptions ?? null,
                    'times_redeemed' => $promotionCode->times_redeemed,
                    'created' => date('Y-m-d H:i:s', $promotionCode->created),
                    'metadata' => $promotionCode->metadata->toArray()
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro ao criar promotion code", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar código promocional',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao criar promotion code no Stripe", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar código promocional',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar promotion code", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar código promocional',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Lista códigos promocionais disponíveis
     * GET /v1/promotion-codes
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados
     *   - active: Filtrar por status ativo (true/false)
     *   - code: Filtrar por código específico
     *   - coupon: Filtrar por ID do cupom
     *   - customer: Filtrar por ID do customer
     *   - starting_after: ID do promotion code para paginação
     *   - ending_before: ID do promotion code para paginação reversa
     */
    public function list(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query;
            
            $options = [];
            
            if (isset($queryParams['limit'])) {
                $options['limit'] = (int)$queryParams['limit'];
            }
            
            if (isset($queryParams['active'])) {
                $options['active'] = filter_var($queryParams['active'], FILTER_VALIDATE_BOOLEAN);
            }
            
            if (!empty($queryParams['code'])) {
                $options['code'] = $queryParams['code'];
            }
            
            if (!empty($queryParams['coupon'])) {
                $options['coupon'] = $queryParams['coupon'];
            }
            
            if (!empty($queryParams['customer'])) {
                $options['customer'] = $queryParams['customer'];
            }
            
            if (!empty($queryParams['starting_after'])) {
                $options['starting_after'] = $queryParams['starting_after'];
            }
            
            if (!empty($queryParams['ending_before'])) {
                $options['ending_before'] = $queryParams['ending_before'];
            }
            
            $promotionCodes = $this->stripeService->listPromotionCodes($options);
            
            // Formata resposta
            $formattedCodes = [];
            foreach ($promotionCodes->data as $promotionCode) {
                $formattedCodes[] = [
                    'id' => $promotionCode->id,
                    'code' => $promotionCode->code,
                    'coupon' => [
                        'id' => $promotionCode->coupon->id,
                        'name' => $promotionCode->coupon->name ?? null,
                        'percent_off' => $promotionCode->coupon->percent_off ?? null,
                        'amount_off' => $promotionCode->coupon->amount_off ?? null,
                        'currency' => $promotionCode->coupon->currency ?? null,
                        'duration' => $promotionCode->coupon->duration ?? null
                    ],
                    'active' => $promotionCode->active,
                    'customer' => $promotionCode->customer ?? null,
                    'expires_at' => $promotionCode->expires_at ? date('Y-m-d H:i:s', $promotionCode->expires_at) : null,
                    'first_time_transaction' => $promotionCode->first_time_transaction ?? false,
                    'max_redemptions' => $promotionCode->max_redemptions ?? null,
                    'times_redeemed' => $promotionCode->times_redeemed,
                    'created' => date('Y-m-d H:i:s', $promotionCode->created),
                    'metadata' => $promotionCode->metadata->toArray()
                ];
            }
            
            Flight::json([
                'success' => true,
                'data' => $formattedCodes,
                'has_more' => $promotionCodes->has_more,
                'count' => count($formattedCodes)
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao listar promotion codes", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar códigos promocionais',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar promotion codes", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar códigos promocionais',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém código promocional por ID
     * GET /v1/promotion-codes/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $promotionCode = $this->stripeService->getPromotionCode($id);

            // Valida se pertence ao tenant (via metadata)
            if (isset($promotionCode->metadata->tenant_id) && 
                (string)$promotionCode->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Código promocional não encontrado'], 404);
                return;
            }

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $promotionCode->id,
                    'code' => $promotionCode->code,
                    'coupon' => [
                        'id' => $promotionCode->coupon->id,
                        'name' => $promotionCode->coupon->name ?? null,
                        'percent_off' => $promotionCode->coupon->percent_off ?? null,
                        'amount_off' => $promotionCode->coupon->amount_off ?? null,
                        'currency' => $promotionCode->coupon->currency ?? null,
                        'duration' => $promotionCode->coupon->duration ?? null
                    ],
                    'active' => $promotionCode->active,
                    'customer' => $promotionCode->customer ?? null,
                    'expires_at' => $promotionCode->expires_at ? date('Y-m-d H:i:s', $promotionCode->expires_at) : null,
                    'first_time_transaction' => $promotionCode->first_time_transaction ?? false,
                    'max_redemptions' => $promotionCode->max_redemptions ?? null,
                    'times_redeemed' => $promotionCode->times_redeemed,
                    'created' => date('Y-m-d H:i:s', $promotionCode->created),
                    'metadata' => $promotionCode->metadata->toArray()
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Logger::error("Promotion code não encontrado", ['promotion_code_id' => $id]);
                Flight::json([
                    'error' => 'Código promocional não encontrado',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 404);
            } else {
                Logger::error("Erro ao obter promotion code", [
                    'error' => $e->getMessage(),
                    'promotion_code_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao obter código promocional',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao obter promotion code", [
                'error' => $e->getMessage(),
                'promotion_code_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter código promocional',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Atualiza código promocional
     * PUT /v1/promotion-codes/:id
     * 
     * Body:
     *   - active (opcional): Se o código está ativo
     *   - metadata (opcional): Metadados
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Primeiro, verifica se o promotion code existe e pertence ao tenant
            $promotionCode = $this->stripeService->getPromotionCode($id);
            
            if (isset($promotionCode->metadata->tenant_id) && 
                (string)$promotionCode->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Código promocional não encontrado'], 404);
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

            // Preserva tenant_id nos metadados se metadata for atualizado
            if (isset($data['metadata'])) {
                $data['metadata']['tenant_id'] = $tenantId;
            }

            $promotionCode = $this->stripeService->updatePromotionCode($id, $data);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $promotionCode->id,
                    'code' => $promotionCode->code,
                    'active' => $promotionCode->active,
                    'metadata' => $promotionCode->metadata->toArray()
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro ao atualizar promotion code", [
                'error' => $e->getMessage(),
                'promotion_code_id' => $id
            ]);
            Flight::json([
                'error' => 'Erro ao atualizar código promocional',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Código promocional não encontrado'], 404);
            } else {
                Logger::error("Erro ao atualizar promotion code", [
                    'error' => $e->getMessage(),
                    'promotion_code_id' => $id
                ]);
                Flight::json([
                    'error' => 'Erro ao atualizar código promocional',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar promotion code", [
                'error' => $e->getMessage(),
                'promotion_code_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


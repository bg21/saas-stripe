<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\Validator;
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_promotion_code']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_promotion_code', 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            // ✅ Validação consistente usando Validator
            $errors = Validator::validatePromotionCodeCreate($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_promotion_code', 'tenant_id' => $tenantId]
                );
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $promotionCode = $this->stripeService->createPromotionCode($data);

            ResponseHelper::sendCreated([
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
            ], 'Código promocional criado com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError(
                $e->getMessage(),
                [],
                ['action' => 'create_promotion_code', 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar código promocional no Stripe',
                ['action' => 'create_promotion_code', 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar código promocional',
                'PROMOTION_CODE_CREATE_ERROR',
                ['action' => 'create_promotion_code', 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_promotion_codes']);
                return;
            }

            // ✅ CORREÇÃO: Flight::request()->query retorna Collection, precisa converter para array
            try {
                $queryParams = Flight::request()->query->getData();
                if (!is_array($queryParams)) {
                    $queryParams = [];
                }
            } catch (\Exception $e) {
                error_log("Erro ao obter query params: " . $e->getMessage());
                $queryParams = [];
            }
            
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
            
            // ✅ CORREÇÃO: Retorna array diretamente, meta separado
            Flight::json([
                'success' => true,
                'data' => $formattedCodes,
                'meta' => [
                    'has_more' => $promotionCodes->has_more,
                    'count' => count($formattedCodes)
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao listar códigos promocionais',
                ['action' => 'list_promotion_codes', 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar códigos promocionais',
                'PROMOTION_CODE_LIST_ERROR',
                ['action' => 'list_promotion_codes', 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_promotion_codes']);
                return;
            }

            $promotionCode = $this->stripeService->getPromotionCode($id);

            // Valida se pertence ao tenant (via metadata)
            if (isset($promotionCode->metadata->tenant_id) && 
                (string)$promotionCode->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Código promocional', ['action' => 'get_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            ResponseHelper::sendSuccess([
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
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Código promocional', ['action' => 'get_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter código promocional',
                    ['action' => 'get_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter código promocional',
                'PROMOTION_CODE_GET_ERROR',
                ['action' => 'get_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
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
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_promotion_codes']);
                return;
            }

            // Primeiro, verifica se o promotion code existe e pertence ao tenant
            $promotionCode = $this->stripeService->getPromotionCode($id);
            
            if (isset($promotionCode->metadata->tenant_id) && 
                (string)$promotionCode->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Código promocional', ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }
            
            // Valida que há dados para atualizar
            if (empty($data)) {
                ResponseHelper::sendValidationError(
                    'Nenhum dado fornecido para atualização',
                    [],
                    ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Valida campos permitidos (apenas active e metadata)
            $allowedFields = ['active', 'metadata'];
            $invalidFields = array_diff(array_keys($data), $allowedFields);
            if (!empty($invalidFields)) {
                ResponseHelper::sendValidationError(
                    'Campos inválidos para atualização',
                    ['fields' => 'Apenas active e metadata podem ser atualizados. Campos inválidos: ' . implode(', ', $invalidFields)],
                    ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId, 'invalid_fields' => $invalidFields]
                );
                return;
            }
            
            // Valida active se fornecido
            if (isset($data['active']) && !is_bool($data['active'])) {
                ResponseHelper::sendValidationError(
                    'active inválido',
                    ['active' => 'Deve ser true ou false'],
                    ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]
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
                        ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]
                    );
                    return;
                }
            }

            // Preserva tenant_id nos metadados se metadata for atualizado
            if (isset($data['metadata'])) {
                $data['metadata']['tenant_id'] = $tenantId;
            }

            $promotionCode = $this->stripeService->updatePromotionCode($id, $data);

            ResponseHelper::sendSuccess([
                'id' => $promotionCode->id,
                'code' => $promotionCode->code,
                'active' => $promotionCode->active,
                'metadata' => $promotionCode->metadata->toArray()
            ], 200, 'Código promocional atualizado com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError(
                $e->getMessage(),
                [],
                ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Código promocional', ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao atualizar código promocional',
                    ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar código promocional',
                'PROMOTION_CODE_UPDATE_ERROR',
                ['action' => 'update_promotion_code', 'promotion_code_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


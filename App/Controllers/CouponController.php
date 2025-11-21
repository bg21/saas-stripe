<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
use Flight;
use Config;

/**
 * Controller para gerenciar cupons de desconto
 */
class CouponController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo cupom
     * POST /v1/coupons
     * 
     * Body:
     *   - id (opcional): ID customizado
     *   - percent_off (opcional): Desconto percentual (0-100)
     *   - amount_off (opcional): Desconto em valor fixo (em centavos)
     *   - currency (opcional): Moeda para amount_off
     *   - duration (obrigatório): 'once', 'repeating', ou 'forever'
     *   - duration_in_months (opcional): Número de meses se duration for 'repeating'
     *   - max_redemptions (opcional): Número máximo de resgates
     *   - redeem_by (opcional): Data limite para resgate
     *   - name (opcional): Nome do cupom
     *   - metadata (opcional): Metadados
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_coupon']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_coupon']);
                    return;
                }
                $data = [];
            }

            // Validações obrigatórias
            $errors = [];
            if (empty($data['duration'])) {
                $errors['duration'] = 'Campo duration é obrigatório';
            }

            if (!isset($data['percent_off']) && !isset($data['amount_off'])) {
                $errors['discount'] = 'É necessário fornecer percent_off ou amount_off';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError('Dados inválidos', $errors, ['action' => 'create_coupon', 'tenant_id' => $tenantId]);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $coupon = $this->stripeService->createCoupon($data);

            ResponseHelper::sendCreated([
                'id' => $coupon->id,
                'name' => $coupon->name,
                'percent_off' => $coupon->percent_off,
                'amount_off' => $coupon->amount_off,
                'currency' => $coupon->currency,
                'duration' => $coupon->duration,
                'duration_in_months' => $coupon->duration_in_months,
                'max_redemptions' => $coupon->max_redemptions,
                'times_redeemed' => $coupon->times_redeemed,
                'redeem_by' => $coupon->redeem_by ? date('Y-m-d H:i:s', $coupon->redeem_by) : null,
                'valid' => $coupon->valid,
                'created' => date('Y-m-d H:i:s', $coupon->created),
                'metadata' => $coupon->metadata->toArray()
            ], 'Cupom criado com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_coupon', 'tenant_id' => $tenantId ?? null]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendStripeError($e, 'Erro ao criar cupom', ['action' => 'create_coupon', 'tenant_id' => $tenantId ?? null]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar cupom', 'COUPON_CREATE_ERROR', ['action' => 'create_coupon', 'tenant_id' => $tenantId ?? null]);
        }
    }

    /**
     * Lista cupons disponíveis
     * GET /v1/coupons
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados
     *   - starting_after: ID do cupom para paginação
     *   - ending_before: ID do cupom para paginação reversa
     */
    public function list(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_coupon']);
                return;
            }

            $queryParams = Flight::request()->query;
            
            $options = [];
            
            if (isset($queryParams['limit'])) {
                $options['limit'] = (int)$queryParams['limit'];
            }
            
            if (!empty($queryParams['starting_after'])) {
                $options['starting_after'] = $queryParams['starting_after'];
            }
            
            if (!empty($queryParams['ending_before'])) {
                $options['ending_before'] = $queryParams['ending_before'];
            }
            
            $coupons = $this->stripeService->listCoupons($options);
            
            // Formata resposta
            $formattedCoupons = [];
            foreach ($coupons->data as $coupon) {
                $formattedCoupons[] = [
                    'id' => $coupon->id,
                    'name' => $coupon->name,
                    'percent_off' => $coupon->percent_off,
                    'amount_off' => $coupon->amount_off,
                    'currency' => $coupon->currency,
                    'duration' => $coupon->duration,
                    'duration_in_months' => $coupon->duration_in_months,
                    'max_redemptions' => $coupon->max_redemptions,
                    'times_redeemed' => $coupon->times_redeemed,
                    'redeem_by' => $coupon->redeem_by ? date('Y-m-d H:i:s', $coupon->redeem_by) : null,
                    'valid' => $coupon->valid,
                    'created' => date('Y-m-d H:i:s', $coupon->created),
                    'metadata' => $coupon->metadata->toArray()
                ];
            }
            
            Flight::json([
                'success' => true,
                'data' => $formattedCoupons,
                'has_more' => $coupons->has_more,
                'count' => count($formattedCoupons)
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Erro ao listar cupons", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar cupons',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar cupons", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar cupons',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém cupom por ID
     * GET /v1/coupons/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_coupon']);
                return;
            }

            $coupon = $this->stripeService->getCoupon($id);

            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $coupon->id,
                    'name' => $coupon->name,
                    'percent_off' => $coupon->percent_off,
                    'amount_off' => $coupon->amount_off,
                    'currency' => $coupon->currency,
                    'duration' => $coupon->duration,
                    'duration_in_months' => $coupon->duration_in_months,
                    'max_redemptions' => $coupon->max_redemptions,
                    'times_redeemed' => $coupon->times_redeemed,
                    'redeem_by' => $coupon->redeem_by ? date('Y-m-d H:i:s', $coupon->redeem_by) : null,
                    'valid' => $coupon->valid,
                    'created' => date('Y-m-d H:i:s', $coupon->created),
                    'metadata' => $coupon->metadata->toArray()
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Cupom não encontrado", ['coupon_id' => $id]);
            Flight::json([
                'error' => 'Cupom não encontrado',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 404);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter cupom", [
                'error' => $e->getMessage(),
                'coupon_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter cupom',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Atualiza cupom
     * PUT /v1/coupons/:id
     * 
     * Nota: O Stripe não permite alterar campos principais de um cupom (percent_off, amount_off, duration, etc.).
     * Apenas é possível atualizar: name e metadata.
     * 
     * Body:
     *   - name (opcional): Nome do cupom
     *   - metadata (opcional): Metadados
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_coupon']);
                return;
            }

            // Primeiro, verifica se o cupom existe
            $coupon = $this->stripeService->getCoupon($id);

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_coupon']);
                    return;
                }
                $data = [];
            }

            // Preserva tenant_id nos metadados se metadata for atualizado
            if (isset($data['metadata'])) {
                $data['metadata']['tenant_id'] = $tenantId;
            }

            $coupon = $this->stripeService->updateCoupon($id, $data);

            Flight::json([
                'success' => true,
                'message' => 'Cupom atualizado com sucesso',
                'data' => [
                    'id' => $coupon->id,
                    'name' => $coupon->name,
                    'percent_off' => $coupon->percent_off,
                    'amount_off' => $coupon->amount_off,
                    'currency' => $coupon->currency,
                    'duration' => $coupon->duration,
                    'duration_in_months' => $coupon->duration_in_months,
                    'max_redemptions' => $coupon->max_redemptions,
                    'times_redeemed' => $coupon->times_redeemed,
                    'redeem_by' => $coupon->redeem_by ? date('Y-m-d H:i:s', $coupon->redeem_by) : null,
                    'valid' => $coupon->valid,
                    'created' => date('Y-m-d H:i:s', $coupon->created),
                    'metadata' => $coupon->metadata->toArray()
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro de validação ao atualizar cupom", [
                'error' => $e->getMessage(),
                'coupon_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro de validação',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Cupom não encontrado'], 404);
            } else {
                Logger::error("Erro ao atualizar cupom no Stripe", [
                    'error' => $e->getMessage(),
                    'coupon_id' => $id,
                    'tenant_id' => $tenantId ?? null
                ]);
                Flight::json([
                    'error' => 'Erro ao atualizar cupom',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar cupom", [
                'error' => $e->getMessage(),
                'coupon_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Deleta cupom
     * DELETE /v1/coupons/:id
     */
    public function delete(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_coupon']);
                return;
            }

            $coupon = $this->stripeService->deleteCoupon($id);

            Flight::json([
                'success' => true,
                'message' => 'Cupom deletado com sucesso',
                'data' => [
                    'id' => $coupon->id,
                    'deleted' => $coupon->deleted
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Logger::error("Cupom não encontrado para deletar", ['coupon_id' => $id]);
            Flight::json([
                'error' => 'Cupom não encontrado',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 404);
        } catch (\Exception $e) {
            Logger::error("Erro ao deletar cupom", [
                'error' => $e->getMessage(),
                'coupon_id' => $id,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao deletar cupom',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\Validator;
use Flight;
use Config;

/**
 * Controller para gerenciar Payouts (saques) do Stripe
 */
class PayoutController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Lista Payouts
     * GET /v1/payouts
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados (padrão: 10, máximo: 100)
     *   - starting_after: ID do payout para paginação
     *   - ending_before: ID do payout para paginação reversa
     *   - created_gte: Filtrar payouts criados a partir desta data (timestamp Unix)
     *   - created_lte: Filtrar payouts criados até esta data (timestamp Unix)
     *   - created_gt: Filtrar payouts criados após esta data (timestamp Unix)
     *   - created_lt: Filtrar payouts criados antes desta data (timestamp Unix)
     *   - status: Status do payout (pending, paid, failed, canceled)
     *   - destination: ID da conta/bank account de destino
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_payouts');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_payouts']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            
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

            // Filtros de data (created)
            $created = [];
            if (!empty($queryParams['created_gte'])) {
                $created['gte'] = (int)$queryParams['created_gte'];
            }
            if (!empty($queryParams['created_lte'])) {
                $created['lte'] = (int)$queryParams['created_lte'];
            }
            if (!empty($queryParams['created_gt'])) {
                $created['gt'] = (int)$queryParams['created_gt'];
            }
            if (!empty($queryParams['created_lt'])) {
                $created['lt'] = (int)$queryParams['created_lt'];
            }
            if (!empty($created)) {
                $options['created'] = $created;
            }
            
            // Filtro por status
            if (!empty($queryParams['status'])) {
                $validStatuses = ['pending', 'paid', 'failed', 'canceled', 'in_transit'];
                if (in_array($queryParams['status'], $validStatuses)) {
                    $options['status'] = $queryParams['status'];
                }
            }
            
            // Filtro por destination
            if (!empty($queryParams['destination'])) {
                $options['destination'] = $queryParams['destination'];
            }

            // Lista payouts
            $payouts = $this->stripeService->listPayouts($options);

            // Prepara resposta
            $response = [
                'success' => true,
                'data' => [],
                'has_more' => $payouts->has_more,
                'count' => count($payouts->data)
            ];

            foreach ($payouts->data as $payout) {
                $response['data'][] = [
                    'id' => $payout->id,
                    'object' => $payout->object,
                    'amount' => $payout->amount,
                    'amount_formatted' => number_format($payout->amount / 100, 2, ',', '.'),
                    'currency' => strtoupper($payout->currency),
                    'status' => $payout->status,
                    'arrival_date' => $payout->arrival_date ? date('Y-m-d H:i:s', $payout->arrival_date) : null,
                    'arrival_date_timestamp' => $payout->arrival_date,
                    'created' => date('Y-m-d H:i:s', $payout->created),
                    'created_timestamp' => $payout->created,
                    'description' => $payout->description ?? null,
                    'destination' => $payout->destination ?? null,
                    'method' => $payout->method ?? null,
                    'type' => $payout->type ?? null,
                    'metadata' => $payout->metadata->toArray()
                ];
            }

            // ✅ CORREÇÃO: Retorna array diretamente, meta separado
            Flight::json([
                'success' => true,
                'data' => $response['data'],
                'meta' => [
                    'has_more' => $response['has_more'],
                    'count' => $response['count']
                ]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao listar payouts',
                ['action' => 'list_payouts', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar payouts',
                'PAYOUT_LIST_ERROR',
                ['action' => 'list_payouts', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém Payout específico
     * GET /v1/payouts/:id
     */
    public function get(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_payouts');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_payouts']);
                return;
            }
            
            // Valida ID
            $idErrors = Validator::validateStripeId($id, 'payout_id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID do payout inválido',
                    $idErrors,
                    ['action' => 'get_payout', 'payout_id' => $id]
                );
                return;
            }

            // Obtém payout
            $payout = $this->stripeService->getPayout($id);

            // Prepara resposta
            $response = [
                'success' => true,
                'data' => [
                    'id' => $payout->id,
                    'object' => $payout->object,
                    'amount' => $payout->amount,
                    'amount_formatted' => number_format($payout->amount / 100, 2, ',', '.'),
                    'currency' => strtoupper($payout->currency),
                    'status' => $payout->status,
                    'arrival_date' => $payout->arrival_date ? date('Y-m-d H:i:s', $payout->arrival_date) : null,
                    'arrival_date_timestamp' => $payout->arrival_date,
                    'created' => date('Y-m-d H:i:s', $payout->created),
                    'created_timestamp' => $payout->created,
                    'description' => $payout->description ?? null,
                    'destination' => $payout->destination ?? null,
                    'method' => $payout->method ?? null,
                    'type' => $payout->type ?? null,
                    'failure_code' => $payout->failure_code ?? null,
                    'failure_message' => $payout->failure_message ?? null,
                    'metadata' => $payout->metadata->toArray()
                ]
            ];

            ResponseHelper::sendSuccess($response['data']);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                ResponseHelper::sendNotFoundError('Payout', ['action' => 'get_payout', 'payout_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter payout',
                    ['action' => 'get_payout', 'payout_id' => $id, 'tenant_id' => Flight::get('tenant_id') ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter payout',
                'PAYOUT_GET_ERROR',
                ['action' => 'get_payout', 'payout_id' => $id, 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Cria um novo Payout
     * POST /v1/payouts
     * 
     * Body:
     *   - amount (obrigatório): Valor em centavos
     *   - currency (obrigatório): Moeda (ex: 'brl', 'usd')
     *   - destination (opcional): ID da conta/bank account de destino
     *   - method (opcional): Método de transferência ('standard' ou 'instant')
     *   - description (opcional): Descrição do saque
     *   - metadata (opcional): Metadados
     */
    public function create(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('manage_payouts');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_payouts']);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'create_payout', 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            // ✅ Validação consistente
            $errors = [];
            if (empty($data['amount']) || !is_numeric($data['amount'])) {
                $errors['amount'] = 'Obrigatório e deve ser numérico';
            } else {
                $amount = (int)$data['amount'];
                if ($amount < 1) {
                    $errors['amount'] = 'Deve ser maior que zero';
                }
            }

            if (empty($data['currency'])) {
                $errors['currency'] = 'Obrigatório';
            } elseif (!is_string($data['currency']) || strlen($data['currency']) !== 3) {
                $errors['currency'] = 'Deve ser um código de moeda válido (3 letras, ex: BRL, USD)';
            }
            
            // Valida metadata se fornecido
            if (isset($data['metadata'])) {
                $metadataErrors = Validator::validateMetadata($data['metadata'], 'metadata');
                if (!empty($metadataErrors)) {
                    $errors = array_merge($errors, $metadataErrors);
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_payout', 'tenant_id' => $tenantId]
                );
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            // Cria payout
            $payout = $this->stripeService->createPayout($data);

            ResponseHelper::sendCreated([
                'id' => $payout->id,
                'amount' => $payout->amount,
                'amount_formatted' => number_format($payout->amount / 100, 2, ',', '.'),
                'currency' => strtoupper($payout->currency),
                'status' => $payout->status,
                'arrival_date' => $payout->arrival_date ? date('Y-m-d H:i:s', $payout->arrival_date) : null,
                'created' => date('Y-m-d H:i:s', $payout->created),
                'description' => $payout->description ?? null,
                'destination' => $payout->destination ?? null,
                'method' => $payout->method ?? null,
                'type' => $payout->type ?? null,
                'metadata' => $payout->metadata->toArray()
            ], 'Payout criado com sucesso');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError(
                $e->getMessage(),
                [],
                ['action' => 'create_payout', 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar payout',
                ['action' => 'create_payout', 'tenant_id' => $tenantId ?? null]
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar payout',
                'PAYOUT_CREATE_ERROR',
                ['action' => 'create_payout', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Cancela um Payout pendente
     * POST /v1/payouts/:id/cancel
     */
    public function cancel(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('manage_payouts');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_payouts']);
                return;
            }
            
            // Valida ID
            $idErrors = Validator::validateStripeId($id, 'payout_id');
            if (!empty($idErrors)) {
                ResponseHelper::sendValidationError(
                    'ID do payout inválido',
                    $idErrors,
                    ['action' => 'get_payout', 'payout_id' => $id]
                );
                return;
            }

            // Cancela payout
            $payout = $this->stripeService->cancelPayout($id);

            ResponseHelper::sendSuccess([
                'id' => $payout->id,
                'amount' => $payout->amount,
                'amount_formatted' => number_format($payout->amount / 100, 2, ',', '.'),
                'currency' => strtoupper($payout->currency),
                'status' => $payout->status,
                'arrival_date' => $payout->arrival_date ? date('Y-m-d H:i:s', $payout->arrival_date) : null,
                'created' => date('Y-m-d H:i:s', $payout->created),
                'description' => $payout->description ?? null,
                'destination' => $payout->destination ?? null,
                'method' => $payout->method ?? null,
                'type' => $payout->type ?? null,
                'metadata' => $payout->metadata->toArray()
            ], 200, 'Payout cancelado com sucesso');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                ResponseHelper::sendNotFoundError('Payout', ['action' => 'cancel_payout', 'payout_id' => $id, 'tenant_id' => $tenantId]);
            } elseif ($e->getStripeCode() === 'payout_cannot_be_canceled') {
                ResponseHelper::sendValidationError(
                    'Payout não pode ser cancelado',
                    ['payout_id' => 'Apenas payouts pendentes podem ser cancelados'],
                    ['action' => 'cancel_payout', 'payout_id' => $id, 'tenant_id' => $tenantId]
                );
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao cancelar payout',
                    ['action' => 'cancel_payout', 'payout_id' => $id, 'tenant_id' => $tenantId ?? null]
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao cancelar payout',
                'PAYOUT_CANCEL_ERROR',
                ['action' => 'cancel_payout', 'payout_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


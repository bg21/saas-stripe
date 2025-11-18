<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
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
                Flight::json(['error' => 'Não autenticado'], 401);
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

            Flight::json($response);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao listar payouts", [
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            
            Flight::json([
                'error' => 'Erro ao listar payouts',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao listar payouts", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            
            Flight::json([
                'error' => 'Erro ao listar payouts',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            if (empty($id)) {
                Flight::halt(400, json_encode(['error' => 'ID do payout é obrigatório']));
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

            Flight::json($response);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao obter payout", [
                'payout_id' => $id,
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                Flight::json([
                    'error' => 'Payout não encontrado',
                    'message' => $e->getMessage()
                ], 404);
            } else {
                Flight::json([
                    'error' => 'Erro ao obter payout',
                    'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao obter payout", [
                'payout_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            
            Flight::json([
                'error' => 'Erro ao obter payout',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
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
            if (empty($data['amount']) || !is_numeric($data['amount'])) {
                Flight::json(['error' => 'Campo amount é obrigatório e deve ser numérico'], 400);
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

            // Cria payout
            $payout = $this->stripeService->createPayout($data);

            // Prepara resposta
            Flight::json([
                'success' => true,
                'message' => 'Payout criado com sucesso',
                'data' => [
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
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro de validação ao criar payout", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro de validação',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao criar payout", [
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar payout',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao criar payout", [
                'error' => $e->getMessage(),
                'trace' => Config::isDevelopment() ? $e->getTraceAsString() : null,
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar payout',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            if (empty($id)) {
                Flight::halt(400, json_encode(['error' => 'ID do payout é obrigatório']));
                return;
            }

            // Cancela payout
            $payout = $this->stripeService->cancelPayout($id);

            // Prepara resposta
            Flight::json([
                'success' => true,
                'message' => 'Payout cancelado com sucesso',
                'data' => [
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
                ]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao cancelar payout", [
                'payout_id' => $id,
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode(),
                'tenant_id' => $tenantId ?? null
            ]);
            
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                Flight::json([
                    'error' => 'Payout não encontrado',
                    'message' => $e->getMessage()
                ], 404);
            } elseif ($e->getStripeCode() === 'payout_cannot_be_canceled') {
                Flight::json([
                    'error' => 'Payout não pode ser cancelado',
                    'message' => Config::isDevelopment() ? $e->getMessage() : 'Apenas payouts pendentes podem ser cancelados'
                ], 400);
            } else {
                Flight::json([
                    'error' => 'Erro ao cancelar payout',
                    'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao cancelar payout", [
                'payout_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            
            Flight::json([
                'error' => 'Erro ao cancelar payout',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        }
    }
}


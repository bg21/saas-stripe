<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar Balance Transactions (transações de saldo) do Stripe
 */
class BalanceTransactionController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Lista Balance Transactions
     * GET /v1/balance-transactions
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados (padrão: 10)
     *   - starting_after: ID da transação para paginação
     *   - ending_before: ID da transação para paginação reversa
     *   - created_gte: Filtrar transações criadas a partir desta data (timestamp Unix)
     *   - created_lte: Filtrar transações criadas até esta data (timestamp Unix)
     *   - created_gt: Filtrar transações criadas após esta data (timestamp Unix)
     *   - created_lt: Filtrar transações criadas antes desta data (timestamp Unix)
     *   - payout: ID do payout para filtrar transações de um payout específico
     *   - type: Tipo de transação (charge, refund, adjustment, application_fee, etc.)
     *   - currency: Código da moeda (ex: 'brl', 'usd')
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_balance_transactions');
            
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

            // Processa filtros de data (created)
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
            
            if (!empty($queryParams['payout'])) {
                $options['payout'] = $queryParams['payout'];
            }
            
            if (!empty($queryParams['type'])) {
                $options['type'] = $queryParams['type'];
            }
            
            if (!empty($queryParams['currency'])) {
                $options['currency'] = strtolower($queryParams['currency']);
            }
            
            $balanceTransactions = $this->stripeService->listBalanceTransactions($options);
            
            // Formata resposta
            $formattedTransactions = [];
            foreach ($balanceTransactions->data as $transaction) {
                $formattedTransactions[] = [
                    'id' => $transaction->id,
                    'object' => $transaction->object,
                    'amount' => $transaction->amount,
                    'currency' => strtoupper($transaction->currency),
                    'net' => $transaction->net,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'description' => $transaction->description ?? null,
                    'fee' => $transaction->fee,
                    'fee_details' => $transaction->fee_details ?? [],
                    'created' => date('Y-m-d H:i:s', $transaction->created),
                    'available_on' => $transaction->available_on ? date('Y-m-d H:i:s', $transaction->available_on) : null,
                    'exchange_rate' => $transaction->exchange_rate ?? null,
                    'source' => $transaction->source ?? null,
                    'reporting_category' => $transaction->reporting_category ?? null
                ];
            }
            
            Flight::json([
                'success' => true,
                'data' => $formattedTransactions,
                'has_more' => $balanceTransactions->has_more,
                'count' => count($formattedTransactions)
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao listar balance transactions", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::json([
                'error' => 'Erro ao listar balance transactions',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao listar balance transactions", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::json([
                'error' => 'Erro ao listar balance transactions',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém Balance Transaction por ID
     * GET /v1/balance-transactions/:id
     */
    public function get(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_balance_transactions');
            
            if (empty($id)) {
                Flight::halt(400, json_encode(['error' => 'ID da balance transaction é obrigatório']));
                return;
            }

            $balanceTransaction = $this->stripeService->getBalanceTransaction($id);
            
            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $balanceTransaction->id,
                    'object' => $balanceTransaction->object,
                    'amount' => $balanceTransaction->amount,
                    'currency' => strtoupper($balanceTransaction->currency),
                    'net' => $balanceTransaction->net,
                    'type' => $balanceTransaction->type,
                    'status' => $balanceTransaction->status,
                    'description' => $balanceTransaction->description ?? null,
                    'fee' => $balanceTransaction->fee,
                    'fee_details' => $balanceTransaction->fee_details ?? [],
                    'created' => date('Y-m-d H:i:s', $balanceTransaction->created),
                    'available_on' => $balanceTransaction->available_on ? date('Y-m-d H:i:s', $balanceTransaction->available_on) : null,
                    'exchange_rate' => $balanceTransaction->exchange_rate ?? null,
                    'source' => $balanceTransaction->source ?? null,
                    'reporting_category' => $balanceTransaction->reporting_category ?? null
                ]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao obter balance transaction", [
                'balance_transaction_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                Flight::json([
                    'error' => 'Balance transaction não encontrada',
                    'message' => $e->getMessage()
                ], 404);
            } else {
                Flight::json([
                    'error' => 'Erro ao obter balance transaction',
                    'message' => $e->getMessage()
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao obter balance transaction", [
                'balance_transaction_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::json([
                'error' => 'Erro ao obter balance transaction',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


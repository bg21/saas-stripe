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
     * Obtém Balance Transaction por ID com detalhes expandidos
     * GET /v1/balance-transactions/:id
     * 
     * Retorna informações detalhadas da transação, incluindo:
     * - Informações básicas da transação
     * - Detalhes do source (charge, refund, etc.) se disponível
     * - Informações de payout se houver
     * - Detalhes de taxas
     * - Informações do customer se aplicável
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
            
            // Prepara dados básicos
            $data = [
                'id' => $balanceTransaction->id,
                'object' => $balanceTransaction->object,
                'amount' => $balanceTransaction->amount,
                'amount_formatted' => number_format(abs($balanceTransaction->amount) / 100, 2, ',', '.'),
                'currency' => strtoupper($balanceTransaction->currency),
                'net' => $balanceTransaction->net,
                'net_formatted' => number_format(abs($balanceTransaction->net) / 100, 2, ',', '.'),
                'type' => $balanceTransaction->type,
                'status' => $balanceTransaction->status,
                'description' => $balanceTransaction->description ?? null,
                'fee' => $balanceTransaction->fee,
                'fee_formatted' => number_format(abs($balanceTransaction->fee) / 100, 2, ',', '.'),
                'fee_details' => [],
                'created' => date('Y-m-d H:i:s', $balanceTransaction->created),
                'created_timestamp' => $balanceTransaction->created,
                'available_on' => $balanceTransaction->available_on ? date('Y-m-d H:i:s', $balanceTransaction->available_on) : null,
                'available_on_timestamp' => $balanceTransaction->available_on ?? null,
                'exchange_rate' => $balanceTransaction->exchange_rate ?? null,
                'source' => null,
                'source_details' => null,
                'reporting_category' => $balanceTransaction->reporting_category ?? null,
                'payout' => $balanceTransaction->payout ?? null,
                'payout_details' => null
            ];

            // Processa fee_details
            if (!empty($balanceTransaction->fee_details) && is_array($balanceTransaction->fee_details)) {
                foreach ($balanceTransaction->fee_details as $feeDetail) {
                    $data['fee_details'][] = [
                        'amount' => $feeDetail->amount ?? null,
                        'amount_formatted' => isset($feeDetail->amount) ? number_format(abs($feeDetail->amount) / 100, 2, ',', '.') : null,
                        'currency' => isset($feeDetail->currency) ? strtoupper($feeDetail->currency) : null,
                        'description' => $feeDetail->description ?? null,
                        'type' => $feeDetail->type ?? null
                    ];
                }
            }

            // Tenta obter detalhes do source (charge, refund, etc.)
            if (!empty($balanceTransaction->source)) {
                $sourceId = is_string($balanceTransaction->source) 
                    ? $balanceTransaction->source 
                    : $balanceTransaction->source->id ?? null;
                
                $data['source'] = $sourceId;

                if ($sourceId) {
                    try {
                        // Determina o tipo do source baseado no ID ou type
                        $sourceType = $balanceTransaction->type ?? 'unknown';
                        
                        // Se for uma charge, busca detalhes
                        if (strpos($sourceId, 'ch_') === 0 || $sourceType === 'charge') {
                            try {
                                $charge = $this->stripeService->getCharge($sourceId);
                                $data['source_details'] = [
                                    'type' => 'charge',
                                    'id' => $charge->id,
                                    'amount' => $charge->amount,
                                    'amount_formatted' => number_format($charge->amount / 100, 2, ',', '.'),
                                    'currency' => strtoupper($charge->currency),
                                    'status' => $charge->status,
                                    'paid' => $charge->paid,
                                    'refunded' => $charge->refunded,
                                    'amount_refunded' => $charge->amount_refunded,
                                    'amount_refunded_formatted' => number_format($charge->amount_refunded / 100, 2, ',', '.'),
                                    'description' => $charge->description,
                                    'customer' => $charge->customer,
                                    'payment_method' => $charge->payment_method,
                                    'payment_intent' => $charge->payment_intent,
                                    'receipt_url' => $charge->receipt_url,
                                    'created' => date('Y-m-d H:i:s', $charge->created),
                                    'billing_details' => $charge->billing_details ? [
                                        'name' => $charge->billing_details->name ?? null,
                                        'email' => $charge->billing_details->email ?? null,
                                        'phone' => $charge->billing_details->phone ?? null,
                                        'address' => $charge->billing_details->address ? [
                                            'line1' => $charge->billing_details->address->line1 ?? null,
                                            'line2' => $charge->billing_details->address->line2 ?? null,
                                            'city' => $charge->billing_details->address->city ?? null,
                                            'state' => $charge->billing_details->address->state ?? null,
                                            'postal_code' => $charge->billing_details->address->postal_code ?? null,
                                            'country' => $charge->billing_details->address->country ?? null
                                        ] : null
                                    ] : null,
                                    'outcome' => $charge->outcome ? [
                                        'type' => $charge->outcome->type ?? null,
                                        'network_status' => $charge->outcome->network_status ?? null,
                                        'reason' => $charge->outcome->reason ?? null,
                                        'risk_level' => $charge->outcome->risk_level ?? null,
                                        'risk_score' => $charge->outcome->risk_score ?? null,
                                        'seller_message' => $charge->outcome->seller_message ?? null
                                    ] : null
                                ];
                            } catch (\Exception $e) {
                                Logger::warning("Não foi possível obter detalhes da charge", [
                                    'charge_id' => $sourceId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        // Se for um refund, busca detalhes
                        elseif (strpos($sourceId, 're_') === 0 || $sourceType === 'refund') {
                            try {
                                $refund = $this->stripeService->getRefund($sourceId);
                                $data['source_details'] = [
                                    'type' => 'refund',
                                    'id' => $refund->id,
                                    'amount' => $refund->amount,
                                    'amount_formatted' => number_format($refund->amount / 100, 2, ',', '.'),
                                    'currency' => strtoupper($refund->currency),
                                    'status' => $refund->status,
                                    'reason' => $refund->reason,
                                    'charge' => $refund->charge,
                                    'payment_intent' => $refund->payment_intent,
                                    'created' => date('Y-m-d H:i:s', $refund->created)
                                ];
                            } catch (\Exception $e) {
                                Logger::warning("Não foi possível obter detalhes do refund", [
                                    'refund_id' => $sourceId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::warning("Erro ao obter detalhes do source", [
                            'source_id' => $sourceId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Tenta obter detalhes do payout se houver
            if (!empty($balanceTransaction->payout)) {
                $payoutId = is_string($balanceTransaction->payout) 
                    ? $balanceTransaction->payout 
                    : $balanceTransaction->payout->id ?? null;
                
                if ($payoutId) {
                    try {
                        $payout = $this->stripeService->getPayout($payoutId);
                        $data['payout_details'] = [
                            'id' => $payout->id,
                            'amount' => $payout->amount,
                            'amount_formatted' => number_format($payout->amount / 100, 2, ',', '.'),
                            'currency' => strtoupper($payout->currency),
                            'status' => $payout->status,
                            'arrival_date' => $payout->arrival_date ? date('Y-m-d H:i:s', $payout->arrival_date) : null,
                            'created' => date('Y-m-d H:i:s', $payout->created),
                            'description' => $payout->description,
                            'destination' => $payout->destination,
                            'method' => $payout->method,
                            'type' => $payout->type
                        ];
                    } catch (\Exception $e) {
                        Logger::warning("Não foi possível obter detalhes do payout", [
                            'payout_id' => $payoutId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            Flight::json([
                'success' => true,
                'data' => $data
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


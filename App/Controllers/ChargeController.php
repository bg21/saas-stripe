<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar Charges (cobranças) do Stripe
 */
class ChargeController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Lista Charges
     * GET /v1/charges
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados (padrão: 10, máximo: 100)
     *   - starting_after: ID da charge para paginação
     *   - ending_before: ID da charge para paginação reversa
     *   - created_gte: Filtrar charges criadas a partir desta data (timestamp Unix)
     *   - created_lte: Filtrar charges criadas até esta data (timestamp Unix)
     *   - created_gt: Filtrar charges criadas após esta data (timestamp Unix)
     *   - created_lt: Filtrar charges criadas antes desta data (timestamp Unix)
     *   - customer: ID do customer para filtrar charges de um customer específico
     *   - payment_intent: ID do payment intent para filtrar charges de um payment intent específico
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_charges');
            
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

            // Filtros de data
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

            // Filtro por customer
            if (!empty($queryParams['customer'])) {
                $options['customer'] = $queryParams['customer'];
            }

            // Filtro por payment_intent
            if (!empty($queryParams['payment_intent'])) {
                $options['payment_intent'] = $queryParams['payment_intent'];
            }

            // Lista charges
            $charges = $this->stripeService->listCharges($options);

            // Prepara resposta
            $response = [
                'object' => 'list',
                'data' => [],
                'has_more' => $charges->has_more
            ];

            foreach ($charges->data as $charge) {
                $response['data'][] = [
                    'id' => $charge->id,
                    'object' => $charge->object,
                    'amount' => $charge->amount,
                    'amount_captured' => $charge->amount_captured,
                    'amount_refunded' => $charge->amount_refunded,
                    'currency' => strtoupper($charge->currency),
                    'customer' => $charge->customer,
                    'description' => $charge->description,
                    'status' => $charge->status,
                    'paid' => $charge->paid,
                    'refunded' => $charge->refunded,
                    'payment_intent' => $charge->payment_intent,
                    'payment_method' => $charge->payment_method,
                    'receipt_email' => $charge->receipt_email,
                    'receipt_url' => $charge->receipt_url,
                    'created' => date('Y-m-d H:i:s', $charge->created),
                    'metadata' => $charge->metadata->toArray()
                ];
            }

            Flight::json($response);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao listar charges", [
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode()
            ]);
            
            Flight::json([
                'error' => 'Erro ao listar charges',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao listar charges", [
                'error' => $e->getMessage()
            ]);
            
            Flight::json([
                'error' => 'Erro ao listar charges',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        }
    }

    /**
     * Obtém Charge específica
     * GET /v1/charges/:id
     */
    public function get(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_charges');
            
            // Obtém charge
            $charge = $this->stripeService->getCharge($id);

            // Prepara resposta
            $response = [
                'id' => $charge->id,
                'object' => $charge->object,
                'amount' => $charge->amount,
                'amount_captured' => $charge->amount_captured,
                'amount_refunded' => $charge->amount_refunded,
                'currency' => strtoupper($charge->currency),
                'customer' => $charge->customer,
                'description' => $charge->description,
                'status' => $charge->status,
                'paid' => $charge->paid,
                'refunded' => $charge->refunded,
                'payment_intent' => $charge->payment_intent,
                'payment_method' => $charge->payment_method,
                'receipt_email' => $charge->receipt_email,
                'receipt_url' => $charge->receipt_url,
                'created' => date('Y-m-d H:i:s', $charge->created),
                'metadata' => $charge->metadata->toArray(),
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

            Flight::json($response);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            if ($e->getStripeCode() === 'resource_missing' || $e->getHttpStatus() === 404) {
                Flight::halt(404, json_encode(['error' => 'Charge não encontrada']));
                return;
            }

            Logger::error("Erro ao obter charge", [
                'charge_id' => $id,
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode()
            ]);
            
            Flight::json([
                'error' => 'Erro ao obter charge',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao obter charge", [
                'charge_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Flight::json([
                'error' => 'Erro ao obter charge',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        }
    }

    /**
     * Atualiza Charge (apenas metadata)
     * PUT /v1/charges/:id
     * 
     * Body:
     *   - metadata (opcional): Metadados para atualizar
     */
    public function update(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('manage_charges');
            
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

            // Valida que há dados para atualizar
            if (empty($data)) {
                Flight::json(['error' => 'Nenhum dado fornecido para atualização'], 400);
                return;
            }

            // Valida que apenas metadata está sendo atualizado
            $allowedFields = ['metadata'];
            $invalidFields = array_diff(array_keys($data), $allowedFields);
            
            if (!empty($invalidFields)) {
                Flight::json([
                    'error' => 'Campos inválidos',
                    'message' => 'Apenas metadata pode ser atualizado. Campos inválidos: ' . implode(', ', $invalidFields)
                ], 400);
                return;
            }

            // Atualiza charge
            $charge = $this->stripeService->updateCharge($id, $data);

            // Prepara resposta
            $response = [
                'id' => $charge->id,
                'metadata' => $charge->metadata->toArray(),
                'updated' => true
            ];

            Flight::json($response);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            if ($e->getStripeCode() === 'resource_missing' || $e->getHttpStatus() === 404) {
                Flight::halt(404, json_encode(['error' => 'Charge não encontrada']));
                return;
            }

            Logger::error("Erro ao atualizar charge", [
                'charge_id' => $id,
                'error' => $e->getMessage(),
                'stripe_error' => $e->getStripeCode()
            ]);
            
            Flight::json([
                'error' => 'Erro ao atualizar charge',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao atualizar charge", [
                'charge_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            Flight::json([
                'error' => 'Erro ao atualizar charge',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Erro ao processar requisição'
            ], 500);
        }
    }
}


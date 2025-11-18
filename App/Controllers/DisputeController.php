<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar Disputes (disputas/chargebacks) do Stripe
 */
class DisputeController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Lista Disputes
     * GET /v1/disputes
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados (padrão: 10)
     *   - starting_after: ID da disputa para paginação
     *   - ending_before: ID da disputa para paginação reversa
     *   - created_gte: Filtrar disputas criadas a partir desta data (timestamp Unix)
     *   - created_lte: Filtrar disputas criadas até esta data (timestamp Unix)
     *   - created_gt: Filtrar disputas criadas após esta data (timestamp Unix)
     *   - created_lt: Filtrar disputas criadas antes desta data (timestamp Unix)
     *   - charge: ID da charge para filtrar disputas de uma charge específica
     *   - payment_intent: ID do payment intent para filtrar disputas de um payment intent específico
     */
    public function list(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_disputes');
            
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
            
            if (!empty($queryParams['charge'])) {
                $options['charge'] = $queryParams['charge'];
            }
            
            if (!empty($queryParams['payment_intent'])) {
                $options['payment_intent'] = $queryParams['payment_intent'];
            }
            
            $disputes = $this->stripeService->listDisputes($options);
            
            // Formata resposta
            $formattedDisputes = [];
            foreach ($disputes->data as $dispute) {
                $formattedDisputes[] = [
                    'id' => $dispute->id,
                    'object' => $dispute->object,
                    'amount' => $dispute->amount,
                    'currency' => strtoupper($dispute->currency),
                    'status' => $dispute->status,
                    'reason' => $dispute->reason,
                    'charge' => $dispute->charge,
                    'payment_intent' => $dispute->payment_intent ?? null,
                    'created' => date('Y-m-d H:i:s', $dispute->created),
                    'evidence_details' => [
                        'due_by' => $dispute->evidence_details->due_by ? date('Y-m-d H:i:s', $dispute->evidence_details->due_by) : null,
                        'has_evidence' => $dispute->evidence_details->has_evidence,
                        'past_due' => $dispute->evidence_details->past_due,
                        'submission_count' => $dispute->evidence_details->submission_count
                    ],
                    'evidence' => [
                        'access_activity_log' => $dispute->evidence->access_activity_log ?? null,
                        'billing_address' => $dispute->evidence->billing_address ?? null,
                        'cancellation_policy' => $dispute->evidence->cancellation_policy ?? null,
                        'cancellation_policy_disclosure' => $dispute->evidence->cancellation_policy_disclosure ?? null,
                        'cancellation_rebuttal' => $dispute->evidence->cancellation_rebuttal ?? null,
                        'customer_communication' => $dispute->evidence->customer_communication ?? null,
                        'customer_email_address' => $dispute->evidence->customer_email_address ?? null,
                        'customer_name' => $dispute->evidence->customer_name ?? null,
                        'customer_purchase_ip' => $dispute->evidence->customer_purchase_ip ?? null,
                        'customer_signature' => $dispute->evidence->customer_signature ?? null,
                        'duplicate_charge_documentation' => $dispute->evidence->duplicate_charge_documentation ?? null,
                        'duplicate_charge_explanation' => $dispute->evidence->duplicate_charge_explanation ?? null,
                        'duplicate_charge_id' => $dispute->evidence->duplicate_charge_id ?? null,
                        'product_description' => $dispute->evidence->product_description ?? null,
                        'receipt' => $dispute->evidence->receipt ?? null,
                        'refund_policy' => $dispute->evidence->refund_policy ?? null,
                        'refund_policy_disclosure' => $dispute->evidence->refund_policy_disclosure ?? null,
                        'refund_refusal_explanation' => $dispute->evidence->refund_refusal_explanation ?? null,
                        'service_date' => $dispute->evidence->service_date ?? null,
                        'service_documentation' => $dispute->evidence->service_documentation ?? null,
                        'shipping_address' => $dispute->evidence->shipping_address ?? null,
                        'shipping_carrier' => $dispute->evidence->shipping_carrier ?? null,
                        'shipping_date' => $dispute->evidence->shipping_date ?? null,
                        'shipping_documentation' => $dispute->evidence->shipping_documentation ?? null,
                        'shipping_tracking_number' => $dispute->evidence->shipping_tracking_number ?? null,
                        'uncategorized_file' => $dispute->evidence->uncategorized_file ?? null,
                        'uncategorized_text' => $dispute->evidence->uncategorized_text ?? null
                    ],
                    'metadata' => $dispute->metadata->toArray() ?? []
                ];
            }
            
            Flight::json([
                'success' => true,
                'data' => $formattedDisputes,
                'has_more' => $disputes->has_more,
                'count' => count($formattedDisputes)
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao listar disputes", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::json([
                'error' => 'Erro ao listar disputes',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao listar disputes", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::json([
                'error' => 'Erro ao listar disputes',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém Dispute por ID
     * GET /v1/disputes/:id
     */
    public function get(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_disputes');
            
            if (empty($id)) {
                Flight::halt(400, json_encode(['error' => 'ID da dispute é obrigatório']));
                return;
            }

            $dispute = $this->stripeService->getDispute($id);
            
            Flight::json([
                'success' => true,
                'data' => [
                    'id' => $dispute->id,
                    'object' => $dispute->object,
                    'amount' => $dispute->amount,
                    'currency' => strtoupper($dispute->currency),
                    'status' => $dispute->status,
                    'reason' => $dispute->reason,
                    'charge' => $dispute->charge,
                    'payment_intent' => $dispute->payment_intent ?? null,
                    'created' => date('Y-m-d H:i:s', $dispute->created),
                    'evidence_details' => [
                        'due_by' => $dispute->evidence_details->due_by ? date('Y-m-d H:i:s', $dispute->evidence_details->due_by) : null,
                        'has_evidence' => $dispute->evidence_details->has_evidence,
                        'past_due' => $dispute->evidence_details->past_due,
                        'submission_count' => $dispute->evidence_details->submission_count
                    ],
                    'evidence' => [
                        'access_activity_log' => $dispute->evidence->access_activity_log ?? null,
                        'billing_address' => $dispute->evidence->billing_address ?? null,
                        'cancellation_policy' => $dispute->evidence->cancellation_policy ?? null,
                        'cancellation_policy_disclosure' => $dispute->evidence->cancellation_policy_disclosure ?? null,
                        'cancellation_rebuttal' => $dispute->evidence->cancellation_rebuttal ?? null,
                        'customer_communication' => $dispute->evidence->customer_communication ?? null,
                        'customer_email_address' => $dispute->evidence->customer_email_address ?? null,
                        'customer_name' => $dispute->evidence->customer_name ?? null,
                        'customer_purchase_ip' => $dispute->evidence->customer_purchase_ip ?? null,
                        'customer_signature' => $dispute->evidence->customer_signature ?? null,
                        'duplicate_charge_documentation' => $dispute->evidence->duplicate_charge_documentation ?? null,
                        'duplicate_charge_explanation' => $dispute->evidence->duplicate_charge_explanation ?? null,
                        'duplicate_charge_id' => $dispute->evidence->duplicate_charge_id ?? null,
                        'product_description' => $dispute->evidence->product_description ?? null,
                        'receipt' => $dispute->evidence->receipt ?? null,
                        'refund_policy' => $dispute->evidence->refund_policy ?? null,
                        'refund_policy_disclosure' => $dispute->evidence->refund_policy_disclosure ?? null,
                        'refund_refusal_explanation' => $dispute->evidence->refund_refusal_explanation ?? null,
                        'service_date' => $dispute->evidence->service_date ?? null,
                        'service_documentation' => $dispute->evidence->service_documentation ?? null,
                        'shipping_address' => $dispute->evidence->shipping_address ?? null,
                        'shipping_carrier' => $dispute->evidence->shipping_carrier ?? null,
                        'shipping_date' => $dispute->evidence->shipping_date ?? null,
                        'shipping_documentation' => $dispute->evidence->shipping_documentation ?? null,
                        'shipping_tracking_number' => $dispute->evidence->shipping_tracking_number ?? null,
                        'uncategorized_file' => $dispute->evidence->uncategorized_file ?? null,
                        'uncategorized_text' => $dispute->evidence->uncategorized_text ?? null
                    ],
                    'metadata' => $dispute->metadata->toArray() ?? []
                ]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao obter dispute", [
                'dispute_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                Flight::halt(404, json_encode([
                    'error' => 'Dispute não encontrada',
                    'message' => $e->getMessage()
                ]));
            } else {
                Flight::halt(400, json_encode([
                    'error' => 'Erro ao obter dispute',
                    'message' => $e->getMessage()
                ]));
            }
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao obter dispute", [
                'dispute_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao obter dispute',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }

    /**
     * Atualiza Dispute (adiciona evidências)
     * PUT /v1/disputes/:id
     * 
     * Body JSON com campos de evidência (todos opcionais):
     *   - access_activity_log, billing_address, cancellation_policy, etc.
     */
    public function update(string $id): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('manage_disputes');
            
            if (empty($id)) {
                Flight::halt(400, json_encode(['error' => 'ID da dispute é obrigatório']));
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
            
            // Valida se há dados de evidência
            if (empty($data)) {
                Flight::halt(400, json_encode(['error' => 'Dados de evidência são obrigatórios']));
                return;
            }

            $dispute = $this->stripeService->updateDispute($id, $data);
            
            Flight::json([
                'success' => true,
                'message' => 'Dispute atualizada com sucesso',
                'data' => [
                    'id' => $dispute->id,
                    'status' => $dispute->status,
                    'evidence_details' => [
                        'due_by' => $dispute->evidence_details->due_by ? date('Y-m-d H:i:s', $dispute->evidence_details->due_by) : null,
                        'has_evidence' => $dispute->evidence_details->has_evidence,
                        'past_due' => $dispute->evidence_details->past_due,
                        'submission_count' => $dispute->evidence_details->submission_count
                    ]
                ]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao atualizar dispute", [
                'dispute_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            
            // Se não encontrado, retorna 404
            if ($e->getHttpStatus() === 404) {
                Flight::halt(404, json_encode([
                    'error' => 'Dispute não encontrada',
                    'message' => $e->getMessage()
                ]));
            } else {
                Flight::halt(400, json_encode([
                    'error' => 'Erro ao atualizar dispute',
                    'message' => $e->getMessage()
                ]));
            }
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao atualizar dispute", [
                'dispute_id' => $id,
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id')
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro ao atualizar dispute',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ]));
        }
    }
}


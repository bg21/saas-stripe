<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para gerenciar clientes Stripe
 */
class CustomerController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Cria um novo cliente
     * POST /v1/customers
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null || $tenantId === '') {
                Flight::json([
                    'error' => 'Não autenticado',
                    'debug' => Config::isDevelopment() ? [
                        'tenant_id' => $tenantId,
                        'type' => gettype($tenantId),
                        'all_flight_data' => [
                            'tenant_id' => Flight::get('tenant_id'),
                            'is_master' => Flight::get('is_master'),
                            'tenant' => Flight::get('tenant')
                        ]
                    ] : null
                ], 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            if (empty($data['email'])) {
                Flight::json(['error' => 'Email é obrigatório'], 400);
                return;
            }

            $customer = $this->paymentService->createCustomer($tenantId, $data);

            Flight::json([
                'success' => true,
                'data' => $customer
            ], 201);
        } catch (\Exception $e) {
            Logger::error("Erro ao criar cliente", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro ao criar cliente',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Lista clientes do tenant
     * GET /v1/customers
     */
    public function list(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }
            
            $customerModel = new \App\Models\Customer();
            $customers = $customerModel->findByTenant($tenantId);

            Flight::json([
                'success' => true,
                'data' => $customers,
                'count' => count($customers)
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar clientes", ['error' => $e->getMessage()]);
            Flight::json(['error' => 'Erro ao listar clientes'], 500);
        }
    }
}


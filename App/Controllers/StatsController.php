<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Models\Customer;
use App\Models\Subscription;
use Flight;
use Config;

/**
 * Controller para fornecer estatísticas e métricas do sistema
 */
class StatsController
{
    private StripeService $stripeService;
    private Customer $customerModel;
    private Subscription $subscriptionModel;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->customerModel = new Customer();
        $this->subscriptionModel = new Subscription();
    }

    /**
     * Retorna estatísticas do tenant
     * GET /v1/stats
     * 
     * Query params opcionais:
     *   - period: 'today', 'week', 'month', 'year', 'all' (padrão: 'all')
     */
    public function get(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query;
            $period = $queryParams['period'] ?? 'all';

            // Calcula período
            $dateFilter = $this->getDateFilter($period);

            // Estatísticas de Customers
            $customers = $this->customerModel->findByTenant($tenantId);
            $totalCustomers = count($customers);
            
            $newCustomers = 0;
            if ($dateFilter) {
                foreach ($customers as $customer) {
                    $createdAt = strtotime($customer['created_at']);
                    if ($createdAt >= $dateFilter['start'] && $createdAt <= $dateFilter['end']) {
                        $newCustomers++;
                    }
                }
            } else {
                $newCustomers = $totalCustomers;
            }

            // Estatísticas de Assinaturas
            $subscriptions = $this->subscriptionModel->findByTenant($tenantId);
            $totalSubscriptions = count($subscriptions);
            
            $activeSubscriptions = 0;
            $canceledSubscriptions = 0;
            $trialingSubscriptions = 0;
            $newSubscriptions = 0;
            $mrr = 0; // Monthly Recurring Revenue

            foreach ($subscriptions as $subscription) {
                $status = strtolower($subscription['status'] ?? '');
                
                if ($status === 'active') {
                    $activeSubscriptions++;
                } elseif ($status === 'canceled') {
                    $canceledSubscriptions++;
                } elseif ($status === 'trialing') {
                    $trialingSubscriptions++;
                }

                // Calcula MRR (apenas assinaturas ativas)
                // O amount já está em formato monetário (DECIMAL), não em centavos
                if ($status === 'active' && isset($subscription['amount'])) {
                    $amount = (float)($subscription['amount'] ?? 0);
                    // Por padrão, assumimos mensal. Se houver campo interval no metadata, podemos ajustar
                    // Por enquanto, consideramos todas como mensais
                    $mrr += $amount;
                }

                // Conta novas assinaturas no período
                if ($dateFilter) {
                    $createdAt = strtotime($subscription['created_at']);
                    if ($createdAt >= $dateFilter['start'] && $createdAt <= $dateFilter['end']) {
                        $newSubscriptions++;
                    }
                } else {
                    $newSubscriptions = $totalSubscriptions;
                }
            }

            // Estatísticas de Receita (via Stripe - se necessário)
            // Por enquanto, calculamos apenas MRR baseado nas assinaturas do banco
            // O amount já está em formato monetário (não em centavos)
            $revenue = [
                'mrr' => round($mrr, 2), // MRR (Monthly Recurring Revenue)
                'currency' => 'BRL' // Pode ser ajustado baseado nas assinaturas
            ];

            // Taxa de conversão (assinaturas / customers)
            $conversionRate = $totalCustomers > 0 
                ? round(($totalSubscriptions / $totalCustomers) * 100, 2) 
                : 0;

            // Taxa de churn (assinaturas canceladas / total)
            $churnRate = $totalSubscriptions > 0 
                ? round(($canceledSubscriptions / $totalSubscriptions) * 100, 2) 
                : 0;

            Flight::json([
                'success' => true,
                'period' => $period,
                'data' => [
                    'customers' => [
                        'total' => $totalCustomers,
                        'new' => $newCustomers
                    ],
                    'subscriptions' => [
                        'total' => $totalSubscriptions,
                        'active' => $activeSubscriptions,
                        'canceled' => $canceledSubscriptions,
                        'trialing' => $trialingSubscriptions,
                        'new' => $newSubscriptions
                    ],
                    'revenue' => $revenue,
                    'metrics' => [
                        'conversion_rate' => $conversionRate . '%',
                        'churn_rate' => $churnRate . '%'
                    ]
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter estatísticas", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter estatísticas',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calcula filtro de data baseado no período
     * 
     * @param string $period 'today', 'week', 'month', 'year', 'all'
     * @return array|null Array com 'start' e 'end' timestamps ou null para 'all'
     */
    private function getDateFilter(string $period): ?array
    {
        $now = time();
        
        switch (strtolower($period)) {
            case 'today':
                return [
                    'start' => strtotime('today 00:00:00'),
                    'end' => $now
                ];
            
            case 'week':
                return [
                    'start' => strtotime('-7 days', $now),
                    'end' => $now
                ];
            
            case 'month':
                return [
                    'start' => strtotime('-1 month', $now),
                    'end' => $now
                ];
            
            case 'year':
                return [
                    'start' => strtotime('-1 year', $now),
                    'end' => $now
                ];
            
            case 'all':
            default:
                return null;
        }
    }
}


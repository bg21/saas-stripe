<?php

namespace App\Services;

use App\Services\StripeService;
use App\Services\CacheService;
use App\Services\Logger;
use App\Models\Subscription;
use App\Models\Customer;
use App\Utils\Database;
use Config;

/**
 * Serviço para geração de relatórios e analytics
 */
class ReportService
{
    private StripeService $stripeService;
    private Subscription $subscriptionModel;
    private Customer $customerModel;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->subscriptionModel = new Subscription();
        $this->customerModel = new Customer();
    }

    /**
     * Processa filtros de período
     * 
     * @param array $queryParams Query parameters da requisição
     * @return array ['start' => timestamp, 'end' => timestamp, 'period_type' => string]
     */
    public function processPeriodFilter(array $queryParams): array
    {
        $now = time();
        
        // Período customizado
        if (!empty($queryParams['start_date']) && !empty($queryParams['end_date'])) {
            $start = strtotime($queryParams['start_date'] . ' 00:00:00');
            $end = strtotime($queryParams['end_date'] . ' 23:59:59');
            return [
                'start' => $start,
                'end' => $end,
                'start_date' => date('Y-m-d', $start),
                'end_date' => date('Y-m-d', $end),
                'period_type' => 'custom'
            ];
        }

        // Período predefinido
        $period = $queryParams['period'] ?? 'month';

        switch ($period) {
            case 'today':
                $start = strtotime('today 00:00:00');
                $end = strtotime('today 23:59:59');
                break;
            
            case 'week':
                $start = strtotime('monday this week 00:00:00');
                $end = strtotime('sunday this week 23:59:59');
                break;
            
            case 'month':
                $start = strtotime('first day of this month 00:00:00');
                $end = strtotime('last day of this month 23:59:59');
                break;
            
            case 'year':
                $start = strtotime('first day of January this year 00:00:00');
                $end = strtotime('last day of December this year 23:59:59');
                break;
            
            case 'last_month':
                $start = strtotime('first day of last month 00:00:00');
                $end = strtotime('last day of last month 23:59:59');
                break;
            
            case 'last_year':
                $start = strtotime('January 1 last year 00:00:00');
                $end = strtotime('December 31 last year 23:59:59');
                break;
            
            default:
                $start = strtotime('first day of this month 00:00:00');
                $end = strtotime('last day of this month 23:59:59');
                $period = 'month';
        }

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => date('Y-m-d', $start),
            'end_date' => date('Y-m-d', $end),
            'period_type' => $period
        ];
    }

    /**
     * Calcula receita por período
     * 
     * @param int $tenantId ID do tenant
     * @param array $period Período processado
     * @return array Dados de receita
     */
    public function getRevenue(int $tenantId, array $period): array
    {
        $cacheKey = 'report:revenue:' . $tenantId . ':' . md5(json_encode($period));
        
        // Tenta obter do cache (TTL de 15 minutos)
        $cached = CacheService::getJson($cacheKey);
        if ($cached !== null) {
            Logger::info("Receita obtida do cache", ['cache_key' => $cacheKey]);
            return $cached;
        }

        try {
            // Busca balance transactions do Stripe no período
            $balanceTransactions = $this->stripeService->listBalanceTransactions([
                'limit' => 100,
                'created' => [
                    'gte' => $period['start'],
                    'lte' => $period['end']
                ]
            ]);

            $totalRevenue = 0;
            $revenueByPlan = [];
            $revenueByCurrency = [];
            $currency = 'BRL';

            foreach ($balanceTransactions->data as $transaction) {
                // Filtra apenas transações do tipo charge (receita)
                if ($transaction->type === 'charge' && $transaction->status === 'available') {
                    // Valor em centavos, converte para reais
                    $amount = $transaction->net / 100;
                    
                    $totalRevenue += $amount;
                    
                    $txnCurrency = strtoupper($transaction->currency ?? 'brl');
                    if (!isset($revenueByCurrency[$txnCurrency])) {
                        $revenueByCurrency[$txnCurrency] = 0;
                    }
                    $revenueByCurrency[$txnCurrency] += $amount;

                    // Tenta identificar o plano pela descrição ou metadata
                    if (!empty($transaction->description)) {
                        // Extrai informações do plano se disponível
                        // (pode ser melhorado com busca na subscription)
                    }
                }
            }

            // Busca receita por plano das assinaturas do banco local
            // Usa apenas para mostrar breakdown por plano, não soma ao total (para evitar duplicação)
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT 
                    plan_id,
                    plan_name,
                    COUNT(*) as subscription_count,
                    AVG(amount) as avg_amount,
                    currency
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status = 'active'
                    AND created_at <= :end
                GROUP BY plan_id, plan_name, currency
            ");

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':end' => date('Y-m-d H:i:s', $period['end'])
            ]);

            $subscriptionPlans = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($subscriptionPlans as $row) {
                $planId = $row['plan_id'] ?? 'unknown';
                $planName = $row['plan_name'] ?? 'Plano sem nome';
                $avgAmount = (float)$row['avg_amount'];
                $subscriptionCount = (int)$row['subscription_count'];
                
                // Calcula receita estimada do plano (avg_amount * count)
                // Nota: Este é apenas para breakdown, não é somado ao total (que vem do Stripe)
                $revenueByPlan[$planId] = [
                    'plan_id' => $planId,
                    'plan_name' => $planName,
                    'amount' => round($avgAmount * $subscriptionCount, 2),
                    'subscriptions' => $subscriptionCount,
                    'avg_amount' => round($avgAmount, 2),
                    'currency' => strtoupper($row['currency'] ?? 'BRL')
                ];
            }

            // Determina moeda principal (a com maior receita)
            if (!empty($revenueByCurrency)) {
                $currency = array_search(max($revenueByCurrency), $revenueByCurrency);
            }

            $result = [
                'total' => round($totalRevenue, 2),
                'currency' => $currency,
                'by_plan' => array_values($revenueByPlan),
                'by_currency' => $revenueByCurrency,
                'period' => [
                    'start' => $period['start_date'],
                    'end' => $period['end_date'],
                    'type' => $period['period_type']
                ]
            ];

            // Salva no cache (15 minutos)
            CacheService::setJson($cacheKey, $result, 900);

            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao calcular receita", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'period' => $period
            ]);
            throw $e;
        }
    }

    /**
     * Calcula estatísticas de assinaturas
     * 
     * @param int $tenantId ID do tenant
     * @param array $period Período processado
     * @return array Estatísticas de assinaturas
     */
    public function getSubscriptionsStats(int $tenantId, array $period): array
    {
        $cacheKey = 'report:subscriptions:' . $tenantId . ':' . md5(json_encode($period));
        
        $cached = CacheService::getJson($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $db = Database::getInstance();

            // Total de assinaturas no período
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND created_at BETWEEN :start AND :end
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $total = (int)$stmt->fetchColumn();

            // Assinaturas ativas
            $stmt = $db->prepare("
                SELECT COUNT(*) as active
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status = 'active'
                    AND (created_at BETWEEN :start AND :end OR updated_at BETWEEN :start2 AND :end2)
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end']),
                ':start2' => date('Y-m-d H:i:s', $period['start']),
                ':end2' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $active = (int)$stmt->fetchColumn();

            // Assinaturas canceladas
            $stmt = $db->prepare("
                SELECT COUNT(*) as canceled
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status IN ('canceled', 'unpaid', 'past_due')
                    AND updated_at BETWEEN :start AND :end
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $canceled = (int)$stmt->fetchColumn();

            // Assinaturas em trial
            $stmt = $db->prepare("
                SELECT COUNT(*) as trial
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status = 'trialing'
                    AND (created_at BETWEEN :start AND :end OR updated_at BETWEEN :start2 AND :end2)
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end']),
                ':start2' => date('Y-m-d H:i:s', $period['start']),
                ':end2' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $trial = (int)$stmt->fetchColumn();

            // Assinaturas por status
            $stmt = $db->prepare("
                SELECT status, COUNT(*) as count
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND (created_at BETWEEN :start AND :end OR updated_at BETWEEN :start2 AND :end2)
                GROUP BY status
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end']),
                ':start2' => date('Y-m-d H:i:s', $period['start']),
                ':end2' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $byStatus = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $byStatus[$row['status']] = (int)$row['count'];
            }

            $result = [
                'total' => $total,
                'active' => $active,
                'canceled' => $canceled,
                'trial' => $trial,
                'by_status' => $byStatus,
                'period' => [
                    'start' => $period['start_date'],
                    'end' => $period['end_date'],
                    'type' => $period['period_type']
                ]
            ];

            // Cache por 10 minutos
            CacheService::setJson($cacheKey, $result, 600);

            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao calcular estatísticas de assinaturas", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }
    }

    /**
     * Calcula taxa de churn
     * 
     * @param int $tenantId ID do tenant
     * @param array $period Período processado
     * @return array Taxa de churn
     */
    public function getChurnRate(int $tenantId, array $period): array
    {
        $cacheKey = 'report:churn:' . $tenantId . ':' . md5(json_encode($period));
        
        $cached = CacheService::getJson($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $db = Database::getInstance();

            // Assinaturas canceladas no período
            $stmt = $db->prepare("
                SELECT COUNT(*) as canceled
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status IN ('canceled', 'unpaid')
                    AND updated_at BETWEEN :start AND :end
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $canceled = (int)$stmt->fetchColumn();

            // Assinaturas ativas no início do período
            $stmt = $db->prepare("
                SELECT COUNT(*) as active_start
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status = 'active'
                    AND created_at <= :start
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start'])
            ]);
            $activeStart = (int)$stmt->fetchColumn();

            // Assinaturas ativas no final do período
            $stmt = $db->prepare("
                SELECT COUNT(*) as active_end
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status = 'active'
                    AND created_at <= :end
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':end' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $activeEnd = (int)$stmt->fetchColumn();

            // Calcula taxa de churn
            // Churn Rate = (Cancelamentos no período) / (Assinaturas ativas no início do período) * 100
            $churnRate = $activeStart > 0 ? ($canceled / $activeStart) * 100 : 0;

            // Taxa de retenção (inverso do churn)
            $retentionRate = 100 - $churnRate;

            $result = [
                'churn_rate' => round($churnRate, 2),
                'retention_rate' => round($retentionRate, 2),
                'canceled' => $canceled,
                'active_start' => $activeStart,
                'active_end' => $activeEnd,
                'period' => [
                    'start' => $period['start_date'],
                    'end' => $period['end_date'],
                    'type' => $period['period_type']
                ]
            ];

            // Cache por 15 minutos
            CacheService::setJson($cacheKey, $result, 900);

            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao calcular taxa de churn", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }
    }

    /**
     * Calcula estatísticas de clientes
     * 
     * @param int $tenantId ID do tenant
     * @param array $period Período processado
     * @return array Estatísticas de clientes
     */
    public function getCustomersStats(int $tenantId, array $period): array
    {
        $cacheKey = 'report:customers:' . $tenantId . ':' . md5(json_encode($period));
        
        $cached = CacheService::getJson($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $db = Database::getInstance();

            // Total de clientes no período
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM customers
                WHERE tenant_id = :tenant_id
                    AND created_at BETWEEN :start AND :end
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start' => date('Y-m-d H:i:s', $period['start']),
                ':end' => date('Y-m-d H:i:s', $period['end'])
            ]);
            $total = (int)$stmt->fetchColumn();

            // Total de clientes ativos (com assinatura ativa)
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT c.id) as active
                FROM customers c
                INNER JOIN subscriptions s ON c.id = s.customer_id
                WHERE c.tenant_id = :tenant_id
                    AND s.status = 'active'
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId
            ]);
            $active = (int)$stmt->fetchColumn();

            // Clientes novos (criados no período)
            $new = $total;

            // Clientes com assinatura
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT customer_id) as with_subscription
                FROM subscriptions
                WHERE tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId
            ]);
            $withSubscription = (int)$stmt->fetchColumn();

            // Clientes sem assinatura
            $withoutSubscription = $total - $withSubscription;

            $result = [
                'total' => $total,
                'active' => $active,
                'new' => $new,
                'with_subscription' => $withSubscription,
                'without_subscription' => $withoutSubscription,
                'period' => [
                    'start' => $period['start_date'],
                    'end' => $period['end_date'],
                    'type' => $period['period_type']
                ]
            ];

            // Cache por 10 minutos
            CacheService::setJson($cacheKey, $result, 600);

            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao calcular estatísticas de clientes", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }
    }

    /**
     * Calcula estatísticas de pagamentos
     * 
     * @param int $tenantId ID do tenant
     * @param array $period Período processado
     * @return array Estatísticas de pagamentos
     */
    public function getPaymentsStats(int $tenantId, array $period): array
    {
        $cacheKey = 'report:payments:' . $tenantId . ':' . md5(json_encode($period));
        
        $cached = CacheService::getJson($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Busca charges do Stripe no período
            $charges = $this->stripeService->listCharges([
                'limit' => 100,
                'created' => [
                    'gte' => $period['start'],
                    'lte' => $period['end']
                ]
            ]);

            $total = 0;
            $succeeded = 0;
            $failed = 0;
            $refunded = 0;
            $pending = 0;
            $totalAmount = 0;
            $refundedAmount = 0;
            $byCurrency = [];

            foreach ($charges->data as $charge) {
                $total++;
                $amount = $charge->amount / 100; // Converte centavos para reais
                $currency = strtoupper($charge->currency ?? 'brl');

                if ($charge->paid) {
                    $succeeded++;
                    $totalAmount += $amount;

                    if (!isset($byCurrency[$currency])) {
                        $byCurrency[$currency] = ['count' => 0, 'amount' => 0];
                    }
                    $byCurrency[$currency]['count']++;
                    $byCurrency[$currency]['amount'] += $amount;
                }

                if ($charge->refunded) {
                    $refunded++;
                    $refundedAmount += $amount;
                }

                if ($charge->status === 'failed') {
                    $failed++;
                }

                if ($charge->status === 'pending') {
                    $pending++;
                }
            }

            $result = [
                'total' => $total,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'refunded' => $refunded,
                'pending' => $pending,
                'total_amount' => round($totalAmount, 2),
                'refunded_amount' => round($refundedAmount, 2),
                'success_rate' => $total > 0 ? round(($succeeded / $total) * 100, 2) : 0,
                'by_currency' => $byCurrency,
                'period' => [
                    'start' => $period['start_date'],
                    'end' => $period['end_date'],
                    'type' => $period['period_type']
                ]
            ];

            // Cache por 10 minutos
            CacheService::setJson($cacheKey, $result, 600);

            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao calcular estatísticas de pagamentos", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }
    }

    /**
     * Calcula MRR (Monthly Recurring Revenue)
     * 
     * @param int $tenantId ID do tenant
     * @return array MRR
     */
    public function getMRR(int $tenantId): array
    {
        $cacheKey = 'report:mrr:' . $tenantId;
        
        $cached = CacheService::getJson($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $db = Database::getInstance();

            // Busca todas as assinaturas ativas e calcula MRR
            // Agrupa apenas por plan_id para evitar duplicação
            $stmt = $db->prepare("
                SELECT 
                    plan_id,
                    plan_name,
                    currency,
                    COUNT(*) as subscription_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                FROM subscriptions
                WHERE tenant_id = :tenant_id
                    AND status = 'active'
                GROUP BY plan_id, plan_name, currency
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId
            ]);

            $mrr = 0;
            $mrrByPlan = [];
            $currency = 'BRL';

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $totalAmount = (float)$row['total_amount'];
                $planId = $row['plan_id'] ?? 'unknown';
                $planName = $row['plan_name'] ?? 'Plano sem nome';
                $subscriptionCount = (int)$row['subscription_count'];
                $avgAmount = (float)$row['avg_amount'];

                // Soma o MRR (assumindo que amount já é mensal)
                // Se for plano anual, precisaria dividir por 12
                // (Aqui assumimos que amount já está no formato mensal)
                $mrr += $totalAmount;

                $mrrByPlan[$planId] = [
                    'plan_id' => $planId,
                    'plan_name' => $planName,
                    'subscriptions' => $subscriptionCount,
                    'mrr' => round($totalAmount, 2),
                    'avg_amount' => round($avgAmount, 2),
                    'currency' => strtoupper($row['currency'] ?? 'BRL')
                ];

                $currency = strtoupper($row['currency'] ?? 'BRL');
            }

            $result = [
                'mrr' => round($mrr, 2),
                'currency' => $currency,
                'by_plan' => array_values($mrrByPlan),
                'total_subscriptions' => array_sum(array_column($mrrByPlan, 'subscriptions'))
            ];

            // Cache por 5 minutos (MRR muda com frequência)
            CacheService::setJson($cacheKey, $result, 300);

            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao calcular MRR", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }
    }

    /**
     * Calcula ARR (Annual Recurring Revenue)
     * 
     * @param int $tenantId ID do tenant
     * @return array ARR
     */
    public function getARR(int $tenantId): array
    {
        $mrr = $this->getMRR($tenantId);
        
        $arr = $mrr['mrr'] * 12;

        return [
            'arr' => round($arr, 2),
            'mrr' => $mrr['mrr'],
            'currency' => $mrr['currency'],
            'by_plan' => array_map(function($plan) {
                return [
                    'plan_id' => $plan['plan_id'],
                    'plan_name' => $plan['plan_name'],
                    'arr' => round($plan['mrr'] * 12, 2),
                    'mrr' => $plan['mrr'],
                    'avg_amount' => $plan['avg_amount'] ?? 0,
                    'subscriptions' => $plan['subscriptions'],
                    'currency' => $plan['currency']
                ];
            }, $mrr['by_plan'])
        ];
    }
}


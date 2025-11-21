<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Models\Customer;
use App\Models\Subscription;
use App\Utils\ResponseHelper;
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
     * 
     * ✅ OTIMIZAÇÃO: Usa queries SQL agregadas ao invés de carregar todos os registros em memória
     * ✅ OTIMIZAÇÃO: Cache de 60 segundos para reduzir carga no banco
     */
    public function get(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_stats']);
                return;
            }

            $queryParams = Flight::request()->query;
            $period = $queryParams['period'] ?? 'all';

            // ✅ OTIMIZAÇÃO: Cache de 60 segundos (stats mudam pouco)
            $cacheKey = sprintf('stats:%d:%s', $tenantId, $period);
            $cached = \App\Services\CacheService::getJson($cacheKey);
            if ($cached !== null) {
                // Se o cache já tem formato ResponseHelper, usa diretamente
                if (isset($cached['success']) && isset($cached['data'])) {
                    ResponseHelper::sendSuccess($cached['data']);
                } else {
                    // Formato antigo, converte
                    ResponseHelper::sendSuccess($cached);
                }
                return;
            }

            // Calcula período
            $dateFilter = $this->getDateFilter($period);

            // ✅ OTIMIZAÇÃO: Usa queries SQL agregadas (muito mais rápido que carregar tudo em memória)
            $db = \App\Utils\Database::getInstance();

            // Estatísticas de Customers (1 query ao invés de carregar todos)
            $customerSql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN created_at >= :start_date AND created_at <= :end_date THEN 1 ELSE 0 END) as new
            FROM customers 
            WHERE tenant_id = :tenant_id";
            
            $customerParams = [
                'tenant_id' => $tenantId,
                'start_date' => $dateFilter ? date('Y-m-d H:i:s', $dateFilter['start']) : '1970-01-01 00:00:00',
                'end_date' => $dateFilter ? date('Y-m-d H:i:s', $dateFilter['end']) : date('Y-m-d H:i:s', time())
            ];
            
            $customerStmt = $db->prepare($customerSql);
            $customerStmt->execute($customerParams);
            $customerStats = $customerStmt->fetch(\PDO::FETCH_ASSOC);
            
            $totalCustomers = (int)($customerStats['total'] ?? 0);
            $newCustomers = $dateFilter ? (int)($customerStats['new'] ?? 0) : $totalCustomers;

            // ✅ OTIMIZAÇÃO: Estatísticas de Assinaturas em 1 query agregada (ao invés de loop PHP)
            $subscriptionSql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN LOWER(status) = 'canceled' THEN 1 ELSE 0 END) as canceled,
                SUM(CASE WHEN LOWER(status) = 'trialing' THEN 1 ELSE 0 END) as trialing,
                SUM(CASE WHEN created_at >= :start_date AND created_at <= :end_date THEN 1 ELSE 0 END) as new,
                SUM(CASE WHEN LOWER(status) = 'active' THEN COALESCE(amount, 0) ELSE 0 END) as mrr
            FROM subscriptions 
            WHERE tenant_id = :tenant_id";
            
            $subscriptionParams = [
                'tenant_id' => $tenantId,
                'start_date' => $dateFilter ? date('Y-m-d H:i:s', $dateFilter['start']) : '1970-01-01 00:00:00',
                'end_date' => $dateFilter ? date('Y-m-d H:i:s', $dateFilter['end']) : date('Y-m-d H:i:s', time())
            ];
            
            $subscriptionStmt = $db->prepare($subscriptionSql);
            $subscriptionStmt->execute($subscriptionParams);
            $subscriptionStats = $subscriptionStmt->fetch(\PDO::FETCH_ASSOC);
            
            $totalSubscriptions = (int)($subscriptionStats['total'] ?? 0);
            $activeSubscriptions = (int)($subscriptionStats['active'] ?? 0);
            $canceledSubscriptions = (int)($subscriptionStats['canceled'] ?? 0);
            $trialingSubscriptions = (int)($subscriptionStats['trialing'] ?? 0);
            $newSubscriptions = $dateFilter ? (int)($subscriptionStats['new'] ?? 0) : $totalSubscriptions;
            $mrr = round((float)($subscriptionStats['mrr'] ?? 0), 2);

            // Estatísticas de Receita
            $revenue = [
                'mrr' => $mrr,
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

            $statsData = [
                'period' => $period,
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
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // ✅ OTIMIZAÇÃO: Salva no cache (TTL: 60 segundos) - formato ResponseHelper
            $cacheResponse = [
                'success' => true,
                'data' => $statsData
            ];
            \App\Services\CacheService::setJson($cacheKey, $cacheResponse, 60);

            ResponseHelper::sendSuccess($statsData);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter estatísticas',
                'STATS_GET_ERROR',
                ['action' => 'get_stats', 'tenant_id' => $tenantId ?? null]
            );
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


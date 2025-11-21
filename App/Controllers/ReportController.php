<?php

namespace App\Controllers;

use App\Services\ReportService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;
use Config;

/**
 * Controller para gerenciar relatórios e analytics
 */
class ReportController
{
    private ReportService $reportService;

    public function __construct(StripeService $stripeService)
    {
        $this->reportService = new ReportService($stripeService);
    }

    /**
     * Obtém receita por período
     * GET /v1/reports/revenue
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function revenue(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $revenue = $this->reportService->getRevenue($tenantId, $period);

            ResponseHelper::sendSuccess($revenue);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de receita',
                'REPORT_REVENUE_ERROR',
                ['action' => 'get_revenue_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém estatísticas de assinaturas
     * GET /v1/reports/subscriptions
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function subscriptions(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getSubscriptionsStats($tenantId, $period);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de assinaturas',
                'REPORT_SUBSCRIPTIONS_ERROR',
                ['action' => 'get_subscriptions_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém taxa de churn
     * GET /v1/reports/churn
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function churn(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $churn = $this->reportService->getChurnRate($tenantId, $period);

            ResponseHelper::sendSuccess($churn);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de churn',
                'REPORT_CHURN_ERROR',
                ['action' => 'get_churn_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém estatísticas de clientes
     * GET /v1/reports/customers
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function customers(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getCustomersStats($tenantId, $period);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de clientes',
                'REPORT_CUSTOMERS_ERROR',
                ['action' => 'get_customers_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém estatísticas de pagamentos
     * GET /v1/reports/payments
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function payments(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getPaymentsStats($tenantId, $period);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de pagamentos',
                'REPORT_PAYMENTS_ERROR',
                ['action' => 'get_payments_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém MRR (Monthly Recurring Revenue)
     * GET /v1/reports/mrr
     */
    public function mrr(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $mrr = $this->reportService->getMRR($tenantId);

            ResponseHelper::sendSuccess($mrr);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter MRR',
                'REPORT_MRR_ERROR',
                ['action' => 'get_mrr_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém ARR (Annual Recurring Revenue)
     * GET /v1/reports/arr
     */
    public function arr(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $arr = $this->reportService->getARR($tenantId);

            ResponseHelper::sendSuccess($arr);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter ARR',
                'REPORT_ARR_ERROR',
                ['action' => 'get_arr_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }
}


<?php

namespace App\Controllers;

use App\Services\ReportService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $revenue = $this->reportService->getRevenue($tenantId, $period);

            Flight::json([
                'success' => true,
                'data' => $revenue
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter relatório de receita", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter relatório de receita',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getSubscriptionsStats($tenantId, $period);

            Flight::json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter relatório de assinaturas", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter relatório de assinaturas',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $churn = $this->reportService->getChurnRate($tenantId, $period);

            Flight::json([
                'success' => true,
                'data' => $churn
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter relatório de churn", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter relatório de churn',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getCustomersStats($tenantId, $period);

            Flight::json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter relatório de clientes", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter relatório de clientes',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getPaymentsStats($tenantId, $period);

            Flight::json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter relatório de pagamentos", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter relatório de pagamentos',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $mrr = $this->reportService->getMRR($tenantId);

            Flight::json([
                'success' => true,
                'data' => $mrr
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter MRR", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter MRR',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $arr = $this->reportService->getARR($tenantId);

            Flight::json([
                'success' => true,
                'data' => $arr
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao obter ARR", [
                'error' => $e->getMessage(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao obter ARR',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


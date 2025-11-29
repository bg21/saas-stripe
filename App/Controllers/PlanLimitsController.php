<?php

namespace App\Controllers;

use App\Services\PlanLimitsService;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;

/**
 * Controller para gerenciar limites de planos
 */
class PlanLimitsController
{
    private PlanLimitsService $planLimitsService;

    public function __construct(PlanLimitsService $planLimitsService)
    {
        $this->planLimitsService = $planLimitsService;
    }

    /**
     * Obtém todos os limites do plano atual do tenant
     * GET /v1/plan-limits
     */
    public function getAll(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_subscriptions');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_plan_limits']);
                return;
            }

            $limits = $this->planLimitsService->getAllLimits($tenantId);

            ResponseHelper::sendSuccess($limits, 'Limites do plano obtidos com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter limites do plano',
                'PLAN_LIMITS_ERROR',
                ['action' => 'get_plan_limits', 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


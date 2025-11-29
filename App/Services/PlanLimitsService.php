<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Professional;
use App\Models\Appointment;
use App\Models\User;
use App\Services\CacheService;

/**
 * Service para gerenciar limites de planos
 * 
 * Verifica limites de recursos baseados no plano de assinatura do tenant
 */
class PlanLimitsService
{
    /**
     * Mapeia planos Stripe para limites
     * 
     * @param string $priceId ID do preço no Stripe
     * @return array Limites do plano
     */
    private function getPlanLimits(string $priceId): array
    {
        // Mapeamento de price_id do Stripe para limites
        // ATUALIZADO: 2025-01-22 - Planos criados no Stripe
        $planLimits = [
            // Plano Básico - Mensal
            'price_1SWSxHByYvrEJg7OP74k90Wf' => [
                'max_professionals' => 3,
                'max_appointments_per_month' => 100,
                'max_users' => 1,
                'features' => ['basic'],
                'plan_name' => 'Plano Básico',
                'billing_interval' => 'month'
            ],
            // Plano Básico - Anual
            'price_1SWSxIByYvrEJg7OoCvpeJqj' => [
                'max_professionals' => 3,
                'max_appointments_per_month' => 100,
                'max_users' => 1,
                'features' => ['basic'],
                'plan_name' => 'Plano Básico',
                'billing_interval' => 'year'
            ],
            // Plano Profissional - Mensal
            'price_1SWSxIByYvrEJg7ODQHwI1DB' => [
                'max_professionals' => 10,
                'max_appointments_per_month' => null, // ilimitado
                'max_users' => 5,
                'features' => ['basic', 'advanced_reports', 'history'],
                'plan_name' => 'Plano Profissional',
                'billing_interval' => 'month'
            ],
            // Plano Profissional - Anual
            'price_1SWSxIByYvrEJg7OKHZvLIfm' => [
                'max_professionals' => 10,
                'max_appointments_per_month' => null, // ilimitado
                'max_users' => 5,
                'features' => ['basic', 'advanced_reports', 'history'],
                'plan_name' => 'Plano Profissional',
                'billing_interval' => 'year'
            ],
            // Plano Premium - Mensal
            'price_1SWSxJByYvrEJg7OLAoRRj16' => [
                'max_professionals' => null, // ilimitado
                'max_appointments_per_month' => null, // ilimitado
                'max_users' => null, // ilimitado
                'features' => ['all'],
                'plan_name' => 'Plano Premium',
                'billing_interval' => 'month'
            ],
            // Plano Premium - Anual
            'price_1SWSxJByYvrEJg7OEE1NAOdd' => [
                'max_professionals' => null, // ilimitado
                'max_appointments_per_month' => null, // ilimitado
                'max_users' => null, // ilimitado
                'features' => ['all'],
                'plan_name' => 'Plano Premium',
                'billing_interval' => 'year'
            ]
        ];
        
        return $planLimits[$priceId] ?? [
            'max_professionals' => null,
            'max_appointments_per_month' => null,
            'max_users' => null,
            'features' => [],
            'plan_name' => 'Plano Desconhecido',
            'billing_interval' => 'month'
        ];
    }

    /**
     * Obtém limites do plano com cache
     * 
     * @param string $priceId ID do preço no Stripe
     * @return array Limites do plano
     */
    private function getPlanLimitsCached(string $priceId): array
    {
        $cacheKey = "plan_limits:{$priceId}";
        $cached = CacheService::getJson($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $limits = $this->getPlanLimits($priceId);
        CacheService::setJson($cacheKey, $limits, 300); // 5 minutos
        
        return $limits;
    }
    
    /**
     * Verifica limite de profissionais
     * 
     * @param int $tenantId ID do tenant
     * @return array Resultado da verificação com informações de limite
     */
    public function checkProfessionalLimit(int $tenantId): array
    {
        $subscription = $this->getActiveSubscription($tenantId);
        if (!$subscription) {
            return [
                'allowed' => false, 
                'reason' => 'Nenhuma assinatura ativa',
                'current' => 0,
                'limit' => 0,
                'remaining' => 0,
                'percentage' => 0
            ];
        }
        
        $limits = $this->getPlanLimitsCached($subscription['plan_id']);
        if ($limits['max_professionals'] === null) {
            // Ilimitado
            $currentCount = (new Professional())->count([
                'tenant_id' => $tenantId,
                'status' => 'active'
            ]);
            
            return [
                'allowed' => true,
                'unlimited' => true,
                'current' => $currentCount,
                'limit' => null,
                'remaining' => null,
                'percentage' => 0
            ];
        }
        
        $currentCount = (new Professional())->count([
            'tenant_id' => $tenantId,
            'status' => 'active'
        ]);
        
        $percentage = $limits['max_professionals'] > 0 
            ? round(($currentCount / $limits['max_professionals']) * 100, 2)
            : 0;
        
        return [
            'allowed' => $currentCount < $limits['max_professionals'],
            'current' => $currentCount,
            'limit' => $limits['max_professionals'],
            'remaining' => max(0, $limits['max_professionals'] - $currentCount),
            'percentage' => $percentage,
            'near_limit' => $percentage >= 80 && $percentage < 100,
            'at_limit' => $percentage >= 100
        ];
    }
    
    /**
     * Verifica limite de agendamentos mensais
     * 
     * @param int $tenantId ID do tenant
     * @param string|null $month Mês no formato Y-m (padrão: mês atual)
     * @return array Resultado da verificação com informações de limite
     */
    public function checkAppointmentLimit(int $tenantId, ?string $month = null): array
    {
        $subscription = $this->getActiveSubscription($tenantId);
        if (!$subscription) {
            return [
                'allowed' => false, 
                'reason' => 'Nenhuma assinatura ativa',
                'current' => 0,
                'limit' => 0,
                'remaining' => 0,
                'percentage' => 0
            ];
        }
        
        $limits = $this->getPlanLimitsCached($subscription['plan_id']);
        if ($limits['max_appointments_per_month'] === null) {
            // Ilimitado
            $month = $month ?? date('Y-m');
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $currentCount = (new Appointment())->count([
                'tenant_id' => $tenantId,
                'appointment_date' => ['>=' => $startDate, '<=' => $endDate]
            ]);
            
            return [
                'allowed' => true,
                'unlimited' => true,
                'current' => $currentCount,
                'limit' => null,
                'remaining' => null,
                'percentage' => 0,
                'month' => $month
            ];
        }
        
        $month = $month ?? date('Y-m');
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $currentCount = (new Appointment())->count([
            'tenant_id' => $tenantId,
            'appointment_date' => ['>=' => $startDate, '<=' => $endDate]
        ]);
        
        $percentage = $limits['max_appointments_per_month'] > 0 
            ? round(($currentCount / $limits['max_appointments_per_month']) * 100, 2)
            : 0;
        
        return [
            'allowed' => $currentCount < $limits['max_appointments_per_month'],
            'current' => $currentCount,
            'limit' => $limits['max_appointments_per_month'],
            'remaining' => max(0, $limits['max_appointments_per_month'] - $currentCount),
            'percentage' => $percentage,
            'near_limit' => $percentage >= 80 && $percentage < 100,
            'at_limit' => $percentage >= 100,
            'month' => $month
        ];
    }
    
    /**
     * Verifica limite de usuários
     * 
     * @param int $tenantId ID do tenant
     * @return array Resultado da verificação com informações de limite
     */
    public function checkUserLimit(int $tenantId): array
    {
        $subscription = $this->getActiveSubscription($tenantId);
        if (!$subscription) {
            return [
                'allowed' => false, 
                'reason' => 'Nenhuma assinatura ativa',
                'current' => 0,
                'limit' => 0,
                'remaining' => 0,
                'percentage' => 0
            ];
        }
        
        $limits = $this->getPlanLimitsCached($subscription['plan_id']);
        if ($limits['max_users'] === null) {
            // Ilimitado
            $currentCount = (new User())->count([
                'tenant_id' => $tenantId,
                'status' => 'active'
            ]);
            
            return [
                'allowed' => true,
                'unlimited' => true,
                'current' => $currentCount,
                'limit' => null,
                'remaining' => null,
                'percentage' => 0
            ];
        }
        
        $currentCount = (new User())->count([
            'tenant_id' => $tenantId,
            'status' => 'active'
        ]);
        
        $percentage = $limits['max_users'] > 0 
            ? round(($currentCount / $limits['max_users']) * 100, 2)
            : 0;
        
        return [
            'allowed' => $currentCount < $limits['max_users'],
            'current' => $currentCount,
            'limit' => $limits['max_users'],
            'remaining' => max(0, $limits['max_users'] - $currentCount),
            'percentage' => $percentage,
            'near_limit' => $percentage >= 80 && $percentage < 100,
            'at_limit' => $percentage >= 100
        ];
    }
    
    /**
     * Obtém todos os limites do plano atual do tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array Limites completos do plano
     */
    public function getAllLimits(int $tenantId): array
    {
        $subscription = $this->getActiveSubscription($tenantId);
        
        if (!$subscription) {
            return [
                'has_subscription' => false,
                'subscription' => null,
                'limits' => null,
                'professionals' => $this->checkProfessionalLimit($tenantId),
                'appointments' => $this->checkAppointmentLimit($tenantId),
                'users' => $this->checkUserLimit($tenantId)
            ];
        }
        
        $limits = $this->getPlanLimitsCached($subscription['plan_id']);
        
        return [
            'has_subscription' => true,
            'subscription' => [
                'id' => $subscription['id'],
                'plan_id' => $subscription['plan_id'],
                'plan_name' => $subscription['plan_name'] ?? 'Plano',
                'status' => $subscription['status']
            ],
            'limits' => $limits,
            'professionals' => $this->checkProfessionalLimit($tenantId),
            'appointments' => $this->checkAppointmentLimit($tenantId),
            'users' => $this->checkUserLimit($tenantId)
        ];
    }
    
    /**
     * Obtém assinatura ativa do tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array|null Assinatura ativa ou null
     */
    private function getActiveSubscription(int $tenantId): ?array
    {
        $subscriptionModel = new Subscription();
        return $subscriptionModel->findActiveByTenant($tenantId);
    }
}


<?php

namespace App\Models;

/**
 * Model para gerenciar assinaturas Stripe
 */
class Subscription extends BaseModel
{
    protected string $table = 'subscriptions';

    /**
     * Busca assinatura por Stripe Subscription ID
     */
    public function findByStripeId(string $stripeSubscriptionId): ?array
    {
        return $this->findBy('stripe_subscription_id', $stripeSubscriptionId);
    }

    /**
     * Busca assinaturas por tenant com paginação
     */
    public function findByTenant(int $tenantId, int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $conditions = ['tenant_id' => $tenantId];
        
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        
        if (!empty($filters['customer'])) {
            $conditions['customer_id'] = (int)$filters['customer'];
        }
        
        $orderBy = ['created_at' => 'DESC'];
        
        // ✅ OTIMIZAÇÃO: Usa método otimizado com COUNT em uma query (MySQL 8.0+)
        // Fallback para método antigo se window function não suportado
        try {
            $result = $this->findAllWithCount($conditions, $orderBy, $limit, $offset);
        } catch (\Exception $e) {
            // Fallback: usa método antigo (2 queries) se window function não suportado
            $result = [
                'data' => $this->findAll($conditions, $orderBy, $limit, $offset),
                'total' => $this->count($conditions)
            ];
        }
        
        return [
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($result['total'] / $limit)
        ];
    }

    /**
     * Busca assinaturas por customer
     */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(['customer_id' => $customerId]);
    }

    /**
     * Busca assinatura por tenant e ID (proteção IDOR)
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID da assinatura
     * @return array|null Assinatura encontrada ou null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE id = :id 
             AND tenant_id = :tenant_id 
             LIMIT 1"
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Cria ou atualiza assinatura
     */
    public function createOrUpdate(int $tenantId, int $customerId, array $stripeData): int
    {
        $existing = $this->findByStripeId($stripeData['id']);

        // Trata current_period_start (pode não existir em assinaturas em trial)
        $currentPeriodStart = null;
        if (isset($stripeData['current_period_start'])) {
            $currentPeriodStart = is_numeric($stripeData['current_period_start']) 
                ? date('Y-m-d H:i:s', $stripeData['current_period_start'])
                : $stripeData['current_period_start'];
        }

        // Trata current_period_end (pode não existir em assinaturas em trial)
        $currentPeriodEnd = null;
        if (isset($stripeData['current_period_end'])) {
            $currentPeriodEnd = is_numeric($stripeData['current_period_end'])
                ? date('Y-m-d H:i:s', $stripeData['current_period_end'])
                : $stripeData['current_period_end'];
        }

        // Trata trial_end se existir
        $trialEnd = null;
        if (isset($stripeData['trial_end'])) {
            $trialEnd = is_numeric($stripeData['trial_end'])
                ? date('Y-m-d H:i:s', $stripeData['trial_end'])
                : $stripeData['trial_end'];
        }

        $subscriptionData = [
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'stripe_subscription_id' => $stripeData['id'],
            'stripe_customer_id' => $stripeData['customer'] ?? null,
            'status' => $stripeData['status'] ?? 'incomplete',
            'plan_id' => $stripeData['items']['data'][0]['price']['id'] ?? null,
            'plan_name' => $stripeData['items']['data'][0]['price']['nickname'] ?? null,
            'amount' => ($stripeData['items']['data'][0]['price']['unit_amount'] ?? 0) / 100,
            'currency' => strtoupper($stripeData['currency'] ?? 'usd'),
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'cancel_at_period_end' => ($stripeData['cancel_at_period_end'] ?? false) ? 1 : 0,
            'metadata' => isset($stripeData['metadata']) ? json_encode($stripeData['metadata']) : null
        ];

        if ($existing) {
            $this->update($existing['id'], $subscriptionData);
            return $existing['id'];
        }

        return $this->insert($subscriptionData);
    }
}


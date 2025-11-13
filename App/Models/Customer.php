<?php

namespace App\Models;

/**
 * Model para gerenciar clientes Stripe
 */
class Customer extends BaseModel
{
    protected string $table = 'customers';

    /**
     * Busca cliente por Stripe Customer ID
     */
    public function findByStripeId(string $stripeCustomerId): ?array
    {
        return $this->findBy('stripe_customer_id', $stripeCustomerId);
    }

    /**
     * Busca clientes por tenant
     */
    public function findByTenant(int $tenantId): array
    {
        return $this->findAll(['tenant_id' => $tenantId]);
    }

    /**
     * Cria ou atualiza cliente
     */
    public function createOrUpdate(int $tenantId, string $stripeCustomerId, array $data): int
    {
        $existing = $this->findByStripeId($stripeCustomerId);

        $customerData = [
            'tenant_id' => $tenantId,
            'stripe_customer_id' => $stripeCustomerId,
            'email' => $data['email'] ?? null,
            'name' => $data['name'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
        ];

        if ($existing) {
            $this->update($existing['id'], $customerData);
            return $existing['id'];
        }

        return $this->insert($customerData);
    }
}


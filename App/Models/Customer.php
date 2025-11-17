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
     * Busca clientes por tenant com paginação
     */
    public function findByTenant(int $tenantId, int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $conditions = ['tenant_id' => $tenantId];
        
        // Adiciona filtros
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $conditions['OR'] = [
                'email LIKE' => "%{$search}%",
                'name LIKE' => "%{$search}%"
            ];
        }
        
        if (isset($filters['status'])) {
            // Se houver campo status no banco
            $conditions['status'] = $filters['status'];
        }
        
        $orderBy = [];
        if (!empty($filters['sort'])) {
            $orderBy[$filters['sort']] = 'DESC';
        } else {
            $orderBy['created_at'] = 'DESC';
        }
        
        $customers = $this->findAll($conditions, $orderBy, $limit, $offset);
        $total = $this->count($conditions);
        
        return [
            'data' => $customers,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
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


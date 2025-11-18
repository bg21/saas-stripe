<?php

use Phinx\Migration\AbstractMigration;

/**
 * ✅ OTIMIZAÇÃO: Adiciona índices compostos para queries de estatísticas
 * Melhora performance de COUNT, SUM e filtros por tenant + data
 */
class AddStatsIndexes extends AbstractMigration
{
    public function change()
    {
        // ✅ OTIMIZAÇÃO: Índice composto para customers (tenant_id + created_at)
        // Usado em queries de stats para contar customers por período
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_customers_tenant_created 
            ON customers (tenant_id, created_at)
        ");
        
        // ✅ OTIMIZAÇÃO: Índice composto para subscriptions (tenant_id + status + created_at)
        // Usado em queries de stats para contar e somar por status e período
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_status_created 
            ON subscriptions (tenant_id, status, created_at)
        ");
        
        // ✅ OTIMIZAÇÃO: Índice adicional para MRR (tenant_id + status + amount)
        // Usado para calcular MRR (soma de amount onde status = 'active')
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_status_amount 
            ON subscriptions (tenant_id, status, amount)
        ");
    }
}


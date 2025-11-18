<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration para adicionar índices compostos para otimização de performance
 * 
 * Esta migration adiciona índices compostos que melhoram significativamente
 * o desempenho de queries filtradas e ordenadas por tenant.
 */
class AddCompositeIndexes extends AbstractMigration
{
    /**
     * Migrate Up - Adiciona índices compostos
     */
    public function up()
    {
        // Índices para customers
        $this->execute("
            ALTER TABLE customers 
            ADD INDEX idx_tenant_email (tenant_id, email),
            ADD INDEX idx_tenant_created (tenant_id, created_at)
        ");
        
        // Índices para subscriptions
        $this->execute("
            ALTER TABLE subscriptions 
            ADD INDEX idx_tenant_status_created (tenant_id, status, created_at),
            ADD INDEX idx_tenant_customer (tenant_id, customer_id)
        ");
        
        // Índices para subscription_history
        $this->execute("
            ALTER TABLE subscription_history 
            ADD INDEX idx_subscription_tenant_created (subscription_id, tenant_id, created_at)
        ");
        
        // Full-text index para busca em customers (MySQL 5.7+)
        // Verifica se a versão do MySQL suporta antes de criar
        try {
            $this->execute("
                ALTER TABLE customers 
                ADD FULLTEXT INDEX idx_fulltext_search (email, name)
            ");
        } catch (\Exception $e) {
            // Se falhar (versão antiga do MySQL), apenas loga warning
            // O sistema continuará funcionando sem full-text search
            $this->output->writeln("AVISO: Full-text index não pôde ser criado. MySQL 5.7+ requerido.");
        }
    }
    
    /**
     * Migrate Down - Remove índices compostos
     */
    public function down()
    {
        // Remove full-text index (se existir)
        try {
            $this->execute("
                ALTER TABLE customers 
                DROP INDEX idx_fulltext_search
            ");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        // Remove índices compostos
        $this->execute("
            ALTER TABLE customers 
            DROP INDEX idx_tenant_email,
            DROP INDEX idx_tenant_created
        ");
        
        $this->execute("
            ALTER TABLE subscriptions 
            DROP INDEX idx_tenant_status_created,
            DROP INDEX idx_tenant_customer
        ");
        
        $this->execute("
            ALTER TABLE subscription_history 
            DROP INDEX idx_subscription_tenant_created
        ");
    }
}


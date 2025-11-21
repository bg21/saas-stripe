<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adiciona suporte a soft deletes nas tabelas customers, subscriptions e tenants
 * 
 * Esta migration:
 * 1. Adiciona campo deleted_at (TIMESTAMP NULL) nas tabelas customers, subscriptions e tenants
 * 2. Adiciona índices para melhorar performance de queries com soft deletes
 */
final class AddSoftDeletesToModels extends AbstractMigration
{
    /**
     * Migrate Up - Adiciona campo deleted_at
     */
    public function up(): void
    {
        // Adiciona deleted_at na tabela customers
        $this->table('customers')
            ->addColumn('deleted_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'comment' => 'Data de exclusão lógica (soft delete)'
            ])
            ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
            ->update();
        
        // Adiciona deleted_at na tabela subscriptions
        $this->table('subscriptions')
            ->addColumn('deleted_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'comment' => 'Data de exclusão lógica (soft delete)'
            ])
            ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
            ->update();
        
        // Adiciona deleted_at na tabela tenants
        $this->table('tenants')
            ->addColumn('deleted_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'comment' => 'Data de exclusão lógica (soft delete)'
            ])
            ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
            ->update();
    }

    /**
     * Migrate Down - Remove campo deleted_at
     */
    public function down(): void
    {
        // Remove deleted_at da tabela customers
        $this->table('customers')
            ->removeIndex(['deleted_at'])
            ->removeColumn('deleted_at')
            ->update();
        
        // Remove deleted_at da tabela subscriptions
        $this->table('subscriptions')
            ->removeIndex(['deleted_at'])
            ->removeColumn('deleted_at')
            ->update();
        
        // Remove deleted_at da tabela tenants
        $this->table('tenants')
            ->removeIndex(['deleted_at'])
            ->removeColumn('deleted_at')
            ->update();
    }
}

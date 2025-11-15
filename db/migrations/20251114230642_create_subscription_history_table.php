<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSubscriptionHistoryTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // Tabela de histórico de mudanças de assinatura
        $subscriptionHistory = $this->table('subscription_history', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Histórico de mudanças de assinaturas - auditoria de alterações'
        ]);
        
        $subscriptionHistory->addColumn('subscription_id', 'integer', [
                'null' => false,
                'signed' => false,
                'limit' => 11,
                'comment' => 'ID da assinatura (FK para subscriptions)'
            ])
            ->addColumn('tenant_id', 'integer', [
                'null' => false,
                'signed' => false,
                'limit' => 11,
                'comment' => 'ID do tenant (para filtros rápidos)'
            ])
            ->addColumn('change_type', 'string', [
                'limit' => 50,
                'null' => false,
                'comment' => 'Tipo de mudança: created, updated, canceled, reactivated, plan_changed, status_changed'
            ])
            ->addColumn('changed_by', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'Origem da mudança: api, webhook, admin'
            ])
            ->addColumn('old_status', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'Status anterior da assinatura'
            ])
            ->addColumn('new_status', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'Status novo da assinatura'
            ])
            ->addColumn('old_plan_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'ID do plano anterior (price_id)'
            ])
            ->addColumn('new_plan_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'ID do plano novo (price_id)'
            ])
            ->addColumn('old_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => true,
                'comment' => 'Valor anterior (em formato monetário)'
            ])
            ->addColumn('new_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => true,
                'comment' => 'Valor novo (em formato monetário)'
            ])
            ->addColumn('old_currency', 'string', [
                'limit' => 3,
                'null' => true,
                'comment' => 'Moeda anterior'
            ])
            ->addColumn('new_currency', 'string', [
                'limit' => 3,
                'null' => true,
                'comment' => 'Moeda nova'
            ])
            ->addColumn('old_current_period_end', 'datetime', [
                'null' => true,
                'comment' => 'Fim do período anterior'
            ])
            ->addColumn('new_current_period_end', 'datetime', [
                'null' => true,
                'comment' => 'Fim do período novo'
            ])
            ->addColumn('old_cancel_at_period_end', 'boolean', [
                'default' => false,
                'null' => true,
                'comment' => 'Cancelar ao fim do período anterior'
            ])
            ->addColumn('new_cancel_at_period_end', 'boolean', [
                'default' => false,
                'null' => true,
                'comment' => 'Cancelar ao fim do período novo'
            ])
            ->addColumn('metadata', 'json', [
                'null' => true,
                'comment' => 'Metadados adicionais da mudança (JSON)'
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'comment' => 'Descrição da mudança (opcional)'
            ])
            ->addColumn('created_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
                'comment' => 'Data e hora da mudança'
            ])
            ->addIndex(['subscription_id'], ['name' => 'idx_subscription_id'])
            ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
            ->addIndex(['change_type'], ['name' => 'idx_change_type'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->addIndex(['subscription_id', 'created_at'], ['name' => 'idx_subscription_created'])
            ->addIndex(['tenant_id', 'created_at'], ['name' => 'idx_tenant_created'])
            ->create();
        
        // Adiciona foreign key após criar a tabela (usando SQL direto)
        $this->execute('ALTER TABLE `subscription_history` ADD CONSTRAINT `fk_subscription_history_subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->execute('ALTER TABLE `subscription_history` ADD CONSTRAINT `fk_subscription_history_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}

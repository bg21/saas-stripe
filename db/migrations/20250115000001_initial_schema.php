<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration inicial - Cria todas as tabelas base do sistema
 * 
 * Esta migration reflete o schema atual do sistema (schema.sql)
 * e deve ser executada apenas em bancos novos ou após backup.
 */
class InitialSchema extends AbstractMigration
{
    /**
     * Migrate Up - Cria todas as tabelas
     */
    public function up()
    {
        // Tabela de tenants (clientes SaaS)
        $tenants = $this->table('tenants', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de tenants (clientes SaaS)'
        ]);
        $tenants->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('api_key', 'string', ['limit' => 64, 'null' => false])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'inactive', 'suspended'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('created_at', 'timestamp', [
                    'default' => 'CURRENT_TIMESTAMP',
                    'null' => false
                ])
                ->addColumn('updated_at', 'timestamp', [
                    'default' => 'CURRENT_TIMESTAMP',
                    'update' => 'CURRENT_TIMESTAMP',
                    'null' => false
                ])
                ->addIndex(['api_key'], ['unique' => true, 'name' => 'idx_api_key'])
                ->addIndex(['status'], ['name' => 'idx_status'])
                ->create();

        // Tabela de usuários
        $users = $this->table('users', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de usuários'
        ]);
        $users->addColumn('tenant_id', 'integer', ['null' => false, 'signed' => false, 'limit' => 11])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('status', 'enum', [
                  'values' => ['active', 'inactive'],
                  'default' => 'active',
                  'null' => false
              ])
              ->addColumn('created_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP',
                  'null' => false
              ])
              ->addColumn('updated_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP',
                  'update' => 'CURRENT_TIMESTAMP',
                  'null' => false
              ])
              ->addIndex(['tenant_id', 'email'], ['unique' => true, 'name' => 'unique_tenant_email'])
              ->addIndex(['email'], ['name' => 'idx_email'])
              ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
              ->create();
        
        // Adiciona foreign key após criar a tabela (usando SQL direto)
        $this->execute('ALTER TABLE `users` ADD CONSTRAINT `fk_users_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');

        // Tabela de clientes Stripe
        $customers = $this->table('customers', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de clientes Stripe'
        ]);
        $customers->addColumn('tenant_id', 'integer', ['null' => false, 'signed' => false, 'limit' => 11])
                  ->addColumn('stripe_customer_id', 'string', ['limit' => 255, 'null' => false])
                  ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('metadata', 'json', ['null' => true])
                  ->addColumn('created_at', 'timestamp', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'null' => false
                  ])
                  ->addColumn('updated_at', 'timestamp', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP',
                      'null' => false
                  ])
                  ->addIndex(['stripe_customer_id'], ['unique' => true, 'name' => 'unique_stripe_customer'])
                  ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                  ->addIndex(['email'], ['name' => 'idx_email'])
                  ->create();
        
        // Adiciona foreign key após criar a tabela (usando SQL direto)
        $this->execute('ALTER TABLE `customers` ADD CONSTRAINT `fk_customers_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');

        // Tabela de assinaturas
        $subscriptions = $this->table('subscriptions', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de assinaturas'
        ]);
        $subscriptions->addColumn('tenant_id', 'integer', ['null' => false, 'signed' => false, 'limit' => 11])
                      ->addColumn('customer_id', 'integer', ['null' => false, 'signed' => false, 'limit' => 11])
                      ->addColumn('stripe_subscription_id', 'string', ['limit' => 255, 'null' => false])
                      ->addColumn('stripe_customer_id', 'string', ['limit' => 255, 'null' => false])
                      ->addColumn('status', 'string', ['limit' => 50, 'null' => false])
                      ->addColumn('plan_id', 'string', ['limit' => 255, 'null' => true])
                      ->addColumn('plan_name', 'string', ['limit' => 255, 'null' => true])
                      ->addColumn('amount', 'decimal', [
                          'precision' => 10,
                          'scale' => 2,
                          'null' => true
                      ])
                      ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'usd'])
                      ->addColumn('current_period_start', 'datetime', ['null' => true])
                      ->addColumn('current_period_end', 'datetime', ['null' => true])
                      ->addColumn('cancel_at_period_end', 'boolean', ['default' => false])
                      ->addColumn('metadata', 'json', ['null' => true])
                      ->addColumn('created_at', 'timestamp', [
                          'default' => 'CURRENT_TIMESTAMP',
                          'null' => false
                      ])
                      ->addColumn('updated_at', 'timestamp', [
                          'default' => 'CURRENT_TIMESTAMP',
                          'update' => 'CURRENT_TIMESTAMP',
                          'null' => false
                      ])
                      ->addIndex(['stripe_subscription_id'], ['unique' => true, 'name' => 'unique_stripe_subscription'])
                      ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                      ->addIndex(['customer_id'], ['name' => 'idx_customer_id'])
                      ->addIndex(['status'], ['name' => 'idx_status'])
                      ->create();
        
        // Adiciona foreign keys após criar a tabela (usando SQL direto)
        $this->execute('ALTER TABLE `subscriptions` ADD CONSTRAINT `fk_subscriptions_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->execute('ALTER TABLE `subscriptions` ADD CONSTRAINT `fk_subscriptions_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');

        // Tabela de eventos Stripe (para idempotência de webhooks)
        $stripeEvents = $this->table('stripe_events', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de eventos Stripe (idempotência de webhooks)'
        ]);
        $stripeEvents->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
                     ->addColumn('event_type', 'string', ['limit' => 100, 'null' => false])
                     ->addColumn('processed', 'boolean', ['default' => false])
                     ->addColumn('data', 'json', ['null' => true])
                     ->addColumn('created_at', 'timestamp', [
                         'default' => 'CURRENT_TIMESTAMP',
                         'null' => false
                     ])
                     ->addIndex(['event_id'], ['unique' => true, 'name' => 'idx_event_id'])
                     ->addIndex(['event_type'], ['name' => 'idx_event_type'])
                     ->addIndex(['processed'], ['name' => 'idx_processed'])
                     ->create();

        // Tabela de rate limits (fallback quando Redis não está disponível)
        $rateLimits = $this->table('rate_limits', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de rate limits (fallback quando Redis não está disponível)'
        ]);
        $rateLimits->addColumn('identifier_key', 'string', ['limit' => 255, 'null' => false])
                   ->addColumn('request_count', 'integer', ['default' => 1, 'null' => false])
                   ->addColumn('reset_at', 'integer', ['null' => false])
                   ->addColumn('created_at', 'integer', ['null' => false])
                   ->addColumn('updated_at', 'integer', ['null' => false])
                   ->addIndex(['identifier_key'], ['name' => 'idx_identifier_key'])
                   ->addIndex(['reset_at'], ['name' => 'idx_reset_at'])
                   ->create();
    }

    /**
     * Migrate Down - Remove todas as tabelas (ordem inversa)
     */
    public function down()
    {
        // Remove tabelas na ordem inversa (respeitando foreign keys)
        $this->table('rate_limits')->drop()->save();
        $this->table('stripe_events')->drop()->save();
        $this->table('subscriptions')->drop()->save();
        $this->table('customers')->drop()->save();
        $this->table('users')->drop()->save();
        $this->table('tenants')->drop()->save();
    }
}


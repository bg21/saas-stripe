<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela performance_metrics
 * 
 * Armazena métricas de performance das requisições (tempo de resposta, memória, etc.)
 */
final class CreatePerformanceMetricsTable extends AbstractMigration
{
    public function up(): void
    {
        // Verifica se a tabela já existe
        if ($this->hasTable('performance_metrics')) {
            $this->output->writeln('Tabela performance_metrics já existe. Pulando criação.');
            return;
        }
        
        $table = $this->table('performance_metrics', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Métricas de performance das requisições'
        ]);
        
        $table->addColumn('endpoint', 'string', [
            'limit' => 255,
            'null' => false,
            'comment' => 'Endpoint da requisição'
        ])
        ->addColumn('method', 'string', [
            'limit' => 10,
            'null' => false,
            'comment' => 'Método HTTP (GET, POST, PUT, DELETE, etc)'
        ])
        ->addColumn('duration_ms', 'integer', [
            'null' => false,
            'comment' => 'Duração da requisição em milissegundos'
        ])
        ->addColumn('memory_mb', 'decimal', [
            'precision' => 10,
            'scale' => 2,
            'null' => false,
            'comment' => 'Memória utilizada em megabytes'
        ])
        ->addColumn('tenant_id', 'integer', [
            'signed' => false,
            'null' => true,
            'comment' => 'ID do tenant (NULL para requisições não autenticadas)'
        ])
        ->addColumn('user_id', 'integer', [
            'signed' => false,
            'null' => true,
            'comment' => 'ID do usuário (NULL para requisições não autenticadas)'
        ])
        ->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
            'comment' => 'Data e hora da requisição'
        ])
        ->addIndex(['endpoint'], ['name' => 'idx_endpoint'])
        ->addIndex(['created_at'], ['name' => 'idx_created_at'])
        ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
        ->addIndex(['method'], ['name' => 'idx_method'])
        ->addIndex(['endpoint', 'method'], ['name' => 'idx_endpoint_method'])
        ->create();
        
        // Adiciona foreign keys apenas se não existirem
        try {
            $this->execute('ALTER TABLE `performance_metrics` ADD CONSTRAINT `fk_performance_metrics_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
        
        try {
            $this->execute('ALTER TABLE `performance_metrics` ADD CONSTRAINT `fk_performance_metrics_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
    }

    public function down(): void
    {
        // Remove foreign keys primeiro
        try {
            $this->execute('ALTER TABLE `performance_metrics` DROP FOREIGN KEY `fk_performance_metrics_tenant_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        try {
            $this->execute('ALTER TABLE `performance_metrics` DROP FOREIGN KEY `fk_performance_metrics_user_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        $this->table('performance_metrics')->drop()->save();
    }
}

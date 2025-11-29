<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela schedule_blocks
 * 
 * Armazena bloqueios de agenda dos profissionais (férias, almoços, etc.)
 */
final class CreateScheduleBlocksTable extends AbstractMigration
{
    public function up(): void
    {
        // Verifica se a tabela já existe
        if ($this->hasTable('schedule_blocks')) {
            $this->output->writeln('Tabela schedule_blocks já existe. Pulando criação.');
            return;
        }
        
        $table = $this->table('schedule_blocks', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Bloqueios de agenda dos profissionais'
        ]);
        
        $table->addColumn('tenant_id', 'integer', [
            'signed' => false,
            'null' => false,
            'comment' => 'ID do tenant'
        ])
        ->addColumn('professional_id', 'integer', [
            'signed' => false,
            'null' => false,
            'comment' => 'ID do profissional'
        ])
        ->addColumn('start_datetime', 'datetime', [
            'null' => false,
            'comment' => 'Data e hora de início do bloqueio'
        ])
        ->addColumn('end_datetime', 'datetime', [
            'null' => false,
            'comment' => 'Data e hora de fim do bloqueio'
        ])
        ->addColumn('reason', 'string', [
            'limit' => 255,
            'null' => true,
            'comment' => 'Motivo do bloqueio (ex: férias, almoço)'
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
        ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
        ->addIndex(['professional_id'], ['name' => 'idx_professional_id'])
        ->addIndex(['start_datetime', 'end_datetime'], ['name' => 'idx_datetime'])
        ->create();
        
        // Adiciona foreign keys apenas se não existirem
        try {
            $this->execute('ALTER TABLE `schedule_blocks` ADD CONSTRAINT `fk_schedule_blocks_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
        
        try {
            $this->execute('ALTER TABLE `schedule_blocks` ADD CONSTRAINT `fk_schedule_blocks_professional_id` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
    }

    public function down(): void
    {
        // Remove foreign keys primeiro
        try {
            $this->execute('ALTER TABLE `schedule_blocks` DROP FOREIGN KEY `fk_schedule_blocks_tenant_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        try {
            $this->execute('ALTER TABLE `schedule_blocks` DROP FOREIGN KEY `fk_schedule_blocks_professional_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        $this->table('schedule_blocks')->drop()->save();
    }
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela professional_schedules
 * 
 * Armazena os horários de trabalho dos profissionais por dia da semana
 */
final class CreateProfessionalSchedulesTable extends AbstractMigration
{
    public function up(): void
    {
        // Verifica se a tabela já existe
        if ($this->hasTable('professional_schedules')) {
            $this->output->writeln('Tabela professional_schedules já existe. Pulando criação.');
            return;
        }
        
        $table = $this->table('professional_schedules', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Horários de trabalho dos profissionais por dia da semana'
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
        ->addColumn('day_of_week', 'integer', [
            'limit' => 1,
            'null' => false,
            'comment' => 'Dia da semana: 0=domingo, 1=segunda, ..., 6=sábado'
        ])
        ->addColumn('start_time', 'time', [
            'null' => false,
            'comment' => 'Hora de início do trabalho'
        ])
        ->addColumn('end_time', 'time', [
            'null' => false,
            'comment' => 'Hora de fim do trabalho'
        ])
        ->addColumn('is_available', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Se o horário está disponível/ativo'
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
        ->addIndex(['professional_id', 'day_of_week'], ['unique' => true, 'name' => 'unique_professional_day'])
        ->create();
        
        // Adiciona foreign keys apenas se não existirem
        try {
            $this->execute('ALTER TABLE `professional_schedules` ADD CONSTRAINT `fk_professional_schedules_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
        
        try {
            $this->execute('ALTER TABLE `professional_schedules` ADD CONSTRAINT `fk_professional_schedules_professional_id` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
    }

    public function down(): void
    {
        // Remove foreign keys primeiro
        try {
            $this->execute('ALTER TABLE `professional_schedules` DROP FOREIGN KEY `fk_professional_schedules_tenant_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        try {
            $this->execute('ALTER TABLE `professional_schedules` DROP FOREIGN KEY `fk_professional_schedules_professional_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        $this->table('professional_schedules')->drop()->save();
    }
}

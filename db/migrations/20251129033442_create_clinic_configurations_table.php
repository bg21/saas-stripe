<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela clinic_configurations
 * 
 * Armazena configurações específicas da clínica veterinária por tenant
 */
final class CreateClinicConfigurationsTable extends AbstractMigration
{
    public function up(): void
    {
        // Verifica se a tabela já existe
        if ($this->hasTable('clinic_configurations')) {
            $this->output->writeln('Tabela clinic_configurations já existe. Pulando criação.');
            return;
        }
        
        $table = $this->table('clinic_configurations', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Configurações da clínica veterinária por tenant'
        ]);
        
        $table->addColumn('tenant_id', 'integer', [
            'signed' => false,
            'null' => false,
            'comment' => 'ID do tenant (clínica)'
        ])
        // Horários de funcionamento por dia da semana
        ->addColumn('opening_time_monday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Segunda-feira'
        ])
        ->addColumn('closing_time_monday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Segunda-feira'
        ])
        ->addColumn('opening_time_tuesday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Terça-feira'
        ])
        ->addColumn('closing_time_tuesday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Terça-feira'
        ])
        ->addColumn('opening_time_wednesday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Quarta-feira'
        ])
        ->addColumn('closing_time_wednesday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Quarta-feira'
        ])
        ->addColumn('opening_time_thursday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Quinta-feira'
        ])
        ->addColumn('closing_time_thursday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Quinta-feira'
        ])
        ->addColumn('opening_time_friday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Sexta-feira'
        ])
        ->addColumn('closing_time_friday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Sexta-feira'
        ])
        ->addColumn('opening_time_saturday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Sábado'
        ])
        ->addColumn('closing_time_saturday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Sábado'
        ])
        ->addColumn('opening_time_sunday', 'time', [
            'null' => true,
            'comment' => 'Horário de abertura - Domingo'
        ])
        ->addColumn('closing_time_sunday', 'time', [
            'null' => true,
            'comment' => 'Horário de fechamento - Domingo'
        ])
        // Configurações gerais
        ->addColumn('default_appointment_duration', 'integer', [
            'default' => 30,
            'null' => false,
            'comment' => 'Duração padrão de consulta em minutos'
        ])
        ->addColumn('time_slot_interval', 'integer', [
            'default' => 15,
            'null' => false,
            'comment' => 'Intervalo entre horários disponíveis em minutos'
        ])
        ->addColumn('allow_online_booking', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Permitir agendamento online'
        ])
        ->addColumn('require_confirmation', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Requer confirmação para agendamentos'
        ])
        ->addColumn('cancellation_hours', 'integer', [
            'default' => 24,
            'null' => false,
            'comment' => 'Horas mínimas antes do cancelamento'
        ])
        ->addColumn('metadata', 'text', [
            'null' => true,
            'comment' => 'Dados adicionais em JSON'
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
        ->addIndex(['tenant_id'], ['unique' => true, 'name' => 'unique_tenant_id'])
        ->create();
        
        // Adiciona foreign key apenas se não existir
        try {
            $this->execute('ALTER TABLE `clinic_configurations` ADD CONSTRAINT `fk_clinic_configurations_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Ignora se já existir
        }
    }

    public function down(): void
    {
        // Remove foreign key primeiro
        try {
            $this->execute('ALTER TABLE `clinic_configurations` DROP FOREIGN KEY `fk_clinic_configurations_tenant_id`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        $this->table('clinic_configurations')->drop()->save();
    }
}

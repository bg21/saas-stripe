<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adiciona campos de status de confirmação e conclusão na tabela appointments
 * 
 * Esta migration adiciona os campos:
 * - confirmed_at: Data/hora em que o agendamento foi confirmado
 * - confirmed_by: ID do usuário que confirmou o agendamento
 * - completed_at: Data/hora em que o agendamento foi concluído
 * - completed_by: ID do usuário que marcou o agendamento como concluído
 */
final class AddAppointmentStatusFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('appointments');
        
        // Adiciona campos de confirmação
        $table->addColumn('confirmed_at', 'datetime', [
            'null' => true,
            'after' => 'status',
            'comment' => 'Data e hora em que o agendamento foi confirmado'
        ])
        ->addColumn('confirmed_by', 'integer', [
            'signed' => false,
            'null' => true,
            'after' => 'confirmed_at',
            'comment' => 'ID do usuário que confirmou o agendamento'
        ])
        // Adiciona campos de conclusão
        ->addColumn('completed_at', 'datetime', [
            'null' => true,
            'after' => 'confirmed_by',
            'comment' => 'Data e hora em que o agendamento foi concluído'
        ])
        ->addColumn('completed_by', 'integer', [
            'signed' => false,
            'null' => true,
            'after' => 'completed_at',
            'comment' => 'ID do usuário que marcou o agendamento como concluído'
        ])
        ->addIndex(['confirmed_by'], ['name' => 'idx_confirmed_by'])
        ->addIndex(['completed_by'], ['name' => 'idx_completed_by'])
        ->update();
        
        // Adiciona foreign keys (opcional, pode ser NULL)
        $this->execute('ALTER TABLE `appointments` ADD CONSTRAINT `fk_appointments_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->execute('ALTER TABLE `appointments` ADD CONSTRAINT `fk_appointments_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $table = $this->table('appointments');
        
        // Remove foreign keys primeiro
        try {
            $this->execute('ALTER TABLE `appointments` DROP FOREIGN KEY `fk_appointments_confirmed_by`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        try {
            $this->execute('ALTER TABLE `appointments` DROP FOREIGN KEY `fk_appointments_completed_by`');
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        // Remove colunas
        $table->removeColumn('completed_by')
              ->removeColumn('completed_at')
              ->removeColumn('confirmed_by')
              ->removeColumn('confirmed_at')
              ->update();
    }
}

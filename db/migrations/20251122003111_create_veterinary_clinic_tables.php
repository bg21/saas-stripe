<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria todas as tabelas do sistema de clínica veterinária
 * 
 * Esta migration cria:
 * 1. clinic_configurations - Configurações da clínica
 * 2. specialties - Especialidades veterinárias
 * 3. professionals - Profissionais (veterinários, atendentes)
 * 4. clients - Clientes (donos de pets)
 * 5. pets - Pets
 * 6. professional_schedules - Agendas dos profissionais
 * 7. schedule_blocks - Bloqueios de agenda
 * 8. appointments - Agendamentos/consultas
 * 9. appointment_history - Histórico de agendamentos
 */
final class CreateVeterinaryClinicTables extends AbstractMigration
{
    /**
     * Migrate Up - Cria todas as tabelas
     */
    public function up(): void
    {
        // 1. Tabela: clinic_configurations
        $clinicConfig = $this->table('clinic_configurations', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Configurações da clínica veterinária'
        ]);
        $clinicConfig->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                    ->addColumn('opening_time_monday', 'time', ['default' => '08:00:00'])
                    ->addColumn('closing_time_monday', 'time', ['default' => '18:00:00'])
                    ->addColumn('opening_time_tuesday', 'time', ['default' => '08:00:00'])
                    ->addColumn('closing_time_tuesday', 'time', ['default' => '18:00:00'])
                    ->addColumn('opening_time_wednesday', 'time', ['default' => '08:00:00'])
                    ->addColumn('closing_time_wednesday', 'time', ['default' => '18:00:00'])
                    ->addColumn('opening_time_thursday', 'time', ['default' => '08:00:00'])
                    ->addColumn('closing_time_thursday', 'time', ['default' => '18:00:00'])
                    ->addColumn('opening_time_friday', 'time', ['default' => '08:00:00'])
                    ->addColumn('closing_time_friday', 'time', ['default' => '18:00:00'])
                    ->addColumn('opening_time_saturday', 'time', ['default' => '08:00:00'])
                    ->addColumn('closing_time_saturday', 'time', ['default' => '12:00:00'])
                    ->addColumn('opening_time_sunday', 'time', ['null' => true, 'default' => null])
                    ->addColumn('closing_time_sunday', 'time', ['null' => true, 'default' => null])
                    ->addColumn('default_appointment_duration', 'integer', ['default' => 30, 'comment' => 'Duração padrão em minutos'])
                    ->addColumn('time_slot_interval', 'integer', ['default' => 15, 'comment' => 'Intervalo entre horários em minutos'])
                    ->addColumn('allow_online_booking', 'boolean', ['default' => true])
                    ->addColumn('require_confirmation', 'boolean', ['default' => false])
                    ->addColumn('cancellation_hours', 'integer', ['default' => 24, 'comment' => 'Horas mínimas para cancelamento'])
                    ->addColumn('metadata', 'json', ['null' => true])
                    ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                    ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
                    ->addIndex(['tenant_id'], ['unique' => true, 'name' => 'idx_tenant_id'])
                    ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                    ->create();

        // 2. Tabela: specialties
        $specialties = $this->table('specialties', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Especialidades veterinárias'
        ]);
        $specialties->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                    ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                    ->addColumn('description', 'text', ['null' => true])
                    ->addColumn('status', 'enum', ['values' => ['active', 'inactive'], 'default' => 'active', 'null' => false])
                    ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                    ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
                    ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
                    ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                    ->addIndex(['status'], ['name' => 'idx_status'])
                    ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
                    ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                    ->create();

        // 3. Tabela: professionals
        $professionals = $this->table('professionals', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Profissionais da clínica (veterinários, atendentes)'
        ]);
        $professionals->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                      ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false, 'comment' => 'Relacionamento com users'])
                      ->addColumn('crmv', 'string', ['limit' => 20, 'null' => true, 'comment' => 'CRMV do veterinário'])
                      ->addColumn('specialties', 'json', ['null' => true, 'comment' => 'Array de IDs de especialidades'])
                      ->addColumn('default_consultation_duration', 'integer', ['default' => 30, 'comment' => 'Duração padrão em minutos'])
                      ->addColumn('status', 'enum', ['values' => ['active', 'inactive', 'on_leave'], 'default' => 'active', 'null' => false])
                      ->addColumn('metadata', 'json', ['null' => true])
                      ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                      ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
                      ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
                      ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                      ->addIndex(['user_id'], ['name' => 'idx_user_id'])
                      ->addIndex(['status'], ['name' => 'idx_status'])
                      ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
                      ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                      ->create();

        // 4. Tabela: clients
        $clients = $this->table('clients', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Clientes (donos de pets)'
        ]);
        $clients->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('phone_alt', 'string', ['limit' => 20, 'null' => true, 'comment' => 'Telefone alternativo'])
                ->addColumn('address', 'text', ['null' => true])
                ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('state', 'string', ['limit' => 2, 'null' => true])
                ->addColumn('postal_code', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
                ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
                ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                ->addIndex(['email'], ['name' => 'idx_email'])
                ->addIndex(['phone'], ['name' => 'idx_phone'])
                ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
                ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();

        // 5. Tabela: pets
        $pets = $this->table('pets', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Pets dos clientes'
        ]);
        $pets->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
             ->addColumn('client_id', 'integer', ['signed' => false, 'null' => false])
             ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
             ->addColumn('species', 'string', ['limit' => 50, 'null' => false, 'comment' => 'cachorro, gato, ave, etc.'])
             ->addColumn('breed', 'string', ['limit' => 100, 'null' => true])
             ->addColumn('gender', 'enum', ['values' => ['male', 'female', 'unknown'], 'default' => 'unknown'])
             ->addColumn('birth_date', 'date', ['null' => true])
             ->addColumn('weight', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'comment' => 'Peso em kg'])
             ->addColumn('color', 'string', ['limit' => 50, 'null' => true])
             ->addColumn('microchip', 'string', ['limit' => 50, 'null' => true])
             ->addColumn('medical_history', 'json', ['null' => true, 'comment' => 'Histórico médico em JSON'])
             ->addColumn('notes', 'text', ['null' => true])
             ->addColumn('metadata', 'json', ['null' => true])
             ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
             ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
             ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
             ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
             ->addIndex(['client_id'], ['name' => 'idx_client_id'])
             ->addIndex(['species'], ['name' => 'idx_species'])
             ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
             ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();

        // 6. Tabela: professional_schedules
        $schedules = $this->table('professional_schedules', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Agendas dos profissionais'
        ]);
        $schedules->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('professional_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('day_of_week', 'integer', ['limit' => 1, 'null' => false, 'comment' => '0=Domingo, 1=Segunda, ..., 6=Sábado'])
                  ->addColumn('start_time', 'time', ['null' => false])
                  ->addColumn('end_time', 'time', ['null' => false])
                  ->addColumn('is_available', 'boolean', ['default' => true])
                  ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                  ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
                  ->addIndex(['tenant_id', 'professional_id'], ['name' => 'idx_tenant_professional'])
                  ->addIndex(['day_of_week'], ['name' => 'idx_day_of_week'])
                  ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                  ->addForeignKey('professional_id', 'professionals', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                  ->create();

        // 7. Tabela: schedule_blocks
        $blocks = $this->table('schedule_blocks', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Bloqueios de agenda (férias, licenças, etc.)'
        ]);
        $blocks->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
               ->addColumn('professional_id', 'integer', ['signed' => false, 'null' => false])
               ->addColumn('start_datetime', 'datetime', ['null' => false])
               ->addColumn('end_datetime', 'datetime', ['null' => false])
               ->addColumn('reason', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Motivo do bloqueio'])
               ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
               ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
               ->addIndex(['tenant_id', 'professional_id'], ['name' => 'idx_tenant_professional'])
               ->addIndex(['start_datetime', 'end_datetime'], ['name' => 'idx_datetime_range'])
               ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
               ->addForeignKey('professional_id', 'professionals', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
               ->create();

        // 8. Tabela: appointments
        $appointments = $this->table('appointments', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Agendamentos/consultas'
        ]);
        $appointments->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                     ->addColumn('professional_id', 'integer', ['signed' => false, 'null' => false])
                     ->addColumn('client_id', 'integer', ['signed' => false, 'null' => false])
                     ->addColumn('pet_id', 'integer', ['signed' => false, 'null' => false])
                     ->addColumn('specialty_id', 'integer', ['signed' => false, 'null' => true])
                     ->addColumn('appointment_date', 'date', ['null' => false])
                     ->addColumn('appointment_time', 'time', ['null' => false])
                     ->addColumn('duration_minutes', 'integer', ['default' => 30, 'null' => false])
                     ->addColumn('status', 'enum', ['values' => ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'], 'default' => 'scheduled', 'null' => false])
                     ->addColumn('notes', 'text', ['null' => true])
                     ->addColumn('cancellation_reason', 'text', ['null' => true])
                     ->addColumn('cancelled_by', 'integer', ['signed' => false, 'null' => true, 'comment' => 'ID do usuário que cancelou'])
                     ->addColumn('cancelled_at', 'timestamp', ['null' => true, 'default' => null])
                     ->addColumn('metadata', 'json', ['null' => true])
                     ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                     ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
                     ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
                     ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                     ->addIndex(['professional_id'], ['name' => 'idx_professional_id'])
                     ->addIndex(['client_id'], ['name' => 'idx_client_id'])
                     ->addIndex(['pet_id'], ['name' => 'idx_pet_id'])
                     ->addIndex(['appointment_date', 'appointment_time'], ['name' => 'idx_appointment_datetime'])
                     ->addIndex(['status'], ['name' => 'idx_status'])
                     ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
                     ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                     ->addForeignKey('professional_id', 'professionals', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                     ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                     ->addForeignKey('pet_id', 'pets', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                     ->addForeignKey('specialty_id', 'specialties', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                     ->create();

        // 9. Tabela: appointment_history
        $history = $this->table('appointment_history', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Histórico de mudanças em agendamentos'
        ]);
        $history->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('appointment_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('event_type', 'string', ['limit' => 50, 'null' => false, 'comment' => 'created, updated, status_changed, cancelled, etc.'])
                ->addColumn('old_data', 'json', ['null' => true])
                ->addColumn('new_data', 'json', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                ->addIndex(['appointment_id'], ['name' => 'idx_appointment_id'])
                ->addIndex(['event_type'], ['name' => 'idx_event_type'])
                ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('appointment_id', 'appointments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->create();
    }

    /**
     * Migrate Down - Remove todas as tabelas
     */
    public function down(): void
    {
        $this->table('appointment_history')->drop()->save();
        $this->table('appointments')->drop()->save();
        $this->table('schedule_blocks')->drop()->save();
        $this->table('professional_schedules')->drop()->save();
        $this->table('pets')->drop()->save();
        $this->table('clients')->drop()->save();
        $this->table('professionals')->drop()->save();
        $this->table('specialties')->drop()->save();
        $this->table('clinic_configurations')->drop()->save();
    }
}

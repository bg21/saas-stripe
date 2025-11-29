<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration para adicionar índices de performance
 * 
 * Adiciona índices em campos usados frequentemente em WHERE e ORDER BY
 * para melhorar significativamente o desempenho das queries.
 */
final class AddPerformanceIndexes extends AbstractMigration
{
    public function up(): void
    {
        // ✅ Índices para appointments (tabela mais consultada)
        // Índice composto para verificação de conflitos (hasConflict)
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointments_tenant_prof_date_status 
            ON appointments (tenant_id, professional_id, appointment_date, status)
        ");
        
        // Índices para filtros comuns
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointments_tenant_client 
            ON appointments (tenant_id, client_id)
        ");
        
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointments_tenant_pet 
            ON appointments (tenant_id, pet_id)
        ");
        
        // Índice para filtros de data
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointments_date 
            ON appointments (appointment_date)
        ");
        
        // Índice para ordenação por data
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointments_tenant_created 
            ON appointments (tenant_id, created_at)
        ");
        
        // ✅ Índices para professionals
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_professionals_tenant_user 
            ON professionals (tenant_id, user_id)
        ");
        
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_professionals_tenant_status 
            ON professionals (tenant_id, status)
        ");
        
        // ✅ Índices para pets
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_pets_tenant_client 
            ON pets (tenant_id, client_id)
        ");
        
        // ✅ Índices para exams
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_exams_tenant_pet 
            ON exams (tenant_id, pet_id)
        ");
        
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_exams_tenant_professional 
            ON exams (tenant_id, professional_id)
        ");
        
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_exams_tenant_status 
            ON exams (tenant_id, status)
        ");
        
        // ✅ Índices para professional_schedules
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_prof_schedules_tenant_prof_day 
            ON professional_schedules (tenant_id, professional_id, day_of_week)
        ");
        
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_prof_schedules_tenant_prof_available 
            ON professional_schedules (tenant_id, professional_id, is_available)
        ");
        
        // ✅ Índices para schedule_blocks
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_schedule_blocks_tenant_prof_datetime 
            ON schedule_blocks (tenant_id, professional_id, start_datetime, end_datetime)
        ");
        
        // ✅ Índices para clients
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_clients_tenant_created 
            ON clients (tenant_id, created_at)
        ");
        
        // ✅ Índices para users (usado em joins)
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_users_tenant 
            ON users (tenant_id)
        ");
        
        // ✅ Índices para specialties
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_specialties_tenant 
            ON specialties (tenant_id)
        ");
    }

    public function down(): void
    {
        // Remove índices
        $this->execute("DROP INDEX IF EXISTS idx_appointments_tenant_prof_date_status ON appointments");
        $this->execute("DROP INDEX IF EXISTS idx_appointments_tenant_client ON appointments");
        $this->execute("DROP INDEX IF EXISTS idx_appointments_tenant_pet ON appointments");
        $this->execute("DROP INDEX IF EXISTS idx_appointments_date ON appointments");
        $this->execute("DROP INDEX IF EXISTS idx_appointments_tenant_created ON appointments");
        
        $this->execute("DROP INDEX IF EXISTS idx_professionals_tenant_user ON professionals");
        $this->execute("DROP INDEX IF EXISTS idx_professionals_tenant_status ON professionals");
        
        $this->execute("DROP INDEX IF EXISTS idx_pets_tenant_client ON pets");
        
        $this->execute("DROP INDEX IF EXISTS idx_exams_tenant_pet ON exams");
        $this->execute("DROP INDEX IF EXISTS idx_exams_tenant_professional ON exams");
        $this->execute("DROP INDEX IF EXISTS idx_exams_tenant_status ON exams");
        
        $this->execute("DROP INDEX IF EXISTS idx_prof_schedules_tenant_prof_day ON professional_schedules");
        $this->execute("DROP INDEX IF EXISTS idx_prof_schedules_tenant_prof_available ON professional_schedules");
        
        $this->execute("DROP INDEX IF EXISTS idx_schedule_blocks_tenant_prof_datetime ON schedule_blocks");
        
        $this->execute("DROP INDEX IF EXISTS idx_clients_tenant_created ON clients");
        $this->execute("DROP INDEX IF EXISTS idx_users_tenant ON users");
        $this->execute("DROP INDEX IF EXISTS idx_specialties_tenant ON specialties");
    }
}

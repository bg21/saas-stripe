<?php

namespace App\Models;

/**
 * Model para gerenciar agendas dos profissionais
 */
class ProfessionalSchedule extends BaseModel
{
    protected string $table = 'professional_schedules';

    /**
     * Busca agenda de um profissional
     * 
     * @param int $professionalId ID do profissional
     * @return array
     */
    public function findByProfessional(int $professionalId): array
    {
        return $this->findAll(
            ['professional_id' => $professionalId],
            ['day_of_week' => 'ASC', 'start_time' => 'ASC']
        );
    }

    /**
     * Busca horários disponíveis para um dia específico
     * 
     * @param int $professionalId ID do profissional
     * @param int $dayOfWeek Dia da semana (0=Domingo, 1=Segunda, ..., 6=Sábado)
     * @return array|null
     */
    public function findAvailableByDay(int $professionalId, int $dayOfWeek): ?array
    {
        $schedules = $this->findAll([
            'professional_id' => $professionalId,
            'day_of_week' => $dayOfWeek,
            'is_available' => 1
        ]);
        return $schedules[0] ?? null;
    }

    /**
     * Lista todas as agendas do profissional por tenant
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @return array
     */
    public function findByTenantAndProfessional(int $tenantId, int $professionalId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ], ['day_of_week' => 'ASC', 'start_time' => 'ASC']);
    }
}


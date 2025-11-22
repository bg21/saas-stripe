<?php

namespace App\Models;

/**
 * Model para gerenciar agendamentos/consultas
 */
class Appointment extends BaseModel
{
    protected string $table = 'appointments';
    protected bool $usesSoftDeletes = true;

    /**
     * Busca agendamento por tenant e ID
     * 
     * @param int $tenantId ID do tenant
     * @param int $id ID do agendamento
     * @return array|null
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $appointment = $this->findById($id);
        return $appointment && $appointment['tenant_id'] == $tenantId ? $appointment : null;
    }

    /**
     * Verifica conflito de horário
     * 
     * @param int $professionalId ID do profissional
     * @param string $date Data (Y-m-d)
     * @param string $time Hora (H:i:s)
     * @param int $duration Duração em minutos
     * @param int|null $excludeId ID do agendamento a excluir da verificação (para updates)
     * @return bool True se houver conflito
     */
    public function hasConflict(int $professionalId, string $date, string $time, int $duration, ?int $excludeId = null): bool
    {
        $datetime = new \DateTime("$date $time");
        $endDatetime = (clone $datetime)->modify("+$duration minutes");
        
        // Busca agendamentos do profissional na mesma data com status que ocupam horário
        $appointments = $this->findAll([
            'professional_id' => $professionalId,
            'appointment_date' => $date
        ]);
        
        foreach ($appointments as $apt) {
            // Ignora o próprio agendamento (para updates)
            if ($excludeId && $apt['id'] == $excludeId) {
                continue;
            }
            
            // Ignora agendamentos cancelados ou concluídos
            if (in_array($apt['status'], ['cancelled', 'completed', 'no_show'])) {
                continue;
            }
            
            $aptStart = new \DateTime("{$apt['appointment_date']} {$apt['appointment_time']}");
            $aptEnd = (clone $aptStart)->modify("+{$apt['duration_minutes']} minutes");
            
            // Verifica sobreposição
            if (($datetime >= $aptStart && $datetime < $aptEnd) || 
                ($endDatetime > $aptStart && $endDatetime <= $aptEnd) ||
                ($datetime <= $aptStart && $endDatetime >= $aptEnd)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Lista agendamentos por profissional e data
     * 
     * @param int $professionalId ID do profissional
     * @param string $date Data (Y-m-d)
     * @return array
     */
    public function findByProfessionalAndDate(int $professionalId, string $date): array
    {
        return $this->findAll([
            'professional_id' => $professionalId,
            'appointment_date' => $date
        ], ['appointment_time' => 'ASC']);
    }

    /**
     * Lista agendamentos do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions, ['appointment_date' => 'DESC', 'appointment_time' => 'ASC']);
    }

    /**
     * Lista agendamentos por pet
     * 
     * @param int $petId ID do pet
     * @return array
     */
    public function findByPet(int $petId): array
    {
        return $this->findAll(['pet_id' => $petId], ['appointment_date' => 'DESC', 'appointment_time' => 'DESC']);
    }

    /**
     * Lista agendamentos por cliente
     * 
     * @param int $clientId ID do cliente
     * @return array
     */
    public function findByClient(int $clientId): array
    {
        return $this->findAll(['client_id' => $clientId], ['appointment_date' => 'DESC', 'appointment_time' => 'DESC']);
    }
}


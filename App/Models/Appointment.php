<?php

namespace App\Models;

/**
 * Model para gerenciar agendamentos
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
     * Lista agendamentos do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }

    /**
     * Lista agendamentos por profissional
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @return array
     */
    public function findByProfessional(int $tenantId, int $professionalId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ]);
    }

    /**
     * Lista agendamentos por cliente
     * 
     * @param int $tenantId ID do tenant
     * @param int $clientId ID do cliente
     * @return array
     */
    public function findByClient(int $tenantId, int $clientId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'client_id' => $clientId
        ]);
    }

    /**
     * Lista agendamentos por pet
     * 
     * @param int $tenantId ID do tenant
     * @param int $petId ID do pet
     * @return array
     */
    public function findByPet(int $tenantId, int $petId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'pet_id' => $petId
        ]);
    }

    /**
     * Verifica conflito de horário
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param string $date Data (Y-m-d)
     * @param string $time Hora (H:i:s)
     * @param int $duration Duração em minutos
     * @param int|null $excludeId ID do agendamento a excluir da verificação
     * @return bool
     */
    public function hasConflict(int $tenantId, int $professionalId, string $date, string $time, int $duration, ?int $excludeId = null): bool
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND appointment_date = :date 
                AND status IN ('scheduled', 'confirmed')
                AND deleted_at IS NULL";
        
        $params = [
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'date' => $date
        ];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $datetime = new \DateTime("$date $time");
        $endDatetime = (clone $datetime)->modify("+$duration minutes");
        
        foreach ($appointments as $apt) {
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
}


<?php

namespace App\Models;

/**
 * Model para gerenciar histórico de agendamentos
 */
class AppointmentHistory extends BaseModel
{
    protected string $table = 'appointment_history';

    /**
     * Lista histórico de um agendamento
     * 
     * @param int $tenantId ID do tenant
     * @param int $appointmentId ID do agendamento
     * @return array
     */
    public function findByAppointment(int $tenantId, int $appointmentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE tenant_id = :tenant_id 
             AND appointment_id = :appointment_id 
             ORDER BY created_at DESC"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId
        ]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Processa JSON fields
        foreach ($results as &$result) {
            if (!empty($result['old_data'])) {
                $oldData = json_decode($result['old_data'], true);
                $result['old_data'] = is_array($oldData) ? $oldData : null;
            } else {
                $result['old_data'] = null;
            }
            
            if (!empty($result['new_data'])) {
                $newData = json_decode($result['new_data'], true);
                $result['new_data'] = is_array($newData) ? $newData : null;
            } else {
                $result['new_data'] = null;
            }
        }
        
        return $results;
    }

    /**
     * Cria um registro de histórico
     * 
     * @param int $tenantId ID do tenant
     * @param int $appointmentId ID do agendamento
     * @param string $eventType Tipo do evento
     * @param array|null $oldData Dados antigos
     * @param array|null $newData Dados novos
     * @param string|null $notes Notas
     * @param int|null $userId ID do usuário que fez a alteração
     * @return int ID do registro criado
     */
    public function create(int $tenantId, int $appointmentId, string $eventType, ?array $oldData = null, ?array $newData = null, ?string $notes = null, ?int $userId = null): int
    {
        return $this->insert([
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'old_data' => $oldData ? json_encode($oldData) : null,
            'new_data' => $newData ? json_encode($newData) : null,
            'notes' => $notes
        ]);
    }
}


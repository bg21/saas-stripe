<?php

namespace App\Models;

/**
 * Model para gerenciar histórico de mudanças em agendamentos
 */
class AppointmentHistory extends BaseModel
{
    protected string $table = 'appointment_history';

    /**
     * Registra mudança no agendamento
     * 
     * @param int $appointmentId ID do agendamento
     * @param string $eventType Tipo do evento (created, updated, status_changed, cancelled, etc.)
     * @param array|null $oldData Dados antigos (opcional)
     * @param array|null $newData Dados novos (opcional)
     * @param int|null $userId ID do usuário que fez a alteração (opcional)
     * @return int ID do registro de histórico
     */
    public function logChange(int $appointmentId, string $eventType, ?array $oldData = null, ?array $newData = null, ?int $userId = null): int
    {
        $appointment = (new Appointment())->findById($appointmentId);
        
        if (!$appointment) {
            throw new \RuntimeException("Agendamento com ID {$appointmentId} não encontrado");
        }
        
        return $this->insert([
            'tenant_id' => $appointment['tenant_id'],
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'old_data' => $oldData ? json_encode($oldData) : null,
            'new_data' => $newData ? json_encode($newData) : null
        ]);
    }

    /**
     * Busca histórico de um agendamento
     * 
     * @param int $appointmentId ID do agendamento
     * @return array
     */
    public function findByAppointment(int $appointmentId): array
    {
        $history = $this->findAll(
            ['appointment_id' => $appointmentId],
            ['created_at' => 'DESC']
        );
        
        // Decodifica JSON dos campos old_data e new_data
        foreach ($history as &$entry) {
            if ($entry['old_data']) {
                $entry['old_data'] = json_decode($entry['old_data'], true);
            }
            if ($entry['new_data']) {
                $entry['new_data'] = json_decode($entry['new_data'], true);
            }
        }
        
        return $history;
    }

    /**
     * Lista histórico do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions, ['created_at' => 'DESC']);
    }
}


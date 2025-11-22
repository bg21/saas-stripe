<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\Professional;
use App\Models\Client;
use App\Models\Pet;
use App\Models\ScheduleBlock;
use App\Services\ScheduleService;
use App\Services\Logger;

/**
 * Serviço para gerenciar agendamentos/consultas
 */
class AppointmentService
{
    private Appointment $appointmentModel;
    private AppointmentHistory $historyModel;
    private Professional $professionalModel;
    private Client $clientModel;
    private Pet $petModel;
    private ScheduleBlock $blockModel;
    private ScheduleService $scheduleService;

    public function __construct(
        Appointment $appointmentModel,
        AppointmentHistory $historyModel,
        Professional $professionalModel,
        Client $clientModel,
        Pet $petModel,
        ScheduleBlock $blockModel,
        ScheduleService $scheduleService
    ) {
        $this->appointmentModel = $appointmentModel;
        $this->historyModel = $historyModel;
        $this->professionalModel = $professionalModel;
        $this->clientModel = $clientModel;
        $this->petModel = $petModel;
        $this->blockModel = $blockModel;
        $this->scheduleService = $scheduleService;
    }

    /**
     * Cria um novo agendamento
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados do agendamento
     * @param int|null $userId ID do usuário que está criando (opcional)
     * @return array Dados do agendamento criado
     * @throws \RuntimeException Se validações falharem
     */
    public function create(int $tenantId, array $data, ?int $userId = null): array
    {
        // Validações
        $this->validateAppointmentData($tenantId, $data);

        // Valida disponibilidade
        $datetime = new \DateTime("{$data['appointment_date']} {$data['appointment_time']}");
        $duration = $data['duration_minutes'] ?? 30;

        // Verifica se está dentro do horário da clínica
        if (!$this->scheduleService->isWithinClinicHours($datetime, $tenantId)) {
            throw new \RuntimeException("Horário fora do funcionamento da clínica");
        }

        // Verifica bloqueio
        if ($this->blockModel->hasBlock($data['professional_id'], $datetime)) {
            throw new \RuntimeException("Horário bloqueado para este profissional");
        }

        // Verifica conflito
        if ($this->appointmentModel->hasConflict(
            $data['professional_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $duration
        )) {
            throw new \RuntimeException("Horário já está ocupado");
        }

        // Cria agendamento
        $appointmentData = [
            'tenant_id' => $tenantId,
            'professional_id' => $data['professional_id'],
            'client_id' => $data['client_id'],
            'pet_id' => $data['pet_id'],
            'specialty_id' => $data['specialty_id'] ?? null,
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'duration_minutes' => $duration,
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? null
        ];

        $appointmentId = $this->appointmentModel->insert($appointmentData);
        $appointment = $this->appointmentModel->findById($appointmentId);

        // Registra no histórico
        $this->historyModel->logChange(
            $appointmentId,
            'created',
            null,
            $appointment,
            $userId
        );

        Logger::info("Agendamento criado", [
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId,
            'professional_id' => $data['professional_id'],
            'client_id' => $data['client_id'],
            'pet_id' => $data['pet_id']
        ]);

        return $appointment;
    }

    /**
     * Atualiza um agendamento
     * 
     * @param int $tenantId ID do tenant
     * @param int $appointmentId ID do agendamento
     * @param array $data Dados a atualizar
     * @param int|null $userId ID do usuário que está atualizando (opcional)
     * @return array Dados do agendamento atualizado
     * @throws \RuntimeException Se validações falharem
     */
    public function update(int $tenantId, int $appointmentId, array $data, ?int $userId = null): array
    {
        // Busca agendamento
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $appointmentId);
        if (!$appointment) {
            throw new \RuntimeException("Agendamento não encontrado");
        }

        // Não permite atualizar agendamentos cancelados ou concluídos
        if (in_array($appointment['status'], ['cancelled', 'completed'])) {
            throw new \RuntimeException("Não é possível atualizar agendamento {$appointment['status']}");
        }

        $oldData = $appointment;

        // Se está mudando data/hora, valida disponibilidade
        if (isset($data['appointment_date']) || isset($data['appointment_time']) || isset($data['duration_minutes'])) {
            $newDate = $data['appointment_date'] ?? $appointment['appointment_date'];
            $newTime = $data['appointment_time'] ?? $appointment['appointment_time'];
            $newDuration = $data['duration_minutes'] ?? $appointment['duration_minutes'];

            $datetime = new \DateTime("$newDate $newTime");

            // Verifica se está dentro do horário da clínica
            if (!$this->scheduleService->isWithinClinicHours($datetime, $tenantId)) {
                throw new \RuntimeException("Horário fora do funcionamento da clínica");
            }

            // Verifica bloqueio
            if ($this->blockModel->hasBlock($appointment['professional_id'], $datetime)) {
                throw new \RuntimeException("Horário bloqueado para este profissional");
            }

            // Verifica conflito (excluindo o próprio agendamento)
            if ($this->appointmentModel->hasConflict(
                $appointment['professional_id'],
                $newDate,
                $newTime,
                $newDuration,
                $appointmentId
            )) {
                throw new \RuntimeException("Horário já está ocupado");
            }
        }

        // Atualiza
        $this->appointmentModel->update($appointmentId, $data);
        $newData = $this->appointmentModel->findById($appointmentId);

        // Registra no histórico
        $this->historyModel->logChange(
            $appointmentId,
            'updated',
            $oldData,
            $newData,
            $userId
        );

        Logger::info("Agendamento atualizado", [
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId
        ]);

        return $newData;
    }

    /**
     * Confirma um agendamento
     * 
     * @param int $tenantId ID do tenant
     * @param int $appointmentId ID do agendamento
     * @param int|null $userId ID do usuário que está confirmando (opcional)
     * @return array Dados do agendamento confirmado
     */
    public function confirm(int $tenantId, int $appointmentId, ?int $userId = null): array
    {
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $appointmentId);
        if (!$appointment) {
            throw new \RuntimeException("Agendamento não encontrado");
        }

        if ($appointment['status'] !== 'scheduled') {
            throw new \RuntimeException("Apenas agendamentos com status 'scheduled' podem ser confirmados");
        }

        $oldData = $appointment;
        $this->appointmentModel->update($appointmentId, ['status' => 'confirmed']);
        $newData = $this->appointmentModel->findById($appointmentId);

        // Registra no histórico
        $this->historyModel->logChange(
            $appointmentId,
            'status_changed',
            $oldData,
            $newData,
            $userId
        );

        Logger::info("Agendamento confirmado", [
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId
        ]);

        return $newData;
    }

    /**
     * Cancela um agendamento
     * 
     * @param int $tenantId ID do tenant
     * @param int $appointmentId ID do agendamento
     * @param string $reason Motivo do cancelamento
     * @param int|null $userId ID do usuário que está cancelando (opcional)
     * @return array Dados do agendamento cancelado
     */
    public function cancel(int $tenantId, int $appointmentId, string $reason, ?int $userId = null): array
    {
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $appointmentId);
        if (!$appointment) {
            throw new \RuntimeException("Agendamento não encontrado");
        }

        if ($appointment['status'] === 'cancelled') {
            throw new \RuntimeException("Agendamento já está cancelado");
        }

        if ($appointment['status'] === 'completed') {
            throw new \RuntimeException("Não é possível cancelar agendamento já concluído");
        }

        $oldData = $appointment;
        $this->appointmentModel->update($appointmentId, [
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_by' => $userId,
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
        $newData = $this->appointmentModel->findById($appointmentId);

        // Registra no histórico
        $this->historyModel->logChange(
            $appointmentId,
            'cancelled',
            $oldData,
            $newData,
            $userId
        );

        Logger::info("Agendamento cancelado", [
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId,
            'reason' => $reason
        ]);

        return $newData;
    }

    /**
     * Marca agendamento como concluído
     * 
     * @param int $tenantId ID do tenant
     * @param int $appointmentId ID do agendamento
     * @param int|null $userId ID do usuário que está marcando como concluído (opcional)
     * @return array Dados do agendamento concluído
     */
    public function complete(int $tenantId, int $appointmentId, ?int $userId = null): array
    {
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $appointmentId);
        if (!$appointment) {
            throw new \RuntimeException("Agendamento não encontrado");
        }

        if ($appointment['status'] === 'completed') {
            throw new \RuntimeException("Agendamento já está concluído");
        }

        if ($appointment['status'] === 'cancelled') {
            throw new \RuntimeException("Não é possível concluir agendamento cancelado");
        }

        $oldData = $appointment;
        $this->appointmentModel->update($appointmentId, ['status' => 'completed']);
        $newData = $this->appointmentModel->findById($appointmentId);

        // Registra no histórico
        $this->historyModel->logChange(
            $appointmentId,
            'status_changed',
            $oldData,
            $newData,
            $userId
        );

        Logger::info("Agendamento concluído", [
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId
        ]);

        return $newData;
    }

    /**
     * Verifica disponibilidade de horário
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param \DateTime $datetime Data e hora
     * @param int $duration Duração em minutos
     * @param int|null $excludeAppointmentId ID do agendamento a excluir (para updates)
     * @return bool True se disponível
     */
    public function isTimeSlotAvailable(int $tenantId, int $professionalId, \DateTime $datetime, int $duration, ?int $excludeAppointmentId = null): bool
    {
        // Verifica se está dentro do horário da clínica
        if (!$this->scheduleService->isWithinClinicHours($datetime, $tenantId)) {
            return false;
        }

        // Verifica bloqueio
        if ($this->blockModel->hasBlock($professionalId, $datetime)) {
            return false;
        }

        // Verifica conflito
        if ($this->appointmentModel->hasConflict(
            $professionalId,
            $datetime->format('Y-m-d'),
            $datetime->format('H:i:s'),
            $duration,
            $excludeAppointmentId
        )) {
            return false;
        }

        return true;
    }

    /**
     * Obtém horários disponíveis para um profissional em uma data
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param string $date Data (Y-m-d)
     * @param int|null $duration Duração em minutos (usa padrão se não fornecido)
     * @return array Array de horários disponíveis
     */
    public function getAvailableSlots(int $tenantId, int $professionalId, string $date, ?int $duration = null): array
    {
        return $this->scheduleService->calculateAvailableSlots($tenantId, $professionalId, $date, $duration);
    }

    /**
     * Valida dados do agendamento
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados a validar
     * @throws \RuntimeException Se validações falharem
     */
    private function validateAppointmentData(int $tenantId, array $data): void
    {
        // Campos obrigatórios
        $required = ['professional_id', 'client_id', 'pet_id', 'appointment_date', 'appointment_time'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Campo obrigatório ausente: {$field}");
            }
        }

        // Valida profissional
        $professional = $this->professionalModel->findByTenantAndId($tenantId, $data['professional_id']);
        if (!$professional) {
            throw new \RuntimeException("Profissional não encontrado");
        }

        // Valida cliente
        $client = $this->clientModel->findByTenantAndId($tenantId, $data['client_id']);
        if (!$client) {
            throw new \RuntimeException("Cliente não encontrado");
        }

        // Valida pet
        $pet = $this->petModel->findByTenantAndId($tenantId, $data['pet_id']);
        if (!$pet) {
            throw new \RuntimeException("Pet não encontrado");
        }

        // Valida que pet pertence ao cliente
        if ($pet['client_id'] != $data['client_id']) {
            throw new \RuntimeException("Pet não pertence ao cliente informado");
        }

        // Valida formato de data
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['appointment_date'])) {
            throw new \RuntimeException("Formato de data inválido. Use Y-m-d");
        }

        // Valida formato de hora
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['appointment_time'])) {
            throw new \RuntimeException("Formato de hora inválido. Use H:i ou H:i:s");
        }

        // Valida que data não é no passado
        $appointmentDate = new \DateTime($data['appointment_date']);
        $today = new \DateTime('today');
        if ($appointmentDate < $today) {
            throw new \RuntimeException("Não é possível agendar para datas passadas");
        }
    }
}


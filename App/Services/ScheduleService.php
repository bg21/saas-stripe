<?php

namespace App\Services;

use App\Models\ClinicConfiguration;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Models\Appointment;
use App\Services\Logger;

/**
 * Serviço para gerenciar agendas e horários disponíveis
 */
class ScheduleService
{
    private ClinicConfiguration $clinicConfigModel;
    private Professional $professionalModel;
    private ProfessionalSchedule $scheduleModel;
    private ScheduleBlock $blockModel;
    private Appointment $appointmentModel;

    public function __construct(
        ClinicConfiguration $clinicConfigModel,
        Professional $professionalModel,
        ProfessionalSchedule $scheduleModel,
        ScheduleBlock $blockModel,
        Appointment $appointmentModel
    ) {
        $this->clinicConfigModel = $clinicConfigModel;
        $this->professionalModel = $professionalModel;
        $this->scheduleModel = $scheduleModel;
        $this->blockModel = $blockModel;
        $this->appointmentModel = $appointmentModel;
    }

    /**
     * Calcula horários disponíveis para um profissional em uma data
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param string $date Data (Y-m-d)
     * @param int|null $duration Duração em minutos (usa padrão se não fornecido)
     * @return array Array de horários disponíveis ['time' => 'H:i', 'available' => true]
     */
    public function calculateAvailableSlots(int $tenantId, int $professionalId, string $date, ?int $duration = null): array
    {
        // Valida profissional
        $professional = $this->professionalModel->findByTenantAndId($tenantId, $professionalId);
        if (!$professional) {
            throw new \RuntimeException("Profissional não encontrado");
        }

        // Obtém configurações da clínica
        $clinicConfig = $this->clinicConfigModel->findByTenant($tenantId);
        if (!$clinicConfig) {
            throw new \RuntimeException("Configurações da clínica não encontradas");
        }

        // Duração padrão
        if ($duration === null) {
            $duration = $professional['default_consultation_duration'] ?? $clinicConfig['default_appointment_duration'] ?? 30;
        }

        // Intervalo entre horários
        $interval = $clinicConfig['time_slot_interval'] ?? 15;

        // Obtém dia da semana (0=Domingo, 1=Segunda, ..., 6=Sábado)
        $dayOfWeek = (int)date('w', strtotime($date));

        // Busca horários padrões do profissional para este dia
        $schedule = $this->scheduleModel->findAvailableByDay($professionalId, $dayOfWeek);
        
        if (!$schedule || !$schedule['is_available']) {
            return []; // Profissional não trabalha neste dia
        }

        // Horários de funcionamento da clínica para este dia
        $clinicOpening = $this->getClinicOpeningTime($clinicConfig, $dayOfWeek);
        $clinicClosing = $this->getClinicClosingTime($clinicConfig, $dayOfWeek);

        if (!$clinicOpening || !$clinicClosing) {
            return []; // Clínica não funciona neste dia
        }

        // Usa horários do profissional ou da clínica
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];

        // Garante que está dentro do horário da clínica
        if ($startTime < $clinicOpening) {
            $startTime = $clinicOpening;
        }
        if ($endTime > $clinicClosing) {
            $endTime = $clinicClosing;
        }

        // Gera slots disponíveis
        $slots = [];
        $currentTime = new \DateTime("$date $startTime");
        $endDateTime = new \DateTime("$date $endTime");

        while ($currentTime < $endDateTime) {
            $slotEnd = (clone $currentTime)->modify("+$duration minutes");
            
            // Verifica se o slot cabe no horário de funcionamento
            if ($slotEnd <= $endDateTime) {
                // Verifica se há bloqueio
                if (!$this->blockModel->hasBlock($professionalId, $currentTime)) {
                    // Verifica se há conflito com agendamento
                    $hasConflict = $this->appointmentModel->hasConflict(
                        $professionalId,
                        $date,
                        $currentTime->format('H:i:s'),
                        $duration
                    );

                    if (!$hasConflict) {
                        $slots[] = [
                            'time' => $currentTime->format('H:i'),
                            'available' => true
                        ];
                    }
                }
            }

            // Avança para o próximo slot
            $currentTime->modify("+$interval minutes");
        }

        Logger::debug("Horários disponíveis calculados", [
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'date' => $date,
            'slots_count' => count($slots)
        ]);

        return $slots;
    }

    /**
     * Verifica se horário está dentro do funcionamento da clínica
     * 
     * @param \DateTime $datetime Data e hora a verificar
     * @param int $tenantId ID do tenant
     * @return bool
     */
    public function isWithinClinicHours(\DateTime $datetime, int $tenantId): bool
    {
        $clinicConfig = $this->clinicConfigModel->findByTenant($tenantId);
        if (!$clinicConfig) {
            return false;
        }

        $dayOfWeek = (int)$datetime->format('w');
        $time = $datetime->format('H:i:s');

        $opening = $this->getClinicOpeningTime($clinicConfig, $dayOfWeek);
        $closing = $this->getClinicClosingTime($clinicConfig, $dayOfWeek);

        if (!$opening || !$closing) {
            return false; // Clínica não funciona neste dia
        }

        return $time >= $opening && $time <= $closing;
    }

    /**
     * Cria bloqueio de agenda
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param \DateTime $start Data/hora inicial
     * @param \DateTime $end Data/hora final
     * @param string|null $reason Motivo do bloqueio
     * @return int ID do bloqueio criado
     */
    public function createBlock(int $tenantId, int $professionalId, \DateTime $start, \DateTime $end, ?string $reason = null): int
    {
        // Valida profissional
        $professional = $this->professionalModel->findByTenantAndId($tenantId, $professionalId);
        if (!$professional) {
            throw new \RuntimeException("Profissional não encontrado");
        }

        // Valida que end é depois de start
        if ($end <= $start) {
            throw new \RuntimeException("Data/hora final deve ser posterior à inicial");
        }

        return $this->blockModel->insert([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
            'reason' => $reason
        ]);
    }

    /**
     * Remove bloqueio de agenda
     * 
     * @param int $blockId ID do bloqueio
     * @return bool
     */
    public function removeBlock(int $blockId): bool
    {
        return $this->blockModel->delete($blockId);
    }

    /**
     * Obtém horário de abertura da clínica para um dia da semana
     * 
     * @param array $clinicConfig Configurações da clínica
     * @param int $dayOfWeek Dia da semana (0-6)
     * @return string|null Horário de abertura (H:i:s) ou null
     */
    private function getClinicOpeningTime(array $clinicConfig, int $dayOfWeek): ?string
    {
        $days = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday'
        ];

        $dayName = $days[$dayOfWeek] ?? null;
        if (!$dayName) {
            return null;
        }

        $key = "opening_time_{$dayName}";
        return $clinicConfig[$key] ?? null;
    }

    /**
     * Obtém horário de fechamento da clínica para um dia da semana
     * 
     * @param array $clinicConfig Configurações da clínica
     * @param int $dayOfWeek Dia da semana (0-6)
     * @return string|null Horário de fechamento (H:i:s) ou null
     */
    private function getClinicClosingTime(array $clinicConfig, int $dayOfWeek): ?string
    {
        $days = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday'
        ];

        $dayName = $days[$dayOfWeek] ?? null;
        if (!$dayName) {
            return null;
        }

        $key = "closing_time_{$dayName}";
        return $clinicConfig[$key] ?? null;
    }
}


<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\Professional;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Specialty;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Controller para gerenciar agendamentos
 */
class AppointmentController
{
    private Appointment $appointmentModel;
    private AppointmentHistory $appointmentHistoryModel;
    private Professional $professionalModel;
    private Client $clientModel;
    private Pet $petModel;
    private Specialty $specialtyModel;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->appointmentHistoryModel = new AppointmentHistory();
        $this->professionalModel = new Professional();
        $this->clientModel = new Client();
        $this->petModel = new Pet();
        $this->specialtyModel = new Specialty();
    }

    /**
     * Lista agendamentos do tenant
     * GET /v1/appointments
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_appointments']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            
            $filters = [];
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            if (isset($queryParams['professional_id']) && !empty($queryParams['professional_id'])) {
                $filters['professional_id'] = (int)$queryParams['professional_id'];
            }
            if (isset($queryParams['client_id']) && !empty($queryParams['client_id'])) {
                $filters['client_id'] = (int)$queryParams['client_id'];
            }
            if (isset($queryParams['pet_id']) && !empty($queryParams['pet_id'])) {
                $filters['pet_id'] = (int)$queryParams['pet_id'];
            }
            if (isset($queryParams['date']) && !empty($queryParams['date'])) {
                $filters['appointment_date'] = $queryParams['date'];
            }
            if (isset($queryParams['start_date']) && !empty($queryParams['start_date'])) {
                $filters['start_date'] = $queryParams['start_date'];
            }
            if (isset($queryParams['end_date']) && !empty($queryParams['end_date'])) {
                $filters['end_date'] = $queryParams['end_date'];
            }
            
            // Busca agendamentos (remove filtros de data do findByTenant)
            $baseFilters = $filters;
            unset($baseFilters['start_date'], $baseFilters['end_date']);
            $appointments = $this->appointmentModel->findByTenant($tenantId, $baseFilters);
            
            // Aplica filtros de data se fornecidos
            if (isset($filters['start_date']) || isset($filters['end_date'])) {
                $appointments = array_filter($appointments, function($apt) use ($filters) {
                    $aptDate = $apt['appointment_date'] ?? '';
                    if (isset($filters['start_date']) && $aptDate < $filters['start_date']) {
                        return false;
                    }
                    if (isset($filters['end_date']) && $aptDate > $filters['end_date']) {
                        return false;
                    }
                    return true;
                });
                $appointments = array_values($appointments); // Reindexa array
            }
            
            // Aplica busca textual se fornecida
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $searchLower = strtolower(trim($queryParams['search']));
                $appointments = array_filter($appointments, function($apt) use ($searchLower) {
                    $notes = strtolower($apt['notes'] ?? '');
                    return strpos($notes, $searchLower) !== false;
                });
                $appointments = array_values($appointments); // Reindexa array
            }
            
            // Enriquece com dados relacionados
            $enrichedAppointments = [];
            foreach ($appointments as $appointment) {
                $enriched = $appointment;
                
                // Carrega profissional
                if ($appointment['professional_id']) {
                    $professional = $this->professionalModel->findByTenantAndId($tenantId, $appointment['professional_id']);
                    if ($professional) {
                        $enriched['professional'] = $professional;
                    }
                }
                
                // Carrega cliente
                if ($appointment['client_id']) {
                    $client = $this->clientModel->findByTenantAndId($tenantId, $appointment['client_id']);
                    if ($client) {
                        $enriched['client'] = $client;
                    }
                }
                
                // Carrega pet
                if ($appointment['pet_id']) {
                    $pet = $this->petModel->findByTenantAndId($tenantId, $appointment['pet_id']);
                    if ($pet) {
                        $enriched['pet'] = $pet;
                    }
                }
                
                // Carrega especialidade
                if ($appointment['specialty_id']) {
                    $specialty = $this->specialtyModel->findByTenantAndId($tenantId, $appointment['specialty_id']);
                    if ($specialty) {
                        $enriched['specialty'] = $specialty;
                    }
                }
                
                $enrichedAppointments[] = $enriched;
            }
            
            // Ordenação
            $sortBy = $queryParams['sort'] ?? 'appointment_date';
            $sortOrder = $queryParams['order'] ?? 'ASC';
            usort($enrichedAppointments, function($a, $b) use ($sortBy, $sortOrder) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
                
                if ($sortBy === 'appointment_date') {
                    $aDate = strtotime($aVal . ' ' . ($a['appointment_time'] ?? '00:00:00'));
                    $bDate = strtotime($bVal . ' ' . ($b['appointment_time'] ?? '00:00:00'));
                    return $sortOrder === 'ASC' ? $aDate - $bDate : $bDate - $aDate;
                }
                
                $result = strcmp($aVal, $bVal);
                return $sortOrder === 'ASC' ? $result : -$result;
            });
            
            ResponseHelper::sendSuccess([
                'appointments' => $enrichedAppointments,
                'count' => count($enrichedAppointments)
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar agendamentos',
                'APPOINTMENTS_LIST_ERROR',
                ['action' => 'list_appointments', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém agendamento específico
     * GET /v1/appointments/:id
     */
    public function get(string $id): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_appointment', 'appointment_id' => $id]);
                return;
            }

            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'get_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Enriquece com dados relacionados
            if ($appointment['professional_id']) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, $appointment['professional_id']);
                if ($professional) {
                    $appointment['professional'] = $professional;
                }
            }
            
            if ($appointment['client_id']) {
                $client = $this->clientModel->findByTenantAndId($tenantId, $appointment['client_id']);
                if ($client) {
                    $appointment['client'] = $client;
                }
            }
            
            if ($appointment['pet_id']) {
                $pet = $this->petModel->findByTenantAndId($tenantId, $appointment['pet_id']);
                if ($pet) {
                    $appointment['pet'] = $pet;
                }
            }
            
            if ($appointment['specialty_id']) {
                $specialty = $this->specialtyModel->findByTenantAndId($tenantId, $appointment['specialty_id']);
                if ($specialty) {
                    $appointment['specialty'] = $specialty;
                }
            }
            
            ResponseHelper::sendSuccess($appointment);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter agendamento',
                'APPOINTMENT_GET_ERROR',
                ['action' => 'get_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Cria um novo agendamento
     * POST /v1/appointments
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_appointment']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_appointment']);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (empty($data['professional_id'])) {
                $errors['professional_id'] = 'Profissional é obrigatório';
            } else {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$data['professional_id']);
                if (!$professional) {
                    $errors['professional_id'] = 'Profissional não encontrado';
                }
            }
            
            if (empty($data['client_id'])) {
                $errors['client_id'] = 'Cliente é obrigatório';
            } else {
                $client = $this->clientModel->findByTenantAndId($tenantId, (int)$data['client_id']);
                if (!$client) {
                    $errors['client_id'] = 'Cliente não encontrado';
                }
            }
            
            if (empty($data['pet_id'])) {
                $errors['pet_id'] = 'Pet é obrigatório';
            } else {
                $pet = $this->petModel->findByTenantAndId($tenantId, (int)$data['pet_id']);
                if (!$pet) {
                    $errors['pet_id'] = 'Pet não encontrado';
                } elseif ($pet['client_id'] != $data['client_id']) {
                    $errors['pet_id'] = 'Pet não pertence ao cliente informado';
                }
            }
            
            if (empty($data['appointment_date'])) {
                $errors['appointment_date'] = 'Data do agendamento é obrigatória';
            } else {
                $date = \DateTime::createFromFormat('Y-m-d', $data['appointment_date']);
                if (!$date || $date->format('Y-m-d') !== $data['appointment_date']) {
                    $errors['appointment_date'] = 'Data inválida. Use o formato YYYY-MM-DD';
                }
            }
            
            if (empty($data['appointment_time'])) {
                $errors['appointment_time'] = 'Hora do agendamento é obrigatória';
            } else {
                $time = \DateTime::createFromFormat('H:i', $data['appointment_time']);
                if (!$time) {
                    $errors['appointment_time'] = 'Hora inválida. Use o formato HH:MM';
                }
            }
            
            if (!empty($data['status']) && !in_array($data['status'], ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])) {
                $errors['status'] = 'Status inválido';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'create_appointment']);
                return;
            }
            
            // Verifica conflito de horário
            $duration = (int)($data['duration_minutes'] ?? 30);
            if ($this->appointmentModel->hasConflict(
                $tenantId,
                (int)$data['professional_id'],
                $data['appointment_date'],
                $data['appointment_time'],
                $duration
            )) {
                ResponseHelper::sendError(
                    'Conflito de horário: já existe um agendamento neste horário',
                    409,
                    'APPOINTMENT_CONFLICT',
                    ['action' => 'create_appointment']
                );
                return;
            }
            
            // Prepara dados para inserção
            $insertData = [
                'tenant_id' => $tenantId,
                'professional_id' => (int)$data['professional_id'],
                'client_id' => (int)$data['client_id'],
                'pet_id' => (int)$data['pet_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'duration_minutes' => $duration,
                'status' => $data['status'] ?? 'scheduled'
            ];
            
            if (!empty($data['specialty_id'])) {
                $specialty = $this->specialtyModel->findByTenantAndId($tenantId, (int)$data['specialty_id']);
                if ($specialty) {
                    $insertData['specialty_id'] = (int)$data['specialty_id'];
                }
            }
            
            if (!empty($data['notes'])) {
                $insertData['notes'] = $data['notes'];
            }
            
            if (!empty($data['metadata']) && is_array($data['metadata'])) {
                $insertData['metadata'] = json_encode($data['metadata']);
            }
            
            $appointmentId = $this->appointmentModel->insert($insertData);
            
            // Busca agendamento criado
            $appointment = $this->appointmentModel->findById($appointmentId);
            
            // Enriquece com dados relacionados
            if ($appointment['professional_id']) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, $appointment['professional_id']);
                if ($professional) {
                    $appointment['professional'] = $professional;
                }
            }
            
            if ($appointment['client_id']) {
                $client = $this->clientModel->findByTenantAndId($tenantId, $appointment['client_id']);
                if ($client) {
                    $appointment['client'] = $client;
                }
            }
            
            if ($appointment['pet_id']) {
                $pet = $this->petModel->findByTenantAndId($tenantId, $appointment['pet_id']);
                if ($pet) {
                    $appointment['pet'] = $pet;
                }
            }
            
            ResponseHelper::sendSuccess($appointment, 201, 'Agendamento criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar agendamento',
                'APPOINTMENT_CREATE_ERROR',
                ['action' => 'create_appointment', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza agendamento
     * PUT /v1/appointments/:id
     */
    public function update(string $id): void
    {
        try {
            PermissionHelper::require('update_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'update_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_appointment', 'appointment_id' => $id]);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (isset($data['appointment_date'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $data['appointment_date']);
                if (!$date || $date->format('Y-m-d') !== $data['appointment_date']) {
                    $errors['appointment_date'] = 'Data inválida. Use o formato YYYY-MM-DD';
                }
            }
            
            if (isset($data['appointment_time'])) {
                $time = \DateTime::createFromFormat('H:i', $data['appointment_time']);
                if (!$time) {
                    $errors['appointment_time'] = 'Hora inválida. Use o formato HH:MM';
                }
            }
            
            if (isset($data['status']) && !in_array($data['status'], ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])) {
                $errors['status'] = 'Status inválido';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'update_appointment', 'appointment_id' => $id]);
                return;
            }
            
            // Verifica conflito de horário se data/hora foram alteradas
            if (isset($data['appointment_date']) || isset($data['appointment_time'])) {
                $date = $data['appointment_date'] ?? $appointment['appointment_date'];
                $time = $data['appointment_time'] ?? $appointment['appointment_time'];
                $duration = (int)($data['duration_minutes'] ?? $appointment['duration_minutes'] ?? 30);
                $professionalId = (int)($data['professional_id'] ?? $appointment['professional_id']);
                
                if ($this->appointmentModel->hasConflict(
                    $tenantId,
                    $professionalId,
                    $date,
                    $time,
                    $duration,
                    (int)$id
                )) {
                    ResponseHelper::sendError(
                        'Conflito de horário: já existe um agendamento neste horário',
                        409,
                        'APPOINTMENT_CONFLICT',
                        ['action' => 'update_appointment', 'appointment_id' => $id]
                    );
                    return;
                }
            }
            
            // Prepara dados para atualização
            $updateData = [];
            
            if (isset($data['professional_id'])) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$data['professional_id']);
                if ($professional) {
                    $updateData['professional_id'] = (int)$data['professional_id'];
                } else {
                    $errors['professional_id'] = 'Profissional não encontrado';
                }
            }
            
            if (isset($data['client_id'])) {
                $client = $this->clientModel->findByTenantAndId($tenantId, (int)$data['client_id']);
                if ($client) {
                    $updateData['client_id'] = (int)$data['client_id'];
                } else {
                    $errors['client_id'] = 'Cliente não encontrado';
                }
            }
            
            if (isset($data['pet_id'])) {
                $pet = $this->petModel->findByTenantAndId($tenantId, (int)$data['pet_id']);
                if ($pet) {
                    $updateData['pet_id'] = (int)$data['pet_id'];
                } else {
                    $errors['pet_id'] = 'Pet não encontrado';
                }
            }
            
            if (isset($data['appointment_date'])) {
                $updateData['appointment_date'] = $data['appointment_date'];
            }
            
            if (isset($data['appointment_time'])) {
                $updateData['appointment_time'] = $data['appointment_time'];
            }
            
            if (isset($data['duration_minutes'])) {
                $updateData['duration_minutes'] = (int)$data['duration_minutes'];
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
                
                // Se cancelado, registra informações de cancelamento
                if ($data['status'] === 'cancelled') {
                    $userId = Flight::get('user_id');
                    if ($userId) {
                        $updateData['cancelled_by'] = $userId;
                    }
                    $updateData['cancelled_at'] = date('Y-m-d H:i:s');
                    if (isset($data['cancellation_reason'])) {
                        $updateData['cancellation_reason'] = $data['cancellation_reason'];
                    }
                }
            }
            
            if (isset($data['specialty_id'])) {
                if (empty($data['specialty_id'])) {
                    $updateData['specialty_id'] = null;
                } else {
                    $specialty = $this->specialtyModel->findByTenantAndId($tenantId, (int)$data['specialty_id']);
                    if ($specialty) {
                        $updateData['specialty_id'] = (int)$data['specialty_id'];
                    }
                }
            }
            
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }
            
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $updateData['metadata'] = json_encode($data['metadata']);
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'update_appointment', 'appointment_id' => $id]);
                return;
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendError('Nenhum campo para atualizar', 400, 'NO_FIELDS_TO_UPDATE', ['action' => 'update_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $this->appointmentModel->update((int)$id, $updateData);
            
            // Busca agendamento atualizado
            $updated = $this->appointmentModel->findById((int)$id);
            
            // Enriquece com dados relacionados
            if ($updated['professional_id']) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, $updated['professional_id']);
                if ($professional) {
                    $updated['professional'] = $professional;
                }
            }
            
            if ($updated['client_id']) {
                $client = $this->clientModel->findByTenantAndId($tenantId, $updated['client_id']);
                if ($client) {
                    $updated['client'] = $client;
                }
            }
            
            if ($updated['pet_id']) {
                $pet = $this->petModel->findByTenantAndId($tenantId, $updated['pet_id']);
                if ($pet) {
                    $updated['pet'] = $pet;
                }
            }
            
            ResponseHelper::sendSuccess($updated, 200, 'Agendamento atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar agendamento',
                'APPOINTMENT_UPDATE_ERROR',
                ['action' => 'update_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Deleta agendamento (soft delete)
     * DELETE /v1/appointments/:id
     */
    public function delete(string $id): void
    {
        try {
            PermissionHelper::require('delete_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'delete_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Soft delete
            $this->appointmentModel->delete((int)$id);
            
            ResponseHelper::sendSuccess(null, 200, 'Agendamento deletado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao deletar agendamento',
                'APPOINTMENT_DELETE_ERROR',
                ['action' => 'delete_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém histórico do agendamento
     * GET /v1/appointments/:id/history
     */
    public function history(string $id): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_appointment_history', 'appointment_id' => $id]);
                return;
            }
            
            // Verifica se agendamento existe e pertence ao tenant
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'get_appointment_history', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Busca histórico
            $history = $this->appointmentHistoryModel->findByAppointment($tenantId, (int)$id);
            
            ResponseHelper::sendSuccess($history);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter histórico do agendamento',
                'APPOINTMENT_HISTORY_ERROR',
                ['action' => 'get_appointment_history', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


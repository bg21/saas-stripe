<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Specialty;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use App\Utils\Validator;
use App\Services\Logger;
use App\Services\EmailService;
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
    private ProfessionalSchedule $scheduleModel;
    private ScheduleBlock $blockModel;
    private Client $clientModel;
    private Pet $petModel;
    private Specialty $specialtyModel;
    private EmailService $emailService;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->appointmentHistoryModel = new AppointmentHistory();
        $this->professionalModel = new Professional();
        $this->scheduleModel = new ProfessionalSchedule();
        $this->blockModel = new ScheduleBlock();
        $this->clientModel = new Client();
        $this->petModel = new Pet();
        $this->specialtyModel = new Specialty();
        $this->emailService = new EmailService();
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
            
            // ✅ OTIMIZAÇÃO: Carrega todos os dados relacionados de uma vez (elimina N+1)
            // Coleta IDs únicos
            $professionalIds = array_unique(array_filter(array_column($appointments, 'professional_id')));
            $clientIds = array_unique(array_filter(array_column($appointments, 'client_id')));
            $petIds = array_unique(array_filter(array_column($appointments, 'pet_id')));
            $specialtyIds = array_unique(array_filter(array_column($appointments, 'specialty_id')));
            
            // Carrega todos os profissionais de uma vez
            $professionalsById = [];
            if (!empty($professionalIds)) {
                $db = \App\Utils\Database::getInstance();
                $placeholders = implode(',', array_fill(0, count($professionalIds), '?'));
                $stmt = $db->prepare("
                    SELECT * FROM professionals 
                    WHERE tenant_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL
                ");
                $stmt->execute(array_merge([$tenantId], $professionalIds));
                $professionals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($professionals as $prof) {
                    $professionalsById[$prof['id']] = $prof;
                }
            }
            
            // Carrega todos os clientes de uma vez
            $clientsById = [];
            if (!empty($clientIds)) {
                $db = \App\Utils\Database::getInstance();
                $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
                $stmt = $db->prepare("
                    SELECT * FROM clients 
                    WHERE tenant_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL
                ");
                $stmt->execute(array_merge([$tenantId], $clientIds));
                $clients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($clients as $client) {
                    $clientsById[$client['id']] = $client;
                }
            }
            
            // Carrega todos os pets de uma vez
            $petsById = [];
            if (!empty($petIds)) {
                $db = \App\Utils\Database::getInstance();
                $placeholders = implode(',', array_fill(0, count($petIds), '?'));
                $stmt = $db->prepare("
                    SELECT * FROM pets 
                    WHERE tenant_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL
                ");
                $stmt->execute(array_merge([$tenantId], $petIds));
                $pets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($pets as $pet) {
                    $petsById[$pet['id']] = $pet;
                }
            }
            
            // Carrega todas as especialidades de uma vez
            $specialtiesById = [];
            if (!empty($specialtyIds)) {
                $db = \App\Utils\Database::getInstance();
                $placeholders = implode(',', array_fill(0, count($specialtyIds), '?'));
                $stmt = $db->prepare("
                    SELECT * FROM specialties 
                    WHERE tenant_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL
                ");
                $stmt->execute(array_merge([$tenantId], $specialtyIds));
                $specialties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($specialties as $specialty) {
                    $specialtiesById[$specialty['id']] = $specialty;
                }
            }
            
            // Enriquece agendamentos com dados já carregados
            $enrichedAppointments = [];
            foreach ($appointments as $appointment) {
                $enriched = $appointment;
                
                // Adiciona profissional (se existir)
                if (!empty($appointment['professional_id']) && isset($professionalsById[$appointment['professional_id']])) {
                    $enriched['professional'] = $professionalsById[$appointment['professional_id']];
                }
                
                // Adiciona cliente (se existir)
                if (!empty($appointment['client_id']) && isset($clientsById[$appointment['client_id']])) {
                    $enriched['client'] = $clientsById[$appointment['client_id']];
                }
                
                // Adiciona pet (se existir)
                if (!empty($appointment['pet_id']) && isset($petsById[$appointment['pet_id']])) {
                    $enriched['pet'] = $petsById[$appointment['pet_id']];
                }
                
                // Adiciona especialidade (se existir)
                if (!empty($appointment['specialty_id']) && isset($specialtiesById[$appointment['specialty_id']])) {
                    $enriched['specialty'] = $specialtiesById[$appointment['specialty_id']];
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
                    409,
                    'Conflito de horário',
                    'Já existe um agendamento neste horário',
                    'APPOINTMENT_CONFLICT',
                    [],
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
            
            // ✅ CORREÇÃO: Valida tamanho do array metadata (prevenção de DoS)
            if (!empty($data['metadata']) && is_array($data['metadata'])) {
                $metadataErrors = Validator::validateArraySize($data['metadata'], 'metadata', 50);
                if (!empty($metadataErrors)) {
                    $errors = array_merge($errors, $metadataErrors);
                } else {
                    $insertData['metadata'] = json_encode($data['metadata']);
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'create_appointment', 'tenant_id' => $tenantId]);
                return;
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
            
            // Envia email de confirmação de criação
            try {
                if (isset($appointment['client']) && isset($appointment['pet']) && isset($appointment['professional'])) {
                    $this->emailService->sendAppointmentCreated(
                        $appointment,
                        $appointment['client'],
                        $appointment['pet'],
                        $appointment['professional']
                    );
                }
            } catch (\Exception $e) {
                // Log erro, mas não falha a criação
                Logger::error('Erro ao enviar email de agendamento criado', [
                    'error' => $e->getMessage(),
                    'appointment_id' => $appointmentId,
                    'tenant_id' => $tenantId
                ]);
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
                        409,
                        'Conflito de horário',
                        'Já existe um agendamento neste horário',
                        'APPOINTMENT_CONFLICT',
                        [],
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
            
            // ✅ CORREÇÃO: Valida tamanho do array metadata (prevenção de DoS)
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $metadataErrors = Validator::validateArraySize($data['metadata'], 'metadata', 50);
                if (!empty($metadataErrors)) {
                    $errors = array_merge($errors, $metadataErrors);
                } else {
                    $updateData['metadata'] = json_encode($data['metadata']);
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'update_appointment', 'appointment_id' => $id]);
                return;
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendError(400, 'Validação', 'Nenhum campo para atualizar', 'NO_FIELDS_TO_UPDATE', [], ['action' => 'update_appointment', 'appointment_id' => $id]);
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
            
            // Se foi cancelado, envia email de cancelamento
            if (isset($data['status']) && $data['status'] === 'cancelled' && $appointment['status'] !== 'cancelled') {
                try {
                    $client = null;
                    $pet = null;
                    $professional = null;
                    
                    if ($updated['client_id']) {
                        $client = $this->clientModel->findByTenantAndId($tenantId, $updated['client_id']);
                    }
                    if ($updated['pet_id']) {
                        $pet = $this->petModel->findByTenantAndId($tenantId, $updated['pet_id']);
                    }
                    if ($updated['professional_id']) {
                        $professional = $this->professionalModel->findByTenantAndId($tenantId, $updated['professional_id']);
                    }
                    
                    if ($client && $pet && $professional) {
                        $reason = $data['cancellation_reason'] ?? $updated['cancellation_reason'] ?? null;
                        $this->emailService->sendAppointmentCancelled(
                            $updated,
                            $client,
                            $pet,
                            $professional,
                            $reason
                        );
                    }
                } catch (\Exception $e) {
                    // Log erro, mas não falha a atualização
                    Logger::error('Erro ao enviar email de agendamento cancelado', [
                        'error' => $e->getMessage(),
                        'appointment_id' => $id,
                        'tenant_id' => $tenantId
                    ]);
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

    /**
     * Confirma um agendamento
     * POST /v1/appointments/:id/confirm
     */
    public function confirm(string $id): void
    {
        try {
            PermissionHelper::require('confirm_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'confirm_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'confirm_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            if ($appointment['status'] !== 'scheduled') {
                ResponseHelper::sendError(400, 'Status inválido', 'Apenas agendamentos marcados podem ser confirmados', 'INVALID_STATUS', [], ['action' => 'confirm_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $userId = Flight::get('user_id');
            
            // Prepara dados para atualização
            $updateData = [
                'status' => 'confirmed'
            ];
            
            // Tenta adicionar campos de confirmação se existirem na tabela
            // Se não existirem, o update() vai ignorar esses campos
            $updateData['confirmed_at'] = date('Y-m-d H:i:s');
            if ($userId) {
                $updateData['confirmed_by'] = $userId;
            }
            
            // Atualiza status
            $this->appointmentModel->update((int)$id, $updateData);
            
            // Registra no histórico usando o método create() do AppointmentHistory
            try {
                $this->appointmentHistoryModel->create(
                    $tenantId,
                    (int)$id,
                    'confirmed',
                    ['status' => 'scheduled'],
                    ['status' => 'confirmed'],
                    'Agendamento confirmado',
                    $userId
                );
            } catch (\Exception $e) {
                // Se a tabela não existir, apenas loga o erro mas não falha a operação
                Logger::warning('Erro ao registrar histórico de agendamento', [
                    'error' => $e->getMessage(),
                    'appointment_id' => $id,
                    'action' => 'confirm'
                ]);
            }
            
            // Busca agendamento atualizado
            $updated = $this->appointmentModel->findById((int)$id);
            
            // Enriquece com dados relacionados para envio de email
            $client = null;
            $pet = null;
            $professional = null;
            
            if ($updated['client_id']) {
                $client = $this->clientModel->findByTenantAndId($tenantId, $updated['client_id']);
            }
            if ($updated['pet_id']) {
                $pet = $this->petModel->findByTenantAndId($tenantId, $updated['pet_id']);
            }
            if ($updated['professional_id']) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, $updated['professional_id']);
            }
            
            // Envia email de confirmação
            try {
                if ($client && $pet && $professional) {
                    $this->emailService->sendAppointmentConfirmed(
                        $updated,
                        $client,
                        $pet,
                        $professional
                    );
                }
            } catch (\Exception $e) {
                // Log erro, mas não falha a confirmação
                Logger::error('Erro ao enviar email de agendamento confirmado', [
                    'error' => $e->getMessage(),
                    'appointment_id' => $id,
                    'tenant_id' => $tenantId
                ]);
            }
            
            ResponseHelper::sendSuccess($updated, 200, 'Agendamento confirmado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao confirmar agendamento',
                'APPOINTMENT_CONFIRM_ERROR',
                ['action' => 'confirm_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Marca agendamento como concluído
     * POST /v1/appointments/:id/complete
     */
    public function complete(string $id): void
    {
        try {
            PermissionHelper::require('update_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'complete_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'complete_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            if (!in_array($appointment['status'], ['scheduled', 'confirmed'])) {
                ResponseHelper::sendError(400, 'Status inválido', 'Apenas agendamentos marcados ou confirmados podem ser concluídos', 'INVALID_STATUS', [], ['action' => 'complete_appointment', 'appointment_id' => $id]);
                return;
            }
            
            $userId = Flight::get('user_id');
            
            // Prepara dados para atualização
            $updateData = [
                'status' => 'completed'
            ];
            
            // Tenta adicionar campos de conclusão se existirem na tabela
            $updateData['completed_at'] = date('Y-m-d H:i:s');
            if ($userId) {
                $updateData['completed_by'] = $userId;
            }
            
            // Atualiza status
            $this->appointmentModel->update((int)$id, $updateData);
            
            // Registra no histórico
            try {
                $this->appointmentHistoryModel->create(
                    $tenantId,
                    (int)$id,
                    'completed',
                    ['status' => $appointment['status']],
                    ['status' => 'completed'],
                    'Agendamento concluído',
                    $userId
                );
            } catch (\Exception $e) {
                // Se a tabela não existir, apenas loga o erro mas não falha a operação
                Logger::warning('Erro ao registrar histórico de agendamento', [
                    'error' => $e->getMessage(),
                    'appointment_id' => $id,
                    'action' => 'complete'
                ]);
            }
            
            // Busca agendamento atualizado
            $updated = $this->appointmentModel->findById((int)$id);
            
            ResponseHelper::sendSuccess($updated, 200, 'Agendamento marcado como concluído');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao concluir agendamento',
                'APPOINTMENT_COMPLETE_ERROR',
                ['action' => 'complete_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém horários disponíveis
     * GET /v1/appointments/available-slots?professional_id=1&date=2025-01-22
     */
    public function availableSlots(): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_available_slots']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            
            if (empty($queryParams['professional_id'])) {
                ResponseHelper::sendError(400, 'Parâmetro obrigatório', 'professional_id é obrigatório', 'MISSING_PARAMETER', [], ['action' => 'get_available_slots']);
                return;
            }
            
            if (empty($queryParams['date'])) {
                ResponseHelper::sendError(400, 'Parâmetro obrigatório', 'date é obrigatório', 'MISSING_PARAMETER', [], ['action' => 'get_available_slots']);
                return;
            }
            
            $professionalId = (int)$queryParams['professional_id'];
            $date = $queryParams['date'];
            
            // Valida formato da data
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                ResponseHelper::sendError(400, 'Data inválida', 'Use o formato YYYY-MM-DD', 'INVALID_DATE', [], ['action' => 'get_available_slots']);
                return;
            }
            
            // Verifica se profissional existe
            $professional = $this->professionalModel->findByTenantAndId($tenantId, $professionalId);
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'get_available_slots', 'professional_id' => $professionalId]);
                return;
            }
            
            // 1. Busca agenda do profissional para o dia da semana
            $dayOfWeek = (int)$dateObj->format('w'); // 0=domingo, 1=segunda, ..., 6=sábado
            $schedule = $this->scheduleModel->findByDay($tenantId, $professionalId, $dayOfWeek);
            
            // Se não tem horário configurado para este dia, retorna vazio
            if (!$schedule) {
                ResponseHelper::sendSuccess([]);
                return;
            }
            
            // Verifica se está disponível
            if (!((int)$schedule['is_available'])) {
                ResponseHelper::sendSuccess([]);
                return;
            }
            
            // 2. Busca bloqueios para a data
            $blocks = $this->blockModel->findByProfessionalAndPeriod(
                $tenantId,
                $professionalId,
                $date,
                $date
            );
            
            // 3. Calcula horários disponíveis baseado na agenda
            $slots = [];
            $startTime = new \DateTime($schedule['start_time']);
            $endTime = new \DateTime($schedule['end_time']);
            $intervalMinutes = (int)($professional['default_consultation_duration'] ?? 30);
            
            $currentTime = clone $startTime;
            
            while ($currentTime < $endTime) {
                $time = $currentTime->format('H:i');
                $datetime = "$date $time:00";
                
                // Verifica se está em um bloqueio
                $isBlocked = false;
                foreach ($blocks as $block) {
                    $blockStart = new \DateTime($block['start_datetime']);
                    $blockEnd = new \DateTime($block['end_datetime']);
                    $slotDateTime = new \DateTime($datetime);
                    
                    if ($slotDateTime >= $blockStart && $slotDateTime < $blockEnd) {
                        $isBlocked = true;
                        break;
                    }
                }
                
                if ($isBlocked) {
                    $currentTime->modify("+{$intervalMinutes} minutes");
                    continue;
                }
                
                // Verifica se há conflito com agendamentos existentes
                $hasConflict = $this->appointmentModel->hasConflict(
                    $tenantId,
                    $professionalId,
                    $date,
                    $time,
                    $intervalMinutes,
                    null
                );
                
                if (!$hasConflict) {
                    $slots[] = [
                        'time' => $time,
                        'available' => true
                    ];
                }
                
                $currentTime->modify("+{$intervalMinutes} minutes");
            }
            
            ResponseHelper::sendSuccess($slots);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter horários disponíveis',
                'AVAILABLE_SLOTS_ERROR',
                ['action' => 'get_available_slots', 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


<?php

namespace App\Controllers;

use App\Services\AppointmentService;
use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar agendamentos/consultas
 */
class AppointmentController
{
    private AppointmentService $appointmentService;
    private Appointment $appointmentModel;

    public function __construct(AppointmentService $appointmentService, Appointment $appointmentModel)
    {
        $this->appointmentService = $appointmentService;
        $this->appointmentModel = $appointmentModel;
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
            $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
            $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            
            $filters = ['tenant_id' => $tenantId];
            
            if (!empty($queryParams['professional_id'])) {
                $filters['professional_id'] = (int)$queryParams['professional_id'];
            }
            
            if (!empty($queryParams['client_id'])) {
                $filters['client_id'] = (int)$queryParams['client_id'];
            }
            
            if (!empty($queryParams['pet_id'])) {
                $filters['pet_id'] = (int)$queryParams['pet_id'];
            }
            
            if (!empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            
            if (!empty($queryParams['date'])) {
                $filters['appointment_date'] = $queryParams['date'];
            }
            
            $result = $this->appointmentModel->findAllWithCount($filters, ['appointment_date' => 'DESC', 'appointment_time' => 'ASC'], $limit, $offset);
            
            // Decodifica JSON
            foreach ($result['data'] as &$appointment) {
                if ($appointment['metadata']) {
                    $appointment['metadata'] = json_decode($appointment['metadata'], true);
                }
            }
            
            $responseData = [
                'appointments' => $result['data'],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $result['total'],
                    'total_pages' => ceil($result['total'] / $limit)
                ]
            ];
            ResponseHelper::sendSuccess($responseData, 200, 'Agendamentos listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar agendamentos', 'APPOINTMENTS_LIST_ERROR', ['action' => 'list_appointments', 'tenant_id' => Flight::get('tenant_id')]);
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
            
            $userId = Flight::get('user_id');
            
            $appointment = $this->appointmentService->create($tenantId, $data, $userId);
            
            // Decodifica JSON
            if ($appointment['metadata']) {
                $appointment['metadata'] = json_decode($appointment['metadata'], true);
            }
            
            ResponseHelper::sendCreated($appointment, 'Agendamento criado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_appointment', 'tenant_id' => $tenantId]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar agendamento', 'APPOINTMENT_CREATE_ERROR', ['action' => 'create_appointment', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém um agendamento
     * GET /v1/appointments/:id
     */
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_appointment']);
                return;
            }
            
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento não encontrado', ['action' => 'get_appointment', 'appointment_id' => $id]);
                return;
            }
            
            // Decodifica JSON
            if ($appointment['metadata']) {
                $appointment['metadata'] = json_decode($appointment['metadata'], true);
            }
            
            ResponseHelper::sendSuccess($appointment, 200, 'Agendamento obtido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter agendamento', 'APPOINTMENT_GET_ERROR', ['action' => 'get_appointment', 'appointment_id' => $id]);
        }
    }

    /**
     * Atualiza um agendamento
     * PUT /v1/appointments/:id
     */
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_appointment']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_appointment']);
                return;
            }
            
            $userId = Flight::get('user_id');
            
            $appointment = $this->appointmentService->update($tenantId, $id, $data, $userId);
            
            // Decodifica JSON
            if ($appointment['metadata']) {
                $appointment['metadata'] = json_decode($appointment['metadata'], true);
            }
            
            ResponseHelper::sendSuccess($appointment, 200, 'Agendamento atualizado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'update_appointment', 'appointment_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar agendamento', 'APPOINTMENT_UPDATE_ERROR', ['action' => 'update_appointment', 'appointment_id' => $id]);
        }
    }

    /**
     * Cancela um agendamento
     * DELETE /v1/appointments/:id
     */
    public function cancel(int $id): void
    {
        try {
            PermissionHelper::require('cancel_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'cancel_appointment']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            $reason = $data['reason'] ?? 'Cancelado pelo usuário';
            
            $userId = Flight::get('user_id');
            
            $appointment = $this->appointmentService->cancel($tenantId, $id, $reason, $userId);
            
            ResponseHelper::sendSuccess($appointment, 200, 'Agendamento cancelado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'cancel_appointment', 'appointment_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao cancelar agendamento', 'APPOINTMENT_CANCEL_ERROR', ['action' => 'cancel_appointment', 'appointment_id' => $id]);
        }
    }

    /**
     * Confirma um agendamento
     * POST /v1/appointments/:id/confirm
     */
    public function confirm(int $id): void
    {
        try {
            PermissionHelper::require('confirm_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'confirm_appointment']);
                return;
            }
            
            $userId = Flight::get('user_id');
            
            $appointment = $this->appointmentService->confirm($tenantId, $id, $userId);
            
            ResponseHelper::sendSuccess($appointment, 200, 'Agendamento confirmado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'confirm_appointment', 'appointment_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao confirmar agendamento', 'APPOINTMENT_CONFIRM_ERROR', ['action' => 'confirm_appointment', 'appointment_id' => $id]);
        }
    }

    /**
     * Marca agendamento como concluído
     * POST /v1/appointments/:id/complete
     */
    public function complete(int $id): void
    {
        try {
            PermissionHelper::require('update_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'complete_appointment']);
                return;
            }
            
            $userId = Flight::get('user_id');
            
            $appointment = $this->appointmentService->complete($tenantId, $id, $userId);
            
            ResponseHelper::sendSuccess($appointment, 200, 'Agendamento marcado como concluído');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'complete_appointment', 'appointment_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao concluir agendamento', 'APPOINTMENT_COMPLETE_ERROR', ['action' => 'complete_appointment', 'appointment_id' => $id]);
        }
    }

    /**
     * Obtém horários disponíveis
     * GET /v1/appointments/available-slots
     */
    public function getAvailableSlots(): void
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
                ResponseHelper::sendValidationError('professional_id é obrigatório', ['professional_id' => 'Obrigatório'], ['action' => 'get_available_slots']);
                return;
            }
            
            if (empty($queryParams['date'])) {
                ResponseHelper::sendValidationError('date é obrigatório', ['date' => 'Obrigatório'], ['action' => 'get_available_slots']);
                return;
            }
            
            $professionalId = (int)$queryParams['professional_id'];
            $date = $queryParams['date'];
            $duration = isset($queryParams['duration']) ? (int)$queryParams['duration'] : null;
            
            $slots = $this->appointmentService->getAvailableSlots($tenantId, $professionalId, $date, $duration);
            
            ResponseHelper::sendSuccess($slots, 200, 'Horários disponíveis obtidos com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter horários disponíveis', 'AVAILABLE_SLOTS_ERROR', ['action' => 'get_available_slots', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém histórico de um agendamento
     * GET /v1/appointments/:id/history
     */
    public function getHistory(int $id): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_appointment_history']);
                return;
            }
            
            $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $id);
            
            if (!$appointment) {
                ResponseHelper::sendNotFoundError('Agendamento não encontrado', ['action' => 'get_appointment_history', 'appointment_id' => $id]);
                return;
            }
            
            $history = (new AppointmentHistory())->findByAppointment($id);
            
            ResponseHelper::sendSuccess($history, 200, 'Histórico do agendamento obtido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter histórico do agendamento', 'APPOINTMENT_HISTORY_ERROR', ['action' => 'get_appointment_history', 'appointment_id' => $id]);
        }
    }
}


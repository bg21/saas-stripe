<?php

namespace App\Controllers;

use App\Services\ScheduleService;
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Models\Professional;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar agendas dos profissionais
 */
class ScheduleController
{
    private ScheduleService $scheduleService;
    private ProfessionalSchedule $scheduleModel;
    private ScheduleBlock $blockModel;

    public function __construct(
        ScheduleService $scheduleService,
        ProfessionalSchedule $scheduleModel,
        ScheduleBlock $blockModel
    ) {
        $this->scheduleService = $scheduleService;
        $this->scheduleModel = $scheduleModel;
        $this->blockModel = $blockModel;
    }

    /**
     * Obtém agenda de um profissional
     * GET /v1/professionals/:id/schedule
     */
    public function getSchedule(int $id): void
    {
        try {
            PermissionHelper::require('view_schedules');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_schedule']);
                return;
            }
            
            // Valida profissional
            $professional = (new Professional())->findByTenantAndId($tenantId, $id);
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional não encontrado', ['action' => 'get_schedule', 'professional_id' => $id]);
                return;
            }
            
            $schedules = $this->scheduleModel->findByTenantAndProfessional($tenantId, $id);
            
            ResponseHelper::sendSuccess($schedules, 'Agenda obtida com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter agenda', 'SCHEDULE_GET_ERROR', ['action' => 'get_schedule', 'professional_id' => $id]);
        }
    }

    /**
     * Atualiza agenda de um profissional
     * PUT /v1/professionals/:id/schedule
     */
    public function updateSchedule(int $id): void
    {
        try {
            PermissionHelper::require('manage_schedules');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_schedule']);
                return;
            }
            
            // Valida profissional
            $professional = (new Professional())->findByTenantAndId($tenantId, $id);
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional não encontrado', ['action' => 'update_schedule', 'professional_id' => $id]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_schedule']);
                return;
            }
            
            // Espera array de horários
            if (!isset($data['schedules']) || !is_array($data['schedules'])) {
                ResponseHelper::sendValidationError('schedules deve ser um array', ['schedules' => 'Deve ser um array'], ['action' => 'update_schedule']);
                return;
            }
            
            // Remove horários existentes do profissional
            $existingSchedules = $this->scheduleModel->findByProfessional($id);
            foreach ($existingSchedules as $schedule) {
                $this->scheduleModel->delete($schedule['id']);
            }
            
            // Cria novos horários
            $created = [];
            foreach ($data['schedules'] as $scheduleData) {
                if (!isset($scheduleData['day_of_week']) || !isset($scheduleData['start_time']) || !isset($scheduleData['end_time'])) {
                    continue; // Ignora horários inválidos
                }
                
                $scheduleId = $this->scheduleModel->insert([
                    'tenant_id' => $tenantId,
                    'professional_id' => $id,
                    'day_of_week' => (int)$scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time'],
                    'is_available' => $scheduleData['is_available'] ?? true
                ]);
                
                $created[] = $this->scheduleModel->findById($scheduleId);
            }
            
            ResponseHelper::sendSuccess($created, 'Agenda atualizada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar agenda', 'SCHEDULE_UPDATE_ERROR', ['action' => 'update_schedule', 'professional_id' => $id]);
        }
    }

    /**
     * Obtém horários disponíveis de um profissional
     * GET /v1/professionals/:id/available-slots
     */
    public function getAvailableSlots(int $id): void
    {
        try {
            PermissionHelper::require('view_schedules');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_available_slots']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            
            if (empty($queryParams['date'])) {
                ResponseHelper::sendValidationError('date é obrigatório', ['date' => 'Obrigatório'], ['action' => 'get_available_slots']);
                return;
            }
            
            $date = $queryParams['date'];
            $duration = isset($queryParams['duration']) ? (int)$queryParams['duration'] : null;
            
            $slots = $this->scheduleService->calculateAvailableSlots($tenantId, $id, $date, $duration);
            
            ResponseHelper::sendSuccess($slots, 'Horários disponíveis obtidos com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter horários disponíveis', 'AVAILABLE_SLOTS_ERROR', ['action' => 'get_available_slots', 'professional_id' => $id]);
        }
    }

    /**
     * Cria bloqueio de agenda
     * POST /v1/professionals/:id/schedule/blocks
     */
    public function createBlock(int $id): void
    {
        try {
            PermissionHelper::require('manage_schedules');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_schedule_block']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_schedule_block']);
                return;
            }
            
            if (empty($data['start_datetime']) || empty($data['end_datetime'])) {
                ResponseHelper::sendValidationError('start_datetime e end_datetime são obrigatórios', [
                    'start_datetime' => 'Obrigatório',
                    'end_datetime' => 'Obrigatório'
                ], ['action' => 'create_schedule_block']);
                return;
            }
            
            $start = new \DateTime($data['start_datetime']);
            $end = new \DateTime($data['end_datetime']);
            $reason = $data['reason'] ?? null;
            
            $blockId = $this->scheduleService->createBlock($tenantId, $id, $start, $end, $reason);
            $block = $this->blockModel->findById($blockId);
            
            ResponseHelper::sendCreated($block, 'Bloqueio criado com sucesso');
        } catch (\RuntimeException $e) {
            ResponseHelper::sendValidationError($e->getMessage(), [], ['action' => 'create_schedule_block', 'professional_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar bloqueio', 'SCHEDULE_BLOCK_CREATE_ERROR', ['action' => 'create_schedule_block', 'professional_id' => $id]);
        }
    }

    /**
     * Remove bloqueio de agenda
     * DELETE /v1/professionals/:id/schedule/blocks/:block_id
     */
    public function deleteBlock(int $id, int $blockId): void
    {
        try {
            PermissionHelper::require('manage_schedules');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_schedule_block']);
                return;
            }
            
            $block = $this->blockModel->findById($blockId);
            
            if (!$block || $block['professional_id'] != $id || $block['tenant_id'] != $tenantId) {
                ResponseHelper::sendNotFoundError('Bloqueio não encontrado', ['action' => 'delete_schedule_block', 'block_id' => $blockId]);
                return;
            }
            
            $this->scheduleService->removeBlock($blockId);
            
            ResponseHelper::sendSuccess(null, 'Bloqueio removido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao remover bloqueio', 'SCHEDULE_BLOCK_DELETE_ERROR', ['action' => 'delete_schedule_block', 'block_id' => $blockId]);
        }
    }
}


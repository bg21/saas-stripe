<?php

namespace App\Controllers;

use App\Models\Professional;
use App\Models\User;
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Services\PlanLimitsService;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use App\Utils\Validator;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Controller para gerenciar profissionais
 */
class ProfessionalController
{
    private Professional $professionalModel;
    private User $userModel;
    private ProfessionalSchedule $scheduleModel;
    private ScheduleBlock $blockModel;
    private PlanLimitsService $planLimitsService;

    public function __construct()
    {
        $this->professionalModel = new Professional();
        $this->userModel = new User();
        $this->scheduleModel = new ProfessionalSchedule();
        $this->blockModel = new ScheduleBlock();
        $this->planLimitsService = new PlanLimitsService();
    }

    /**
     * Lista profissionais do tenant
     * GET /v1/professionals
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_professionals']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            
            $filters = [];
            if (isset($queryParams['status']) && !empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            if (isset($queryParams['specialty_id']) && !empty($queryParams['specialty_id'])) {
                $filters['specialty_id'] = (int)$queryParams['specialty_id'];
            }
            if (isset($queryParams['sort']) && !empty($queryParams['sort'])) {
                $filters['sort'] = $queryParams['sort'];
            }
            if (isset($queryParams['order']) && !empty($queryParams['order'])) {
                $filters['order'] = strtoupper($queryParams['order']) === 'ASC' ? 'ASC' : 'DESC';
            }
            
            // Busca profissionais com dados do usuário
            $professionals = $this->professionalModel->findByTenantWithUser($tenantId, $filters);
            
            // Aplica busca textual se fornecida
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $searchLower = strtolower(trim($queryParams['search']));
                $professionals = array_filter($professionals, function($prof) use ($searchLower) {
                    $name = strtolower($prof['user_name'] ?? '');
                    $crmv = strtolower($prof['crmv'] ?? '');
                    $email = strtolower($prof['user_email'] ?? '');
                    return strpos($name, $searchLower) !== false || 
                           strpos($crmv, $searchLower) !== false ||
                           strpos($email, $searchLower) !== false;
                });
                $professionals = array_values($professionals); // Reindexa array
            }
            
            // Carrega especialidades se necessário
            foreach ($professionals as &$professional) {
                if (!empty($professional['specialties']) && is_array($professional['specialties'])) {
                    // Aqui você pode carregar detalhes das especialidades se necessário
                    // Por enquanto, mantém apenas os IDs
                    $professional['specialties_details'] = [];
                }
            }
            
            ResponseHelper::sendSuccess([
                'professionals' => $professionals,
                'count' => count($professionals)
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar profissionais',
                'PROFESSIONALS_LIST_ERROR',
                ['action' => 'list_professionals', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém profissional específico
     * GET /v1/professionals/:id
     */
    public function get(string $id): void
    {
        try {
            PermissionHelper::require('view_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_professional', 'professional_id' => $id]);
                return;
            }

            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'get_professional', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Carrega dados do usuário
            $user = $this->userModel->findById($professional['user_id']);
            if ($user) {
                unset($user['password_hash']); // Remove senha
                $professional['user'] = $user;
            }
            
            // Processa specialties (JSON)
            if (!empty($professional['specialties'])) {
                $specialties = json_decode($professional['specialties'], true);
                $professional['specialties'] = is_array($specialties) ? $specialties : [];
            } else {
                $professional['specialties'] = [];
            }
            
            ResponseHelper::sendSuccess($professional);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter profissional',
                'PROFESSIONAL_GET_ERROR',
                ['action' => 'get_professional', 'professional_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Cria um novo profissional
     * POST /v1/professionals
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_professional']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_professional']);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (empty($data['user_id'])) {
                $errors['user_id'] = 'Usuário é obrigatório';
            } else {
                $user = $this->userModel->findById((int)$data['user_id']);
                if (!$user) {
                    $errors['user_id'] = 'Usuário não encontrado';
                } elseif ($user['tenant_id'] != $tenantId) {
                    $errors['user_id'] = 'Usuário não pertence a este tenant';
                } else {
                    // Verifica se já existe profissional para este usuário
                    $existing = $this->professionalModel->findByUserAndTenant($tenantId, (int)$data['user_id']);
                    if ($existing) {
                        $errors['user_id'] = 'Já existe um profissional para este usuário';
                    }
                }
            }
            
            if (empty($data['crmv'])) {
                $errors['crmv'] = 'CRMV é obrigatório';
            }
            
            if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive', 'on_leave'])) {
                $errors['status'] = 'Status inválido. Use: active, inactive ou on_leave';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'create_professional']);
                return;
            }
            
            // ✅ Verifica limite de profissionais do plano
            $limitCheck = $this->planLimitsService->checkProfessionalLimit($tenantId);
            if (!$limitCheck['allowed']) {
                ResponseHelper::sendError(
                    'Limite de profissionais excedido',
                    403,
                    'LIMIT_PROFESSIONALS_EXCEEDED',
                    [
                        'action' => 'create_professional',
                        'limit' => $limitCheck['limit'],
                        'current' => $limitCheck['current'],
                        'remaining' => $limitCheck['remaining']
                    ]
                );
                return;
            }
            
            // Prepara dados para inserção
            $insertData = [
                'tenant_id' => $tenantId,
                'user_id' => (int)$data['user_id'],
                'crmv' => $data['crmv'],
                'status' => $data['status'] ?? 'active',
                'default_consultation_duration' => (int)($data['default_consultation_duration'] ?? 30)
            ];
            
            // ✅ CORREÇÃO: Valida tamanho do array specialties (prevenção de DoS)
            if (!empty($data['specialties']) && is_array($data['specialties'])) {
                $specialtiesErrors = Validator::validateArraySize($data['specialties'], 'specialties', 10);
                if (!empty($specialtiesErrors)) {
                    $errors = array_merge($errors, $specialtiesErrors);
                } else {
                    $insertData['specialties'] = json_encode($data['specialties']);
                }
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
                ResponseHelper::sendValidationError($errors, ['action' => 'create_professional', 'tenant_id' => $tenantId]);
                return;
            }
            
            $professionalId = $this->professionalModel->insert($insertData);
            
            // Busca profissional criado
            $professional = $this->professionalModel->findById($professionalId);
            
            // Carrega dados do usuário
            $user = $this->userModel->findById($professional['user_id']);
            if ($user) {
                unset($user['password_hash']);
                $professional['user'] = $user;
            }
            
            // Processa specialties
            if (!empty($professional['specialties'])) {
                $specialties = json_decode($professional['specialties'], true);
                $professional['specialties'] = is_array($specialties) ? $specialties : [];
            } else {
                $professional['specialties'] = [];
            }
            
            ResponseHelper::sendSuccess($professional, 201, 'Profissional criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar profissional',
                'PROFESSIONAL_CREATE_ERROR',
                ['action' => 'create_professional', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza profissional
     * PUT /v1/professionals/:id
     */
    public function update(string $id): void
    {
        try {
            PermissionHelper::require('update_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_professional', 'professional_id' => $id]);
                return;
            }
            
            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'update_professional', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_professional', 'professional_id' => $id]);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'on_leave'])) {
                $errors['status'] = 'Status inválido. Use: active, inactive ou on_leave';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'update_professional', 'professional_id' => $id]);
                return;
            }
            
            // Prepara dados para atualização
            $updateData = [];
            
            if (isset($data['crmv'])) {
                $updateData['crmv'] = $data['crmv'];
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            if (isset($data['default_consultation_duration'])) {
                $updateData['default_consultation_duration'] = (int)$data['default_consultation_duration'];
            }
            
            // ✅ CORREÇÃO: Valida tamanho do array specialties (prevenção de DoS)
            if (isset($data['specialties']) && is_array($data['specialties'])) {
                $specialtiesErrors = Validator::validateArraySize($data['specialties'], 'specialties', 10);
                if (!empty($specialtiesErrors)) {
                    $errors = array_merge($errors, $specialtiesErrors);
                } else {
                    $updateData['specialties'] = json_encode($data['specialties']);
                }
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
                ResponseHelper::sendValidationError($errors, ['action' => 'update_professional', 'professional_id' => $id]);
                return;
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendError('Nenhum campo para atualizar', 400, 'NO_FIELDS_TO_UPDATE', ['action' => 'update_professional', 'professional_id' => $id]);
                return;
            }
            
            $this->professionalModel->update((int)$id, $updateData);
            
            // Busca profissional atualizado
            $updated = $this->professionalModel->findById((int)$id);
            
            // Carrega dados do usuário
            $user = $this->userModel->findById($updated['user_id']);
            if ($user) {
                unset($user['password_hash']);
                $updated['user'] = $user;
            }
            
            // Processa specialties
            if (!empty($updated['specialties'])) {
                $specialties = json_decode($updated['specialties'], true);
                $updated['specialties'] = is_array($specialties) ? $specialties : [];
            } else {
                $updated['specialties'] = [];
            }
            
            ResponseHelper::sendSuccess($updated, 200, 'Profissional atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar profissional',
                'PROFESSIONAL_UPDATE_ERROR',
                ['action' => 'update_professional', 'professional_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Deleta profissional (soft delete)
     * DELETE /v1/professionals/:id
     */
    public function delete(string $id): void
    {
        try {
            PermissionHelper::require('delete_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_professional', 'professional_id' => $id]);
                return;
            }
            
            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'delete_professional', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Soft delete
            $this->professionalModel->delete((int)$id);
            
            ResponseHelper::sendSuccess(null, 200, 'Profissional deletado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao deletar profissional',
                'PROFESSIONAL_DELETE_ERROR',
                ['action' => 'delete_professional', 'professional_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém agenda do profissional
     * GET /v1/professionals/:id/schedule
     */
    public function schedule(string $id): void
    {
        try {
            // Log para debug
            \App\Services\Logger::debug('ProfessionalController::schedule chamado', [
                'professional_id' => $id,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
            ]);
            
            PermissionHelper::require('view_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_professional_schedule', 'professional_id' => $id]);
                return;
            }
            
            // Verifica se profissional existe e pertence ao tenant
            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'get_professional_schedule', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Busca agenda do profissional
            $schedule = $this->scheduleModel->findByProfessional($tenantId, (int)$id);
            
            // Busca bloqueios futuros de agenda
            $blocks = $this->blockModel->findFutureBlocks($tenantId, (int)$id);
            
            ResponseHelper::sendSuccess([
                'schedule' => $schedule,
                'blocks' => $blocks
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter agenda do profissional',
                'PROFESSIONAL_SCHEDULE_ERROR',
                ['action' => 'get_professional_schedule', 'professional_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza agenda do profissional
     * PUT /v1/professionals/:id/schedule
     */
    public function updateSchedule(string $id): void
    {
        try {
            PermissionHelper::require('update_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_professional_schedule', 'professional_id' => $id]);
                return;
            }
            
            // Verifica se profissional existe
            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'update_professional_schedule', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_professional_schedule', 'professional_id' => $id]);
                return;
            }
            
            // Validações
            if (empty($data['schedule']) || !is_array($data['schedule'])) {
                ResponseHelper::sendError(400, 'Dados inválidos', 'Campo schedule é obrigatório e deve ser um array', 'INVALID_DATA', [], ['action' => 'update_professional_schedule', 'professional_id' => $id]);
                return;
            }
            
            // ✅ CORREÇÃO: Valida tamanho do array schedule (máximo 7 dias da semana)
            $scheduleErrors = Validator::validateArraySize($data['schedule'], 'schedule', 7);
            if (!empty($scheduleErrors)) {
                ResponseHelper::sendValidationError($scheduleErrors, ['action' => 'update_professional_schedule', 'professional_id' => $id]);
                return;
            }
            
            $savedSchedules = [];
            
            // Processa cada dia da semana
            foreach ($data['schedule'] as $daySchedule) {
                if (!isset($daySchedule['day_of_week']) || !isset($daySchedule['start_time']) || !isset($daySchedule['end_time'])) {
                    continue; // Pula dias inválidos
                }
                
                $dayOfWeek = (int)$daySchedule['day_of_week'];
                if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                    continue; // Dia inválido
                }
                
                $startTime = $daySchedule['start_time'];
                $endTime = $daySchedule['end_time'];
                $isActive = isset($daySchedule['is_active']) ? (bool)$daySchedule['is_active'] : true;
                
                // Valida formato de hora
                $timePattern = '/^([0-1][0-9]|2[0-3]):[0-5][0-9](:00)?$/';
                if (!preg_match($timePattern, $startTime) || !preg_match($timePattern, $endTime)) {
                    continue; // Hora inválida
                }
                
                // Normaliza formato de hora (adiciona :00 se necessário)
                if (strlen($startTime) === 5) {
                    $startTime .= ':00';
                }
                if (strlen($endTime) === 5) {
                    $endTime .= ':00';
                }
                
                $scheduleId = $this->scheduleModel->saveSchedule(
                    $tenantId,
                    (int)$id,
                    $dayOfWeek,
                    $startTime,
                    $endTime,
                    $isActive
                );
                
                $savedSchedules[] = [
                    'id' => $scheduleId,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_available' => $isActive
                ];
            }
            
            // Busca agenda atualizada
            $updatedSchedule = $this->scheduleModel->findByProfessional($tenantId, (int)$id);
            
            ResponseHelper::sendSuccess([
                'schedule' => $updatedSchedule,
                'saved' => $savedSchedules
            ], 200, 'Agenda atualizada com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar agenda do profissional',
                'PROFESSIONAL_SCHEDULE_UPDATE_ERROR',
                ['action' => 'update_professional_schedule', 'professional_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Cria bloqueio de agenda
     * POST /v1/professionals/:id/schedule/blocks
     */
    public function createBlock(string $id): void
    {
        try {
            PermissionHelper::require('update_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_schedule_block', 'professional_id' => $id]);
                return;
            }
            
            // Verifica se profissional existe
            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'create_schedule_block', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_schedule_block', 'professional_id' => $id]);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (empty($data['start_datetime'])) {
                $errors['start_datetime'] = 'Data/hora de início é obrigatória';
            } else {
                $startDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $data['start_datetime']);
                if (!$startDateTime) {
                    $startDateTime = \DateTime::createFromFormat('Y-m-d H:i', $data['start_datetime']);
                }
                if (!$startDateTime) {
                    $errors['start_datetime'] = 'Formato inválido. Use YYYY-MM-DD HH:MM ou YYYY-MM-DD HH:MM:SS';
                }
            }
            
            if (empty($data['end_datetime'])) {
                $errors['end_datetime'] = 'Data/hora de fim é obrigatória';
            } else {
                $endDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $data['end_datetime']);
                if (!$endDateTime) {
                    $endDateTime = \DateTime::createFromFormat('Y-m-d H:i', $data['end_datetime']);
                }
                if (!$endDateTime) {
                    $errors['end_datetime'] = 'Formato inválido. Use YYYY-MM-DD HH:MM ou YYYY-MM-DD HH:MM:SS';
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError($errors, ['action' => 'create_schedule_block', 'professional_id' => $id]);
                return;
            }
            
            // Valida que end_datetime é depois de start_datetime
            if (isset($startDateTime) && isset($endDateTime) && $endDateTime <= $startDateTime) {
                ResponseHelper::sendError(400, 'Data inválida', 'Data/hora de fim deve ser posterior à data/hora de início', 'INVALID_DATETIME', [], ['action' => 'create_schedule_block', 'professional_id' => $id]);
                return;
            }
            
            // Normaliza formato
            $startDatetime = $startDateTime->format('Y-m-d H:i:s');
            $endDatetime = $endDateTime->format('Y-m-d H:i:s');
            
            // Insere bloqueio
            $blockId = $this->blockModel->insert([
                'tenant_id' => $tenantId,
                'professional_id' => (int)$id,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'reason' => $data['reason'] ?? null
            ]);
            
            // Busca bloqueio criado
            $block = $this->blockModel->findById($blockId);
            
            ResponseHelper::sendSuccess($block, 201, 'Bloqueio de agenda criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar bloqueio de agenda',
                'SCHEDULE_BLOCK_CREATE_ERROR',
                ['action' => 'create_schedule_block', 'professional_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Remove bloqueio de agenda
     * DELETE /v1/professionals/:id/schedule/blocks/:block_id
     */
    public function deleteBlock(string $id, string $blockId): void
    {
        try {
            PermissionHelper::require('update_professionals');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_schedule_block', 'professional_id' => $id, 'block_id' => $blockId]);
                return;
            }
            
            // Verifica se profissional existe
            $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$id);
            if (!$professional) {
                ResponseHelper::sendNotFoundError('Profissional', ['action' => 'delete_schedule_block', 'professional_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se bloqueio existe e pertence ao profissional
            $block = $this->blockModel->findById((int)$blockId);
            if (!$block) {
                ResponseHelper::sendNotFoundError('Bloqueio de agenda', ['action' => 'delete_schedule_block', 'block_id' => $blockId]);
                return;
            }
            
            if ($block['tenant_id'] != $tenantId || $block['professional_id'] != (int)$id) {
                ResponseHelper::sendError(403, 'Acesso negado', 'Bloqueio não pertence a este profissional', 'FORBIDDEN', [], ['action' => 'delete_schedule_block', 'block_id' => $blockId]);
                return;
            }
            
            // Remove bloqueio
            $this->blockModel->delete((int)$blockId);
            
            ResponseHelper::sendSuccess(null, 200, 'Bloqueio de agenda removido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao remover bloqueio de agenda',
                'SCHEDULE_BLOCK_DELETE_ERROR',
                ['action' => 'delete_schedule_block', 'professional_id' => $id, 'block_id' => $blockId, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


<?php

namespace App\Controllers;

use App\Models\Professional;
use App\Models\User;
use App\Services\PlanLimitsService;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
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
    private PlanLimitsService $planLimitsService;

    public function __construct()
    {
        $this->professionalModel = new Professional();
        $this->userModel = new User();
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
            
            // Processa specialties (JSON)
            if (!empty($data['specialties']) && is_array($data['specialties'])) {
                $insertData['specialties'] = json_encode($data['specialties']);
            }
            
            // Processa metadata (JSON)
            if (!empty($data['metadata']) && is_array($data['metadata'])) {
                $insertData['metadata'] = json_encode($data['metadata']);
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
            
            // Processa specialties (JSON)
            if (isset($data['specialties']) && is_array($data['specialties'])) {
                $updateData['specialties'] = json_encode($data['specialties']);
            }
            
            // Processa metadata (JSON)
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $updateData['metadata'] = json_encode($data['metadata']);
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
            
            PermissionHelper::require('view_schedules');
            
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
            $db = \App\Utils\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT * FROM professional_schedules 
                 WHERE tenant_id = :tenant_id 
                 AND professional_id = :professional_id 
                 ORDER BY day_of_week ASC, start_time ASC"
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'professional_id' => (int)$id
            ]);
            $schedule = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Busca bloqueios de agenda
            $blocksStmt = $db->prepare(
                "SELECT * FROM schedule_blocks 
                 WHERE tenant_id = :tenant_id 
                 AND professional_id = :professional_id 
                 AND end_datetime >= NOW()
                 ORDER BY start_datetime ASC"
            );
            $blocksStmt->execute([
                'tenant_id' => $tenantId,
                'professional_id' => (int)$id
            ]);
            $blocks = $blocksStmt->fetchAll(\PDO::FETCH_ASSOC);
            
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
}


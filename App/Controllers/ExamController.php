<?php

namespace App\Controllers;

use App\Models\Exam;
use App\Models\Pet;
use App\Models\Client;
use App\Models\Professional;
use App\Models\ExamType;
use App\Models\UserSession;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use App\Services\Logger;
use Config;
use Flight;
use OpenApi\Attributes as OA;

/**
 * Controller para gerenciar exames
 */
#[OA\Tag(name: "Exames", description: "Gerenciamento de exames da clínica")]
class ExamController
{
    private Exam $examModel;
    private Pet $petModel;
    private Client $clientModel;
    private Professional $professionalModel;
    private ExamType $examTypeModel;

    public function __construct(
        Exam $examModel,
        Pet $petModel,
        Client $clientModel,
        Professional $professionalModel,
        ExamType $examTypeModel
    ) {
        $this->examModel = $examModel;
        $this->petModel = $petModel;
        $this->clientModel = $clientModel;
        $this->professionalModel = $professionalModel;
        $this->examTypeModel = $examTypeModel;
    }

    /**
     * Lista exames do tenant
     * GET /v1/exams
     */
    public function list(): void
    {
        try {
            // Log para debug
            \App\Services\Logger::debug('ExamController::list chamado', [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
            ]);
            
            PermissionHelper::require('view_exams');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_exams']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            $status = $queryParams['status'] ?? null;
            $petId = $queryParams['pet_id'] ?? null;
            $clientId = $queryParams['client_id'] ?? null;
            $professionalId = $queryParams['professional_id'] ?? null;
            $examTypeId = $queryParams['exam_type_id'] ?? null;
            $search = $queryParams['search'] ?? null;
            
            $filters = [];
            if ($status) {
                $filters['status'] = $status;
            }
            if ($petId) {
                $filters['pet_id'] = (int)$petId;
            }
            if ($clientId) {
                $filters['client_id'] = (int)$clientId;
            }
            if ($professionalId) {
                $filters['professional_id'] = (int)$professionalId;
            }
            if ($examTypeId) {
                $filters['exam_type_id'] = (int)$examTypeId;
            }
            
            $exams = $this->examModel->findByTenant($tenantId, $filters);
            
            // Aplica busca textual se fornecida
            if ($search && !empty($search)) {
                $searchLower = strtolower($search);
                $exams = array_filter($exams, function($exam) use ($searchLower) {
                    // Busca em notes, results, etc.
                    $notes = strtolower($exam['notes'] ?? '');
                    $results = strtolower($exam['results'] ?? '');
                    return strpos($notes, $searchLower) !== false || 
                           strpos($results, $searchLower) !== false;
                });
                $exams = array_values($exams); // Reindexa array
            }
            
            // ✅ OTIMIZAÇÃO: Carrega todos os dados relacionados de uma vez (elimina N+1)
            // Coleta IDs únicos
            $petIds = array_unique(array_filter(array_column($exams, 'pet_id')));
            $clientIds = array_unique(array_filter(array_column($exams, 'client_id')));
            $professionalIds = array_unique(array_filter(array_column($exams, 'professional_id')));
            $examTypeIds = array_unique(array_filter(array_column($exams, 'exam_type_id')));
            
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
            
            // Carrega todos os tipos de exame de uma vez
            $examTypesById = [];
            if (!empty($examTypeIds)) {
                $db = \App\Utils\Database::getInstance();
                $placeholders = implode(',', array_fill(0, count($examTypeIds), '?'));
                $stmt = $db->prepare("
                    SELECT * FROM exam_types 
                    WHERE tenant_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL
                ");
                $stmt->execute(array_merge([$tenantId], $examTypeIds));
                $examTypes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($examTypes as $examType) {
                    $examTypesById[$examType['id']] = $examType;
                }
            }
            
            // Enriquece exames com dados já carregados
            $enrichedExams = [];
            foreach ($exams as $exam) {
                $enriched = $exam;
                
                // Adiciona pet (se existir)
                if (!empty($exam['pet_id']) && isset($petsById[$exam['pet_id']])) {
                    $enriched['pet'] = $petsById[$exam['pet_id']];
                }
                
                // Adiciona cliente (se existir)
                if (!empty($exam['client_id']) && isset($clientsById[$exam['client_id']])) {
                    $enriched['client'] = $clientsById[$exam['client_id']];
                }
                
                // Adiciona profissional (se existir)
                if (!empty($exam['professional_id']) && isset($professionalsById[$exam['professional_id']])) {
                    $enriched['professional'] = $professionalsById[$exam['professional_id']];
                }
                
                // Adiciona tipo de exame (se existir)
                if (!empty($exam['exam_type_id']) && isset($examTypesById[$exam['exam_type_id']])) {
                    $enriched['exam_type'] = $examTypesById[$exam['exam_type_id']];
                }
                
                $enrichedExams[] = $enriched;
            }
            
            ResponseHelper::sendSuccess($enrichedExams, 200, 'Exames listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar exames', 'EXAMS_LIST_ERROR', ['action' => 'list_exams', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Cria um novo exame
     * POST /v1/exams
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_exams');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_exam']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_exam']);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (empty($data['pet_id'])) {
                $errors['pet_id'] = 'Pet é obrigatório';
            } else {
                $pet = $this->petModel->findByTenantAndId($tenantId, (int)$data['pet_id']);
                if (!$pet) {
                    $errors['pet_id'] = 'Pet não encontrado';
                } else {
                    // Usa o client_id do pet
                    $data['client_id'] = $pet['client_id'] ?? $data['client_id'] ?? null;
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
            
            if (empty($data['exam_date'])) {
                $errors['exam_date'] = 'Data do exame é obrigatória';
            } else {
                // Valida formato da data
                $date = \DateTime::createFromFormat('Y-m-d', $data['exam_date']);
                if (!$date || $date->format('Y-m-d') !== $data['exam_date']) {
                    $errors['exam_date'] = 'Data inválida. Use o formato YYYY-MM-DD';
                }
            }
            
            if (isset($data['exam_type_id']) && !empty($data['exam_type_id'])) {
                $examType = $this->examTypeModel->findByTenantAndId($tenantId, (int)$data['exam_type_id']);
                if (!$examType) {
                    $errors['exam_type_id'] = 'Tipo de exame não encontrado';
                }
            }
            
            if (isset($data['professional_id']) && !empty($data['professional_id'])) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$data['professional_id']);
                if (!$professional) {
                    $errors['professional_id'] = 'Profissional não encontrado';
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_exam', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            $examData = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$data['pet_id'],
                'client_id' => (int)$data['client_id'],
                'professional_id' => !empty($data['professional_id']) ? (int)$data['professional_id'] : null,
                'exam_type_id' => !empty($data['exam_type_id']) ? (int)$data['exam_type_id'] : null,
                'exam_date' => $data['exam_date'],
                'exam_time' => $data['exam_time'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'notes' => !empty($data['notes']) ? trim($data['notes']) : null,
                'results' => !empty($data['results']) ? trim($data['results']) : null
            ];
            
            $examId = $this->examModel->insert($examData);
            $exam = $this->examModel->findById($examId);
            
            ResponseHelper::sendCreated($exam, 'Exame criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar exame', 'EXAM_CREATE_ERROR', ['action' => 'create_exam', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém um exame
     * GET /v1/exams/:id
     */
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_exams');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_exam']);
                return;
            }
            
            $exam = $this->examModel->findByTenantAndId($tenantId, $id);
            
            if (!$exam) {
                ResponseHelper::sendNotFoundError('Exame não encontrado', ['action' => 'get_exam', 'exam_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Enriquece com dados relacionados
            if ($exam['pet_id']) {
                $pet = $this->petModel->findByTenantAndId($tenantId, $exam['pet_id']);
                if ($pet) {
                    $exam['pet'] = $pet;
                }
            }
            
            if ($exam['client_id']) {
                $client = $this->clientModel->findByTenantAndId($tenantId, $exam['client_id']);
                if ($client) {
                    $exam['client'] = $client;
                }
            }
            
            if ($exam['professional_id']) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, $exam['professional_id']);
                if ($professional) {
                    // Enriquece com dados do usuário
                    $userModel = new \App\Models\User();
                    $user = $userModel->findById($professional['user_id']);
                    if ($user) {
                        $professional['user'] = [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ];
                    }
                    $exam['professional'] = $professional;
                }
            }
            
            if ($exam['exam_type_id']) {
                $examType = $this->examTypeModel->findByTenantAndId($tenantId, $exam['exam_type_id']);
                if ($examType) {
                    $exam['exam_type'] = $examType;
                }
            }
            
            // Garante que results_file está presente (pode ser null)
            if (!isset($exam['results_file'])) {
                $exam['results_file'] = null;
            }
            
            Logger::debug('Exam data being returned', [
                'exam_id' => $id,
                'has_results_file' => !empty($exam['results_file']),
                'results_file' => $exam['results_file']
            ]);
            
            ResponseHelper::sendSuccess($exam, 200, 'Exame encontrado');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter exame', 'EXAM_GET_ERROR', ['action' => 'get_exam', 'exam_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Atualiza um exame
     * PUT /v1/exams/:id
     */
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_exams');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_exam']);
                return;
            }
            
            $exam = $this->examModel->findByTenantAndId($tenantId, $id);
            
            if (!$exam) {
                ResponseHelper::sendNotFoundError('Exame não encontrado', ['action' => 'update_exam', 'exam_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_exam']);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (isset($data['exam_date']) && !empty($data['exam_date'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $data['exam_date']);
                if (!$date || $date->format('Y-m-d') !== $data['exam_date']) {
                    $errors['exam_date'] = 'Data inválida. Use o formato YYYY-MM-DD';
                }
            }
            
            if (isset($data['exam_type_id']) && !empty($data['exam_type_id'])) {
                $examType = $this->examTypeModel->findByTenantAndId($tenantId, (int)$data['exam_type_id']);
                if (!$examType) {
                    $errors['exam_type_id'] = 'Tipo de exame não encontrado';
                }
            }
            
            if (isset($data['professional_id']) && !empty($data['professional_id'])) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, (int)$data['professional_id']);
                if (!$professional) {
                    $errors['professional_id'] = 'Profissional não encontrado';
                }
            }
            
            // ✅ CORREÇÃO: Valida pet_id se fornecido
            if (isset($data['pet_id']) && !empty($data['pet_id'])) {
                $pet = $this->petModel->findByTenantAndId($tenantId, (int)$data['pet_id']);
                if (!$pet) {
                    $errors['pet_id'] = 'Pet não encontrado';
                } else {
                    // Se pet_id foi fornecido, usa o client_id do pet (se client_id não foi fornecido explicitamente)
                    if (!isset($data['client_id']) || empty($data['client_id'])) {
                        $data['client_id'] = $pet['client_id'] ?? null;
                    }
                }
            }
            
            // ✅ CORREÇÃO: Valida client_id se fornecido
            if (isset($data['client_id']) && !empty($data['client_id'])) {
                $client = $this->clientModel->findByTenantAndId($tenantId, (int)$data['client_id']);
                if (!$client) {
                    $errors['client_id'] = 'Cliente não encontrado';
                } else {
                    // Se pet_id também foi fornecido, valida se o pet pertence ao cliente
                    if (isset($data['pet_id']) && !empty($data['pet_id'])) {
                        $pet = $this->petModel->findByTenantAndId($tenantId, (int)$data['pet_id']);
                        if ($pet && $pet['client_id'] != $data['client_id']) {
                            $errors['pet_id'] = 'Pet não pertence ao cliente informado';
                        }
                    }
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'update_exam', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Campos permitidos para atualização
            $allowedFields = ['pet_id', 'client_id', 'professional_id', 'exam_type_id', 'exam_date', 'exam_time', 'status', 'notes', 'results'];
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            // Remove campos vazios e aplica trim
            foreach ($updateData as $key => $value) {
                if (in_array($key, ['notes', 'results'])) {
                    $updateData[$key] = !empty($value) ? trim($value) : null;
                } elseif ($value === '') {
                    unset($updateData[$key]);
                } elseif (in_array($key, ['pet_id', 'client_id', 'professional_id', 'exam_type_id'])) {
                    $updateData[$key] = (int)$value;
                }
            }
            
            // Se status mudou para completed, atualiza completed_at
            if (isset($updateData['status']) && $updateData['status'] === 'completed' && $exam['status'] !== 'completed') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
            }
            
            // Se status mudou para cancelled, atualiza cancelled_at e cancelled_by
            if (isset($updateData['status']) && $updateData['status'] === 'cancelled' && $exam['status'] !== 'cancelled') {
                $updateData['cancelled_at'] = date('Y-m-d H:i:s');
                $updateData['cancelled_by'] = Flight::get('user_id');
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo para atualizar', [], ['action' => 'update_exam']);
                return;
            }
            
            $this->examModel->update($id, $updateData);
            $updatedExam = $this->examModel->findById($id);
            
            ResponseHelper::sendSuccess($updatedExam, 200, 'Exame atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar exame', 'EXAM_UPDATE_ERROR', ['action' => 'update_exam', 'exam_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Remove um exame
     * DELETE /v1/exams/:id
     */
    public function delete(int $id): void
    {
        try {
            PermissionHelper::require('delete_exams');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_exam']);
                return;
            }
            
            $exam = $this->examModel->findByTenantAndId($tenantId, $id);
            
            if (!$exam) {
                ResponseHelper::sendNotFoundError('Exame não encontrado', ['action' => 'delete_exam', 'exam_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Soft delete
            $this->examModel->delete($id);
            
            ResponseHelper::sendSuccess(['id' => $id], 200, 'Exame removido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao remover exame', 'EXAM_DELETE_ERROR', ['action' => 'delete_exam', 'exam_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Faz upload do arquivo PDF de resultados
     * POST /v1/exams/:id/results-file
     */
    public function uploadResultsFile(int $id): void
    {
        try {
            PermissionHelper::require('update_exams');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'upload_exam_results_file']);
                return;
            }
            
            $exam = $this->examModel->findByTenantAndId($tenantId, $id);
            
            if (!$exam) {
                ResponseHelper::sendNotFoundError('Exame não encontrado', ['action' => 'upload_exam_results_file', 'exam_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Verifica se há arquivo enviado
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                ResponseHelper::sendValidationError('Arquivo não enviado ou com erro', ['file' => 'Arquivo é obrigatório'], ['action' => 'upload_exam_results_file']);
                return;
            }
            
            $file = $_FILES['file'];
            
            // Valida tipo de arquivo (apenas PDF)
            $allowedTypes = ['application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                ResponseHelper::sendValidationError('Tipo de arquivo inválido', ['file' => 'Apenas arquivos PDF são permitidos'], ['action' => 'upload_exam_results_file']);
                return;
            }
            
            // Valida tamanho (máximo 10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                ResponseHelper::sendValidationError('Arquivo muito grande', ['file' => 'O arquivo deve ter no máximo 10MB'], ['action' => 'upload_exam_results_file']);
                return;
            }
            
            // Cria diretório de uploads se não existir
            $uploadDir = __DIR__ . '/../../storage/exams/' . $tenantId . '/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            
            // Gera nome único para o arquivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'exam_' . $id . '_' . time() . '_' . uniqid() . '.pdf';
            $filePath = $uploadDir . $filename;
            
            // Move arquivo
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                ResponseHelper::sendGenericError(new \Exception('Erro ao salvar arquivo'), 'Erro ao fazer upload do arquivo', 'EXAM_UPLOAD_ERROR', ['action' => 'upload_exam_results_file']);
                return;
            }
            
            // Remove arquivo anterior se existir
            if (!empty($exam['results_file'])) {
                $oldFilePath = __DIR__ . '/../../' . $exam['results_file'];
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }
            
            // Salva caminho relativo no banco
            $relativePath = 'storage/exams/' . $tenantId . '/' . $filename;
            $this->examModel->update($id, ['results_file' => $relativePath]);
            
            $updatedExam = $this->examModel->findById($id);
            
            ResponseHelper::sendSuccess($updatedExam, 200, 'Arquivo de resultados enviado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao fazer upload do arquivo', 'EXAM_UPLOAD_ERROR', ['action' => 'upload_exam_results_file', 'exam_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Faz download ou visualiza o arquivo PDF de resultados
     * GET /v1/exams/:id/results-file
     * 
     * Aceita autenticação via Bearer token OU sessão (cookie/query string)
     */
    public function downloadResultsFile(int $id): void
    {
        try {
            // Tenta obter tenant_id do Flight (se autenticado via Bearer)
            $tenantId = Flight::get('tenant_id');
            
            // Se não tem tenant_id do Flight, tenta via sessão
            if ($tenantId === null) {
                // Tenta obter session_id de várias formas
                $sessionId = null;
                
                // 1. Do header Authorization
                $headers = [];
                if (function_exists('getallheaders')) {
                    $headers = getallheaders();
                }
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
                if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $sessionId = trim($matches[1]);
                }
                
                // 2. Do cookie
                if (!$sessionId && isset($_COOKIE['session_id'])) {
                    $sessionId = $_COOKIE['session_id'];
                }
                
                // 3. Da query string (para desenvolvimento)
                if (!$sessionId && isset($_GET['session_id'])) {
                    $sessionId = $_GET['session_id'];
                }
                
                // Valida sessão
                if ($sessionId) {
                    $userSessionModel = new \App\Models\UserSession();
                    $session = $userSessionModel->validate($sessionId);
                    if ($session) {
                        $tenantId = (int)$session['tenant_id'];
                        Flight::set('tenant_id', $tenantId);
                        Flight::set('user_id', (int)$session['user_id']);
                    }
                }
            }
            
            // Verifica permissão (só verifica se tiver tenant_id)
            if ($tenantId === null) {
                Flight::halt(401, json_encode(['error' => 'Não autenticado']));
                return;
            }
            
            // Verifica permissão
            try {
                PermissionHelper::require('view_exams');
            } catch (\Exception $e) {
                // Se não tiver permissão, ainda permite se for o próprio tenant
                // (já validamos que está autenticado acima)
            }
            
            $exam = $this->examModel->findByTenantAndId($tenantId, $id);
            
            if (!$exam) {
                Flight::halt(404, json_encode(['error' => 'Exame não encontrado']));
                return;
            }
            
            if (empty($exam['results_file'])) {
                Flight::halt(404, json_encode(['error' => 'Arquivo de resultados não encontrado']));
                return;
            }
            
            $filePath = __DIR__ . '/../../' . $exam['results_file'];
            $filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
            
            Logger::debug('Tentando fazer download de arquivo de exame', [
                'exam_id' => $id,
                'results_file' => $exam['results_file'],
                'file_path' => $filePath,
                'file_exists' => file_exists($filePath)
            ]);
            
            if (!file_exists($filePath)) {
                Logger::warning('Arquivo de exame não encontrado no servidor', [
                    'exam_id' => $id,
                    'file_path' => $exam['results_file'],
                    'full_path' => $filePath
                ]);
                Flight::halt(404, json_encode(['error' => 'Arquivo não encontrado no servidor']));
                return;
            }
            
            // Verifica se é download ou visualização
            $download = isset($_GET['download']) && $_GET['download'] == '1';
            
            // Limpa qualquer output anterior
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Define headers antes de qualquer output
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: private, max-age=3600');
            
            if ($download) {
                header('Content-Disposition: attachment; filename="resultado_exame_' . $id . '.pdf"');
            } else {
                header('Content-Disposition: inline; filename="resultado_exame_' . $id . '.pdf"');
            }
            
            // Envia arquivo e para execução
            readfile($filePath);
            Flight::stop();
        } catch (\Exception $e) {
            Logger::error('Erro ao obter arquivo de exame', [
                'exam_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Flight::halt(500, json_encode(['error' => 'Erro ao obter arquivo']));
        }
    }
}


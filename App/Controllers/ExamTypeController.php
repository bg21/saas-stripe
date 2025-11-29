<?php

namespace App\Controllers;

use App\Models\ExamType;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use App\Services\Logger;
use Config;
use Flight;
use OpenApi\Attributes as OA;

/**
 * Controller para gerenciar tipos de exames
 */
#[OA\Tag(name: "Tipos de Exames", description: "Gerenciamento de tipos de exames da clínica")]
class ExamTypeController
{
    private ExamType $examTypeModel;

    public function __construct(ExamType $examTypeModel)
    {
        $this->examTypeModel = $examTypeModel;
    }

    /**
     * Lista tipos de exames do tenant
     * GET /v1/exam-types
     */
    public function list(): void
    {
        try {
            // Log para debug
            \App\Services\Logger::debug('ExamTypeController::list chamado', [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'query_params' => Flight::request()->query->getData()
            ]);
            
            PermissionHelper::require('view_exam_types');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_exam_types']);
                return;
            }
            
            // Debug: log do tenant_id (apenas em desenvolvimento)
            if (Config::isDevelopment()) {
                Logger::debug("Listando tipos de exames", [
                    'tenant_id' => $tenantId,
                    'user_id' => Flight::get('user_id'),
                    'user_email' => Flight::get('user_email')
                ]);
            }
            
            $queryParams = Flight::request()->query;
            $status = $queryParams['status'] ?? null;
            $category = $queryParams['category'] ?? null;
            
            $filters = [];
            if ($status) {
                $filters['status'] = $status;
            }
            if ($category) {
                $filters['category'] = $category;
            }
            
            $examTypes = $this->examTypeModel->findByTenant($tenantId, $filters);
            
            // Debug: log do resultado (apenas em desenvolvimento)
            if (Config::isDevelopment()) {
                Logger::debug("Tipos de exames encontrados", [
                    'tenant_id' => $tenantId,
                    'count' => count($examTypes)
                ]);
            }
            
            ResponseHelper::sendSuccess($examTypes, 200, 'Tipos de exames listados com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar tipos de exames', 'EXAM_TYPES_LIST_ERROR', ['action' => 'list_exam_types', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Cria um novo tipo de exame
     * POST /v1/exam-types
     */
    #[OA\Post(
        path: "/v1/exam-types",
        summary: "Cria um novo tipo de exame",
        tags: ["Tipos de Exames"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                required: ["name", "category"],
                properties: [
                    new OA\Property(property: "name", type: "string", description: "Nome do tipo de exame", example: "Hemograma Completo", maxLength: 255),
                    new OA\Property(property: "category", type: "string", enum: ["blood", "urine", "imaging", "other"], description: "Categoria do exame", example: "blood"),
                    new OA\Property(property: "description", type: "string", nullable: true, description: "Descrição do tipo de exame", example: "Análise completa do sangue", maxLength: 1000),
                    new OA\Property(property: "status", type: "string", nullable: true, enum: ["active", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Tipo de exame criado com sucesso", content: new OA\JsonContent(type: "object")),
            new OA\Response(response: 400, description: "Dados inválidos", content: new OA\JsonContent(type: "object")),
            new OA\Response(response: 401, description: "Não autenticado"),
            new OA\Response(response: 403, description: "Sem permissão")
        ]
    )]
    public function create(): void
    {
        try {
            PermissionHelper::require('create_exam_types');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_exam_type']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'create_exam_type']);
                return;
            }
            
            // Validações básicas
            $errors = [];
            
            if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Nome deve ter pelo menos 2 caracteres';
            }
            
            if (strlen($data['name'] ?? '') > 255) {
                $errors['name'] = 'Nome não pode ter mais de 255 caracteres';
            }
            
            if (empty($data['category']) || !in_array($data['category'], ['blood', 'urine', 'imaging', 'other'])) {
                $errors['category'] = 'Categoria inválida. Use: blood, urine, imaging ou other';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'create_exam_type', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            $examTypeData = [
                'tenant_id' => $tenantId,
                'name' => trim($data['name']),
                'category' => $data['category'],
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                'status' => $data['status'] ?? 'active'
            ];
            
            $examTypeId = $this->examTypeModel->insert($examTypeData);
            $examType = $this->examTypeModel->findById($examTypeId);
            
            ResponseHelper::sendCreated($examType, 'Tipo de exame criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar tipo de exame', 'EXAM_TYPE_CREATE_ERROR', ['action' => 'create_exam_type', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Obtém um tipo de exame
     * GET /v1/exam-types/:id
     */
    #[OA\Get(
        path: "/v1/exam-types/{id}",
        summary: "Obtém um tipo de exame por ID",
        tags: ["Tipos de Exames"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID do tipo de exame", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Tipo de exame encontrado", content: new OA\JsonContent(type: "object")),
            new OA\Response(response: 404, description: "Tipo de exame não encontrado"),
            new OA\Response(response: 401, description: "Não autenticado"),
            new OA\Response(response: 403, description: "Sem permissão")
        ]
    )]
    public function get(int $id): void
    {
        try {
            PermissionHelper::require('view_exam_types');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_exam_type']);
                return;
            }
            
            $examType = $this->examTypeModel->findByTenantAndId($tenantId, $id);
            
            if (!$examType) {
                ResponseHelper::sendNotFoundError('Tipo de exame não encontrado', ['action' => 'get_exam_type', 'exam_type_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            ResponseHelper::sendSuccess($examType, 200, 'Tipo de exame encontrado');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter tipo de exame', 'EXAM_TYPE_GET_ERROR', ['action' => 'get_exam_type', 'exam_type_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Atualiza um tipo de exame
     * PUT /v1/exam-types/:id
     */
    #[OA\Put(
        path: "/v1/exam-types/{id}",
        summary: "Atualiza um tipo de exame",
        tags: ["Tipos de Exames"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID do tipo de exame", schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "name", type: "string", nullable: true, description: "Nome do tipo de exame", maxLength: 255),
                    new OA\Property(property: "category", type: "string", nullable: true, enum: ["blood", "urine", "imaging", "other"], description: "Categoria do exame"),
                    new OA\Property(property: "description", type: "string", nullable: true, description: "Descrição do tipo de exame", maxLength: 1000),
                    new OA\Property(property: "status", type: "string", nullable: true, enum: ["active", "inactive"], description: "Status do tipo de exame")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Tipo de exame atualizado com sucesso", content: new OA\JsonContent(type: "object")),
            new OA\Response(response: 404, description: "Tipo de exame não encontrado"),
            new OA\Response(response: 400, description: "Dados inválidos"),
            new OA\Response(response: 401, description: "Não autenticado"),
            new OA\Response(response: 403, description: "Sem permissão")
        ]
    )]
    public function update(int $id): void
    {
        try {
            PermissionHelper::require('update_exam_types');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_exam_type']);
                return;
            }
            
            $examType = $this->examTypeModel->findByTenantAndId($tenantId, $id);
            
            if (!$examType) {
                ResponseHelper::sendNotFoundError('Tipo de exame não encontrado', ['action' => 'update_exam_type', 'exam_type_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_exam_type']);
                return;
            }
            
            // Validações
            $errors = [];
            
            if (isset($data['name'])) {
                if (empty(trim($data['name'])) || strlen(trim($data['name'])) < 2) {
                    $errors['name'] = 'Nome deve ter pelo menos 2 caracteres';
                } elseif (strlen(trim($data['name'])) > 255) {
                    $errors['name'] = 'Nome não pode ter mais de 255 caracteres';
                }
            }
            
            if (isset($data['category']) && !in_array($data['category'], ['blood', 'urine', 'imaging', 'other'])) {
                $errors['category'] = 'Categoria inválida. Use: blood, urine, imaging ou other';
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'update_exam_type', 'tenant_id' => $tenantId]
                );
                return;
            }
            
            // Campos permitidos para atualização
            $allowedFields = ['name', 'category', 'description', 'status'];
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            // Remove campos vazios e aplica trim
            foreach ($updateData as $key => $value) {
                if ($key === 'description') {
                    $updateData[$key] = !empty($value) ? trim($value) : null;
                } elseif ($key === 'name') {
                    $updateData[$key] = trim($value);
                } elseif ($value === '') {
                    unset($updateData[$key]);
                }
            }
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo para atualizar', [], ['action' => 'update_exam_type']);
                return;
            }
            
            $this->examTypeModel->update($id, $updateData);
            $updatedExamType = $this->examTypeModel->findById($id);
            
            ResponseHelper::sendSuccess($updatedExamType, 200, 'Tipo de exame atualizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar tipo de exame', 'EXAM_TYPE_UPDATE_ERROR', ['action' => 'update_exam_type', 'exam_type_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Remove um tipo de exame
     * DELETE /v1/exam-types/:id
     */
    #[OA\Delete(
        path: "/v1/exam-types/{id}",
        summary: "Remove um tipo de exame",
        tags: ["Tipos de Exames"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID do tipo de exame", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Tipo de exame removido com sucesso", content: new OA\JsonContent(type: "object")),
            new OA\Response(response: 404, description: "Tipo de exame não encontrado"),
            new OA\Response(response: 401, description: "Não autenticado"),
            new OA\Response(response: 403, description: "Sem permissão")
        ]
    )]
    public function delete(int $id): void
    {
        try {
            PermissionHelper::require('delete_exam_types');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'delete_exam_type']);
                return;
            }
            
            $examType = $this->examTypeModel->findByTenantAndId($tenantId, $id);
            
            if (!$examType) {
                ResponseHelper::sendNotFoundError('Tipo de exame não encontrado', ['action' => 'delete_exam_type', 'exam_type_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }
            
            // Soft delete (se o model usar soft deletes)
            $this->examTypeModel->delete($id);
            
            ResponseHelper::sendSuccess(['id' => $id], 200, 'Tipo de exame removido com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao remover tipo de exame', 'EXAM_TYPE_DELETE_ERROR', ['action' => 'delete_exam_type', 'exam_type_id' => $id, 'tenant_id' => Flight::get('tenant_id')]);
        }
    }
}


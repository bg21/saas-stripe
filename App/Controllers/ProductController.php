<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
use Flight;
use Config;

/**
 * Controller para gerenciar produtos do Stripe
 */
class ProductController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Cria um novo produto
     * POST /v1/products
     * 
     * Body:
     *   - name (obrigatório): Nome do produto
     *   - description (opcional): Descrição do produto
     *   - active (opcional): Se o produto está ativo (padrão: true)
     *   - images (opcional): Array de URLs de imagens
     *   - metadata (opcional): Metadados
     *   - statement_descriptor (opcional): Descrição que aparece na fatura
     *   - unit_label (opcional): Rótulo da unidade (ex: "seat", "user")
     */
    public function create(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['tenant_id' => null]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            // Validações obrigatórias
            if (empty($data['name'])) {
                ResponseHelper::sendValidationError(
                    'O campo name é obrigatório',
                    ['name' => 'Campo name é obrigatório'],
                    ['tenant_id' => $tenantId, 'data' => array_keys($data)]
                );
                return;
            }

            // ✅ SEGURANÇA: Valida tamanho máximo de images (prevenção de DoS)
            if (isset($data['images']) && is_array($data['images'])) {
                $imagesErrors = \App\Utils\Validator::validateArraySize($data['images'], 'images', 20);
                if (!empty($imagesErrors)) {
                    ResponseHelper::sendValidationError(
                        'Dados inválidos',
                        $imagesErrors,
                        ['tenant_id' => $tenantId, 'field' => 'images']
                    );
                    return;
                }
            }
            
            // ✅ SEGURANÇA: Valida metadata se fornecido (prevenção de DoS)
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $metadataErrors = \App\Utils\Validator::validateMetadata($data['metadata'], 'metadata', 50);
                if (!empty($metadataErrors)) {
                    ResponseHelper::sendValidationError(
                        'Dados inválidos',
                        $metadataErrors,
                        ['tenant_id' => $tenantId, 'field' => 'metadata']
                    );
                    return;
                }
            }
            
            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $product = $this->stripeService->createProduct($data);

            ResponseHelper::sendCreated([
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? null,
                'active' => $product->active,
                'images' => $product->images ?? [],
                'statement_descriptor' => $product->statement_descriptor ?? null,
                'unit_label' => $product->unit_label ?? null,
                'created' => date('Y-m-d H:i:s', $product->created),
                'updated' => date('Y-m-d H:i:s', $product->updated ?? $product->created),
                'metadata' => $product->metadata->toArray()
            ]);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::sendValidationError(
                'Erro de validação ao criar produto',
                ['validation' => $e->getMessage()],
                ['tenant_id' => $tenantId ?? null, 'exception' => get_class($e)]
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao criar produto no Stripe',
                ['tenant_id' => $tenantId ?? null, 'action' => 'create_product']
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao criar produto',
                'PRODUCT_CREATE_ERROR',
                ['tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Lista produtos disponíveis
     * GET /v1/products
     * 
     * Query params opcionais:
     *   - limit: Número máximo de resultados (padrão: 10)
     *   - starting_after: ID do produto para paginação
     *   - ending_before: ID do produto para paginação reversa
     *   - active: true/false para filtrar apenas produtos ativos/inativos
     */
    public function list(): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_products']);
                return;
            }

            $queryParams = Flight::request()->query;
            
            $options = [];
            
            // Define limite padrão de 50 se não fornecido (melhora performance)
            if (isset($queryParams['limit'])) {
                $options['limit'] = min((int)$queryParams['limit'], 100); // Máximo 100
            } else {
                $options['limit'] = 50; // Padrão: 50 itens
            }
            
            if (!empty($queryParams['starting_after'])) {
                $options['starting_after'] = $queryParams['starting_after'];
            }
            
            if (!empty($queryParams['ending_before'])) {
                $options['ending_before'] = $queryParams['ending_before'];
            }
            
            if (isset($queryParams['active'])) {
                $options['active'] = filter_var($queryParams['active'], FILTER_VALIDATE_BOOLEAN);
            }
            
            // Opção para ignorar filtro de tenant (útil para formulários de criação)
            $ignoreTenantFilter = isset($queryParams['all_tenants']) && filter_var($queryParams['all_tenants'], FILTER_VALIDATE_BOOLEAN);
            
            // ✅ CACHE: Gera chave única baseada em parâmetros
            $cacheKey = sprintf(
                'products:list:%d:%d:%s:%s:%s:%s',
                $tenantId,
                $options['limit'] ?? 50,
                md5($options['starting_after'] ?? ''),
                md5($options['ending_before'] ?? ''),
                ($options['active'] ?? '') === true ? '1' : (($options['active'] ?? '') === false ? '0' : ''),
                $ignoreTenantFilter ? '1' : '0'
            );
            
            // ✅ Tenta obter do cache (TTL: 60 segundos)
            $cached = \App\Services\CacheService::getJson($cacheKey);
            if ($cached !== null) {
                Flight::json($cached);
                return;
            }
            
            $products = $this->stripeService->listProducts($options);
            
            // Formata resposta e filtra por tenant_id (via metadata)
            // Otimização: Filtra apenas produtos do tenant atual (exceto se all_tenants=true)
            $formattedProducts = [];
            foreach ($products->data as $product) {
                // Se all_tenants=true, não filtra por tenant
                if (!$ignoreTenantFilter) {
                    // Filtra produtos do tenant atual (se tiver metadata tenant_id)
                    // Se não tiver metadata tenant_id ou for vazio, inclui (produtos antigos ou sem tenant)
                    $metadata = $product->metadata->toArray();
                    if (!empty($metadata) && isset($metadata['tenant_id']) && $metadata['tenant_id'] !== null && $metadata['tenant_id'] !== '') {
                        // Só filtra se tiver tenant_id definido e for diferente
                        if ((string)$metadata['tenant_id'] !== (string)$tenantId) {
                            continue; // Pula produtos de outros tenants
                        }
                    }
                    // Se não tiver tenant_id nos metadados, inclui o produto (produtos antigos ou compartilhados)
                }
                
                // Converte metadata para array para uso consistente
                $metadata = $product->metadata->toArray();
                
                $formattedProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description ?? null,
                    'active' => $product->active,
                    'images' => $product->images ?? [],
                    'statement_descriptor' => $product->statement_descriptor ?? null,
                    'unit_label' => $product->unit_label ?? null,
                    'created' => date('Y-m-d H:i:s', $product->created),
                    'updated' => date('Y-m-d H:i:s', $product->updated ?? $product->created),
                    'metadata' => $metadata
                ];
            }
            
            // Se não encontrou produtos suficientes e há mais páginas, busca mais
            // (apenas se não foi especificado um limite específico)
            if (count($formattedProducts) < $options['limit'] && $products->has_more && !isset($queryParams['limit'])) {
                // Busca mais produtos até atingir o limite ou não houver mais
                $remaining = $options['limit'] - count($formattedProducts);
                $lastProduct = end($products->data);
                
                while ($remaining > 0 && $products->has_more) {
                    $options['starting_after'] = $lastProduct->id;
                    $options['limit'] = min($remaining, 50);
                    $moreProducts = $this->stripeService->listProducts($options);
                    
                    foreach ($moreProducts->data as $product) {
                        // Aplica o mesmo filtro de tenant_id (exceto se all_tenants=true)
                        if (!$ignoreTenantFilter) {
                            $metadata = $product->metadata->toArray();
                            if (!empty($metadata) && isset($metadata['tenant_id']) && $metadata['tenant_id'] !== null && $metadata['tenant_id'] !== '') {
                                if ((string)$metadata['tenant_id'] !== (string)$tenantId) {
                                    continue;
                                }
                            }
                            // Se não tiver tenant_id, inclui
                        }
                        
                        // Converte metadata para array
                        $metadata = $product->metadata->toArray();
                        
                        $formattedProducts[] = [
                            'id' => $product->id,
                            'name' => $product->name,
                            'description' => $product->description ?? null,
                            'active' => $product->active,
                            'images' => $product->images ?? [],
                            'statement_descriptor' => $product->statement_descriptor ?? null,
                            'unit_label' => $product->unit_label ?? null,
                            'created' => date('Y-m-d H:i:s', $product->created),
                            'updated' => date('Y-m-d H:i:s', $product->updated ?? $product->created),
                            'metadata' => $metadata
                        ];
                        
                        $remaining--;
                        if ($remaining <= 0) break;
                    }
                    
                    $products->has_more = $moreProducts->has_more;
                    if (!empty($moreProducts->data)) {
                        $lastProduct = end($moreProducts->data);
                    }
                }
            }
            
            $response = [
                'success' => true,
                'data' => $formattedProducts,
                'has_more' => $products->has_more,
                'count' => count($formattedProducts)
            ];
            
            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $response, 60);
            
            Flight::json($response);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            ResponseHelper::sendStripeError(
                $e,
                'Erro ao listar produtos',
                ['tenant_id' => $tenantId ?? null, 'action' => 'list_products']
            );
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao listar produtos',
                'PRODUCT_LIST_ERROR',
                ['tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Obtém produto específico
     * GET /v1/products/:id
     */
    public function get(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_products']);
                return;
            }

            $product = $this->stripeService->getProduct($id);

            // Valida se o produto pertence ao tenant (via metadata)
            if (isset($product->metadata->tenant_id) && (string)$product->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Produto', ['product_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            ResponseHelper::sendSuccess([
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? null,
                'active' => $product->active,
                'images' => $product->images ?? [],
                'statement_descriptor' => $product->statement_descriptor ?? null,
                'unit_label' => $product->unit_label ?? null,
                'created' => date('Y-m-d H:i:s', $product->created),
                'updated' => date('Y-m-d H:i:s', $product->updated ?? $product->created),
                'metadata' => $product->metadata->toArray()
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Produto', ['product_id' => $id, 'tenant_id' => $tenantId ?? null]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao obter produto',
                    ['product_id' => $id, 'tenant_id' => $tenantId ?? null, 'action' => 'get_product']
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter produto',
                'PRODUCT_GET_ERROR',
                ['product_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza produto
     * PUT /v1/products/:id
     * 
     * Body:
     *   - name (opcional): Nome do produto
     *   - description (opcional): Descrição do produto
     *   - active (opcional): Se o produto está ativo
     *   - images (opcional): Array de URLs de imagens
     *   - metadata (opcional): Metadados
     *   - statement_descriptor (opcional): Descrição que aparece na fatura
     *   - unit_label (opcional): Rótulo da unidade
     */
    public function update(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_products']);
                return;
            }

            // Primeiro, verifica se o produto existe e pertence ao tenant
            $product = $this->stripeService->getProduct($id);
            
            if (isset($product->metadata->tenant_id) && (string)$product->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Produto', ['product_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['product_id' => $id, 'tenant_id' => $tenantId]);
                    return;
                }
                $data = [];
            }

            // Preserva tenant_id nos metadados se metadata for atualizado
            if (isset($data['metadata'])) {
                $data['metadata']['tenant_id'] = $tenantId;
            }

            $product = $this->stripeService->updateProduct($id, $data);

            ResponseHelper::sendSuccess([
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? null,
                'active' => $product->active,
                'images' => $product->images ?? [],
                'statement_descriptor' => $product->statement_descriptor ?? null,
                'unit_label' => $product->unit_label ?? null,
                'created' => date('Y-m-d H:i:s', $product->created),
                'updated' => date('Y-m-d H:i:s', $product->updated ?? $product->created),
                'metadata' => $product->metadata->toArray()
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Produto', ['product_id' => $id, 'tenant_id' => $tenantId ?? null]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao atualizar produto',
                    ['product_id' => $id, 'tenant_id' => $tenantId ?? null, 'action' => 'update_product']
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar produto',
                'PRODUCT_UPDATE_ERROR',
                ['product_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Deleta produto
     * DELETE /v1/products/:id
     * 
     * Nota: Se o produto tiver preços associados, apenas desativa (soft delete)
     */
    public function delete(string $id): void
    {
        try {
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_products']);
                return;
            }

            // Primeiro, verifica se o produto existe e pertence ao tenant
            $product = $this->stripeService->getProduct($id);
            
            if (isset($product->metadata->tenant_id) && (string)$product->metadata->tenant_id !== (string)$tenantId) {
                ResponseHelper::sendNotFoundError('Produto', ['product_id' => $id, 'tenant_id' => $tenantId]);
                return;
            }

            $product = $this->stripeService->deleteProduct($id);

            // Verifica se foi deletado ou apenas desativado
            $wasDeleted = isset($product->deleted) && $product->deleted === true;
            $isActive = isset($product->active) ? $product->active : false;

            ResponseHelper::sendSuccess([
                'id' => $product->id,
                'deleted' => $wasDeleted,
                'active' => $isActive
            ], 200, $wasDeleted ? 'Produto deletado com sucesso' : 'Produto desativado com sucesso (tem preços associados)');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                ResponseHelper::sendNotFoundError('Produto', ['product_id' => $id, 'tenant_id' => $tenantId ?? null]);
            } else {
                ResponseHelper::sendStripeError(
                    $e,
                    'Erro ao deletar produto',
                    ['product_id' => $id, 'tenant_id' => $tenantId ?? null, 'action' => 'delete_product']
                );
            }
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao deletar produto',
                'PRODUCT_DELETE_ERROR',
                ['product_id' => $id, 'tenant_id' => $tenantId ?? null]
            );
        }
    }
}


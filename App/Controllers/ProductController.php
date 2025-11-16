<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if ($data === null) {
                $data = [];
            }

            // Validações obrigatórias
            if (empty($data['name'])) {
                Flight::json(['error' => 'Campo name é obrigatório'], 400);
                return;
            }

            // Adiciona tenant_id aos metadados se não existir
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['tenant_id'] = $tenantId;

            $product = $this->stripeService->createProduct($data);

            Flight::json([
                'success' => true,
                'data' => [
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
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Erro de validação ao criar produto", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro de validação',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao criar produto no Stripe", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao criar produto',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro inesperado ao criar produto", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
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
            
            Flight::json([
                'success' => true,
                'data' => $formattedProducts,
                'has_more' => $products->has_more,
                'count' => count($formattedProducts)
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::error("Erro ao listar produtos", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro ao listar produtos',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 400);
        } catch (\Exception $e) {
            Logger::error("Erro ao listar produtos", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $product = $this->stripeService->getProduct($id);

            // Valida se o produto pertence ao tenant (via metadata)
            if (isset($product->metadata->tenant_id) && (string)$product->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Produto não encontrado'], 404);
                return;
            }

            Flight::json([
                'success' => true,
                'data' => [
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
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Produto não encontrado'], 404);
            } else {
                Logger::error("Erro ao obter produto", ['error' => $e->getMessage()]);
                Flight::json([
                    'error' => 'Erro ao obter produto',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao obter produto", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Primeiro, verifica se o produto existe e pertence ao tenant
            $product = $this->stripeService->getProduct($id);
            
            if (isset($product->metadata->tenant_id) && (string)$product->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Produto não encontrado'], 404);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if ($data === null) {
                $data = [];
            }

            // Preserva tenant_id nos metadados se metadata for atualizado
            if (isset($data['metadata'])) {
                $data['metadata']['tenant_id'] = $tenantId;
            }

            $product = $this->stripeService->updateProduct($id, $data);

            Flight::json([
                'success' => true,
                'data' => [
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
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Produto não encontrado'], 404);
            } else {
                Logger::error("Erro ao atualizar produto", ['error' => $e->getMessage()]);
                Flight::json([
                    'error' => 'Erro ao atualizar produto',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao atualizar produto", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
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
                Flight::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Primeiro, verifica se o produto existe e pertence ao tenant
            $product = $this->stripeService->getProduct($id);
            
            if (isset($product->metadata->tenant_id) && (string)$product->metadata->tenant_id !== (string)$tenantId) {
                Flight::json(['error' => 'Produto não encontrado'], 404);
                return;
            }

            $product = $this->stripeService->deleteProduct($id);

            // Verifica se foi deletado ou apenas desativado
            $wasDeleted = isset($product->deleted) && $product->deleted === true;
            $isActive = isset($product->active) ? $product->active : false;

            Flight::json([
                'success' => true,
                'message' => $wasDeleted ? 'Produto deletado com sucesso' : 'Produto desativado com sucesso (tem preços associados)',
                'data' => [
                    'id' => $product->id,
                    'deleted' => $wasDeleted,
                    'active' => $isActive
                ]
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                Flight::json(['error' => 'Produto não encontrado'], 404);
            } else {
                Logger::error("Erro ao deletar produto", ['error' => $e->getMessage()]);
                Flight::json([
                    'error' => 'Erro ao deletar produto',
                    'message' => Config::isDevelopment() ? $e->getMessage() : null
                ], 400);
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao deletar produto", ['error' => $e->getMessage()]);
            Flight::json([
                'error' => 'Erro interno do servidor',
                'message' => Config::isDevelopment() ? $e->getMessage() : null
            ], 500);
        }
    }
}


<?php

namespace App\Models;

use App\Utils\Database;
use PDO;

/**
 * Classe base ActiveRecord para modelos
 * Fornece métodos CRUD básicos
 */
abstract class BaseModel
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected PDO $db;
    protected bool $usesSoftDeletes = false; // Models podem sobrescrever para ativar soft deletes

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Busca um registro por ID
     * 
     * ✅ OTIMIZAÇÃO: Por padrão, ainda usa SELECT * para compatibilidade
     * Para melhor performance, use findByIdSelect() com campos específicos
     * ✅ SOFT DELETES: Exclui automaticamente registros deletados se soft deletes estiver ativo
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        
        // Se soft deletes estiver ativo, exclui registros deletados
        if ($this->usesSoftDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Busca um registro por ID com campos específicos
     * ✅ OTIMIZAÇÃO: SELECT específico reduz transferência de dados e uso de memória
     * 
     * @param int $id ID do registro
     * @param array $fields Campos a selecionar (ex: ['id', 'email', 'name'])
     * @return array|null
     */
    public function findByIdSelect(int $id, array $fields): ?array
    {
        // Valida campos (whitelist)
        $allowedFields = $this->getAllowedSelectFields();
        if (!empty($allowedFields)) {
            $fields = array_intersect($fields, $allowedFields);
        }
        
        // Sanitiza nomes de campos
        $fields = array_map(function($field) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        }, $fields);
        
        if (empty($fields)) {
            // Fallback para SELECT * se nenhum campo válido
            return $this->findById($id);
        }
        
        $fieldsStr = implode(', ', array_map(fn($f) => "`{$f}`", $fields));
        $stmt = $this->db->prepare("SELECT {$fieldsStr} FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Busca registros com campos específicos
     * 
     * @param array $fields Campos a selecionar (ex: ['id', 'email', 'name'])
     * @param array $conditions Condições WHERE
     * @param array $orderBy Ordenação
     * @param int|null $limit Limite
     * @param int $offset Offset
     * @return array
     */
    public function select(
        array $fields, 
        array $conditions = [], 
        array $orderBy = [], 
        int $limit = null, 
        int $offset = 0
    ): array {
        // Valida campos (whitelist)
        $allowedFields = $this->getAllowedSelectFields();
        if (!empty($allowedFields)) {
            $fields = array_intersect($fields, $allowedFields);
        }
        
        // Sanitiza nomes de campos
        $fields = array_map(function($field) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        }, $fields);
        
        if (empty($fields)) {
            $fields = ['*']; // Fallback
        }
        
        $fieldsStr = implode(', ', array_map(fn($f) => "`{$f}`", $fields));
        $sql = "SELECT {$fieldsStr} FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if ($key === 'OR') {
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (strpos($orKey, ' LIKE') !== false) {
                            $field = str_replace(' LIKE', '', $orKey);
                            $paramKey = 'or_' . str_replace('.', '_', $field);
                            $orConditions[] = "{$field} LIKE :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        } else {
                            $paramKey = 'or_' . str_replace('.', '_', $orKey);
                            $orConditions[] = "{$orKey} = :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        }
                    }
                    $where[] = '(' . implode(' OR ', $orConditions) . ')';
                } elseif (strpos($key, ' LIKE') !== false) {
                    $field = str_replace(' LIKE', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "{$field} LIKE :{$paramKey}";
                    $params[$paramKey] = $value;
                } else {
                    $paramKey = str_replace('.', '_', $key);
                    $where[] = "{$key} = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $order = [];
            $allowedFields = $this->getAllowedOrderFields();
            $allowedDirections = ['ASC', 'DESC'];
            
            foreach ($orderBy as $field => $direction) {
                if (!empty($allowedFields) && !in_array($field, $allowedFields, true)) {
                    continue;
                }
                
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                if (empty($field)) {
                    continue;
                }
                
                $direction = strtoupper(trim($direction));
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'ASC';
                }
                
                $order[] = "`{$field}` {$direction}";
            }
            
            if (!empty($order)) {
                $sql .= " ORDER BY " . implode(', ', $order);
            }
        }

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset > 0) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($offset > 0) {
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Busca todos os registros
     * ✅ SOFT DELETES: Exclui automaticamente registros deletados se soft deletes estiver ativo
     */
    public function findAll(array $conditions = [], array $orderBy = [], int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        $where = [];
        
        // Se soft deletes estiver ativo, adiciona condição para excluir deletados
        if ($this->usesSoftDeletes) {
            $where[] = "deleted_at IS NULL";
        }

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                if ($key === 'OR') {
                    // Suporte para condições OR
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (strpos($orKey, ' LIKE') !== false) {
                            $field = str_replace(' LIKE', '', $orKey);
                            $paramKey = 'or_' . str_replace('.', '_', $field);
                            $orConditions[] = "{$field} LIKE :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        } else {
                            $paramKey = 'or_' . str_replace('.', '_', $orKey);
                            $orConditions[] = "{$orKey} = :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        }
                    }
                    $where[] = '(' . implode(' OR ', $orConditions) . ')';
                } elseif (strpos($key, ' LIKE') !== false) {
                    // Suporte para LIKE
                    $field = str_replace(' LIKE', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "{$field} LIKE :{$paramKey}";
                    $params[$paramKey] = $value;
                } elseif (preg_match('/^(.+?)\s*(>=|<=|>|<|!=|<>)\s*$/', $key, $matches)) {
                    // Suporte para operadores de comparação (>=, <=, >, <, !=, <>)
                    $field = trim($matches[1]);
                    $operator = trim($matches[2]);
                    // Sanitiza o nome do campo (remove caracteres perigosos, mas mantém underscore)
                    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                    if (empty($field)) {
                        continue; // Ignora se o campo estiver vazio após sanitização
                    }
                    // Cria nome de parâmetro único e seguro
                    $paramKey = str_replace(['>=', '<=', '!=', '<>'], ['gte', 'lte', 'ne', 'ne2'], $operator);
                    $paramKey = $field . '_' . $paramKey;
                    $paramKey = preg_replace('/[^a-zA-Z0-9_]/', '', $paramKey);
                    $where[] = "`{$field}` {$operator} :{$paramKey}";
                    $params[$paramKey] = $value;
                } else {
                    // Sanitiza o nome do campo
                    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "`{$field}` = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $order = [];
            $allowedFields = $this->getAllowedOrderFields(); // Método a ser implementado em cada modelo
            $allowedDirections = ['ASC', 'DESC'];
            
            foreach ($orderBy as $field => $direction) {
                // Valida campo contra whitelist
                if (!empty($allowedFields) && !in_array($field, $allowedFields, true)) {
                    continue; // Ignora campos não permitidos
                }
                
                // Sanitiza nome do campo (remove caracteres perigosos)
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                if (empty($field)) {
                    continue;
                }
                
                // Valida direção
                $direction = strtoupper(trim($direction));
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'ASC'; // Default seguro
                }
                
                // Usa backticks para campos (proteção adicional)
                $order[] = "`{$field}` {$direction}";
            }
            
            if (!empty($order)) {
                $sql .= " ORDER BY " . implode(', ', $order);
            }
        }

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset > 0) {
                $sql .= " OFFSET :offset";
            }
        }

        // Log temporário para debug (apenas em desenvolvimento)
        if (defined('Config') && \Config::isDevelopment() && $this->table === 'appointments') {
            \App\Services\Logger::info('SQL gerado no BaseModel::findAll', [
                'table' => $this->table,
                'sql' => $sql,
                'params' => $params,
                'conditions' => $conditions
            ]);
        }

        try {
            $stmt = $this->db->prepare($sql);
        } catch (\PDOException $e) {
            \App\Services\Logger::error('Erro ao preparar SQL no BaseModel::findAll', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }

        foreach ($params as $key => $value) {
            try {
                $stmt->bindValue(":{$key}", $value);
            } catch (\PDOException $e) {
                \App\Services\Logger::error('Erro ao fazer bind no BaseModel::findAll', [
                    'key' => $key,
                    'value' => is_string($value) ? substr($value, 0, 100) : $value,
                    'sql' => $sql,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]);
                throw $e;
            }
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            if ($offset > 0) {
                $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            }
        }

        try {
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            \App\Services\Logger::error('Erro ao executar SQL no BaseModel::findAll', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }
    
    /**
     * Busca registros com contagem total em uma única query
     * Usa window function COUNT(*) OVER() do MySQL 8.0+
     * 
     * @param array $conditions Condições WHERE
     * @param array $orderBy Ordenação
     * @param int|null $limit Limite
     * @param int $offset Offset
     * @return array ['data' => array, 'total' => int]
     */
    public function findAllWithCount(
        array $conditions = [], 
        array $orderBy = [], 
        int $limit = null, 
        int $offset = 0
    ): array {
        $sql = "SELECT *, COUNT(*) OVER() as _total FROM {$this->table}";
        $params = [];
        
        $where = [];
        
        // Se soft deletes estiver ativo, adiciona condição para excluir deletados
        if ($this->usesSoftDeletes) {
            $where[] = "deleted_at IS NULL";
        }

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                if ($key === 'OR') {
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (strpos($orKey, ' LIKE') !== false) {
                            $field = str_replace(' LIKE', '', $orKey);
                            $paramKey = 'or_' . str_replace('.', '_', $field);
                            $orConditions[] = "{$field} LIKE :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        } else {
                            $paramKey = 'or_' . str_replace('.', '_', $orKey);
                            $orConditions[] = "{$orKey} = :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        }
                    }
                    $where[] = '(' . implode(' OR ', $orConditions) . ')';
                } elseif (strpos($key, ' LIKE') !== false) {
                    $field = str_replace(' LIKE', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "{$field} LIKE :{$paramKey}";
                    $params[$paramKey] = $value;
                } else {
                    $paramKey = str_replace('.', '_', $key);
                    $where[] = "{$key} = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $order = [];
            $allowedFields = $this->getAllowedOrderFields();
            $allowedDirections = ['ASC', 'DESC'];
            
            foreach ($orderBy as $field => $direction) {
                if (!empty($allowedFields) && !in_array($field, $allowedFields, true)) {
                    continue;
                }
                
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                if (empty($field)) {
                    continue;
                }
                
                $direction = strtoupper(trim($direction));
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'ASC';
                }
                
                $order[] = "`{$field}` {$direction}";
            }
            
            if (!empty($order)) {
                $sql .= " ORDER BY " . implode(', ', $order);
            }
        }

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset > 0) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($offset > 0) {
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $total = !empty($results) ? (int)$results[0]['_total'] : 0;
        
        // Remove campo _total dos resultados
        $results = array_map(function($row) {
            unset($row['_total']);
            return $row;
        }, $results);
        
        return [
            'data' => $results,
            'total' => $total
        ];
    }
    
    /**
     * Conta registros com condições
     * ✅ SOFT DELETES: Exclui automaticamente registros deletados se soft deletes estiver ativo
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        $where = [];
        
        // Se soft deletes estiver ativo, adiciona condição para excluir deletados
        if ($this->usesSoftDeletes) {
            $where[] = "deleted_at IS NULL";
        }

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                if ($key === 'OR') {
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (strpos($orKey, ' LIKE') !== false) {
                            $field = str_replace(' LIKE', '', $orKey);
                            $paramKey = 'or_' . str_replace('.', '_', $field);
                            $orConditions[] = "{$field} LIKE :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        } else {
                            $paramKey = 'or_' . str_replace('.', '_', $orKey);
                            $orConditions[] = "{$orKey} = :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        }
                    }
                    $where[] = '(' . implode(' OR ', $orConditions) . ')';
                } elseif (strpos($key, ' LIKE') !== false) {
                    $field = str_replace(' LIKE', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "{$field} LIKE :{$paramKey}";
                    $params[$paramKey] = $value;
                } else {
                    $paramKey = str_replace('.', '_', $key);
                    $where[] = "{$key} = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Insere um novo registro
     * ✅ CORREÇÃO: Suporta tabelas com e sem AUTO_INCREMENT
     * Se a chave primária estiver presente nos dados, retorna o valor fornecido
     * Caso contrário, usa lastInsertId() para obter o ID gerado
     */
    public function insert(array $data): int|string
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ":{$field}", $fields);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        // ✅ CORREÇÃO: Execute lança exceção se falhar (PDO::ERRMODE_EXCEPTION)
        $stmt->execute();
        
        // ✅ CORREÇÃO: Verifica se a chave primária foi fornecida manualmente
        // Se sim, retorna o valor fornecido (suporta strings como IDs)
        if (isset($data[$this->primaryKey])) {
            return $data[$this->primaryKey];
        }
        
        // Se não foi fornecida, usa lastInsertId() (para AUTO_INCREMENT)
        $insertId = $this->db->lastInsertId();
        
        // ✅ CORREÇÃO: Valida se o insert foi bem-sucedido
        // lastInsertId() pode retornar string vazia ou "0" se não houver AUTO_INCREMENT
        if (empty($insertId) || $insertId === '0') {
            throw new \RuntimeException("Falha ao inserir registro: lastInsertId retornou '{$insertId}'. Verifique se a tabela possui AUTO_INCREMENT na chave primária ou forneça o ID manualmente.");
        }
        
        // Retorna como int se for numérico, caso contrário como string
        return is_numeric($insertId) ? (int) $insertId : $insertId;
    }

    /**
     * Atualiza um registro
     */
    public function update(int $id, array $data): bool
    {
        $fields = array_keys($data);
        $set = array_map(fn($field) => "{$field} = :{$field}", $fields);

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . 
               " WHERE {$this->primaryKey} = :id";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Deleta um registro
     * ✅ SOFT DELETES: Se soft deletes estiver ativo, marca como deletado em vez de remover fisicamente
     */
    public function delete(int $id): bool
    {
        if ($this->usesSoftDeletes) {
            // Soft delete: marca deleted_at com timestamp atual
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        
        // Hard delete: remove fisicamente do banco
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Restaura um registro deletado (soft delete)
     * 
     * @param int $id ID do registro
     * @return bool Sucesso da operação
     * @throws \RuntimeException Se soft deletes não estiver ativo
     */
    public function restore(int $id): bool
    {
        if (!$this->usesSoftDeletes) {
            throw new \RuntimeException("Soft deletes não está ativo para este model");
        }
        
        return $this->update($id, ['deleted_at' => null]);
    }
    
    /**
     * Busca registros incluindo os deletados (soft delete)
     * 
     * @param array $conditions Condições WHERE
     * @param array $orderBy Ordenação
     * @param int|null $limit Limite
     * @param int $offset Offset
     * @return array Lista de registros
     */
    public function withTrashed(array $conditions = [], array $orderBy = [], int $limit = null, int $offset = 0): array
    {
        if (!$this->usesSoftDeletes) {
            // Se não usa soft deletes, retorna findAll normal
            return $this->findAll($conditions, $orderBy, $limit, $offset);
        }
        
        // Busca todos, incluindo deletados (ignora condição deleted_at)
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                // Ignora deleted_at se presente nas condições
                if ($key === 'deleted_at') {
                    continue;
                }
                
                if ($key === 'OR') {
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (strpos($orKey, ' LIKE') !== false) {
                            $field = str_replace(' LIKE', '', $orKey);
                            $paramKey = 'or_' . str_replace('.', '_', $field);
                            $orConditions[] = "{$field} LIKE :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        } else {
                            $paramKey = 'or_' . str_replace('.', '_', $orKey);
                            $orConditions[] = "{$orKey} = :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        }
                    }
                    $where[] = '(' . implode(' OR ', $orConditions) . ')';
                } elseif (strpos($key, ' LIKE') !== false) {
                    $field = str_replace(' LIKE', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "{$field} LIKE :{$paramKey}";
                    $params[$paramKey] = $value;
                } else {
                    $paramKey = str_replace('.', '_', $key);
                    $where[] = "{$key} = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
        }
        
        if (!empty($orderBy)) {
            $order = [];
            $allowedFields = $this->getAllowedOrderFields();
            $allowedDirections = ['ASC', 'DESC'];
            
            foreach ($orderBy as $field => $direction) {
                if (!empty($allowedFields) && !in_array($field, $allowedFields, true)) {
                    continue;
                }
                
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                if (empty($field)) {
                    continue;
                }
                
                $direction = strtoupper(trim($direction));
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'ASC';
                }
                
                $order[] = "`{$field}` {$direction}";
            }
            
            if (!empty($order)) {
                $sql .= " ORDER BY " . implode(', ', $order);
            }
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset > 0) {
                $sql .= " OFFSET :offset";
            }
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($offset > 0) {
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Busca apenas registros deletados (soft delete)
     * 
     * @param array $conditions Condições WHERE adicionais
     * @param array $orderBy Ordenação
     * @param int|null $limit Limite
     * @param int $offset Offset
     * @return array Lista de registros deletados
     */
    public function onlyTrashed(array $conditions = [], array $orderBy = [], int $limit = null, int $offset = 0): array
    {
        if (!$this->usesSoftDeletes) {
            // Se não usa soft deletes, retorna array vazio
            return [];
        }
        
        // Adiciona condição para buscar apenas deletados
        $conditions['deleted_at'] = ['IS NOT', null];
        
        // Usa método findAll mas com condição modificada
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        $where = ["deleted_at IS NOT NULL"];
        
        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                if ($key === 'deleted_at') {
                    continue; // Já adicionado acima
                }
                
                if ($key === 'OR') {
                    $orConditions = [];
                    foreach ($value as $orKey => $orValue) {
                        if (strpos($orKey, ' LIKE') !== false) {
                            $field = str_replace(' LIKE', '', $orKey);
                            $paramKey = 'or_' . str_replace('.', '_', $field);
                            $orConditions[] = "{$field} LIKE :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        } else {
                            $paramKey = 'or_' . str_replace('.', '_', $orKey);
                            $orConditions[] = "{$orKey} = :{$paramKey}";
                            $params[$paramKey] = $orValue;
                        }
                    }
                    $where[] = '(' . implode(' OR ', $orConditions) . ')';
                } elseif (strpos($key, ' LIKE') !== false) {
                    $field = str_replace(' LIKE', '', $key);
                    $paramKey = str_replace('.', '_', $field);
                    $where[] = "{$field} LIKE :{$paramKey}";
                    $params[$paramKey] = $value;
                } else {
                    $paramKey = str_replace('.', '_', $key);
                    $where[] = "{$key} = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
        }
        
        $sql .= " WHERE " . implode(' AND ', $where);
        
        if (!empty($orderBy)) {
            $order = [];
            $allowedFields = $this->getAllowedOrderFields();
            $allowedDirections = ['ASC', 'DESC'];
            
            foreach ($orderBy as $field => $direction) {
                if (!empty($allowedFields) && !in_array($field, $allowedFields, true)) {
                    continue;
                }
                
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                if (empty($field)) {
                    continue;
                }
                
                $direction = strtoupper(trim($direction));
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'ASC';
                }
                
                $order[] = "`{$field}` {$direction}";
            }
            
            if (!empty($order)) {
                $sql .= " ORDER BY " . implode(', ', $order);
            }
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset > 0) {
                $sql .= " OFFSET :offset";
            }
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($offset > 0) {
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Busca um registro por campo único
     * ✅ SOFT DELETES: Exclui automaticamente registros deletados se soft deletes estiver ativo
     */
    public function findBy(string $field, $value): ?array
    {
        // Sanitiza nome do campo para prevenir SQL Injection
        $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        if (empty($field)) {
            return null;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE `{$field}` = :value";
        
        // Se soft deletes estiver ativo, exclui registros deletados
        if ($this->usesSoftDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['value' => $value]);
        $result = $stmt->fetch();

        return $result ?: null;
    }
    
    /**
     * Retorna lista de campos permitidos para ordenação
     * Cada modelo deve sobrescrever este método com seus campos específicos
     * 
     * @return array Lista de campos permitidos (vazio = todos os campos são permitidos)
     */
    protected function getAllowedOrderFields(): array
    {
        // Por padrão, retorna vazio (todos permitidos)
        // Modelos específicos devem sobrescrever este método
        return [];
    }
    
    /**
     * Retorna lista de campos permitidos para SELECT
     * Modelos podem sobrescrever para segurança
     * 
     * @return array Lista de campos permitidos (vazio = todos os campos são permitidos)
     */
    protected function getAllowedSelectFields(): array
    {
        // Por padrão, retorna vazio (todos permitidos)
        // Modelos específicos devem sobrescrever este método para restringir campos
        return [];
    }
}


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

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Busca um registro por ID
     * 
     * ✅ OTIMIZAÇÃO: Por padrão, ainda usa SELECT * para compatibilidade
     * Para melhor performance, use findByIdSelect() com campos específicos
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
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
     */
    public function findAll(array $conditions = [], array $orderBy = [], int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
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
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
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
     * ✅ CORREÇÃO: Garante que exceções sejam lançadas corretamente
     */
    public function insert(array $data): int
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
        
        $insertId = (int) $this->db->lastInsertId();
        
        // ✅ CORREÇÃO: Valida se o insert foi bem-sucedido
        if ($insertId <= 0) {
            throw new \RuntimeException("Falha ao inserir registro: lastInsertId retornou {$insertId}");
        }
        
        return $insertId;
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
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Busca um registro por campo único
     */
    public function findBy(string $field, $value): ?array
    {
        // Sanitiza nome do campo para prevenir SQL Injection
        $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        if (empty($field)) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE `{$field}` = :value LIMIT 1");
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


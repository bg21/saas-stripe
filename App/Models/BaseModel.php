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
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
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

        $stmt->execute();
        return (int) $this->db->lastInsertId();
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
}


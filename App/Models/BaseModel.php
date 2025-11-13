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
    public function findAll(array $conditions = [], array $orderBy = [], int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($conditions)) {
            $where = [];
            foreach (array_keys($conditions) as $key) {
                $where[] = "{$key} = :{$key}";
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $field => $direction) {
                $order[] = "{$field} {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $order);
        }

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $this->db->prepare($sql);

        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
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
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $result = $stmt->fetch();

        return $result ?: null;
    }
}


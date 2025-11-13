<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\BaseModel;
use App\Utils\Database;
use PDO;

/**
 * Testes unitários para BaseModel
 * Usa SQLite in-memory para testes
 */
class BaseModelTest extends TestCase
{
    private PDO $testDb;
    private TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria banco SQLite em memória
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cria tabela de teste
        $this->testDb->exec("
            CREATE TABLE test_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255),
                email VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Cria modelo de teste
        $this->model = new TestModel($this->testDb);
    }

    public function testInsert(): void
    {
        $id = $this->model->insert([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testFindById(): void
    {
        $id = $this->model->insert([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $record = $this->model->findById($id);

        $this->assertNotNull($record);
        $this->assertEquals('Test User', $record['name']);
        $this->assertEquals('test@example.com', $record['email']);
    }

    public function testUpdate(): void
    {
        $id = $this->model->insert([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $updated = $this->model->update($id, ['name' => 'Updated Name']);
        $this->assertTrue($updated);

        $record = $this->model->findById($id);
        $this->assertEquals('Updated Name', $record['name']);
    }

    public function testDelete(): void
    {
        $id = $this->model->insert([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $deleted = $this->model->delete($id);
        $this->assertTrue($deleted);

        $record = $this->model->findById($id);
        $this->assertNull($record);
    }

    public function testFindAll(): void
    {
        $this->model->insert(['name' => 'User 1', 'email' => 'user1@example.com']);
        $this->model->insert(['name' => 'User 2', 'email' => 'user2@example.com']);

        $all = $this->model->findAll();
        $this->assertCount(2, $all);
    }
}

/**
 * Modelo de teste que estende BaseModel
 */
class TestModel extends BaseModel
{
    protected string $table = 'test_table';
    private PDO $customDb;

    public function __construct(PDO $db)
    {
        $this->customDb = $db;
        // Sobrescreve a propriedade db do BaseModel
        $this->db = $db;
    }
}


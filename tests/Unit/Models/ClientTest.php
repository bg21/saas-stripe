<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Client;
use App\Models\Tenant;
use PDO;

/**
 * Testes unitários para Client Model
 */
class ClientTest extends TestCase
{
    private PDO $testDb;
    private Client $model;
    private Tenant $tenantModel;
    private int $testTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->testDb->exec("
            CREATE TABLE tenants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                api_key VARCHAR(255) UNIQUE NOT NULL,
                status VARCHAR(50) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");

        $this->testDb->exec("
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                address TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        $this->tenantModel = new Tenant();
        $reflection = new \ReflectionClass($this->tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->tenantModel, $this->testDb);
        
        $this->testTenantId = $this->tenantModel->create('Test Clinic');

        $this->model = new Client();
        $reflection = new \ReflectionClass($this->model);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->model, $this->testDb);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->testDb);
    }

    public function testCreateClient(): void
    {
        // Arrange
        $data = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '11999999999'
        ];

        // Act
        $id = $this->model->createOrUpdate($this->testTenantId, $data);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $client = $this->model->findById($id);
        $this->assertNotNull($client);
        $this->assertEquals('João Silva', $client['name']);
        $this->assertEquals('joao@example.com', $client['email']);
        $this->assertEquals($this->testTenantId, $client['tenant_id']);
    }

    public function testFindByTenantAndId(): void
    {
        // Arrange
        $id = $this->model->createOrUpdate($this->testTenantId, [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com'
        ]);

        // Act
        $client = $this->model->findByTenantAndId($this->testTenantId, $id);

        // Assert
        $this->assertNotNull($client);
        $this->assertEquals($id, $client['id']);
        $this->assertEquals('Maria Santos', $client['name']);
    }

    public function testFindByEmail(): void
    {
        // Arrange
        $this->model->createOrUpdate($this->testTenantId, [
            'name' => 'Pedro Costa',
            'email' => 'pedro@example.com'
        ]);

        // Act
        $client = $this->model->findByEmail($this->testTenantId, 'pedro@example.com');

        // Assert
        $this->assertNotNull($client);
        $this->assertEquals('Pedro Costa', $client['name']);
        $this->assertEquals('pedro@example.com', $client['email']);
    }

    public function testFindByTenant(): void
    {
        // Arrange
        $this->model->createOrUpdate($this->testTenantId, ['name' => 'Cliente 1']);
        $this->model->createOrUpdate($this->testTenantId, ['name' => 'Cliente 2']);

        // Act
        $clients = $this->model->findByTenant($this->testTenantId);

        // Assert
        $this->assertCount(2, $clients);
    }

    public function testCreateOrUpdateWithInvalidTenant(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant com ID 99999 não encontrado');

        // Act
        $this->model->createOrUpdate(99999, ['name' => 'Test']);
    }

    public function testUpdateClient(): void
    {
        // Arrange
        $id = $this->model->createOrUpdate($this->testTenantId, [
            'name' => 'Original Name',
            'email' => 'original@example.com'
        ]);

        // Act
        $updatedId = $this->model->createOrUpdate($this->testTenantId, [
            'id' => $id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ]);

        // Assert
        $this->assertEquals($id, $updatedId);

        $client = $this->model->findById($id);
        $this->assertEquals('Updated Name', $client['name']);
        $this->assertEquals('updated@example.com', $client['email']);
    }
}


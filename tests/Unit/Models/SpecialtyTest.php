<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Specialty;
use App\Models\Tenant;
use PDO;

/**
 * Testes unitários para Specialty Model
 */
class SpecialtyTest extends TestCase
{
    private PDO $testDb;
    private Specialty $model;
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->testDb->exec("
            CREATE TABLE specialties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                status VARCHAR(50) DEFAULT 'active',
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

        $this->model = new Specialty();
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

    public function testCreateSpecialty(): void
    {
        // Arrange
        $data = [
            'tenant_id' => $this->testTenantId,
            'name' => 'Cardiologia',
            'description' => 'Especialidade em cardiologia veterinária',
            'status' => 'active'
        ];

        // Act
        $id = $this->model->insert($data);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $specialty = $this->model->findById($id);
        $this->assertNotNull($specialty);
        $this->assertEquals('Cardiologia', $specialty['name']);
        $this->assertEquals($this->testTenantId, $specialty['tenant_id']);
    }

    public function testFindByTenantAndId(): void
    {
        // Arrange
        $id = $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Dermatologia',
            'status' => 'active'
        ]);

        // Act
        $specialty = $this->model->findByTenantAndId($this->testTenantId, $id);

        // Assert
        $this->assertNotNull($specialty);
        $this->assertEquals($id, $specialty['id']);
        $this->assertEquals('Dermatologia', $specialty['name']);
    }

    public function testFindByTenantAndIdWrongTenant(): void
    {
        // Arrange
        $otherTenantId = $this->tenantModel->create('Other Clinic');
        $id = $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Ortopedia',
            'status' => 'active'
        ]);

        // Act
        $specialty = $this->model->findByTenantAndId($otherTenantId, $id);

        // Assert
        $this->assertNull($specialty);
    }

    public function testFindActiveByTenant(): void
    {
        // Arrange
        $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Ativa 1',
            'status' => 'active'
        ]);
        $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Ativa 2',
            'status' => 'active'
        ]);
        $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Inativa',
            'status' => 'inactive'
        ]);

        // Act
        $active = $this->model->findActiveByTenant($this->testTenantId);

        // Assert
        $this->assertCount(2, $active);
        foreach ($active as $specialty) {
            $this->assertEquals('active', $specialty['status']);
        }
    }

    public function testFindByTenant(): void
    {
        // Arrange
        $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Especialidade 1',
            'status' => 'active'
        ]);
        $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Especialidade 2',
            'status' => 'inactive'
        ]);

        // Act
        $all = $this->model->findByTenant($this->testTenantId);

        // Assert
        $this->assertCount(2, $all);
    }

    public function testSoftDelete(): void
    {
        // Arrange
        $id = $this->model->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Para Deletar',
            'status' => 'active'
        ]);

        // Act
        $this->model->delete($id);

        // Assert
        $deleted = $this->model->findById($id);
        $this->assertNull($deleted); // Soft delete remove da busca normal
    }
}


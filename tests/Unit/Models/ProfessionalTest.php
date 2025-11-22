<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use PDO;

/**
 * Testes unitários para Professional Model
 */
class ProfessionalTest extends TestCase
{
    private PDO $testDb;
    private Professional $model;
    private Tenant $tenantModel;
    private User $userModel;
    private int $testTenantId;
    private int $testUserId;

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
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                status VARCHAR(50) DEFAULT 'active',
                role VARCHAR(50) DEFAULT 'viewer',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        $this->testDb->exec("
            CREATE TABLE specialties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        $this->testDb->exec("
            CREATE TABLE professionals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                user_id INTEGER,
                specialty_id INTEGER,
                crmv VARCHAR(50),
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                role VARCHAR(50) DEFAULT 'veterinarian',
                status VARCHAR(50) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (specialty_id) REFERENCES specialties(id)
            )
        ");

        $this->tenantModel = new Tenant();
        $reflection = new \ReflectionClass($this->tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->tenantModel, $this->testDb);
        
        $this->testTenantId = $this->tenantModel->create('Test Clinic');

        $this->userModel = new User();
        $reflection = new \ReflectionClass($this->userModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->userModel, $this->testDb);

        $this->testUserId = $this->userModel->insert([
            'tenant_id' => $this->testTenantId,
            'email' => 'vet@example.com',
            'password_hash' => 'hash',
            'name' => 'Veterinário Teste'
        ]);

        $this->model = new Professional();
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

    public function testCreateProfessional(): void
    {
        // Arrange
        $data = [
            'user_id' => $this->testUserId,
            'name' => 'Dr. João Silva',
            'email' => 'joao@clinic.com',
            'crmv' => 'CRMV12345',
            'role' => 'veterinarian',
            'status' => 'active'
        ];

        // Act
        $id = $this->model->createOrUpdate($this->testTenantId, $data);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $professional = $this->model->findById($id);
        $this->assertNotNull($professional);
        $this->assertEquals('Dr. João Silva', $professional['name']);
        $this->assertEquals($this->testTenantId, $professional['tenant_id']);
    }

    public function testFindByTenantAndId(): void
    {
        // Arrange
        $id = $this->model->createOrUpdate($this->testTenantId, [
            'name' => 'Dr. Maria',
            'email' => 'maria@clinic.com'
        ]);

        // Act
        $professional = $this->model->findByTenantAndId($this->testTenantId, $id);

        // Assert
        $this->assertNotNull($professional);
        $this->assertEquals($id, $professional['id']);
    }

    public function testFindByUserId(): void
    {
        // Arrange
        $id = $this->model->createOrUpdate($this->testTenantId, [
            'user_id' => $this->testUserId,
            'name' => 'Dr. Teste'
        ]);

        // Act
        $professional = $this->model->findByUserId($this->testUserId);

        // Assert
        $this->assertNotNull($professional);
        $this->assertEquals($id, $professional['id']);
        $this->assertEquals($this->testUserId, $professional['user_id']);
    }

    public function testCreateOrUpdateWithInvalidTenant(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant com ID 99999 não encontrado');

        // Act
        $this->model->createOrUpdate(99999, ['name' => 'Test']);
    }

    public function testCreateOrUpdateWithInvalidUser(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Usuário com ID 99999 não encontrado');

        // Act
        $this->model->createOrUpdate($this->testTenantId, [
            'user_id' => 99999,
            'name' => 'Test'
        ]);
    }
}


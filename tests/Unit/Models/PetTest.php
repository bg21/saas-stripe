<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Pet;
use App\Models\Client;
use App\Models\Tenant;
use PDO;

/**
 * Testes unitários para Pet Model
 */
class PetTest extends TestCase
{
    private PDO $testDb;
    private Pet $model;
    private Client $clientModel;
    private Tenant $tenantModel;
    private int $testTenantId;
    private int $testClientId;

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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        $this->testDb->exec("
            CREATE TABLE pets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                species VARCHAR(100),
                breed VARCHAR(100),
                birth_date DATE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id),
                FOREIGN KEY (client_id) REFERENCES clients(id)
            )
        ");

        $this->tenantModel = new Tenant();
        $reflection = new \ReflectionClass($this->tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->tenantModel, $this->testDb);
        
        $this->testTenantId = $this->tenantModel->create('Test Clinic');

        $this->clientModel = new Client();
        $reflection = new \ReflectionClass($this->clientModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->clientModel, $this->testDb);

        $this->testClientId = $this->clientModel->createOrUpdate($this->testTenantId, [
            'name' => 'Test Client',
            'email' => 'client@example.com'
        ]);

        $this->model = new Pet();
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

    public function testCreatePet(): void
    {
        // Arrange
        $data = [
            'client_id' => $this->testClientId,
            'name' => 'Rex',
            'species' => 'Cachorro',
            'breed' => 'Labrador',
            'birth_date' => '2020-01-15'
        ];

        // Act
        $id = $this->model->createOrUpdate($this->testTenantId, $data);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $pet = $this->model->findById($id);
        $this->assertNotNull($pet);
        $this->assertEquals('Rex', $pet['name']);
        $this->assertEquals('Cachorro', $pet['species']);
        $this->assertEquals($this->testClientId, $pet['client_id']);
        $this->assertEquals($this->testTenantId, $pet['tenant_id']);
    }

    public function testFindByTenantAndId(): void
    {
        // Arrange
        $id = $this->model->createOrUpdate($this->testTenantId, [
            'client_id' => $this->testClientId,
            'name' => 'Mimi',
            'species' => 'Gato'
        ]);

        // Act
        $pet = $this->model->findByTenantAndId($this->testTenantId, $id);

        // Assert
        $this->assertNotNull($pet);
        $this->assertEquals($id, $pet['id']);
        $this->assertEquals('Mimi', $pet['name']);
    }

    public function testFindByClient(): void
    {
        // Arrange
        $this->model->createOrUpdate($this->testTenantId, [
            'client_id' => $this->testClientId,
            'name' => 'Pet 1'
        ]);
        $this->model->createOrUpdate($this->testTenantId, [
            'client_id' => $this->testClientId,
            'name' => 'Pet 2'
        ]);

        // Act
        $pets = $this->model->findByClient($this->testClientId);

        // Assert
        $this->assertCount(2, $pets);
        foreach ($pets as $pet) {
            $this->assertEquals($this->testClientId, $pet['client_id']);
        }
    }

    public function testCalculateAge(): void
    {
        // Arrange
        $birthDate = '2020-01-15';
        $currentYear = (int)date('Y');
        $expectedAge = $currentYear - 2020;

        // Act
        $age = $this->model->calculateAge($birthDate);

        // Assert
        $this->assertIsInt($age);
        $this->assertGreaterThanOrEqual($expectedAge, $age);
    }

    public function testCalculateAgeWithNull(): void
    {
        // Act
        $age = $this->model->calculateAge(null);

        // Assert
        $this->assertNull($age);
    }

    public function testCreateOrUpdateWithInvalidTenant(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant com ID 99999 não encontrado');

        // Act
        $this->model->createOrUpdate(99999, [
            'client_id' => $this->testClientId,
            'name' => 'Test'
        ]);
    }

    public function testCreateOrUpdateWithInvalidClient(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cliente com ID 99999 não encontrado');

        // Act
        $this->model->createOrUpdate($this->testTenantId, [
            'client_id' => 99999,
            'name' => 'Test'
        ]);
    }
}


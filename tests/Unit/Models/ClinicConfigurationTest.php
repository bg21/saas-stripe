<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\ClinicConfiguration;
use App\Models\Tenant;
use App\Utils\Database;
use PDO;

/**
 * Testes unitários para ClinicConfiguration Model
 */
class ClinicConfigurationTest extends TestCase
{
    private PDO $testDb;
    private ClinicConfiguration $model;
    private Tenant $tenantModel;
    private int $testTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria banco SQLite em memória
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cria tabelas
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
            CREATE TABLE clinic_configurations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                opening_hours TEXT,
                default_appointment_duration INTEGER DEFAULT 30,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        // Cria tenant de teste
        $this->tenantModel = new Tenant();
        $reflection = new \ReflectionClass($this->tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->tenantModel, $this->testDb);
        
        $this->testTenantId = $this->tenantModel->create('Test Clinic');
        
        // Injeta banco de teste no Tenant usado pelo ClinicConfiguration
        // Isso é necessário porque validateTenant() cria uma nova instância de Tenant
        // Vamos mockar o método validateTenant ou ajustar o modelo

        // Cria modelo de teste
        $this->model = new ClinicConfiguration();
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

    public function testFindByTenant(): void
    {
        // Arrange
        $openingHours = json_encode(['monday' => ['08:00', '18:00']]);
        $this->model->createOrUpdate($this->testTenantId, [
            'opening_hours' => $openingHours,
            'default_appointment_duration' => 30
        ]);

        // Act
        $config = $this->model->findByTenant($this->testTenantId);

        // Assert
        $this->assertNotNull($config);
        $this->assertEquals($this->testTenantId, $config['tenant_id']);
        $this->assertEquals($openingHours, $config['opening_hours']);
        $this->assertEquals(30, $config['default_appointment_duration']);
    }

    public function testFindByTenantNotFound(): void
    {
        // Act
        $config = $this->model->findByTenant(99999);

        // Assert
        $this->assertNull($config);
    }

    public function testCreateOrUpdateCreatesNew(): void
    {
        // Arrange
        $data = [
            'opening_hours' => json_encode(['monday' => ['08:00', '18:00']]),
            'default_appointment_duration' => 45
        ];

        // Act
        $id = $this->model->createOrUpdate($this->testTenantId, $data);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $config = $this->model->findById($id);
        $this->assertNotNull($config);
        $this->assertEquals($this->testTenantId, $config['tenant_id']);
        $this->assertEquals(45, $config['default_appointment_duration']);
    }

    public function testCreateOrUpdateUpdatesExisting(): void
    {
        // Arrange
        $id = $this->model->createOrUpdate($this->testTenantId, [
            'opening_hours' => json_encode(['monday' => ['08:00', '18:00']]),
            'default_appointment_duration' => 30
        ]);

        // Act
        $updatedId = $this->model->createOrUpdate($this->testTenantId, [
            'default_appointment_duration' => 60
        ]);

        // Assert
        $this->assertEquals($id, $updatedId);

        $config = $this->model->findById($id);
        $this->assertEquals(60, $config['default_appointment_duration']);
    }

    public function testCreateOrUpdateWithInvalidTenant(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant com ID 99999 não encontrado');

        // Act
        $this->model->createOrUpdate(99999, ['default_appointment_duration' => 30]);
    }
}


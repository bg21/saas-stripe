<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ClinicConfigurationController;
use App\Models\ClinicConfiguration;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;

/**
 * Testes unitários para ClinicConfigurationController
 */
class ClinicConfigurationControllerTest extends TestCase
{
    private ClinicConfigurationController $controller;
    private $mockConfigModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpa Flight
        Flight::clear();
        
        // Limpa output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Cria mock do model
        $this->mockConfigModel = $this->createMock(ClinicConfiguration::class);

        // Cria controller
        $this->controller = new ClinicConfigurationController($this->mockConfigModel);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Flight::clear();
        parent::tearDown();
    }

    public function testGetConfiguration(): void
    {
        // Arrange
        $tenantId = 1;
        $config = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'default_appointment_duration' => 30,
            'time_slot_interval' => 15
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false); // API Key auth não verifica permissões

        $this->mockConfigModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn($config);

        // Act
        ob_start();
        $this->controller->get();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetConfigurationNotFound(): void
    {
        // Arrange
        $tenantId = 1;
        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockConfigModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn(null);

        // Act
        ob_start();
        $this->controller->get();
        $output = ob_get_clean();

        // Assert
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdateConfiguration(): void
    {
        // Arrange
        $tenantId = 1;
        $updateData = [
            'default_appointment_duration' => 45,
            'time_slot_interval' => 30
        ];

        $updatedConfig = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'default_appointment_duration' => 45,
            'time_slot_interval' => 30
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        // Mock RequestCache::getJsonInput()
        $GLOBALS['__php_input_mock'] = json_encode($updateData);

        $this->mockConfigModel->expects($this->once())
            ->method('createOrUpdate')
            ->with($tenantId, $this->callback(function($data) use ($updateData) {
                return isset($data['default_appointment_duration']) && 
                       $data['default_appointment_duration'] == $updateData['default_appointment_duration'];
            }))
            ->willReturn(1);

        $this->mockConfigModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($updatedConfig);

        // Act
        ob_start();
        $this->controller->update();
        $output = ob_get_clean();

        // Assert
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success'] ?? false);
    }
}


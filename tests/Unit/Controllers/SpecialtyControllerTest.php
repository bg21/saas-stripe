<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\SpecialtyController;
use App\Models\Specialty;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use Flight;

/**
 * Testes unitários para SpecialtyController
 */
class SpecialtyControllerTest extends TestCase
{
    private SpecialtyController $controller;
    private $mockSpecialtyModel;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('TESTING')) {
            define('TESTING', true);
        }

        Flight::clear();
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Mock Flight::request() para query params
        $mockRequest = $this->createMock(\stdClass::class);
        $mockRequest->query = new \stdClass();
        Flight::request()->query = $mockRequest->query;

        $this->mockSpecialtyModel = $this->createMock(Specialty::class);
        $this->controller = new SpecialtyController($this->mockSpecialtyModel);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Flight::clear();
        unset($GLOBALS['__php_input_mock']);
        $_GET = [];
        \App\Utils\RequestCache::clear();
        parent::tearDown();
    }

    public function testListSpecialties(): void
    {
        $tenantId = 1;
        $specialties = [
            ['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Cardiologia', 'status' => 'active'],
            ['id' => 2, 'tenant_id' => $tenantId, 'name' => 'Dermatologia', 'status' => 'active']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId, [])
            ->willReturn($specialties);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListSpecialtiesWithStatusFilter(): void
    {
        $tenantId = 1;
        $specialties = [
            ['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Cardiologia', 'status' => 'active']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        
        // Mock query params usando $_GET já que Flight::request()->query usa $_GET
        $_GET['status'] = 'active';

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId, ['status' => 'active'])
            ->willReturn($specialties);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateSpecialty(): void
    {
        $tenantId = 1;
        $data = [
            'name' => 'Cardiologia',
            'description' => 'Especialidade em cardiologia',
            'status' => 'active'
        ];

        $specialtyId = 1;
        $createdSpecialty = [
            'id' => $specialtyId,
            'tenant_id' => $tenantId,
            'name' => 'Cardiologia',
            'description' => 'Especialidade em cardiologia',
            'status' => 'active'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        if (!defined('TESTING')) {
            define('TESTING', true);
        }
        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        $this->mockSpecialtyModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function($arg) use ($tenantId, $data) {
                return $arg['tenant_id'] === $tenantId && 
                       $arg['name'] === $data['name'];
            }))
            ->willReturn($specialtyId);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findById')
            ->with($specialtyId)
            ->willReturn($createdSpecialty);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateSpecialtyWithoutName(): void
    {
        $tenantId = 1;
        $data = ['description' => 'Descrição sem nome'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        if (!defined('TESTING')) {
            define('TESTING', true);
        }
        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testGetSpecialty(): void
    {
        $tenantId = 1;
        $specialtyId = 1;
        $specialty = [
            'id' => $specialtyId,
            'tenant_id' => $tenantId,
            'name' => 'Cardiologia',
            'status' => 'active'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $specialtyId)
            ->willReturn($specialty);

        ob_start();
        $this->controller->get($specialtyId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetSpecialtyNotFound(): void
    {
        $tenantId = 1;
        $specialtyId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $specialtyId)
            ->willReturn(null);

        ob_start();
        $this->controller->get($specialtyId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdateSpecialty(): void
    {
        $tenantId = 1;
        $specialtyId = 1;
        $existingSpecialty = [
            'id' => $specialtyId,
            'tenant_id' => $tenantId,
            'name' => 'Cardiologia',
            'status' => 'active'
        ];
        $updateData = ['name' => 'Cardiologia Veterinária', 'status' => 'inactive'];
        $updatedSpecialty = array_merge($existingSpecialty, $updateData);

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($updateData);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $specialtyId)
            ->willReturn($existingSpecialty);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('update')
            ->with($specialtyId, $this->callback(function($arg) use ($updateData) {
                return isset($arg['name']) && $arg['name'] === $updateData['name'];
            }));

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findById')
            ->with($specialtyId)
            ->willReturn($updatedSpecialty);

        ob_start();
        $this->controller->update($specialtyId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testUpdateSpecialtyNotFound(): void
    {
        $tenantId = 1;
        $specialtyId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $specialtyId)
            ->willReturn(null);

        ob_start();
        $this->controller->update($specialtyId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testDeleteSpecialty(): void
    {
        $tenantId = 1;
        $specialtyId = 1;
        $specialty = [
            'id' => $specialtyId,
            'tenant_id' => $tenantId,
            'name' => 'Cardiologia'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $specialtyId)
            ->willReturn($specialty);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('delete')
            ->with($specialtyId);

        ob_start();
        $this->controller->delete($specialtyId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testDeleteSpecialtyNotFound(): void
    {
        $tenantId = 1;
        $specialtyId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockSpecialtyModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $specialtyId)
            ->willReturn(null);

        ob_start();
        $this->controller->delete($specialtyId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }
}


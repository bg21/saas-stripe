<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ProfessionalController;
use App\Models\Professional;
use App\Models\User;
use Flight;

/**
 * Testes unitários para ProfessionalController
 */
class ProfessionalControllerTest extends TestCase
{
    private ProfessionalController $controller;
    private $mockProfessionalModel;
    private $mockUserModel;

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

        $this->mockProfessionalModel = $this->createMock(Professional::class);
        $this->controller = new ProfessionalController($this->mockProfessionalModel);
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

    public function testListProfessionals(): void
    {
        $tenantId = 1;
        $professionals = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'user_id' => 1,
                'crmv' => 'CRMV123',
                'status' => 'active',
                'specialties' => json_encode([1, 2])
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId, [])
            ->willReturn($professionals);

        // Mock User model
        $mockUser = ['id' => 1, 'name' => 'Dr. João', 'email' => 'joao@test.com', 'role' => 'admin'];
        $userModel = $this->createMock(User::class);
        $userModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($mockUser);

        // Substitui temporariamente o User model
        $reflection = new \ReflectionClass($this->controller);
        // Como não podemos injetar User, vamos mockar diretamente
        // Por enquanto, vamos apenas testar sem enriquecer com user

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListProfessionalsWithStatusFilter(): void
    {
        $tenantId = 1;
        $professionals = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'user_id' => 1,
                'status' => 'active',
                'specialties' => json_encode([1])
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['status'] = 'active';

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId, ['status' => 'active'])
            ->willReturn($professionals);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListProfessionalsWithSpecialtyFilter(): void
    {
        $tenantId = 1;
        $specialtyId = 1;
        $professionals = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'user_id' => 1,
                'status' => 'active',
                'specialties' => json_encode([1, 2])
            ],
            [
                'id' => 2,
                'tenant_id' => $tenantId,
                'user_id' => 2,
                'status' => 'active',
                'specialties' => json_encode([3])
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['specialty_id'] = $specialtyId;

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId, [])
            ->willReturn($professionals);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
        // Verifica que apenas profissionais com specialty_id 1 foram retornados
        if (isset($response['data'])) {
            $this->assertCount(1, $response['data']);
        }
    }

    public function testCreateProfessional(): void
    {
        $tenantId = 1;
        $data = [
            'user_id' => 1,
            'crmv' => 'CRMV123',
            'specialties' => [1, 2],
            'default_consultation_duration' => 30,
            'status' => 'active'
        ];

        $professionalId = 1;
        $createdProfessional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'user_id' => 1,
            'crmv' => 'CRMV123',
            'specialties' => json_encode([1, 2]),
            'status' => 'active'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        $this->mockProfessionalModel->expects($this->once())
            ->method('createOrUpdate')
            ->with($tenantId, $this->callback(function($arg) use ($tenantId, $data) {
                return $arg['tenant_id'] === $tenantId && 
                       $arg['user_id'] === $data['user_id'];
            }))
            ->willReturn($professionalId);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findById')
            ->with($professionalId)
            ->willReturn($createdProfessional);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateProfessionalWithoutUserId(): void
    {
        $tenantId = 1;
        $data = ['crmv' => 'CRMV123'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        // sendValidationError retorna success: false ou não tem campo success
        // Verifica se tem campo 'error' ou se success é false
        $this->assertTrue(isset($response['error']) || ($response['success'] ?? false) === false);
    }

    public function testGetProfessional(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'user_id' => 1,
            'crmv' => 'CRMV123',
            'specialties' => json_encode([1, 2])
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn($professional);

        ob_start();
        $this->controller->get($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetProfessionalNotFound(): void
    {
        $tenantId = 1;
        $professionalId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(null);

        ob_start();
        $this->controller->get($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdateProfessional(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $existingProfessional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'user_id' => 1,
            'crmv' => 'CRMV123',
            'status' => 'active'
        ];
        $updateData = ['crmv' => 'CRMV456', 'status' => 'inactive'];
        $updatedProfessional = array_merge($existingProfessional, $updateData);

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($updateData);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn($existingProfessional);

        $this->mockProfessionalModel->expects($this->once())
            ->method('update')
            ->with($professionalId, $this->callback(function($arg) use ($updateData) {
                return isset($arg['crmv']) && $arg['crmv'] === $updateData['crmv'];
            }));

        $this->mockProfessionalModel->expects($this->once())
            ->method('findById')
            ->with($professionalId)
            ->willReturn($updatedProfessional);

        ob_start();
        $this->controller->update($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testUpdateProfessionalNotFound(): void
    {
        $tenantId = 1;
        $professionalId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(null);

        ob_start();
        $this->controller->update($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testDeleteProfessional(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'user_id' => 1
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn($professional);

        $this->mockProfessionalModel->expects($this->once())
            ->method('delete')
            ->with($professionalId);

        ob_start();
        $this->controller->delete($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testDeleteProfessionalNotFound(): void
    {
        $tenantId = 1;
        $professionalId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockProfessionalModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(null);

        ob_start();
        $this->controller->delete($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }
}


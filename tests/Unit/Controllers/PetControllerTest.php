<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\PetController;
use App\Models\Pet;
use App\Models\Appointment;
use Flight;

/**
 * Testes unitários para PetController
 */
class PetControllerTest extends TestCase
{
    private PetController $controller;
    private $mockPetModel;

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

        $this->mockPetModel = $this->createMock(Pet::class);
        $this->controller = new PetController($this->mockPetModel);
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

    public function testListPets(): void
    {
        $tenantId = 1;
        $pets = [
            ['id' => 1, 'tenant_id' => $tenantId, 'client_id' => 1, 'name' => 'Rex', 'species' => 'Cachorro']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];
        
        // Mock Flight::request()->query como array
        $mockQuery = [];
        Flight::request()->query = $mockQuery;

        $this->mockPetModel->expects($this->once())
            ->method('findAllWithCount')
            ->willReturn(['data' => $pets, 'total' => 1]);

        $this->mockPetModel->expects($this->once())
            ->method('calculateAge')
            ->willReturn(2);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListPetsWithFilters(): void
    {
        $tenantId = 1;
        $pets = [
            ['id' => 1, 'tenant_id' => $tenantId, 'client_id' => 1, 'name' => 'Rex', 'species' => 'Cachorro']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['client_id'] = '1';
        $_GET['species'] = 'Cachorro';

        $this->mockPetModel->expects($this->once())
            ->method('findAllWithCount')
            ->willReturn(['data' => $pets, 'total' => 1]);

        $this->mockPetModel->expects($this->once())
            ->method('calculateAge')
            ->willReturn(2);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreatePet(): void
    {
        $tenantId = 1;
        $data = [
            'client_id' => 1,
            'name' => 'Rex',
            'species' => 'Cachorro',
            'breed' => 'Labrador',
            'gender' => 'male',
            'birth_date' => '2020-01-01'
        ];

        $petId = 1;
        $createdPet = [
            'id' => $petId,
            'tenant_id' => $tenantId,
            'client_id' => 1,
            'name' => 'Rex',
            'species' => 'Cachorro',
            'birth_date' => '2020-01-01'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        $this->mockPetModel->expects($this->once())
            ->method('createOrUpdate')
            ->willReturn($petId);

        $this->mockPetModel->expects($this->once())
            ->method('findById')
            ->with($petId)
            ->willReturn($createdPet);

        $this->mockPetModel->expects($this->once())
            ->method('calculateAge')
            ->willReturn(4);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreatePetWithoutName(): void
    {
        $tenantId = 1;
        $data = ['client_id' => 1, 'species' => 'Cachorro'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testCreatePetWithoutClientId(): void
    {
        $tenantId = 1;
        $data = ['name' => 'Rex', 'species' => 'Cachorro'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testCreatePetWithoutSpecies(): void
    {
        $tenantId = 1;
        $data = ['name' => 'Rex', 'client_id' => 1];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testGetPet(): void
    {
        $tenantId = 1;
        $petId = 1;
        $pet = [
            'id' => $petId,
            'tenant_id' => $tenantId,
            'client_id' => 1,
            'name' => 'Rex',
            'species' => 'Cachorro',
            'birth_date' => '2020-01-01'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockPetModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $petId)
            ->willReturn($pet);

        $this->mockPetModel->expects($this->once())
            ->method('calculateAge')
            ->willReturn(4);

        ob_start();
        $this->controller->get($petId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetPetNotFound(): void
    {
        $tenantId = 1;
        $petId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockPetModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $petId)
            ->willReturn(null);

        ob_start();
        $this->controller->get($petId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdatePet(): void
    {
        $tenantId = 1;
        $petId = 1;
        $existingPet = [
            'id' => $petId,
            'tenant_id' => $tenantId,
            'client_id' => 1,
            'name' => 'Rex',
            'species' => 'Cachorro'
        ];
        $updateData = ['name' => 'Rex Atualizado', 'weight' => 25.5];
        $updatedPet = array_merge($existingPet, $updateData);

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($updateData);

        $this->mockPetModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $petId)
            ->willReturn($existingPet);

        $this->mockPetModel->expects($this->once())
            ->method('update')
            ->with($petId, $this->callback(function($arg) use ($updateData) {
                return isset($arg['name']) && $arg['name'] === $updateData['name'];
            }));

        $this->mockPetModel->expects($this->once())
            ->method('findById')
            ->with($petId)
            ->willReturn($updatedPet);

        ob_start();
        $this->controller->update($petId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testDeletePet(): void
    {
        $tenantId = 1;
        $petId = 1;
        $pet = [
            'id' => $petId,
            'tenant_id' => $tenantId,
            'client_id' => 1,
            'name' => 'Rex'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockPetModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $petId)
            ->willReturn($pet);

        $this->mockPetModel->expects($this->once())
            ->method('delete')
            ->with($petId);

        ob_start();
        $this->controller->delete($petId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListAppointments(): void
    {
        $tenantId = 1;
        $petId = 1;
        $pet = [
            'id' => $petId,
            'tenant_id' => $tenantId,
            'client_id' => 1,
            'name' => 'Rex'
        ];
        $appointments = [
            ['id' => 1, 'pet_id' => $petId, 'appointment_date' => '2024-01-15']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockPetModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $petId)
            ->willReturn($pet);

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->once())
            ->method('findByPet')
            ->with($petId)
            ->willReturn($appointments);

        ob_start();
        $this->controller->listAppointments($petId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }
}


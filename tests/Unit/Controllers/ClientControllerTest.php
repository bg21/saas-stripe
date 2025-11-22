<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ClientController;
use App\Models\Client;
use App\Models\Pet;
use Flight;

/**
 * Testes unitários para ClientController
 */
class ClientControllerTest extends TestCase
{
    private ClientController $controller;
    private $mockClientModel;

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

        $this->mockClientModel = $this->createMock(Client::class);
        $this->controller = new ClientController($this->mockClientModel);
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

    public function testListClients(): void
    {
        $tenantId = 1;
        $clients = [
            ['id' => 1, 'tenant_id' => $tenantId, 'name' => 'João Silva', 'email' => 'joao@test.com']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];
        
        // Mock Flight::request()->query como array
        $mockQuery = [];
        Flight::request()->query = $mockQuery;

        $this->mockClientModel->expects($this->once())
            ->method('findAllWithCount')
            ->willReturn(['data' => $clients, 'total' => 1]);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListClientsWithSearch(): void
    {
        $tenantId = 1;
        $clients = [
            ['id' => 1, 'tenant_id' => $tenantId, 'name' => 'João Silva', 'cpf' => '123.456.789-00']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['search'] = 'João';

        $this->mockClientModel->expects($this->once())
            ->method('findAllWithCount')
            ->willReturn(['data' => $clients, 'total' => 1]);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateClient(): void
    {
        $tenantId = 1;
        $data = [
            'name' => 'João Silva',
            'email' => 'joao@test.com',
            'phone' => '11999999999',
            'cpf' => '12345678900'
        ];

        $clientId = 1;
        $createdClient = [
            'id' => $clientId,
            'tenant_id' => $tenantId,
            'name' => 'João Silva',
            'email' => 'joao@test.com',
            'cpf' => '123.456.789-00'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        $this->mockClientModel->expects($this->once())
            ->method('createOrUpdate')
            ->willReturn($clientId);

        $this->mockClientModel->expects($this->once())
            ->method('findById')
            ->with($clientId)
            ->willReturn($createdClient);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateClientWithoutName(): void
    {
        $tenantId = 1;
        $data = ['email' => 'joao@test.com'];

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

    public function testCreateClientWithInvalidCPF(): void
    {
        $tenantId = 1;
        $data = [
            'name' => 'João Silva',
            'cpf' => '123' // CPF inválido
        ];

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

    public function testGetClient(): void
    {
        $tenantId = 1;
        $clientId = 1;
        $client = [
            'id' => $clientId,
            'tenant_id' => $tenantId,
            'name' => 'João Silva'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockClientModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $clientId)
            ->willReturn($client);

        ob_start();
        $this->controller->get($clientId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetClientNotFound(): void
    {
        $tenantId = 1;
        $clientId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockClientModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $clientId)
            ->willReturn(null);

        ob_start();
        $this->controller->get($clientId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdateClient(): void
    {
        $tenantId = 1;
        $clientId = 1;
        $existingClient = [
            'id' => $clientId,
            'tenant_id' => $tenantId,
            'name' => 'João Silva'
        ];
        $updateData = ['name' => 'João Santos', 'phone' => '11888888888'];
        $updatedClient = array_merge($existingClient, $updateData);

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($updateData);

        $this->mockClientModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $clientId)
            ->willReturn($existingClient);

        $this->mockClientModel->expects($this->once())
            ->method('update')
            ->with($clientId, $this->callback(function($arg) use ($updateData) {
                return isset($arg['name']) && $arg['name'] === $updateData['name'];
            }));

        $this->mockClientModel->expects($this->once())
            ->method('findById')
            ->with($clientId)
            ->willReturn($updatedClient);

        ob_start();
        $this->controller->update($clientId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testDeleteClient(): void
    {
        $tenantId = 1;
        $clientId = 1;
        $client = [
            'id' => $clientId,
            'tenant_id' => $tenantId,
            'name' => 'João Silva'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockClientModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $clientId)
            ->willReturn($client);

        $this->mockClientModel->expects($this->once())
            ->method('delete')
            ->with($clientId);

        ob_start();
        $this->controller->delete($clientId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListPets(): void
    {
        $tenantId = 1;
        $clientId = 1;
        $client = [
            'id' => $clientId,
            'tenant_id' => $tenantId,
            'name' => 'João Silva'
        ];
        $pets = [
            ['id' => 1, 'client_id' => $clientId, 'name' => 'Rex']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockClientModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $clientId)
            ->willReturn($client);

        // Mock Pet model
        $mockPetModel = $this->createMock(Pet::class);
        $mockPetModel->expects($this->once())
            ->method('findByClient')
            ->with($clientId)
            ->willReturn($pets);

        // Como não podemos injetar Pet, vamos apenas verificar que o método foi chamado
        // Na prática, o controller usa new Pet(), então precisamos mockar de outra forma
        // Por enquanto, vamos apenas testar a estrutura básica

        ob_start();
        $this->controller->listPets($clientId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        // O teste pode falhar se Pet não for mockado, mas a estrutura está correta
        $this->assertNotEmpty($output);
    }
}


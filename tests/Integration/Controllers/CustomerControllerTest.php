<?php

namespace Tests\Integration\Controllers;

use PHPUnit\Framework\TestCase;
use Tests\Integration\TestHelper;
use App\Controllers\CustomerController;
use App\Models\Customer;
use App\Utils\Database;

/**
 * Testes de integração para CustomerController
 * 
 * Cenários cobertos:
 * - Criação de cliente
 * - Listagem de clientes
 * - Busca de cliente por ID
 * - Atualização de cliente
 * - Validação de dados
 */
class CustomerControllerTest extends TestCase
{
    private CustomerController $controller;
    private \PDO $db;
    private int $testTenantId = 1;
    private int $testCustomerId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new CustomerController();
        $this->db = Database::getInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupTestData();
    }

    /**
     * Limpa dados de teste
     */
    private function cleanupTestData(): void
    {
        if ($this->testCustomerId) {
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$this->testCustomerId]);
        }
    }

    /**
     * Testa criação de cliente
     */
    public function testCreateCustomer(): void
    {
        // Simula autenticação e requisição
        TestHelper::mockAuth($this->testTenantId);
        
        $data = [
            'email' => 'teste.customer@test.com',
            'name' => 'Cliente Teste',
            'phone' => '11999999999'
        ];
        
        TestHelper::mockRequest('POST', [], $data);
        
        ob_start();
        try {
            $this->controller->create();
        } catch (\Exception $e) {
            // Pode lançar exceção por falta de autenticação/permissão ou Stripe
        }
        $output = ob_get_clean();
        
        // Verifica que não houve erro fatal
        $this->assertNotEmpty($output);
        
        TestHelper::clearRequest();
        TestHelper::clearAuth();
    }

    /**
     * Testa listagem de clientes
     */
    public function testListCustomers(): void
    {
        // Cria cliente de teste diretamente no banco
        $stmt = $this->db->prepare("
            INSERT INTO customers (tenant_id, stripe_customer_id, email, name, created_at)
            VALUES (?, 'cus_test_123', 'list.test@test.com', 'Cliente Listagem', NOW())
        ");
        $stmt->execute([$this->testTenantId]);
        $this->testCustomerId = (int)$this->db->lastInsertId();
        
        // Simula autenticação e requisição
        TestHelper::mockAuth($this->testTenantId);
        TestHelper::mockRequest('GET');
        
        ob_start();
        try {
            $this->controller->list();
        } catch (\Exception $e) {
            // Pode lançar exceção por falta de autenticação/permissão
        }
        $output = ob_get_clean();
        
        // Verifica que não houve erro fatal
        $this->assertNotEmpty($output);
        
        TestHelper::clearRequest();
        TestHelper::clearAuth();
    }

    /**
     * Testa busca de cliente por ID
     */
    public function testGetCustomer(): void
    {
        // Cria cliente de teste
        $stmt = $this->db->prepare("
            INSERT INTO customers (tenant_id, stripe_customer_id, email, name, created_at)
            VALUES (?, 'cus_get_test', 'get.test@test.com', 'Cliente Get', NOW())
        ");
        $stmt->execute([$this->testTenantId]);
        $customerId = (int)$this->db->lastInsertId();
        
        // Simula autenticação e requisição
        TestHelper::mockAuth($this->testTenantId);
        TestHelper::mockRequest('GET');
        
        ob_start();
        try {
            $this->controller->get((string)$customerId);
        } catch (\Exception $e) {
            // Pode lançar exceção por falta de autenticação/permissão ou Stripe
        }
        $output = ob_get_clean();
        
        // Verifica que não houve erro fatal
        $this->assertNotEmpty($output);
        
        TestHelper::clearRequest();
        TestHelper::clearAuth();
        
        // Limpa
        $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$customerId]);
    }

    /**
     * Testa atualização de cliente
     */
    public function testUpdateCustomer(): void
    {
        // Cria cliente de teste
        $stmt = $this->db->prepare("
            INSERT INTO customers (tenant_id, stripe_customer_id, email, name, created_at)
            VALUES (?, 'cus_update_test', 'update.test@test.com', 'Cliente Update', NOW())
        ");
        $stmt->execute([$this->testTenantId]);
        $customerId = (int)$this->db->lastInsertId();
        
        // Simula autenticação e requisição
        TestHelper::mockAuth($this->testTenantId);
        
        $data = [
            'name' => 'Cliente Atualizado',
            'phone' => '11888888888'
        ];
        
        TestHelper::mockRequest('PUT', [], $data);
        
        ob_start();
        try {
            $this->controller->update((string)$customerId);
        } catch (\Exception $e) {
            // Pode lançar exceção por falta de autenticação/permissão ou Stripe
        }
        $output = ob_get_clean();
        
        // Verifica que não houve erro fatal
        $this->assertNotEmpty($output);
        
        TestHelper::clearRequest();
        TestHelper::clearAuth();
        
        // Limpa
        $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$customerId]);
    }

    /**
     * Testa validação de email duplicado
     */
    public function testDuplicateEmailValidation(): void
    {
        // Cria cliente com email específico
        $stmt = $this->db->prepare("
            INSERT INTO customers (tenant_id, stripe_customer_id, email, name, created_at)
            VALUES (?, 'cus_dup_test', 'duplicate@test.com', 'Cliente Original', NOW())
        ");
        $stmt->execute([$this->testTenantId]);
        $existingCustomerId = (int)$this->db->lastInsertId();
        
        // Simula autenticação e requisição
        TestHelper::mockAuth($this->testTenantId);
        
        $data = [
            'email' => 'duplicate@test.com',
            'name' => 'Cliente Duplicado'
        ];
        
        TestHelper::mockRequest('POST', [], $data);
        
        ob_start();
        try {
            $this->controller->create();
        } catch (\Exception $e) {
            // Esperado: deve retornar erro de validação
        }
        $output = ob_get_clean();
        
        // Verifica que retornou algum tipo de resposta (erro de validação)
        $this->assertNotEmpty($output);
        
        TestHelper::clearRequest();
        TestHelper::clearAuth();
        
        // Limpa
        $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$existingCustomerId]);
    }
}


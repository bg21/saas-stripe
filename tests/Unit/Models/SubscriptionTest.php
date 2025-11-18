<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Subscription;
use PDO;

/**
 * Testes unitários para o Model Subscription
 * 
 * Cenários cobertos:
 * - Busca por Stripe Subscription ID
 * - Busca por tenant com paginação e filtros
 * - Busca por tenant e ID (proteção IDOR)
 * - Busca por customer
 * - Estatísticas por tenant
 * - createOrUpdate (upsert)
 */
class SubscriptionTest extends TestCase
{
    private PDO $testDb;
    private Subscription $subscriptionModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria banco SQLite em memória
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cria tabela de subscriptions
        $this->testDb->exec("
            CREATE TABLE subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                customer_id INTEGER NOT NULL,
                stripe_subscription_id VARCHAR(255) UNIQUE NOT NULL,
                stripe_customer_id VARCHAR(255),
                status VARCHAR(50) DEFAULT 'incomplete',
                plan_id VARCHAR(255),
                plan_name VARCHAR(255),
                amount DECIMAL(10,2),
                currency VARCHAR(10) DEFAULT 'USD',
                current_period_start DATETIME,
                current_period_end DATETIME,
                cancel_at_period_end INTEGER DEFAULT 0,
                metadata TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Cria modelo de teste com banco customizado
        $this->subscriptionModel = new Subscription();
        $reflection = new \ReflectionClass($this->subscriptionModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->subscriptionModel, $this->testDb);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->testDb);
    }

    /**
     * Testa busca de assinatura por Stripe ID
     */
    public function testFindByStripeId(): void
    {
        // Arrange
        $tenantId = 1;
        $customerId = 1;
        $stripeSubscriptionId = 'sub_test123456789';
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'status' => 'active'
        ]);

        // Act
        $subscription = $this->subscriptionModel->findByStripeId($stripeSubscriptionId);

        // Assert
        $this->assertNotNull($subscription);
        $this->assertEquals($stripeSubscriptionId, $subscription['stripe_subscription_id']);
        $this->assertEquals('active', $subscription['status']);
    }

    /**
     * Testa busca de assinatura por Stripe ID inexistente
     */
    public function testFindByStripeIdWithNonExistentId(): void
    {
        // Act
        $subscription = $this->subscriptionModel->findByStripeId('sub_nonexistent');

        // Assert
        $this->assertNull($subscription);
    }

    /**
     * Testa busca por tenant e ID (proteção IDOR)
     */
    public function testFindByTenantAndId(): void
    {
        // Arrange
        $tenantId1 = 1;
        $tenantId2 = 2;
        $customerId1 = 1;
        $customerId2 = 2;

        $id1 = $this->subscriptionModel->insert([
            'tenant_id' => $tenantId1,
            'customer_id' => $customerId1,
            'stripe_subscription_id' => 'sub_test1',
            'status' => 'active'
        ]);

        $id2 = $this->subscriptionModel->insert([
            'tenant_id' => $tenantId2,
            'customer_id' => $customerId2,
            'stripe_subscription_id' => 'sub_test2',
            'status' => 'active'
        ]);

        // Act
        $subscription1 = $this->subscriptionModel->findByTenantAndId($tenantId1, $id1);
        $subscription2 = $this->subscriptionModel->findByTenantAndId($tenantId2, $id2);
        $wrongTenant = $this->subscriptionModel->findByTenantAndId($tenantId2, $id1);

        // Assert
        $this->assertNotNull($subscription1);
        $this->assertEquals($id1, $subscription1['id']);
        $this->assertEquals($tenantId1, $subscription1['tenant_id']);

        $this->assertNotNull($subscription2);
        $this->assertEquals($id2, $subscription2['id']);
        $this->assertEquals($tenantId2, $subscription2['tenant_id']);

        // Proteção IDOR: não deve retornar assinatura de outro tenant
        $this->assertNull($wrongTenant);
    }

    /**
     * Testa busca por tenant com paginação
     */
    public function testFindByTenantWithPagination(): void
    {
        // Arrange
        $tenantId = 1;
        for ($i = 1; $i <= 25; $i++) {
            $this->subscriptionModel->insert([
                'tenant_id' => $tenantId,
                'customer_id' => $i,
                'stripe_subscription_id' => "sub_test{$i}",
                'status' => 'active'
            ]);
        }

        // Act
        $result = $this->subscriptionModel->findByTenant($tenantId, 1, 10);

        // Assert
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('limit', $result);
        $this->assertArrayHasKey('total_pages', $result);
        $this->assertCount(10, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(10, $result['limit']);
        $this->assertEquals(3, $result['total_pages']);
    }

    /**
     * Testa busca por tenant com filtro de status
     */
    public function testFindByTenantWithStatusFilter(): void
    {
        // Arrange
        $tenantId = 1;
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 1,
            'stripe_subscription_id' => 'sub_active1',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 2,
            'stripe_subscription_id' => 'sub_canceled1',
            'status' => 'canceled'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 3,
            'stripe_subscription_id' => 'sub_active2',
            'status' => 'active'
        ]);

        // Act
        $result = $this->subscriptionModel->findByTenant($tenantId, 1, 20, ['status' => 'active']);

        // Assert
        $this->assertEquals(2, $result['total']);
        foreach ($result['data'] as $subscription) {
            $this->assertEquals('active', $subscription['status']);
        }
    }

    /**
     * Testa busca por tenant com filtro de customer
     */
    public function testFindByTenantWithCustomerFilter(): void
    {
        // Arrange
        $tenantId = 1;
        $customerId1 = 1;
        $customerId2 = 2;

        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId1,
            'stripe_subscription_id' => 'sub_customer1_1',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId1,
            'stripe_subscription_id' => 'sub_customer1_2',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId2,
            'stripe_subscription_id' => 'sub_customer2_1',
            'status' => 'active'
        ]);

        // Act
        $result = $this->subscriptionModel->findByTenant($tenantId, 1, 20, ['customer' => $customerId1]);

        // Assert
        $this->assertEquals(2, $result['total']);
        foreach ($result['data'] as $subscription) {
            $this->assertEquals($customerId1, $subscription['customer_id']);
        }
    }

    /**
     * Testa busca de assinaturas por customer
     */
    public function testFindByCustomer(): void
    {
        // Arrange
        $tenantId = 1;
        $customerId1 = 1;
        $customerId2 = 2;

        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId1,
            'stripe_subscription_id' => 'sub_customer1_1',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId1,
            'stripe_subscription_id' => 'sub_customer1_2',
            'status' => 'canceled'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId2,
            'stripe_subscription_id' => 'sub_customer2_1',
            'status' => 'active'
        ]);

        // Act
        $subscriptions = $this->subscriptionModel->findByCustomer($customerId1);

        // Assert
        $this->assertCount(2, $subscriptions);
        foreach ($subscriptions as $subscription) {
            $this->assertEquals($customerId1, $subscription['customer_id']);
        }
    }

    /**
     * Testa estatísticas por tenant
     */
    public function testGetStatsByTenant(): void
    {
        // Arrange
        $tenantId = 1;
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 1,
            'stripe_subscription_id' => 'sub_active1',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 2,
            'stripe_subscription_id' => 'sub_active2',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 3,
            'stripe_subscription_id' => 'sub_canceled1',
            'status' => 'canceled'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 4,
            'stripe_subscription_id' => 'sub_trialing1',
            'status' => 'trialing'
        ]);

        // Act
        $stats = $this->subscriptionModel->getStatsByTenant($tenantId);

        // Assert
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('canceled', $stats);
        $this->assertArrayHasKey('trialing', $stats);
        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['canceled']);
        $this->assertEquals(1, $stats['trialing']);
    }

    /**
     * Testa estatísticas por tenant com filtro de customer
     */
    public function testGetStatsByTenantWithCustomerFilter(): void
    {
        // Arrange
        $tenantId = 1;
        $customerId = 1;

        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'stripe_subscription_id' => 'sub_active1',
            'status' => 'active'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'stripe_subscription_id' => 'sub_canceled1',
            'status' => 'canceled'
        ]);
        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => 2,
            'stripe_subscription_id' => 'sub_active2',
            'status' => 'active'
        ]);

        // Act
        $stats = $this->subscriptionModel->getStatsByTenant($tenantId, ['customer' => $customerId]);

        // Assert
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['canceled']);
    }

    /**
     * Testa createOrUpdate quando assinatura não existe (cria)
     */
    public function testCreateOrUpdateWhenSubscriptionDoesNotExist(): void
    {
        // Arrange
        $tenantId = 1;
        $customerId = 1;
        $stripeData = [
            'id' => 'sub_new123',
            'customer' => 'cus_test123',
            'status' => 'active',
            'currency' => 'usd',
            'items' => [
                'data' => [[
                    'price' => [
                        'id' => 'price_test123',
                        'nickname' => 'Test Plan',
                        'unit_amount' => 1000
                    ]
                ]]
            ],
            'current_period_start' => time(),
            'current_period_end' => time() + 86400,
            'cancel_at_period_end' => false,
            'metadata' => ['key' => 'value']
        ];

        // Act
        $id = $this->subscriptionModel->createOrUpdate($tenantId, $customerId, $stripeData);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $subscription = $this->subscriptionModel->findById($id);
        $this->assertNotNull($subscription);
        $this->assertEquals('sub_new123', $subscription['stripe_subscription_id']);
        $this->assertEquals('active', $subscription['status']);
        $this->assertEquals('price_test123', $subscription['plan_id']);
        $this->assertEquals('Test Plan', $subscription['plan_name']);
    }

    /**
     * Testa createOrUpdate quando assinatura existe (atualiza)
     */
    public function testCreateOrUpdateWhenSubscriptionExists(): void
    {
        // Arrange
        $tenantId = 1;
        $customerId = 1;
        $stripeSubscriptionId = 'sub_existing123';
        
        $originalId = $this->subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'status' => 'active',
            'plan_id' => 'price_old'
        ]);

        $stripeData = [
            'id' => $stripeSubscriptionId,
            'customer' => 'cus_test123',
            'status' => 'canceled',
            'currency' => 'usd',
            'items' => [
                'data' => [[
                    'price' => [
                        'id' => 'price_new',
                        'nickname' => 'New Plan',
                        'unit_amount' => 2000
                    ]
                ]]
            ],
            'current_period_start' => time(),
            'current_period_end' => time() + 86400,
            'cancel_at_period_end' => true
        ];

        // Act
        $id = $this->subscriptionModel->createOrUpdate($tenantId, $customerId, $stripeData);

        // Assert
        $this->assertEquals($originalId, $id);

        $subscription = $this->subscriptionModel->findById($id);
        $this->assertEquals('canceled', $subscription['status']);
        $this->assertEquals('price_new', $subscription['plan_id']);
        $this->assertEquals('New Plan', $subscription['plan_name']);
        $this->assertEquals(1, $subscription['cancel_at_period_end']);
    }

    /**
     * Testa que assinaturas de tenants diferentes são isoladas
     */
    public function testTenantIsolation(): void
    {
        // Arrange
        $tenantId1 = 1;
        $tenantId2 = 2;

        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId1,
            'customer_id' => 1,
            'stripe_subscription_id' => 'sub_tenant1',
            'status' => 'active'
        ]);

        $this->subscriptionModel->insert([
            'tenant_id' => $tenantId2,
            'customer_id' => 2,
            'stripe_subscription_id' => 'sub_tenant2',
            'status' => 'active'
        ]);

        // Act
        $subscriptions1 = $this->subscriptionModel->findByTenant($tenantId1);
        $subscriptions2 = $this->subscriptionModel->findByTenant($tenantId2);

        // Assert
        $this->assertEquals(1, $subscriptions1['total']);
        $this->assertEquals(1, $subscriptions2['total']);
        $this->assertEquals('sub_tenant1', $subscriptions1['data'][0]['stripe_subscription_id']);
        $this->assertEquals('sub_tenant2', $subscriptions2['data'][0]['stripe_subscription_id']);
    }
}


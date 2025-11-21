<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\UserSession;
use App\Models\User;
use App\Models\Tenant;
use PDO;

/**
 * Testes unitários para o Model UserSession
 * 
 * Cenários cobertos:
 * - Criação de sessão
 * - Validação de sessão válida
 * - Validação de sessão expirada
 * - Validação de sessão com usuário inativo
 * - Validação de sessão com tenant inativo
 * - Remoção de sessão (logout)
 * - Remoção de todas as sessões de um usuário
 * - Limpeza de sessões expiradas
 * - Busca por session ID
 */
class UserSessionTest extends TestCase
{
    private PDO $testDb;
    private UserSession $sessionModel;
    private User $userModel;
    private Tenant $tenantModel;

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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
                UNIQUE(tenant_id, email),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        $this->testDb->exec("
            CREATE TABLE user_sessions (
                id VARCHAR(64) PRIMARY KEY,
                user_id INTEGER NOT NULL,
                tenant_id INTEGER NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
            )
        ");

        // Cria modelos com banco customizado
        $this->tenantModel = new Tenant();
        $this->userModel = new User();
        $this->sessionModel = new UserSession();

        // Injeta banco de teste nos modelos
        $reflection = new \ReflectionClass($this->tenantModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->tenantModel, $this->testDb);

        $reflection = new \ReflectionClass($this->userModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->userModel, $this->testDb);

        $reflection = new \ReflectionClass($this->sessionModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->sessionModel, $this->testDb);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->testDb);
    }

    /**
     * Testa criação de sessão
     */
    public function testCreate(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123', 'Test User');

        // Act
        $sessionId = $this->sessionModel->create($userId, $tenantId, '127.0.0.1', 'Mozilla/5.0', 24);

        // Assert
        $this->assertNotEmpty($sessionId);
        $this->assertEquals(64, strlen($sessionId), 'Session ID deve ter 64 caracteres (32 bytes em hex)');

        // Verifica se sessão foi salva no banco
        $session = $this->sessionModel->findBySessionId($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals($userId, $session['user_id']);
        $this->assertEquals($tenantId, $session['tenant_id']);
        $this->assertEquals('127.0.0.1', $session['ip_address']);
        $this->assertEquals('Mozilla/5.0', $session['user_agent']);
    }

    /**
     * Testa criação de sessão com duração customizada
     */
    public function testCreateWithCustomHours(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');

        // Act
        $sessionId = $this->sessionModel->create($userId, $tenantId, null, null, 48); // 48 horas

        // Assert
        $session = $this->sessionModel->findBySessionId($sessionId);
        $this->assertNotNull($session);
        
        // Verifica que expires_at está aproximadamente 48 horas no futuro
        $expiresAt = strtotime($session['expires_at']);
        $expectedExpires = time() + (48 * 3600);
        $this->assertLessThan(60, abs($expiresAt - $expectedExpires), 'Expires_at deve estar aproximadamente 48 horas no futuro');
    }

    /**
     * Testa validação de sessão válida
     */
    public function testValidateValidSession(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123', 'Test User', 'admin');
        $sessionId = $this->sessionModel->create($userId, $tenantId);

        // Act
        $session = $this->sessionModel->validate($sessionId);

        // Assert
        $this->assertNotNull($session);
        $this->assertEquals($userId, $session['user_id']);
        $this->assertEquals($tenantId, $session['tenant_id']);
        $this->assertEquals('test@example.com', $session['email']);
        $this->assertEquals('Test User', $session['name']);
        $this->assertEquals('admin', $session['role']);
        $this->assertEquals('Test Tenant', $session['tenant_name']);
    }

    /**
     * Testa validação de sessão inexistente
     */
    public function testValidateNonExistentSession(): void
    {
        // Arrange
        $invalidSessionId = bin2hex(random_bytes(32));

        // Act
        $session = $this->sessionModel->validate($invalidSessionId);

        // Assert
        $this->assertNull($session);
    }

    /**
     * Testa validação de sessão expirada
     */
    public function testValidateExpiredSession(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        
        // Cria sessão com expiração no passado
        $sessionId = bin2hex(random_bytes(32));
        $this->testDb->exec("
            INSERT INTO user_sessions (id, user_id, tenant_id, expires_at)
            VALUES ('{$sessionId}', {$userId}, {$tenantId}, datetime('now', '-1 hour'))
        ");

        // Act
        $session = $this->sessionModel->validate($sessionId);

        // Assert
        $this->assertNull($session, 'Sessão expirada não deve ser válida');
    }

    /**
     * Testa validação de sessão com usuário inativo
     */
    public function testValidateSessionWithInactiveUser(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        $this->userModel->update($userId, ['status' => 'inactive']);
        $sessionId = $this->sessionModel->create($userId, $tenantId);

        // Act
        $session = $this->sessionModel->validate($sessionId);

        // Assert
        $this->assertNull($session, 'Sessão com usuário inativo não deve ser válida');
    }

    /**
     * Testa validação de sessão com tenant inativo
     */
    public function testValidateSessionWithInactiveTenant(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $this->tenantModel->update($tenantId, ['status' => 'inactive']);
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        $sessionId = $this->sessionModel->create($userId, $tenantId);

        // Act
        $session = $this->sessionModel->validate($sessionId);

        // Assert
        $this->assertNull($session, 'Sessão com tenant inativo não deve ser válida');
    }

    /**
     * Testa remoção de sessão (logout)
     */
    public function testDeleteSession(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        $sessionId = $this->sessionModel->create($userId, $tenantId);

        // Verifica que sessão existe
        $session = $this->sessionModel->findBySessionId($sessionId);
        $this->assertNotNull($session);

        // Act
        $deleted = $this->sessionModel->deleteSession($sessionId);

        // Assert
        $this->assertTrue($deleted);
        
        // Verifica que sessão foi removida
        $session = $this->sessionModel->findBySessionId($sessionId);
        $this->assertNull($session);
    }

    /**
     * Testa remoção de sessão inexistente
     */
    public function testDeleteNonExistentSession(): void
    {
        // Arrange
        $invalidSessionId = bin2hex(random_bytes(32));

        // Act
        $deleted = $this->sessionModel->deleteSession($invalidSessionId);

        // Assert
        $this->assertFalse($deleted);
    }

    /**
     * Testa remoção de todas as sessões de um usuário
     */
    public function testDeleteByUser(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        
        // Cria múltiplas sessões
        $sessionId1 = $this->sessionModel->create($userId, $tenantId);
        $sessionId2 = $this->sessionModel->create($userId, $tenantId);
        $sessionId3 = $this->sessionModel->create($userId, $tenantId);

        // Act
        $deleted = $this->sessionModel->deleteByUser($userId);

        // Assert
        $this->assertTrue($deleted);
        
        // Verifica que todas as sessões foram removidas
        $this->assertNull($this->sessionModel->findBySessionId($sessionId1));
        $this->assertNull($this->sessionModel->findBySessionId($sessionId2));
        $this->assertNull($this->sessionModel->findBySessionId($sessionId3));
    }

    /**
     * Testa limpeza de sessões expiradas
     */
    public function testCleanExpired(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        
        // Cria sessão válida
        $validSessionId = $this->sessionModel->create($userId, $tenantId);
        
        // Cria sessões expiradas manualmente
        $expiredSessionId1 = bin2hex(random_bytes(32));
        $expiredSessionId2 = bin2hex(random_bytes(32));
        $this->testDb->exec("
            INSERT INTO user_sessions (id, user_id, tenant_id, expires_at)
            VALUES 
                ('{$expiredSessionId1}', {$userId}, {$tenantId}, datetime('now', '-1 hour')),
                ('{$expiredSessionId2}', {$userId}, {$tenantId}, datetime('now', '-2 hours'))
        ");

        // Act
        $cleaned = $this->sessionModel->cleanExpired();

        // Assert
        $this->assertEquals(2, $cleaned, 'Deve limpar 2 sessões expiradas');
        
        // Verifica que sessões expiradas foram removidas
        $this->assertNull($this->sessionModel->findBySessionId($expiredSessionId1));
        $this->assertNull($this->sessionModel->findBySessionId($expiredSessionId2));
        
        // Verifica que sessão válida ainda existe
        $this->assertNotNull($this->sessionModel->findBySessionId($validSessionId));
    }

    /**
     * Testa busca por session ID
     */
    public function testFindBySessionId(): void
    {
        // Arrange
        $tenantId = $this->tenantModel->create('Test Tenant', 'test_api_key_123456789012345678901234567890123456789012345678901234567890');
        $userId = $this->userModel->create($tenantId, 'test@example.com', 'password123');
        $sessionId = $this->sessionModel->create($userId, $tenantId, '192.168.1.1', 'Test Agent');

        // Act
        $session = $this->sessionModel->findBySessionId($sessionId);

        // Assert
        $this->assertNotNull($session);
        $this->assertEquals($sessionId, $session['id']);
        $this->assertEquals($userId, $session['user_id']);
        $this->assertEquals($tenantId, $session['tenant_id']);
        $this->assertEquals('192.168.1.1', $session['ip_address']);
        $this->assertEquals('Test Agent', $session['user_agent']);
    }
}


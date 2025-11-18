<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use PDO;

/**
 * Testes unitários para o Model User
 * 
 * Cenários cobertos:
 * - Criação de usuário
 * - Hash e verificação de senha
 * - Busca por email e tenant
 * - Verificação de email existente
 * - Atualização de role
 * - Verificação de admin
 * - Busca por tenant
 */
class UserTest extends TestCase
{
    private PDO $testDb;
    private User $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria banco SQLite em memória
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cria tabela de usuários
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
                UNIQUE(tenant_id, email)
            )
        ");

        // Cria modelo de teste com banco customizado
        $this->userModel = new User();
        $reflection = new \ReflectionClass($this->userModel);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->userModel, $this->testDb);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->testDb);
    }

    /**
     * Testa criação de usuário com todos os campos
     */
    public function testCreateUserWithAllFields(): void
    {
        // Arrange
        $tenantId = 1;
        $email = 'test@example.com';
        $password = 'SecurePassword123!';
        $name = 'Test User';
        $role = 'admin';

        // Act
        $id = $this->userModel->create($tenantId, $email, $password, $name, $role);

        // Assert
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $user = $this->userModel->findById($id);
        $this->assertNotNull($user);
        $this->assertEquals($tenantId, $user['tenant_id']);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($name, $user['name']);
        $this->assertEquals($role, $user['role']);
        $this->assertEquals('active', $user['status']);
        $this->assertNotEmpty($user['password_hash']);
    }

    /**
     * Testa criação de usuário com campos mínimos
     */
    public function testCreateUserWithMinimalFields(): void
    {
        // Arrange
        $tenantId = 1;
        $email = 'minimal@example.com';
        $password = 'SecurePassword123!';

        // Act
        $id = $this->userModel->create($tenantId, $email, $password);

        // Assert
        $user = $this->userModel->findById($id);
        $this->assertNotNull($user);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals('viewer', $user['role']); // Default
        $this->assertNull($user['name']); // Opcional
    }

    /**
     * Testa hash de senha
     */
    public function testHashPassword(): void
    {
        // Arrange
        $password = 'SecurePassword123!';

        // Act
        $hash = $this->userModel->hashPassword($password);

        // Assert
        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);
        $this->assertStringStartsWith('$2y$', $hash); // Bcrypt prefix
    }

    /**
     * Testa que senhas diferentes geram hashes diferentes
     */
    public function testDifferentPasswordsGenerateDifferentHashes(): void
    {
        // Arrange
        $password1 = 'Password1!';
        $password2 = 'Password2!';

        // Act
        $hash1 = $this->userModel->hashPassword($password1);
        $hash2 = $this->userModel->hashPassword($password2);

        // Assert
        $this->assertNotEquals($hash1, $hash2);
    }

    /**
     * Testa verificação de senha correta
     */
    public function testVerifyPasswordWithCorrectPassword(): void
    {
        // Arrange
        $password = 'SecurePassword123!';
        $hash = $this->userModel->hashPassword($password);

        // Act
        $isValid = $this->userModel->verifyPassword($password, $hash);

        // Assert
        $this->assertTrue($isValid);
    }

    /**
     * Testa verificação de senha incorreta
     */
    public function testVerifyPasswordWithIncorrectPassword(): void
    {
        // Arrange
        $password = 'SecurePassword123!';
        $wrongPassword = 'WrongPassword123!';
        $hash = $this->userModel->hashPassword($password);

        // Act
        $isValid = $this->userModel->verifyPassword($wrongPassword, $hash);

        // Assert
        $this->assertFalse($isValid);
    }

    /**
     * Testa busca de usuário por email e tenant
     */
    public function testFindByEmailAndTenant(): void
    {
        // Arrange
        $tenantId = 1;
        $email = 'test@example.com';
        $password = 'SecurePassword123!';
        $id = $this->userModel->create($tenantId, $email, $password);

        // Act
        $user = $this->userModel->findByEmailAndTenant($email, $tenantId);

        // Assert
        $this->assertNotNull($user);
        $this->assertEquals($id, $user['id']);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($tenantId, $user['tenant_id']);
    }

    /**
     * Testa busca de usuário por email e tenant inexistente
     */
    public function testFindByEmailAndTenantWithNonExistentUser(): void
    {
        // Arrange
        $email = 'nonexistent@example.com';
        $tenantId = 1;

        // Act
        $user = $this->userModel->findByEmailAndTenant($email, $tenantId);

        // Assert
        $this->assertNull($user);
    }

    /**
     * Testa que usuários de tenants diferentes podem ter mesmo email
     */
    public function testUsersFromDifferentTenantsCanHaveSameEmail(): void
    {
        // Arrange
        $email = 'same@example.com';
        $password = 'SecurePassword123!';
        $tenantId1 = 1;
        $tenantId2 = 2;

        // Act
        $id1 = $this->userModel->create($tenantId1, $email, $password);
        $id2 = $this->userModel->create($tenantId2, $email, $password);

        // Assert
        $this->assertNotEquals($id1, $id2);
        
        $user1 = $this->userModel->findByEmailAndTenant($email, $tenantId1);
        $user2 = $this->userModel->findByEmailAndTenant($email, $tenantId2);
        
        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
        $this->assertEquals($id1, $user1['id']);
        $this->assertEquals($id2, $user2['id']);
    }

    /**
     * Testa verificação de email existente
     */
    public function testEmailExists(): void
    {
        // Arrange
        $tenantId = 1;
        $email = 'test@example.com';
        $password = 'SecurePassword123!';
        $this->userModel->create($tenantId, $email, $password);

        // Act & Assert
        $this->assertTrue($this->userModel->emailExists($email, $tenantId));
        $this->assertFalse($this->userModel->emailExists('nonexistent@example.com', $tenantId));
        $this->assertFalse($this->userModel->emailExists($email, 999)); // Diferente tenant
    }

    /**
     * Testa busca de usuários por tenant
     */
    public function testFindByTenant(): void
    {
        // Arrange
        $tenantId1 = 1;
        $tenantId2 = 2;
        $password = 'SecurePassword123!';

        $this->userModel->create($tenantId1, 'user1@example.com', $password);
        $this->userModel->create($tenantId1, 'user2@example.com', $password);
        $this->userModel->create($tenantId2, 'user3@example.com', $password);

        // Act
        $usersTenant1 = $this->userModel->findByTenant($tenantId1);
        $usersTenant2 = $this->userModel->findByTenant($tenantId2);

        // Assert
        $this->assertCount(2, $usersTenant1);
        $this->assertCount(1, $usersTenant2);
    }

    /**
     * Testa atualização de role
     */
    public function testUpdateRole(): void
    {
        // Arrange
        $tenantId = 1;
        $email = 'test@example.com';
        $password = 'SecurePassword123!';
        $id = $this->userModel->create($tenantId, $email, $password, null, 'viewer');

        // Act
        $updated = $this->userModel->updateRole($id, 'admin');

        // Assert
        $this->assertTrue($updated);
        $user = $this->userModel->findById($id);
        $this->assertEquals('admin', $user['role']);
    }

    /**
     * Testa atualização de role com role inválida
     */
    public function testUpdateRoleWithInvalidRole(): void
    {
        // Arrange
        $tenantId = 1;
        $email = 'test@example.com';
        $password = 'SecurePassword123!';
        $id = $this->userModel->create($tenantId, $email, $password);

        // Act
        $updated = $this->userModel->updateRole($id, 'invalid_role');

        // Assert
        $this->assertFalse($updated);
        $user = $this->userModel->findById($id);
        $this->assertEquals('viewer', $user['role']); // Não mudou
    }

    /**
     * Testa verificação de usuário admin
     */
    public function testIsAdmin(): void
    {
        // Arrange
        $tenantId = 1;
        $password = 'SecurePassword123!';
        
        $adminId = $this->userModel->create($tenantId, 'admin@example.com', $password, null, 'admin');
        $viewerId = $this->userModel->create($tenantId, 'viewer@example.com', $password, null, 'viewer');

        // Act & Assert
        $this->assertTrue($this->userModel->isAdmin($adminId));
        $this->assertFalse($this->userModel->isAdmin($viewerId));
        $this->assertFalse($this->userModel->isAdmin(99999)); // ID inexistente
    }

    /**
     * Testa atualização de usuário
     */
    public function testUpdateUser(): void
    {
        // Arrange
        $tenantId = 1;
        $id = $this->userModel->create($tenantId, 'test@example.com', 'SecurePassword123!', 'Old Name');

        // Act
        $updated = $this->userModel->update($id, ['name' => 'New Name']);

        // Assert
        $this->assertTrue($updated);
        $user = $this->userModel->findById($id);
        $this->assertEquals('New Name', $user['name']);
    }

    /**
     * Testa exclusão de usuário
     */
    public function testDeleteUser(): void
    {
        // Arrange
        $tenantId = 1;
        $id = $this->userModel->create($tenantId, 'test@example.com', 'SecurePassword123!');

        // Act
        $deleted = $this->userModel->delete($id);

        // Assert
        $this->assertTrue($deleted);
        $user = $this->userModel->findById($id);
        $this->assertNull($user);
    }
}


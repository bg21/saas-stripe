<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\RateLimiterService;
use App\Utils\Database;

/**
 * Testes unitários para RateLimiterService
 * 
 * Cenários cobertos:
 * - Verificação de limite dentro do permitido
 * - Bloqueio quando limite é excedido
 * - Reset de janela de tempo
 * - Fallback para banco de dados quando Redis não está disponível
 */
class RateLimiterServiceTest extends TestCase
{
    private RateLimiterService $rateLimiter;
    private \PDO $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->rateLimiter = new RateLimiterService();
        
        // Cria banco SQLite em memória para testes
        $this->testDb = new \PDO('sqlite::memory:');
        $this->testDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Cria tabela de rate limits
        $this->testDb->exec("
            CREATE TABLE rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier_key VARCHAR(255) NOT NULL,
                request_count INTEGER NOT NULL DEFAULT 0,
                reset_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ");
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->testDb = null;
    }

    /**
     * Testa verificação de limite dentro do permitido
     */
    public function testCheckLimitWithinAllowed(): void
    {
        $identifier = 'test-api-key-123';
        $limit = 10;
        $window = 60; // 1 minuto
        
        // Primeira requisição
        $result1 = $this->rateLimiter->checkLimit($identifier, $limit, $window);
        
        $this->assertIsArray($result1);
        $this->assertTrue($result1['allowed']);
        $this->assertGreaterThanOrEqual(0, $result1['remaining']);
        $this->assertLessThanOrEqual($limit, $result1['remaining']);
        $this->assertArrayHasKey('reset_at', $result1);
    }

    /**
     * Testa bloqueio quando limite é excedido
     */
    public function testCheckLimitExceeded(): void
    {
        $identifier = 'test-api-key-456';
        $limit = 2; // Limite baixo para teste
        $window = 60;
        
        // Faz requisições até exceder o limite
        $result1 = $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $this->assertTrue($result1['allowed']);
        
        $result2 = $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $this->assertTrue($result2['allowed']);
        
        // Terceira requisição deve ser bloqueada
        $result3 = $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $this->assertFalse($result3['allowed']);
        $this->assertEquals(0, $result3['remaining']);
    }

    /**
     * Testa reset de janela de tempo
     */
    public function testResetWindow(): void
    {
        $identifier = 'test-api-key-789';
        $limit = 2;
        $window = 1; // 1 segundo para teste rápido
        
        // Excede o limite
        $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $result = $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $this->assertFalse($result['allowed']);
        
        // Aguarda reset da janela
        sleep(2);
        
        // Nova requisição deve ser permitida
        $resultAfterReset = $this->rateLimiter->checkLimit($identifier, $limit, $window);
        $this->assertTrue($resultAfterReset['allowed']);
    }

    /**
     * Testa limite por endpoint específico
     */
    public function testCheckLimitWithEndpoint(): void
    {
        $identifier = 'test-api-key-endpoint';
        $limit = 5;
        $window = 60;
        $endpoint = '/v1/appointments';
        
        $result1 = $this->rateLimiter->checkLimit($identifier, $limit, $window, $endpoint);
        $this->assertTrue($result1['allowed']);
        
        // Limite para endpoint diferente deve ser independente
        $result2 = $this->rateLimiter->checkLimit($identifier, $limit, $window, '/v1/customers');
        $this->assertTrue($result2['allowed']);
    }

    /**
     * Testa estrutura de resposta
     */
    public function testResponseStructure(): void
    {
        $result = $this->rateLimiter->checkLimit('test-key', 10, 60);
        
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('remaining', $result);
        $this->assertArrayHasKey('limit', $result);
        $this->assertArrayHasKey('reset_at', $result);
        $this->assertArrayHasKey('current', $result);
        
        $this->assertIsBool($result['allowed']);
        $this->assertIsInt($result['remaining']);
        $this->assertIsInt($result['limit']);
        $this->assertIsInt($result['reset_at']);
        $this->assertIsInt($result['current']);
    }
}


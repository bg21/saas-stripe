<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\CacheService;

/**
 * Testes unitários para CacheService
 * 
 * Cenários cobertos:
 * - Armazenamento e recuperação de valores
 * - Armazenamento e recuperação de JSON
 * - Expiração de cache (TTL)
 * - Remoção de cache
 * - Locks distribuídos
 * - Comportamento quando Redis não está disponível
 */
class CacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define ambiente de teste
        $_ENV['REDIS_URL'] = 'redis://127.0.0.1:6379';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Testa armazenamento e recuperação de valores simples
     */
    public function testSetAndGet(): void
    {
        $key = 'test:cache:simple:' . time();
        $value = 'test-value-123';
        
        // Tenta armazenar (pode falhar se Redis não estiver disponível)
        $setResult = CacheService::set($key, $value, 60);
        
        if (!$setResult) {
            $this->markTestSkipped('Redis não disponível para testes');
        }
        
        // Recupera valor
        $retrieved = CacheService::get($key);
        
        $this->assertEquals($value, $retrieved);
        
        // Limpa
        CacheService::delete($key);
    }

    /**
     * Testa armazenamento e recuperação de JSON
     */
    public function testSetJsonAndGetJson(): void
    {
        $key = 'test:cache:json:' . time();
        $data = [
            'id' => 1,
            'name' => 'Test',
            'items' => ['a', 'b', 'c']
        ];
        
        $setResult = CacheService::setJson($key, $data, 60);
        
        if (!$setResult) {
            $this->markTestSkipped('Redis não disponível para testes');
        }
        
        $retrieved = CacheService::getJson($key);
        
        $this->assertIsArray($retrieved);
        $this->assertEquals($data['id'], $retrieved['id']);
        $this->assertEquals($data['name'], $retrieved['name']);
        $this->assertEquals($data['items'], $retrieved['items']);
        
        // Limpa
        CacheService::delete($key);
    }

    /**
     * Testa remoção de cache
     */
    public function testDelete(): void
    {
        $key = 'test:cache:delete:' . time();
        $value = 'test-value';
        
        $setResult = CacheService::set($key, $value, 60);
        
        if (!$setResult) {
            $this->markTestSkipped('Redis não disponível para testes');
        }
        
        // Verifica que existe
        $this->assertEquals($value, CacheService::get($key));
        
        // Remove
        $deleteResult = CacheService::delete($key);
        $this->assertTrue($deleteResult);
        
        // Verifica que foi removido
        $this->assertNull(CacheService::get($key));
    }

    /**
     * Testa locks distribuídos
     */
    public function testLockAndUnlock(): void
    {
        $key = 'test:lock:' . time();
        
        // Tenta criar lock
        $lockResult = CacheService::lock($key, 10);
        
        if (!$lockResult) {
            $this->markTestSkipped('Redis não disponível para testes');
        }
        
        // Verifica que lock foi criado
        $this->assertTrue($lockResult);
        
        // Tenta criar lock novamente (deve falhar)
        $lockResult2 = CacheService::lock($key, 10);
        $this->assertFalse($lockResult2);
        
        // Remove lock
        $unlockResult = CacheService::unlock($key);
        $this->assertTrue($unlockResult);
        
        // Agora deve conseguir criar lock novamente
        $lockResult3 = CacheService::lock($key, 10);
        $this->assertTrue($lockResult3);
        
        // Limpa
        CacheService::unlock($key);
    }

    /**
     * Testa comportamento quando Redis não está disponível
     */
    public function testBehaviorWhenRedisUnavailable(): void
    {
        // Força Redis indisponível usando URL inválida
        $originalUrl = $_ENV['REDIS_URL'] ?? null;
        $_ENV['REDIS_URL'] = 'redis://127.0.0.1:9999'; // Porta inválida
        
        // Reseta instância do CacheService
        $reflection = new \ReflectionClass(CacheService::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null);
        
        $failedProperty = $reflection->getProperty('connectionFailed');
        $failedProperty->setAccessible(true);
        $failedProperty->setValue(false);
        
        // Tenta operações (devem retornar null/false graciosamente)
        $result = CacheService::get('test:key');
        $this->assertNull($result);
        
        $setResult = CacheService::set('test:key', 'value', 60);
        $this->assertFalse($setResult);
        
        $jsonResult = CacheService::getJson('test:key');
        $this->assertNull($jsonResult);
        
        // Restaura configuração original
        if ($originalUrl !== null) {
            $_ENV['REDIS_URL'] = $originalUrl;
        } else {
            unset($_ENV['REDIS_URL']);
        }
    }

    /**
     * Testa expiração de cache (TTL)
     */
    public function testCacheExpiration(): void
    {
        $key = 'test:cache:ttl:' . time();
        $value = 'test-value';
        
        $setResult = CacheService::set($key, $value, 1); // TTL de 1 segundo
        
        if (!$setResult) {
            $this->markTestSkipped('Redis não disponível para testes');
        }
        
        // Verifica que existe imediatamente
        $this->assertEquals($value, CacheService::get($key));
        
        // Aguarda expiração
        sleep(2);
        
        // Verifica que expirou
        $this->assertNull(CacheService::get($key));
    }
}


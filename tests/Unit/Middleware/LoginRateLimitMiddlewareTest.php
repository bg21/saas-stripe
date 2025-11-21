<?php

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Middleware\LoginRateLimitMiddleware;
use App\Services\RateLimiterService;
use Config;
use Flight;

/**
 * Testes unitários para LoginRateLimitMiddleware
 * 
 * Cenários cobertos:
 * - Rate limiting desabilitado em desenvolvimento
 * - Rate limiting desabilitado em localhost
 * - Rate limiting ativo em produção
 * - Bloqueio após exceder limite
 * - Registro de tentativas falhas
 */
class LoginRateLimitMiddlewareTest extends TestCase
{
    private RateLimiterService $rateLimiter;
    private LoginRateLimitMiddleware $middleware;
    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        // Salva ambiente original
        $this->originalEnv = Config::get('APP_ENV', 'production');

        // Cria mock do RateLimiterService
        $this->rateLimiter = $this->createMock(RateLimiterService::class);
        
        // Cria middleware
        $this->middleware = new LoginRateLimitMiddleware($this->rateLimiter);

        // Limpa Flight
        Flight::clear();
        
        // Limpa variáveis de servidor
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_NAME']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Restaura ambiente original
        if (method_exists(Config::class, 'set')) {
            Config::set('APP_ENV', $this->originalEnv);
        }
        
        // Limpa variáveis
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_NAME']);
        Flight::clear();
    }

    /**
     * Testa que rate limiting é desabilitado em ambiente de desenvolvimento
     */
    public function testCheckDisabledInDevelopment(): void
    {
        // Arrange
        // Mock Config para retornar development
        $this->setEnvironment('development');
        
        // Act
        $result = $this->middleware->check();

        // Assert
        $this->assertTrue($result, 'Rate limiting deve estar desabilitado em desenvolvimento');
        
        // Verifica que RateLimiterService não foi chamado
        $this->rateLimiter->expects($this->never())
            ->method('checkLimit');
    }

    /**
     * Testa que rate limiting é desabilitado para localhost (127.0.0.1)
     */
    public function testCheckDisabledForLocalhostIp(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_NAME']);

        // Act
        $result = $this->middleware->check();

        // Assert
        $this->assertTrue($result, 'Rate limiting deve estar desabilitado para localhost');
    }

    /**
     * Testa que rate limiting é desabilitado para IPv6 localhost (::1)
     */
    public function testCheckDisabledForLocalhostIpv6(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '::1';
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_NAME']);

        // Captura output
        ob_start();

        // Act
        $result = $this->middleware->check();

        // Limpa output
        ob_end_clean();

        // Assert
        $this->assertTrue($result, 'Rate limiting deve estar desabilitado para IPv6 localhost');
    }

    /**
     * Testa que rate limiting é desabilitado quando hostname contém localhost
     */
    public function testCheckDisabledForLocalhostHostname(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1'; // IP não localhost
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        unset($_SERVER['SERVER_NAME']);

        // Act
        $result = $this->middleware->check();

        // Assert
        $this->assertTrue($result, 'Rate limiting deve estar desabilitado quando hostname contém localhost');
    }

    /**
     * Testa que rate limiting funciona normalmente em produção com IP externo
     */
    public function testCheckActiveInProduction(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1'; // IP externo
        $_SERVER['HTTP_HOST'] = 'example.com';

        // Mock do RateLimiterService retornando permitido
        $this->rateLimiter->expects($this->exactly(2))
            ->method('checkLimit')
            ->willReturn([
                'allowed' => true,
                'remaining' => 4,
                'reset_at' => time() + 900,
                'current' => 1
            ]);

        // Act
        $result = $this->middleware->check();

        // Assert
        $this->assertTrue($result, 'Rate limiting deve permitir quando dentro do limite');
    }

    /**
     * Testa bloqueio quando excede limite de 15 minutos
     */
    public function testCheckBlocksWhenExceeding15MinLimit(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        // Captura output para evitar warnings do PHPUnit
        ob_start();

        // Mock do RateLimiterService retornando bloqueado
        $this->rateLimiter->expects($this->exactly(2))
            ->method('checkLimit')
            ->willReturnCallback(function($identifier, $limit, $window) {
                // Primeira chamada: 15 minutos - bloqueado
                if ($window === 900) {
                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'reset_at' => time() + 600,
                        'current' => 6
                    ];
                }
                // Segunda chamada: 1 hora - permitido
                return [
                    'allowed' => true,
                    'remaining' => 4,
                    'reset_at' => time() + 3600,
                    'current' => 6
                ];
            });

        // Act
        $result = $this->middleware->check();

        // Limpa output
        ob_end_clean();

        // Assert
        $this->assertFalse($result, 'Rate limiting deve bloquear quando excede limite');
    }

    /**
     * Testa bloqueio quando excede limite de 1 hora
     */
    public function testCheckBlocksWhenExceeding1HourLimit(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        // Captura output para evitar warnings do PHPUnit
        ob_start();

        // Mock do RateLimiterService retornando bloqueado na janela de 1 hora
        $this->rateLimiter->expects($this->exactly(2))
            ->method('checkLimit')
            ->willReturnCallback(function($identifier, $limit, $window) {
                // Primeira chamada: 15 minutos - permitido
                if ($window === 900) {
                    return [
                        'allowed' => true,
                        'remaining' => 2,
                        'reset_at' => time() + 600,
                        'current' => 3
                    ];
                }
                // Segunda chamada: 1 hora - bloqueado
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => time() + 1800,
                    'current' => 11
                ];
            });

        // Act
        $result = $this->middleware->check();

        // Limpa output
        ob_end_clean();

        // Assert
        $this->assertFalse($result, 'Rate limiting deve bloquear quando excede limite de 1 hora');
    }

    /**
     * Testa registro de tentativa falha
     */
    public function testRecordFailedAttempt(): void
    {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        // Mock do RateLimiterService
        $this->rateLimiter->expects($this->exactly(2))
            ->method('checkLimit')
            ->willReturn([
                'allowed' => true,
                'remaining' => 4,
                'reset_at' => time() + 900,
                'current' => 1
            ]);

        // Act
        $this->middleware->recordFailedAttempt('test@example.com');

        // Assert
        // Se chegou aqui sem exceção, o método funcionou
        $this->assertTrue(true);
    }

    /**
     * Testa que getClientIp retorna IP correto de REMOTE_ADDR
     */
    public function testGetClientIpFromRemoteAddr(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);

        // Mock do RateLimiterService
        $this->rateLimiter->expects($this->exactly(2))
            ->method('checkLimit')
            ->with(
                $this->stringContains('login_ip_203.0.113.1'),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn([
                'allowed' => true,
                'remaining' => 4,
                'reset_at' => time() + 900,
                'current' => 1
            ]);

        // Act
        $result = $this->middleware->check();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Testa que getClientIp prioriza HTTP_X_FORWARDED_FOR
     */
    public function testGetClientIpFromXForwardedFor(): void
    {
        // Arrange
        $this->setEnvironment('production');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2, 192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        // Mock do RateLimiterService
        $this->rateLimiter->expects($this->exactly(2))
            ->method('checkLimit')
            ->with(
                $this->stringContains('login_ip_203.0.113.2'),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn([
                'allowed' => true,
                'remaining' => 4,
                'reset_at' => time() + 900,
                'current' => 1
            ]);

        // Act
        $result = $this->middleware->check();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Helper para definir ambiente
     */
    private function setEnvironment(string $env): void
    {
        // Como Config::get() lê de $_ENV, podemos definir diretamente
        $_ENV['APP_ENV'] = $env;
        
        // Se Config tem cache, precisamos limpar
        if (method_exists(Config::class, 'clearCache')) {
            Config::clearCache();
        }
    }
}


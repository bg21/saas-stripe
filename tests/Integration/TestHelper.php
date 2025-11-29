<?php

namespace Tests\Integration;

use Flight;

/**
 * Helper para testes de integração
 * 
 * Fornece utilitários para simular requisições HTTP e autenticação
 */
class TestHelper
{
    /**
     * Simula autenticação de usuário
     * 
     * @param int $tenantId ID do tenant
     * @param int|null $userId ID do usuário (opcional)
     */
    public static function mockAuth(int $tenantId, ?int $userId = null): void
    {
        Flight::set('tenant_id', $tenantId);
        if ($userId !== null) {
            Flight::set('user_id', $userId);
        }
    }

    /**
     * Limpa autenticação mockada
     */
    public static function clearAuth(): void
    {
        Flight::clear('tenant_id');
        Flight::clear('user_id');
    }

    /**
     * Simula requisição HTTP
     * 
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param array $queryParams Parâmetros de query (para GET)
     * @param array|null $bodyData Dados do corpo (para POST/PUT)
     */
    public static function mockRequest(string $method, array $queryParams = [], ?array $bodyData = null): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = $queryParams;
        
        if ($bodyData !== null) {
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            // Usa a variável global que RequestCache espera
            $GLOBALS['__php_input_mock'] = json_encode($bodyData);
        }
    }

    /**
     * Limpa mock de requisição
     */
    public static function clearRequest(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE']);
        $_GET = [];
        unset($GLOBALS['__php_input_mock']);
        
        // Limpa cache do RequestCache
        $reflection = new \ReflectionClass(\App\Utils\RequestCache::class);
        $inputCacheProperty = $reflection->getProperty('inputCache');
        $inputCacheProperty->setAccessible(true);
        $inputCacheProperty->setValue(null);
        
        $inputReadProperty = $reflection->getProperty('inputRead');
        $inputReadProperty->setAccessible(true);
        $inputReadProperty->setValue(false);
    }

    /**
     * Obtém resposta JSON da saída capturada
     * 
     * @param string $output Output capturado
     * @return array|null Dados decodificados ou null
     */
    public static function parseJsonResponse(string $output): ?array
    {
        $json = json_decode($output, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : null;
    }
}


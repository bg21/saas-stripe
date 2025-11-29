<?php

namespace App\Middleware;

use Flight;
use Config;

/**
 * Middleware de Tracing de Requisições
 * 
 * Gera um request_id único para cada requisição, permitindo rastreamento
 * através de múltiplos serviços e correlação de logs.
 * 
 * O request_id é:
 * - Gerado no início de cada requisição
 * - Armazenado no Flight para uso em toda a aplicação
 * - Incluído no header X-Request-ID da resposta
 * - Automaticamente incluído em todos os logs via Logger
 */
class TracingMiddleware
{
    private bool $enabled;
    private array $excludedRoutes;

    public function __construct()
    {
        // Configurações
        $this->enabled = Config::get('TRACING_ENABLED', 'true') === 'true';
        $this->excludedRoutes = [
            '/',
            '/health',
            '/health/detailed',
            '/api-docs',
            '/api-docs/ui'
        ];
    }

    /**
     * Gera e armazena request_id no início da requisição
     * Deve ser chamado no before('start') ANTES de outros middlewares
     */
    public function before(): void
    {
        if (!$this->enabled) {
            return;
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        
        // Ignora rotas excluídas
        if (in_array($requestUri, $this->excludedRoutes)) {
            return;
        }

        // Verifica se já existe request_id no header (propagação de tracing)
        // Isso permite rastreamento através de múltiplos serviços
        $existingRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        
        if ($existingRequestId && $this->isValidRequestId($existingRequestId)) {
            // Usa request_id existente (propagação de tracing)
            $requestId = $existingRequestId;
        } else {
            // Gera novo request_id único
            // Formato: 32 caracteres hexadecimais (16 bytes)
            $requestId = bin2hex(random_bytes(16));
        }
        
        // Armazena no Flight para uso em toda a aplicação
        Flight::set('request_id', $requestId);
        
        // Adiciona header na resposta para o cliente
        header('X-Request-ID: ' . $requestId);
        
        // Armazena timestamp de início para cálculo de duração
        Flight::set('request_start_time', microtime(true));
    }

    /**
     * Valida formato do request_id
     * 
     * @param string $requestId Request ID a validar
     * @return bool True se válido
     */
    private function isValidRequestId(string $requestId): bool
    {
        // Formato esperado: 32 caracteres hexadecimais
        return preg_match('/^[a-f0-9]{32}$/i', $requestId) === 1;
    }

    /**
     * Obtém request_id atual (helper estático)
     * 
     * @return string|null Request ID ou null se não disponível
     */
    public static function getRequestId(): ?string
    {
        return Flight::get('request_id');
    }
}


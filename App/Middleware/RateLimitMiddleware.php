<?php

namespace App\Middleware;

use App\Services\RateLimiterService;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Middleware de Rate Limiting
 * 
 * Limita requisições por API key (preferencial) ou IP (fallback).
 * Suporta diferentes limites por endpoint.
 */
class RateLimitMiddleware
{
    private RateLimiterService $rateLimiter;
    
    // Limites padrão (requests por minuto/hora)
    private const DEFAULT_LIMIT_PER_MINUTE = 60;
    private const DEFAULT_LIMIT_PER_HOUR = 1000;
    
    // Limites por endpoint e método HTTP
    // Formato: 'endpoint' => ['GET' => [per_minute, per_hour], 'POST' => [per_minute, per_hour], ...]
    private const ENDPOINT_LIMITS = [
        '/v1/webhook' => [
            'POST' => [200, 10000] // Webhooks podem ter limite maior
        ],
        '/v1/stats' => [
            'GET' => [30, 500] // Stats pode ser mais restritivo
        ],
        '/v1/customers' => [
            'POST' => [20, 200], // Criação: 20/min, 200/hora
            'GET' => [60, 1000]  // Listagem: padrão
        ],
        '/v1/subscriptions' => [
            'POST' => [15, 150], // Criação: 15/min, 150/hora
            'PUT' => [20, 200],  // Atualização: 20/min, 200/hora
            'DELETE' => [10, 100], // Cancelamento: 10/min, 100/hora
            'GET' => [60, 1000]   // Listagem: padrão
        ],
        '/v1/products' => [
            'POST' => [10, 100], // Criação: 10/min, 100/hora
            'PUT' => [15, 150],  // Atualização: 15/min, 150/hora
            'GET' => [60, 1000]  // Listagem: padrão
        ],
        '/v1/prices' => [
            'POST' => [10, 100], // Criação: 10/min, 100/hora
            'PUT' => [15, 150],  // Atualização: 15/min, 150/hora
            'GET' => [60, 1000]  // Listagem: padrão
        ],
        '/v1/auth/login' => [
            'POST' => [5, 20] // Login: muito restritivo (5/min, 20/hora) - já tem middleware específico
        ],
        '/v1/payment-intents' => [
            'POST' => [30, 500] // Criação de payment intents: 30/min, 500/hora
        ],
        '/v1/refunds' => [
            'POST' => [10, 100] // Reembolsos: 10/min, 100/hora
        ],
    ];
    
    // Limites para rotas públicas (mais restritivos)
    private const PUBLIC_ROUTE_LIMITS = [
        '/' => [10, 100],           // 10/min, 100/hora
        '/health' => [30, 500],     // Health checks: 30/min, 500/hora
        '/health/detailed' => [10, 100], // Health detalhado: 10/min, 100/hora
    ];
    
    public function __construct(RateLimiterService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * Verifica rate limit e adiciona headers de resposta
     * 
     * @param string|null $endpoint Endpoint específico (opcional)
     * @param array|null $customLimits Limites customizados ['limit' => int, 'window' => int] (opcional)
     * @return bool true se permitido, false se excedeu limite
     */
    public function check(?string $endpoint = null, ?array $customLimits = null): bool
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Obtém identificador (API key ou IP)
        $identifier = $this->getIdentifier();
        
        if (!$identifier) {
            // Se não tem identificador, permite (já será bloqueado pela autenticação)
            return true;
        }
        
        // Se limites customizados foram fornecidos, usa eles
        if ($customLimits !== null && isset($customLimits['limit']) && isset($customLimits['window'])) {
            $limit = (int)$customLimits['limit'];
            $window = (int)$customLimits['window'];
            
            $check = $this->rateLimiter->checkLimit(
                $identifier,
                $limit,
                $window,
                $endpoint ?? $requestUri
            );
            
            $this->setRateLimitHeaders($check['limit'], $check['remaining'], $check['reset_at']);
            
            if (!$check['allowed']) {
                Logger::warning("Rate limit excedido (customizado)", [
                    'identifier' => substr($identifier, 0, 20) . '...',
                    'endpoint' => $endpoint ?? $requestUri,
                    'limit' => $limit,
                    'window' => $window,
                    'current' => $check['current'] ?? 0
                ]);
                
                Flight::json([
                    'error' => 'Rate limit excedido',
                    'message' => 'Você excedeu o limite de requisições. Tente novamente mais tarde.',
                    'retry_after' => $check['reset_at'] - time()
                ], 429);
                
                Flight::stop();
                return false;
            }
            
            return true;
        }
        
        // Determina limite para este endpoint baseado no método e tipo
        $limitConfig = $this->getLimitConfigForEndpoint($endpoint ?? $requestUri, $method);
        $limitPerMinute = $limitConfig['per_minute'];
        $limitPerHour = $limitConfig['per_hour'];
        
        // Verifica limite por minuto
        $minuteCheck = $this->rateLimiter->checkLimit(
            $identifier,
            $limitPerMinute,
            60, // 1 minuto
            $endpoint ?? $requestUri
        );
        
        // Verifica limite por hora
        $hourCheck = $this->rateLimiter->checkLimit(
            $identifier,
            $limitPerHour,
            3600, // 1 hora
            $endpoint ?? $requestUri
        );
        
        // Usa o mais restritivo
        $allowed = $minuteCheck['allowed'] && $hourCheck['allowed'];
        $remaining = min($minuteCheck['remaining'], $hourCheck['remaining']);
        $limit = min($minuteCheck['limit'], $hourCheck['limit']);
        $resetAt = max($minuteCheck['reset_at'], $hourCheck['reset_at']);
        
        // Adiciona headers de resposta
        $this->setRateLimitHeaders($limit, $remaining, $resetAt);
        
        if (!$allowed) {
            Logger::warning("Rate limit excedido", [
                'identifier' => substr($identifier, 0, 20) . '...',
                'endpoint' => $endpoint ?? $requestUri,
                'method' => $method,
                'limit' => $limit,
                'current' => $minuteCheck['current'] ?? $hourCheck['current'] ?? 0
            ]);
            
            Flight::json([
                'error' => 'Rate limit excedido',
                'message' => 'Você excedeu o limite de requisições. Tente novamente mais tarde.',
                'retry_after' => $resetAt - time()
            ], 429);
            
            Flight::stop();
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtém identificador para rate limiting (API key ou IP)
     */
    private function getIdentifier(): ?string
    {
        // Prioridade 1: API key (se autenticado)
        $tenantId = Flight::get('tenant_id');
        $isMaster = Flight::get('is_master');
        
        if ($tenantId !== null) {
            return 'tenant_' . $tenantId;
        }
        
        if ($isMaster === true) {
            // Master key pode ter limite maior ou ilimitado
            return 'master_key';
        }
        
        // Prioridade 2: IP do cliente
        $ip = $this->getClientIp();
        if ($ip) {
            return 'ip_' . $ip;
        }
        
        return null;
    }
    
    /**
     * Obtém IP do cliente
     */
    private function getClientIp(): ?string
    {
        // Verifica vários headers (proxies, load balancers)
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx
            'HTTP_X_FORWARDED_FOR',  // Proxies
            'REMOTE_ADDR'            // IP direto
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Se for X-Forwarded-For, pega o primeiro IP
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Valida IP (aceita IPs privados também para rate limiting)
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback para REMOTE_ADDR (pode ser IP privado)
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    /**
     * Obtém configuração de limites para um endpoint específico
     * 
     * @param string $endpoint Endpoint
     * @param string $method Método HTTP
     * @return array ['per_minute' => int, 'per_hour' => int]
     */
    private function getLimitConfigForEndpoint(string $endpoint, string $method): array
    {
        // Remove query string
        $endpoint = parse_url($endpoint, PHP_URL_PATH) ?? $endpoint;
        
        // Verifica limites para rotas públicas
        if (isset(self::PUBLIC_ROUTE_LIMITS[$endpoint])) {
            $limits = self::PUBLIC_ROUTE_LIMITS[$endpoint];
            return [
                'per_minute' => $limits[0],
                'per_hour' => $limits[1]
            ];
        }
        
        // Verifica se há limite específico para este endpoint e método
        if (isset(self::ENDPOINT_LIMITS[$endpoint][$method])) {
            $limits = self::ENDPOINT_LIMITS[$endpoint][$method];
            return [
                'per_minute' => $limits[0],
                'per_hour' => $limits[1]
            ];
        }
        
        // Verifica padrões (ex: /v1/customers/:id)
        foreach (self::ENDPOINT_LIMITS as $pattern => $methodLimits) {
            if ($this->matchesPattern($endpoint, $pattern)) {
                if (isset($methodLimits[$method])) {
                    $limits = $methodLimits[$method];
                    return [
                        'per_minute' => $limits[0],
                        'per_hour' => $limits[1]
                    ];
                }
                // Se não tem limite específico para o método, usa o primeiro disponível
                if (!empty($methodLimits)) {
                    $firstMethod = array_key_first($methodLimits);
                    $limits = $methodLimits[$firstMethod];
                    return [
                        'per_minute' => $limits[0],
                        'per_hour' => $limits[1]
                    ];
                }
            }
        }
        
        // Retorna limite padrão
        return [
            'per_minute' => self::DEFAULT_LIMIT_PER_MINUTE,
            'per_hour' => self::DEFAULT_LIMIT_PER_HOUR
        ];
    }
    
    /**
     * Verifica se endpoint corresponde a um padrão
     */
    private function matchesPattern(string $endpoint, string $pattern): bool
    {
        // Converte padrão para regex (ex: /v1/customers/:id -> /v1/customers/.*)
        $regex = preg_replace('/:[^\/]+/', '[^/]+', $pattern);
        $regex = '#^' . $regex . '$#';
        
        return preg_match($regex, $endpoint) === 1;
    }
    
    /**
     * Define headers de rate limit na resposta
     */
    private function setRateLimitHeaders(int $limit, int $remaining, int $resetAt): void
    {
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetAt);
        header('Retry-After: ' . max(0, $resetAt - time()));
    }
}


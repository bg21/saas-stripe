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
    
    // Limites padrão (requests por minuto)
    private const DEFAULT_LIMIT_PER_MINUTE = 60;
    private const DEFAULT_LIMIT_PER_HOUR = 1000;
    
    // Limites por endpoint (requests por minuto)
    private const ENDPOINT_LIMITS = [
        '/v1/webhook' => 200, // Webhooks podem ter limite maior
        '/v1/stats' => 30,     // Stats pode ser mais restritivo
    ];
    
    public function __construct(RateLimiterService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * Verifica rate limit e adiciona headers de resposta
     * 
     * @param string|null $endpoint Endpoint específico (opcional)
     * @return bool true se permitido, false se excedeu limite
     */
    public function check(?string $endpoint = null): bool
    {
        // Rotas públicas não têm rate limiting
        $publicRoutes = ['/', '/v1/webhook', '/health'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        
        if (in_array($requestUri, $publicRoutes)) {
            return true;
        }
        
        // Obtém identificador (API key ou IP)
        $identifier = $this->getIdentifier();
        
        if (!$identifier) {
            // Se não tem identificador, permite (já será bloqueado pela autenticação)
            return true;
        }
        
        // Determina limite para este endpoint
        $limitPerMinute = $this->getLimitForEndpoint($endpoint ?? $requestUri);
        $limitPerHour = $limitPerMinute * 60; // Limite por hora é 60x o limite por minuto
        
        // Verifica limite por minuto
        $minuteCheck = $this->rateLimiter->checkLimit(
            $identifier,
            $limitPerMinute,
            60, // 1 minuto
            $endpoint
        );
        
        // Verifica limite por hora
        $hourCheck = $this->rateLimiter->checkLimit(
            $identifier,
            $limitPerHour,
            3600, // 1 hora
            $endpoint
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
     * Obtém limite para um endpoint específico
     */
    private function getLimitForEndpoint(string $endpoint): int
    {
        // Remove query string
        $endpoint = parse_url($endpoint, PHP_URL_PATH) ?? $endpoint;
        
        // Verifica se há limite específico
        if (isset(self::ENDPOINT_LIMITS[$endpoint])) {
            return self::ENDPOINT_LIMITS[$endpoint];
        }
        
        // Verifica padrões (ex: /v1/customers/:id)
        foreach (self::ENDPOINT_LIMITS as $pattern => $limit) {
            if ($this->matchesPattern($endpoint, $pattern)) {
                return $limit;
            }
        }
        
        // Retorna limite padrão
        return self::DEFAULT_LIMIT_PER_MINUTE;
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


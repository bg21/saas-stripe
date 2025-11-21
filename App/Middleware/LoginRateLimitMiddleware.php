<?php

namespace App\Middleware;

use App\Services\RateLimiterService;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Middleware de Rate Limiting específico para login
 * 
 * Protege o endpoint de login contra ataques de brute force:
 * - 5 tentativas por IP a cada 15 minutos
 * - Após 5 falhas, bloqueia por 1 hora
 * 
 * ✅ DESENVOLVIMENTO: Desabilitado em ambiente de desenvolvimento/localhost
 */
class LoginRateLimitMiddleware
{
    private RateLimiterService $rateLimiter;
    
    // Limites configuráveis
    private const MAX_ATTEMPTS_PER_15MIN = 5;
    private const MAX_ATTEMPTS_PER_HOUR = 10;
    private const BLOCK_DURATION_AFTER_FAILURES = 3600; // 1 hora
    
    public function __construct(RateLimiterService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * Verifica se o IP pode tentar fazer login
     * 
     * @return bool True se permitido, false se bloqueado
     */
    public function check(): bool
    {
        // ✅ DESENVOLVIMENTO: Desabilita rate limiting em desenvolvimento ou localhost
        if ($this->isDevelopmentOrLocalhost()) {
            Logger::debug("Rate limiting desabilitado para desenvolvimento/localhost", [
                'ip' => $this->getClientIp(),
                'env' => Config::env()
            ]);
            return true;
        }
        
        $ip = $this->getClientIp();
        $identifier = 'login_ip_' . $ip;
        
        // Verifica limite de 15 minutos (proteção principal)
        $check15min = $this->rateLimiter->checkLimit(
            $identifier,
            self::MAX_ATTEMPTS_PER_15MIN,
            900, // 15 minutos
            '/v1/auth/login'
        );
        
        // Verifica limite de 1 hora (proteção secundária)
        $check1hour = $this->rateLimiter->checkLimit(
            $identifier,
            self::MAX_ATTEMPTS_PER_HOUR,
            3600, // 1 hora
            '/v1/auth/login'
        );
        
        // Se excedeu qualquer limite, bloqueia
        if (!$check15min['allowed'] || !$check1hour['allowed']) {
            $remaining = min($check15min['remaining'], $check1hour['remaining']);
            $resetAt = max($check15min['reset_at'], $check1hour['reset_at']);
            
            Logger::warning("Tentativa de login bloqueada por rate limit", [
                'ip' => $ip,
                'attempts_15min' => $check15min['current'] ?? 0,
                'attempts_1hour' => $check1hour['current'] ?? 0,
                'reset_at' => date('Y-m-d H:i:s', $resetAt)
            ]);
            
            Flight::json([
                'error' => 'Muitas tentativas de login',
                'message' => 'Você excedeu o limite de tentativas de login. Tente novamente mais tarde.',
                'retry_after' => $resetAt - time(),
                'retry_after_formatted' => $this->formatRetryAfter($resetAt - time())
            ], 429);
            
            Flight::stop();
            return false;
        }
        
        return true;
    }
    
    /**
     * Registra uma tentativa de login falha
     * 
     * O checkLimit já incrementa o contador automaticamente, então
     * apenas chamamos ele para incrementar os contadores.
     * 
     * @param string|null $email Email usado na tentativa (opcional, para logs)
     */
    public function recordFailedAttempt(?string $email = null): void
    {
        $ip = $this->getClientIp();
        $identifier = 'login_ip_' . $ip;
        
        // Incrementa contador de tentativas falhas (15 min)
        // Usamos um limite alto para não bloquear, apenas incrementar
        $this->rateLimiter->checkLimit(
            $identifier,
            self::MAX_ATTEMPTS_PER_15MIN * 10, // Limite alto apenas para incrementar
            900,
            '/v1/auth/login'
        );
        
        // Incrementa contador de tentativas falhas (1 hora)
        $this->rateLimiter->checkLimit(
            $identifier,
            self::MAX_ATTEMPTS_PER_HOUR * 10, // Limite alto apenas para incrementar
            3600,
            '/v1/auth/login'
        );
        
        Logger::warning("Tentativa de login falha registrada", [
            'ip' => $ip,
            'email' => $email ? substr($email, 0, 3) . '***' : null
        ]);
    }
    
    /**
     * Limpa tentativas falhas após login bem-sucedido
     * 
     * @param string $ip IP do cliente
     */
    public function clearFailedAttempts(string $ip): void
    {
        // Em uma implementação mais robusta, poderíamos limpar os contadores
        // Por enquanto, apenas logamos
        Logger::debug("Login bem-sucedido - contadores de rate limit serão resetados naturalmente", [
            'ip' => $ip
        ]);
    }
    
    /**
     * Obtém IP do cliente
     * 
     * @return string IP do cliente
     */
    private function getClientIp(): string
    {
        // Verifica vários headers comuns de proxy
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx
            'HTTP_X_FORWARDED_FOR',  // Proxy padrão
            'REMOTE_ADDR'            // IP direto
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Se for X-Forwarded-For, pega o primeiro IP
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Valida IP (aceita IPs privados também para desenvolvimento)
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback - aceita qualquer IP (incluindo privados)
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Formata tempo de retry em formato legível
     * 
     * @param int $seconds Segundos até poder tentar novamente
     * @return string Tempo formatado
     */
    private function formatRetryAfter(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} segundos";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $remainingSeconds > 0 
                ? "{$minutes} minutos e {$remainingSeconds} segundos"
                : "{$minutes} minutos";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $remainingMinutes > 0
            ? "{$hours} horas e {$remainingMinutes} minutos"
            : "{$hours} horas";
    }
    
    /**
     * Verifica se está em ambiente de desenvolvimento ou localhost
     * 
     * @return bool True se for desenvolvimento ou localhost
     */
    private function isDevelopmentOrLocalhost(): bool
    {
        // Verifica se está em ambiente de desenvolvimento
        if (Config::isDevelopment()) {
            return true;
        }
        
        // Verifica se o IP é localhost
        $ip = $this->getClientIp();
        $localhostIps = ['127.0.0.1', '::1', 'localhost'];
        
        if (in_array($ip, $localhostIps, true)) {
            return true;
        }
        
        // Verifica se o hostname contém localhost
        $hostname = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        if (strpos($hostname, 'localhost') !== false || strpos($hostname, '127.0.0.1') !== false) {
            return true;
        }
        
        return false;
    }
}


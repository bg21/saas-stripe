<?php

namespace App\Services;

use App\Services\CacheService;
use App\Services\Logger;
use App\Utils\Database;
use Config;

/**
 * Serviço de Rate Limiting
 * 
 * Implementa rate limiting usando Redis (preferencial) ou banco de dados (fallback).
 * Suporta diferentes limites por identificador (API key, IP, etc.) e por janela de tempo.
 */
class RateLimiterService
{
    private const REDIS_PREFIX = 'ratelimit:';
    private const DB_TABLE = 'rate_limits';
    
    /**
     * Verifica se uma requisição está dentro do limite
     * 
     * @param string $identifier Identificador único (API key, IP, etc.)
     * @param int $limit Número máximo de requisições
     * @param int $window Janela de tempo em segundos (ex: 60 para 1 minuto, 3600 para 1 hora)
     * @param string|null $endpoint Endpoint específico (opcional, para limites por endpoint)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function checkLimit(string $identifier, int $limit, int $window, ?string $endpoint = null): array
    {
        $key = $this->buildKey($identifier, $window, $endpoint);
        
        // Tenta usar Redis primeiro
        if ($this->isRedisAvailable()) {
            return $this->checkLimitRedis($key, $limit, $window);
        }
        
        // Fallback para banco de dados
        return $this->checkLimitDatabase($identifier, $limit, $window, $endpoint);
    }
    
    /**
     * Verifica limite usando Redis
     * ✅ OTIMIZAÇÃO: Timeout curto e fallback rápido
     */
    private function checkLimitRedis(string $key, int $limit, int $window): array
    {
        try {
            $redis = $this->getRedisClient();
            
            // ✅ Se Redis não estiver disponível, usa fallback imediatamente
            if ($redis === null) {
                return $this->checkLimitDatabase($this->extractIdentifierFromKey($key), $limit, $window, null);
            }
            
            // ✅ OTIMIZAÇÃO: Operações com timeout implícito (já configurado no cliente)
            $startTime = microtime(true);
            $current = @$redis->incr($key);
            $elapsed = microtime(true) - $startTime;
            
            // ✅ Se operação demorou mais de 200ms, usa fallback
            if ($elapsed > 0.2 || $current === false) {
                Logger::warning("Redis lento, usando fallback para rate limit");
                return $this->checkLimitDatabase($this->extractIdentifierFromKey($key), $limit, $window, null);
            }
            
            // Se é a primeira requisição nesta janela, define TTL
            if ($current === 1) {
                @$redis->expire($key, $window);
            }
            
            $remaining = max(0, $limit - $current);
            $ttl = @$redis->ttl($key);
            $resetAt = time() + ($ttl > 0 ? $ttl : $window);
            
            return [
                'allowed' => $current <= $limit,
                'remaining' => $remaining,
                'limit' => $limit,
                'reset_at' => $resetAt,
                'current' => $current
            ];
        } catch (\Exception $e) {
            Logger::warning("Erro ao verificar rate limit no Redis, usando fallback", [
                'error' => $e->getMessage()
            ]);
            // Fallback para banco
            return $this->checkLimitDatabase($this->extractIdentifierFromKey($key), $limit, $window, null);
        }
    }
    
    /**
     * Verifica limite usando banco de dados (fallback)
     */
    private function checkLimitDatabase(string $identifier, int $limit, int $window, ?string $endpoint): array
    {
        try {
            $db = Database::getInstance();
            
            // Cria tabela se não existir
            $this->ensureTableExists();
            
            $key = $this->buildKey($identifier, $window, $endpoint);
            $now = time();
            $windowStart = $now - $window;
            
            // Busca ou cria registro
            $stmt = $db->prepare("
                SELECT id, request_count, reset_at 
                FROM " . self::DB_TABLE . " 
                WHERE identifier_key = ? AND reset_at > ?
            ");
            $stmt->execute([$key, $windowStart]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($record) {
                // Atualiza contador
                $newCount = (int)$record['request_count'] + 1;
                $resetAt = (int)$record['reset_at'];
                
                $stmt = $db->prepare("
                    UPDATE " . self::DB_TABLE . " 
                    SET request_count = ?, updated_at = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$newCount, $now, $record['id']]);
                
                $remaining = max(0, $limit - $newCount);
                
                return [
                    'allowed' => $newCount <= $limit,
                    'remaining' => $remaining,
                    'limit' => $limit,
                    'reset_at' => $resetAt,
                    'current' => $newCount
                ];
            } else {
                // Cria novo registro
                $resetAt = $now + $window;
                
                $stmt = $db->prepare("
                    INSERT INTO " . self::DB_TABLE . " 
                    (identifier_key, request_count, reset_at, created_at, updated_at) 
                    VALUES (?, 1, ?, ?, ?)
                ");
                $stmt->execute([$key, $resetAt, $now, $now]);
                
                $remaining = max(0, $limit - 1);
                
                return [
                    'allowed' => true,
                    'remaining' => $remaining,
                    'limit' => $limit,
                    'reset_at' => $resetAt,
                    'current' => 1
                ];
            }
        } catch (\Exception $e) {
            Logger::error("Erro ao verificar rate limit no banco", [
                'error' => $e->getMessage(),
                'identifier' => $identifier
            ]);
            
            // Em caso de erro, permite a requisição (fail-open)
            return [
                'allowed' => true,
                'remaining' => $limit,
                'limit' => $limit,
                'reset_at' => time() + $window,
                'current' => 0
            ];
        }
    }
    
    /**
     * Limpa registros expirados do banco de dados
     */
    public function cleanupExpired(): void
    {
        try {
            $db = Database::getInstance();
            $now = time();
            
            $stmt = $db->prepare("
                DELETE FROM " . self::DB_TABLE . " 
                WHERE reset_at < ?
            ");
            $stmt->execute([$now]);
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                Logger::debug("Rate limit cleanup: {$deleted} registros expirados removidos");
            }
        } catch (\Exception $e) {
            Logger::warning("Erro ao limpar rate limits expirados", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Constrói chave única para rate limiting
     */
    private function buildKey(string $identifier, int $window, ?string $endpoint): string
    {
        $parts = [self::REDIS_PREFIX, $identifier, $window];
        
        if ($endpoint) {
            $parts[] = md5($endpoint);
        }
        
        return implode(':', $parts);
    }
    
    /**
     * Extrai identificador da chave (para fallback)
     */
    private function extractIdentifierFromKey(string $key): string
    {
        $parts = explode(':', $key);
        return $parts[1] ?? 'unknown';
    }
    
    /**
     * Verifica se Redis está disponível
     */
    private function isRedisAvailable(): bool
    {
        return CacheService::getRedisClient() !== null;
    }
    
    /**
     * Obtém cliente Redis
     */
    private function getRedisClient()
    {
        $client = CacheService::getRedisClient();
        if ($client === null) {
            throw new \RuntimeException("Redis não disponível");
        }
        return $client;
    }
    
    /**
     * Garante que a tabela de rate limits existe
     */
    private function ensureTableExists(): void
    {
        try {
            $db = Database::getInstance();
            
            // Verifica se a tabela existe
            $stmt = $db->query("SHOW TABLES LIKE '" . self::DB_TABLE . "'");
            if ($stmt->rowCount() > 0) {
                return;
            }
            
            // Cria tabela
            $db->exec("
                CREATE TABLE IF NOT EXISTS " . self::DB_TABLE . " (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier_key VARCHAR(255) NOT NULL,
                    request_count INT NOT NULL DEFAULT 1,
                    reset_at INT NOT NULL,
                    created_at INT NOT NULL,
                    updated_at INT NOT NULL,
                    INDEX idx_identifier_key (identifier_key),
                    INDEX idx_reset_at (reset_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            Logger::info("Tabela rate_limits criada automaticamente");
        } catch (\Exception $e) {
            Logger::error("Erro ao criar tabela rate_limits", [
                'error' => $e->getMessage()
            ]);
        }
    }
}


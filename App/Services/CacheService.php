<?php

namespace App\Services;

use Predis\Client;
use Config;
use App\Services\Logger;

/**
 * Serviço de cache usando Redis (Predis)
 */
class CacheService
{
    private static ?Client $instance = null;

    /**
     * Obtém instância única do cliente Redis
     */
    private static function getClient(): Client
    {
        if (self::$instance === null) {
            $redisUrl = Config::get('REDIS_URL', 'redis://127.0.0.1:6379');
            
            try {
                self::$instance = new Client($redisUrl);
                // Testa conexão
                self::$instance->ping();
            } catch (\Exception $e) {
                Logger::warning("Redis não disponível: " . $e->getMessage());
                // Retorna cliente que falhará silenciosamente
                self::$instance = new Client($redisUrl, ['exceptions' => false]);
            }
        }

        return self::$instance;
    }

    /**
     * Obtém valor do cache
     */
    public static function get(string $key): ?string
    {
        try {
            $value = self::getClient()->get($key);
            return $value !== null ? $value : null;
        } catch (\Exception $e) {
            Logger::warning("Erro ao ler cache: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Define valor no cache
     */
    public static function set(string $key, string $value, int $ttl = 3600): bool
    {
        try {
            return self::getClient()->setex($key, $ttl, $value) === 'OK';
        } catch (\Exception $e) {
            Logger::warning("Erro ao escrever cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove valor do cache
     */
    public static function delete(string $key): bool
    {
        try {
            return self::getClient()->del($key) > 0;
        } catch (\Exception $e) {
            Logger::warning("Erro ao deletar cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém e decodifica JSON do cache
     */
    public static function getJson(string $key): ?array
    {
        $value = self::get($key);
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Define JSON no cache
     */
    public static function setJson(string $key, array $value, int $ttl = 3600): bool
    {
        return self::set($key, json_encode($value), $ttl);
    }

    /**
     * Cria lock distribuído
     */
    public static function lock(string $key, int $ttl = 60): bool
    {
        try {
            return self::getClient()->set($key, '1', 'EX', $ttl, 'NX') === 'OK';
        } catch (\Exception $e) {
            Logger::warning("Erro ao criar lock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove lock
     */
    public static function unlock(string $key): bool
    {
        return self::delete($key);
    }
}


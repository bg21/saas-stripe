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
    private static bool $connectionFailed = false;  // ✅ Flag para evitar tentativas repetidas

    /**
     * Obtém instância única do cliente Redis
     * ✅ OTIMIZAÇÃO: Adiciona timeout para evitar travamento
     */
    private static function getClient(): ?Client
    {
        // ✅ Se conexão já falhou anteriormente, retorna null imediatamente
        if (self::$connectionFailed) {
            return null;
        }
        
        if (self::$instance === null) {
            $redisUrl = Config::get('REDIS_URL', 'redis://127.0.0.1:6379');
            
            try {
                // ✅ OTIMIZAÇÃO: Timeout de conexão de 1 segundo (evita travamento)
                $options = [
                    'parameters' => [
                        'timeout' => 1.0,  // 1 segundo timeout de conexão
                        'read_timeout' => 1.0,  // 1 segundo timeout de leitura
                        'write_timeout' => 1.0   // 1 segundo timeout de escrita
                    ],
                    'exceptions' => false  // Não lança exceções, retorna null em caso de erro
                ];
                
                self::$instance = new Client($redisUrl, $options);
                
                // ✅ OTIMIZAÇÃO: Testa conexão com timeout curto
                $startTime = microtime(true);
                $result = @self::$instance->ping();
                $elapsed = microtime(true) - $startTime;
                
                // Se ping demorou mais de 500ms ou falhou, considera Redis indisponível
                if ($elapsed > 0.5 || $result !== 'PONG') {
                    throw new \RuntimeException("Redis timeout ou indisponível");
                }
            } catch (\Exception $e) {
                Logger::warning("Redis não disponível: " . $e->getMessage());
                // ✅ Marca como falhou e retorna null
                self::$instance = null;
                self::$connectionFailed = true;
                return null;
            }
        }

        return self::$instance;
    }

    /**
     * Obtém valor do cache
     * ✅ OTIMIZAÇÃO: Retorna null imediatamente se Redis não estiver disponível
     */
    public static function get(string $key): ?string
    {
        try {
            $client = self::getClient();
            if ($client === null) {
                return null; // Redis não disponível, retorna null imediatamente
            }
            
            $value = @$client->get($key);
            return $value !== null ? $value : null;
        } catch (\Exception $e) {
            Logger::warning("Erro ao ler cache: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Define valor no cache
     * ✅ OTIMIZAÇÃO: Retorna false imediatamente se Redis não estiver disponível
     */
    public static function set(string $key, string $value, int $ttl = 3600): bool
    {
        try {
            $client = self::getClient();
            if ($client === null) {
                return false; // Redis não disponível, retorna false imediatamente
            }
            
            return @$client->setex($key, $ttl, $value) === 'OK';
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

    /**
     * Obtém cliente Redis (para uso em outros serviços)
     * Retorna null se Redis não estiver disponível
     * ✅ OTIMIZAÇÃO: Verifica disponibilidade com timeout
     */
    public static function getRedisClient(): ?Client
    {
        try {
            $client = self::getClient();
            if ($client === null) {
                return null;
            }
            
            // ✅ Testa conexão com timeout curto
            $startTime = microtime(true);
            $result = @$client->ping();
            $elapsed = microtime(true) - $startTime;
            
            // Se ping demorou mais de 500ms ou falhou, retorna null
            if ($elapsed > 0.5 || $result !== 'PONG') {
                return null;
            }
            
            return $client;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Invalida cache de listagem de customers
     * 
     * @param int $tenantId ID do tenant
     * @param int|null $customerId ID do customer (opcional, para invalidar cache específico)
     */
    public static function invalidateCustomerCache(int $tenantId, ?int $customerId = null): void
    {
        try {
            $redis = self::getRedisClient();
            if ($redis) {
                // Invalida cache de listagem (padrão)
                $pattern = "customers:list:{$tenantId}:*";
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                
                // Invalida cache específico do customer
                if ($customerId) {
                    self::delete("customers:get:{$tenantId}:{$customerId}");
                }
            }
        } catch (\Exception $e) {
            Logger::warning("Erro ao invalidar cache de customers: " . $e->getMessage());
        }
    }

    /**
     * Invalida cache de listagem de subscriptions
     * 
     * @param int $tenantId ID do tenant
     * @param int|null $subscriptionId ID da subscription (opcional, para invalidar cache específico)
     */
    public static function invalidateSubscriptionCache(int $tenantId, ?int $subscriptionId = null): void
    {
        try {
            $redis = self::getRedisClient();
            if ($redis) {
                // Invalida cache de listagem (padrão)
                $pattern = "subscriptions:list:{$tenantId}:*";
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                
                // Invalida cache específico da subscription
                if ($subscriptionId) {
                    self::delete("subscriptions:get:{$tenantId}:{$subscriptionId}");
                }
            }
        } catch (\Exception $e) {
            Logger::warning("Erro ao invalidar cache de subscriptions: " . $e->getMessage());
        }
    }
}


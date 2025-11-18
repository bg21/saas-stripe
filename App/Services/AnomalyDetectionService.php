<?php

namespace App\Services;

use App\Utils\Database;
use App\Services\Logger;
use PDO;

/**
 * Serviço de Detecção de Anomalias e Bloqueio Automático
 * 
 * Detecta padrões suspeitos de atividade e bloqueia automaticamente após N tentativas
 */
class AnomalyDetectionService
{
    private const DB_TABLE = 'security_events';
    private const MAX_FAILED_ATTEMPTS = 5; // Máximo de tentativas falhadas
    private const BLOCK_DURATION = 900; // 15 minutos em segundos
    private const MAX_FAILED_ATTEMPTS_PER_HOUR = 10; // Máximo por hora
    private const MAX_FAILED_ATTEMPTS_PER_DAY = 30; // Máximo por dia
    
    /**
     * Registra tentativa de login falhada
     * 
     * @param string $identifier Identificador (email, IP, etc.)
     * @param string $type Tipo de evento ('login_failed', 'invalid_token', etc.)
     * @param array $context Contexto adicional
     * @return array ['blocked' => bool, 'remaining_attempts' => int, 'blocked_until' => int|null]
     */
    public function recordFailedAttempt(string $identifier, string $type = 'login_failed', array $context = []): array
    {
        $this->ensureTableExists();
        
        $db = Database::getInstance();
        $now = time();
        
        // Limpa eventos antigos (mais de 24 horas)
        $this->cleanupOldEvents($db, $now - 86400);
        
        // Registra o evento
        $stmt = $db->prepare("
            INSERT INTO " . self::DB_TABLE . " 
            (identifier, event_type, ip_address, user_agent, context, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $contextJson = !empty($context) ? json_encode($context) : null;
        
        $stmt->execute([
            $identifier,
            $type,
            $ip,
            $userAgent,
            $contextJson,
            $now
        ]);
        
        // Verifica se deve bloquear
        $blockStatus = $this->checkAndBlock($identifier, $now);
        
        // Log de segurança
        if ($blockStatus['blocked']) {
            Logger::warning("Bloqueio automático ativado", [
                'identifier' => substr($identifier, 0, 20) . '...',
                'type' => $type,
                'blocked_until' => $blockStatus['blocked_until'],
                'ip' => $ip
            ]);
        } else {
            Logger::info("Tentativa falhada registrada", [
                'identifier' => substr($identifier, 0, 20) . '...',
                'type' => $type,
                'remaining_attempts' => $blockStatus['remaining_attempts'],
                'ip' => $ip
            ]);
        }
        
        return $blockStatus;
    }
    
    /**
     * Verifica se um identificador está bloqueado
     * 
     * @param string $identifier Identificador a verificar
     * @return array ['blocked' => bool, 'blocked_until' => int|null, 'reason' => string|null]
     */
    public function isBlocked(string $identifier): array
    {
        $this->ensureTableExists();
        
        $db = Database::getInstance();
        $now = time();
        
        // Busca bloqueios ativos
        $stmt = $db->prepare("
            SELECT blocked_until, block_reason 
            FROM " . self::DB_TABLE . " 
            WHERE identifier = ? 
            AND blocked_until > ? 
            AND event_type = 'blocked'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$identifier, $now]);
        $block = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($block) {
            return [
                'blocked' => true,
                'blocked_until' => (int)$block['blocked_until'],
                'reason' => $block['block_reason']
            ];
        }
        
        return [
            'blocked' => false,
            'blocked_until' => null,
            'reason' => null
        ];
    }
    
    /**
     * Verifica se deve bloquear e cria bloqueio se necessário
     * 
     * @param string $identifier Identificador
     * @param int $now Timestamp atual
     * @return array ['blocked' => bool, 'remaining_attempts' => int, 'blocked_until' => int|null]
     */
    private function checkAndBlock(string $identifier, int $now): array
    {
        $db = Database::getInstance();
        
        // Conta tentativas falhadas nos últimos minutos (janela de bloqueio)
        $windowStart = $now - 300; // 5 minutos
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM " . self::DB_TABLE . " 
            WHERE identifier = ? 
            AND event_type IN ('login_failed', 'invalid_token', 'unauthorized')
            AND created_at > ?
        ");
        $stmt->execute([$identifier, $windowStart]);
        $recentFailures = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Conta tentativas falhadas na última hora
        $hourStart = $now - 3600;
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM " . self::DB_TABLE . " 
            WHERE identifier = ? 
            AND event_type IN ('login_failed', 'invalid_token', 'unauthorized')
            AND created_at > ?
        ");
        $stmt->execute([$identifier, $hourStart]);
        $hourFailures = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Conta tentativas falhadas nas últimas 24 horas
        $dayStart = $now - 86400;
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM " . self::DB_TABLE . " 
            WHERE identifier = ? 
            AND event_type IN ('login_failed', 'invalid_token', 'unauthorized')
            AND created_at > ?
        ");
        $stmt->execute([$identifier, $dayStart]);
        $dayFailures = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $remainingAttempts = max(0, self::MAX_FAILED_ATTEMPTS - $recentFailures);
        
        // Verifica se deve bloquear
        $shouldBlock = false;
        $blockReason = null;
        
        if ($recentFailures >= self::MAX_FAILED_ATTEMPTS) {
            $shouldBlock = true;
            $blockReason = "Muitas tentativas falhadas em curto período ({$recentFailures} tentativas em 5 minutos)";
        } elseif ($hourFailures >= self::MAX_FAILED_ATTEMPTS_PER_HOUR) {
            $shouldBlock = true;
            $blockReason = "Muitas tentativas falhadas por hora ({$hourFailures} tentativas na última hora)";
        } elseif ($dayFailures >= self::MAX_FAILED_ATTEMPTS_PER_DAY) {
            $shouldBlock = true;
            $blockReason = "Muitas tentativas falhadas por dia ({$dayFailures} tentativas nas últimas 24 horas)";
        }
        
        if ($shouldBlock) {
            $blockedUntil = $now + self::BLOCK_DURATION;
            
            // Cria registro de bloqueio
            $stmt = $db->prepare("
                INSERT INTO " . self::DB_TABLE . " 
                (identifier, event_type, ip_address, user_agent, block_reason, blocked_until, created_at) 
                VALUES (?, 'blocked', ?, ?, ?, ?, ?)
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt->execute([
                $identifier,
                $ip,
                $userAgent,
                $blockReason,
                $blockedUntil,
                $now
            ]);
            
            return [
                'blocked' => true,
                'remaining_attempts' => 0,
                'blocked_until' => $blockedUntil
            ];
        }
        
        return [
            'blocked' => false,
            'remaining_attempts' => $remainingAttempts,
            'blocked_until' => null
        ];
    }
    
    /**
     * Remove bloqueio de um identificador (para uso administrativo)
     * 
     * @param string $identifier Identificador
     * @return bool True se removido com sucesso
     */
    public function unblock(string $identifier): bool
    {
        $this->ensureTableExists();
        
        $db = Database::getInstance();
        $now = time();
        
        // Remove bloqueios ativos
        $stmt = $db->prepare("
            UPDATE " . self::DB_TABLE . " 
            SET blocked_until = ? 
            WHERE identifier = ? 
            AND event_type = 'blocked' 
            AND blocked_until > ?
        ");
        
        $stmt->execute([$now - 1, $identifier, $now]);
        
        Logger::info("Bloqueio removido manualmente", [
            'identifier' => substr($identifier, 0, 20) . '...'
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Obtém estatísticas de segurança para um identificador
     * 
     * @param string $identifier Identificador
     * @return array Estatísticas
     */
    public function getSecurityStats(string $identifier): array
    {
        $this->ensureTableExists();
        
        $db = Database::getInstance();
        $now = time();
        
        // Últimas 24 horas
        $dayStart = $now - 86400;
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type IN ('login_failed', 'invalid_token', 'unauthorized') THEN 1 ELSE 0 END) as failed_attempts,
                SUM(CASE WHEN event_type = 'login_success' THEN 1 ELSE 0 END) as successful_logins
            FROM " . self::DB_TABLE . " 
            WHERE identifier = ? 
            AND created_at > ?
        ");
        $stmt->execute([$identifier, $dayStart]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verifica se está bloqueado
        $blockStatus = $this->isBlocked($identifier);
        
        return [
            'total_events_24h' => (int)$stats['total_events'],
            'failed_attempts_24h' => (int)$stats['failed_attempts'],
            'successful_logins_24h' => (int)$stats['successful_logins'],
            'is_blocked' => $blockStatus['blocked'],
            'blocked_until' => $blockStatus['blocked_until'],
            'block_reason' => $blockStatus['reason']
        ];
    }
    
    /**
     * Registra tentativa de login bem-sucedida (reseta contadores)
     * 
     * @param string $identifier Identificador
     */
    public function recordSuccessfulLogin(string $identifier): void
    {
        $this->ensureTableExists();
        
        $db = Database::getInstance();
        $now = time();
        
        $stmt = $db->prepare("
            INSERT INTO " . self::DB_TABLE . " 
            (identifier, event_type, ip_address, user_agent, created_at) 
            VALUES (?, 'login_success', ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->execute([
            $identifier,
            $ip,
            $userAgent,
            $now
        ]);
    }
    
    /**
     * Limpa eventos antigos
     * 
     * @param PDO $db Conexão com banco
     * @param int $beforeTimestamp Timestamp antes do qual eventos serão removidos
     */
    private function cleanupOldEvents(PDO $db, int $beforeTimestamp): void
    {
        try {
            $stmt = $db->prepare("
                DELETE FROM " . self::DB_TABLE . " 
                WHERE created_at < ?
            ");
            $stmt->execute([$beforeTimestamp]);
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                Logger::debug("Limpeza de eventos de segurança: {$deleted} registros removidos");
            }
        } catch (\Exception $e) {
            Logger::warning("Erro ao limpar eventos antigos", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Garante que a tabela de eventos de segurança existe
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
                    identifier VARCHAR(255) NOT NULL,
                    event_type VARCHAR(50) NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent VARCHAR(500),
                    context JSON,
                    block_reason TEXT,
                    blocked_until INT,
                    created_at INT NOT NULL,
                    INDEX idx_identifier (identifier),
                    INDEX idx_event_type (event_type),
                    INDEX idx_created_at (created_at),
                    INDEX idx_blocked_until (blocked_until),
                    INDEX idx_identifier_created (identifier, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            Logger::info("Tabela security_events criada automaticamente");
        } catch (\Exception $e) {
            Logger::error("Erro ao criar tabela security_events", [
                'error' => $e->getMessage()
            ]);
        }
    }
}


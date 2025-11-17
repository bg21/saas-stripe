<?php

namespace App\Utils;

/**
 * Classe auxiliar para funções de segurança
 */
class SecurityHelper
{
    /**
     * Escapa HTML para prevenir XSS
     * 
     * @param string|null $text Texto a ser escapado
     * @return string Texto escapado
     */
    public static function escapeHtml(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Valida se um campo está na whitelist permitida
     * 
     * @param string $field Campo a validar
     * @param array $allowedFields Lista de campos permitidos
     * @return bool True se permitido
     */
    public static function isFieldAllowed(string $field, array $allowedFields): bool
    {
        return in_array($field, $allowedFields, true);
    }
    
    /**
     * Valida direção de ordenação (ASC/DESC)
     * 
     * @param string $direction Direção a validar
     * @return string Direção validada (ASC ou DESC)
     */
    public static function validateOrderDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        return in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC';
    }
    
    /**
     * Sanitiza nome de campo para uso em SQL (apenas alfanumérico e underscore)
     * 
     * @param string $field Nome do campo
     * @return string Campo sanitizado
     */
    public static function sanitizeFieldName(string $field): string
    {
        // Remove caracteres não permitidos, mantém apenas alfanuméricos e underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '', $field);
    }
    
    /**
     * Valida tamanho de payload
     * 
     * @param int $maxSize Tamanho máximo em bytes
     * @return bool True se dentro do limite
     */
    public static function validatePayloadSize(int $maxSize = 1048576): bool
    {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        return $contentLength <= $maxSize;
    }
    
    /**
     * Gera token CSRF
     * 
     * @return string Token CSRF
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Valida token CSRF
     * 
     * @param string $token Token a validar
     * @param int $maxAge Idade máxima do token em segundos (padrão: 3600 = 1 hora)
     * @return bool True se válido
     */
    public static function validateCsrfToken(string $token, int $maxAge = 3600): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Verifica se token expirou
        if (time() - $_SESSION['csrf_token_time'] > $maxAge) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // Comparação segura (timing-safe)
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}


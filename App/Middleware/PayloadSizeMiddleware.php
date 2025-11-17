<?php

namespace App\Middleware;

use Flight;
use Config;
use App\Services\Logger;

/**
 * Middleware para validar tamanho de payload
 * Previne DoS via requisições muito grandes
 */
class PayloadSizeMiddleware
{
    private const MAX_PAYLOAD_SIZE = 1048576; // 1MB
    private const MAX_PAYLOAD_SIZE_STRICT = 524288; // 512KB para endpoints críticos
    
    /**
     * Verifica se o tamanho do payload está dentro do limite
     * 
     * @param int|null $maxSize Tamanho máximo em bytes (null = usa padrão)
     * @return bool True se permitido, false se bloqueado
     */
    public function check(?int $maxSize = null): bool
    {
        $maxSize = $maxSize ?? self::MAX_PAYLOAD_SIZE;
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        
        if ($contentLength > $maxSize) {
            Logger::warning("Payload muito grande rejeitado", [
                'size' => $contentLength,
                'max' => $maxSize,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            
            Flight::json([
                'error' => 'Payload muito grande',
                'message' => "O tamanho máximo permitido é " . $this->formatBytes($maxSize),
                'code' => 'PAYLOAD_TOO_LARGE'
            ], 413);
            
            Flight::stop();
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica com limite mais restritivo (para endpoints críticos)
     * 
     * @return bool True se permitido
     */
    public function checkStrict(): bool
    {
        return $this->check(self::MAX_PAYLOAD_SIZE_STRICT);
    }
    
    /**
     * Formata bytes para formato legível
     * 
     * @param int $bytes Bytes
     * @return string String formatada
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}


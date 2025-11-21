<?php

namespace App\Utils;

/**
 * ✅ OTIMIZAÇÃO: Cache para php://input
 * 
 * O stream php://input só pode ser lido uma vez.
 * Esta classe garante que seja lido apenas uma vez e cacheado.
 */
class RequestCache
{
    private static ?string $inputCache = null;
    private static bool $inputRead = false;

    /**
     * Obtém o conteúdo de php://input (lê apenas uma vez)
     * 
     * ✅ TESTES: Suporta mock via variável global para testes unitários
     * 
     * @return string|null Conteúdo do input ou null se vazio
     */
    public static function getInput(): ?string
    {
        // ✅ TESTES: Permite mockar input para testes unitários
        if (defined('TESTING') && TESTING && isset($GLOBALS['__php_input_mock'])) {
            return $GLOBALS['__php_input_mock'];
        }
        
        if (self::$inputRead) {
            return self::$inputCache;
        }

        self::$inputRead = true;
        self::$inputCache = @file_get_contents('php://input') ?: null;

        return self::$inputCache;
    }

    /**
     * Obtém e decodifica JSON do input com validação rigorosa
     * 
     * @return array|null Dados decodificados ou null se inválido
     */
    public static function getJsonInput(): ?array
    {
        $input = self::getInput();
        if ($input === null || $input === '') {
            return null;
        }

        // Valida tamanho máximo do JSON (prevenção de DoS)
        if (strlen($input) > 1048576) { // 1MB
            return null;
        }

        $decoded = json_decode($input, true);
        
        // Valida se houve erro no decode
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Valida se o resultado é um array (não objeto ou outro tipo)
        if (!is_array($decoded)) {
            return null;
        }
        
        return $decoded;
    }

    /**
     * Limpa o cache (útil para testes)
     */
    public static function clear(): void
    {
        self::$inputCache = null;
        self::$inputRead = false;
    }
}


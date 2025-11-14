<?php

namespace Tests\Unit;

/**
 * Helper para testes unitários
 */
class TestHelper
{
    private static ?string $mockInput = null;

    /**
     * Define o conteúdo mockado para file_get_contents('php://input')
     */
    public static function setMockInput(string $data): void
    {
        self::$mockInput = $data;
    }

    /**
     * Limpa o mock
     */
    public static function clearMockInput(): void
    {
        self::$mockInput = null;
    }

    /**
     * Obtém o conteúdo mockado ou null
     */
    public static function getMockInput(): ?string
    {
        return self::$mockInput;
    }
}


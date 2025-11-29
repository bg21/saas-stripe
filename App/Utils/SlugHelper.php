<?php

namespace App\Utils;

/**
 * Helper para gerar slugs a partir de strings
 */
class SlugHelper
{
    /**
     * Gera um slug a partir de uma string
     * 
     * Exemplos:
     * "Cão que Mia" → "cao-que-mia"
     * "Clínica Veterinária ABC" → "clinica-veterinaria-abc"
     * "Pet Shop XYZ!" → "pet-shop-xyz"
     * 
     * @param string $text Texto a ser convertido em slug
     * @param int $maxLength Tamanho máximo do slug (padrão: 100)
     * @return string Slug gerado
     */
    public static function generate(string $text, int $maxLength = 100): string
    {
        // Remove espaços extras e converte para minúsculas
        $text = trim($text);
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove acentos
        $text = self::removeAccents($text);
        
        // Remove caracteres especiais, mantém apenas letras, números e espaços
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        
        // Substitui espaços e múltiplos hífens por um único hífen
        $text = preg_replace('/[\s-]+/', '-', $text);
        
        // Remove hífens no início e fim
        $text = trim($text, '-');
        
        // Limita tamanho
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            $text = rtrim($text, '-'); // Remove hífen no final se cortou no meio
        }
        
        // Se ficou vazio, gera um slug padrão
        if (empty($text)) {
            $text = 'tenant-' . time();
        }
        
        return $text;
    }
    
    /**
     * Remove acentos de uma string
     * 
     * @param string $text Texto com acentos
     * @return string Texto sem acentos
     */
    private static function removeAccents(string $text): string
    {
        // Mapeamento de caracteres acentuados
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];
        
        return strtr($text, $accents);
    }
    
    /**
     * Gera um slug único adicionando um sufixo numérico se necessário
     * 
     * @param string $text Texto base
     * @param callable $checkExists Função que verifica se slug existe: function(string $slug): bool
     * @param int $maxLength Tamanho máximo do slug
     * @return string Slug único
     */
    public static function generateUnique(string $text, callable $checkExists, int $maxLength = 100): string
    {
        $baseSlug = self::generate($text, $maxLength);
        $slug = $baseSlug;
        $counter = 1;
        
        // Se slug já existe, adiciona número no final
        while ($checkExists($slug)) {
            // Calcula espaço disponível para o número
            $numberLength = strlen((string)$counter) + 1; // +1 para o hífen
            $availableLength = $maxLength - $numberLength;
            
            // Trunca base slug se necessário
            $truncatedBase = substr($baseSlug, 0, $availableLength);
            $truncatedBase = rtrim($truncatedBase, '-');
            
            $slug = $truncatedBase . '-' . $counter;
            $counter++;
            
            // Proteção contra loop infinito
            if ($counter > 1000) {
                // Se chegou a 1000, usa timestamp
                $slug = $truncatedBase . '-' . time();
                break;
            }
        }
        
        return $slug;
    }
    
    /**
     * Valida se um slug é válido
     * 
     * @param string $slug Slug a validar
     * @return bool True se válido, false caso contrário
     */
    public static function isValid(string $slug): bool
    {
        // Slug deve ter entre 3 e 100 caracteres
        if (strlen($slug) < 3 || strlen($slug) > 100) {
            return false;
        }
        
        // Slug deve conter apenas letras minúsculas, números e hífens
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return false;
        }
        
        // Slug não pode começar ou terminar com hífen
        if (strpos($slug, '-') === 0 || substr($slug, -1) === '-') {
            return false;
        }
        
        // Slug não pode ter hífens consecutivos
        if (strpos($slug, '--') !== false) {
            return false;
        }
        
        return true;
    }
}


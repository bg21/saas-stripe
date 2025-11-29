<?php

/**
 * Bootstrap para testes PHPUnit
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

// Carrega configurações
Config::load();

// Define ambiente de teste
if (!defined('TESTING')) {
    define('TESTING', true);
}

// Mock para file_get_contents('php://input') usado em RequestCache
if (!function_exists('file_get_contents')) {
    function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
        if ($filename === 'php://input' && isset($GLOBALS['mock_json_input'])) {
            return $GLOBALS['mock_json_input'];
        }
        return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
    }
}

// Inicializa Flight para testes de integração
if (class_exists('Flight')) {
    // Flight já deve estar carregado via autoload
}


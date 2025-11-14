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


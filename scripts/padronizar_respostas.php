<?php

/**
 * Script para padronizar respostas de erro nos controllers
 * 
 * Este script identifica padrões comuns de Flight::json() que devem ser
 * substituídos por ResponseHelper para padronização.
 * 
 * Uso: php scripts/padronizar_respostas.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$controllers = [
    'App/Controllers/InvoiceItemController.php',
    'App/Controllers/CouponController.php',
    'App/Controllers/SetupIntentController.php',
    'App/Controllers/PromotionCodeController.php',
    'App/Controllers/TaxRateController.php',
    'App/Controllers/PriceController.php',
    'App/Controllers/ProductController.php',
    'App/Controllers/CustomerController.php',
    'App/Controllers/SubscriptionController.php',
    'App/Controllers/UserController.php'
];

echo "📋 Padronização de Respostas de Erro\n";
echo "=====================================\n\n";

$totalSubstituicoes = 0;

foreach ($controllers as $controller) {
    $file = __DIR__ . '/../' . $controller;
    
    if (!file_exists($file)) {
        echo "⚠️  Arquivo não encontrado: $controller\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Padrões de substituição
    $patterns = [
        // Erro 401 - Não autenticado
        [
            'pattern' => "/Flight::json\(\[['\"]error['\"]\s*=>\s*['\"]Não autenticado['\"]\],\s*401\);/",
            'replacement' => "ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'ACTION_NAME']);",
            'description' => 'Erro 401 - Não autenticado'
        ],
        // Erro 404 - Não encontrado (genérico)
        [
            'pattern' => "/Flight::json\(\[['\"]error['\"]\s*=>\s*['\"]([^'\"]+) não encontrado['\"]\],\s*404\);/",
            'replacement' => "ResponseHelper::sendNotFoundError('$1', ['action' => 'ACTION_NAME']);",
            'description' => 'Erro 404 - Não encontrado'
        ],
        // Erro 400 - JSON inválido
        [
            'pattern' => "/Flight::json\(\[['\"]error['\"]\s*=>\s*['\"]JSON inválido[^'\"]*['\"]\],\s*400\);/",
            'replacement' => "ResponseHelper::sendInvalidJsonError(['action' => 'ACTION_NAME']);",
            'description' => 'Erro 400 - JSON inválido'
        ],
    ];
    
    $substituicoes = 0;
    
    foreach ($patterns as $pattern) {
        $count = 0;
        $content = preg_replace($pattern['pattern'], $pattern['replacement'], $content, -1, $count);
        if ($count > 0) {
            $substituicoes += $count;
            echo "  ✅ {$pattern['description']}: $count substituições\n";
        }
    }
    
    if ($substituicoes > 0) {
        // file_put_contents($file, $content);
        echo "📝 $controller: $substituicoes substituições identificadas (não aplicadas automaticamente)\n";
        $totalSubstituicoes += $substituicoes;
    }
}

echo "\n📊 Total: $totalSubstituicoes substituições identificadas\n";
echo "\n⚠️  Este script apenas identifica padrões. As substituições devem ser feitas manualmente.\n";


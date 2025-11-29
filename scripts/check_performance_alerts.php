<?php

/**
 * Script CLI para verificar alertas de performance
 * 
 * Uso:
 *   php scripts/check_performance_alerts.php              - Verifica todos os tenants
 *   php scripts/check_performance_alerts.php 1           - Verifica tenant específico
 *   php scripts/check_performance_alerts.php --hours 24   - Verifica últimas 24 horas
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Services\PerformanceAlertService;

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$reset = "\033[0m";

// Parâmetros
$tenantId = null;
$hours = 1;

foreach ($argv as $arg) {
    if (is_numeric($arg) && $arg > 0) {
        $tenantId = (int)$arg;
    } elseif (strpos($arg, '--hours=') === 0) {
        $hours = (int)substr($arg, 8);
    } elseif ($arg === '--hours' && isset($argv[array_search($arg, $argv) + 1])) {
        $hours = (int)$argv[array_search($arg, $argv) + 1];
    }
}

echo "{$cyan}🔔 Verificação de Alertas de Performance{$reset}\n";
echo str_repeat("=", 70) . "\n\n";

if ($tenantId) {
    echo "Tenant ID: {$blue}{$tenantId}{$reset}\n";
} else {
    echo "Escopo: {$blue}Todos os tenants{$reset}\n";
}
echo "Período: {$blue}Últimas {$hours} hora(s){$reset}\n\n";

try {
    $alertService = new PerformanceAlertService();
    $alerts = $alertService->checkSlowEndpoints($tenantId, $hours);
    
    if (empty($alerts)) {
        echo "{$green}✅ Nenhum alerta encontrado. Todos os endpoints estão dentro dos limites!{$reset}\n";
        exit(0);
    }
    
    // Agrupa por severidade
    $critical = array_filter($alerts, fn($a) => $a['severity'] === 'critical');
    $warnings = array_filter($alerts, fn($a) => $a['severity'] === 'warning');
    
    echo "{$red}🚨 Alertas Críticos: " . count($critical) . "{$reset}\n";
    echo "{$yellow}⚠️  Alertas de Aviso: " . count($warnings) . "{$reset}\n\n";
    
    if (!empty($critical)) {
        echo "{$red}═══════════════════════════════════════════════════════════════{$reset}\n";
        echo "{$red}🚨 ALERTAS CRÍTICOS{$reset}\n";
        echo "{$red}═══════════════════════════════════════════════════════════════{$reset}\n\n";
        
        foreach ($critical as $alert) {
            echo "{$red}Endpoint:{$reset} {$alert['method']} {$alert['endpoint']}\n";
            echo "{$red}Tempo Médio:{$reset} {$alert['avg_duration_ms']}ms (limite: {$alert['threshold']}ms)\n";
            echo "{$red}Requisições:{$reset} {$alert['total_requests']}\n";
            echo "{$red}Mensagem:{$reset} {$alert['message']}\n";
            echo "\n";
        }
    }
    
    if (!empty($warnings)) {
        echo "{$yellow}═══════════════════════════════════════════════════════════════{$reset}\n";
        echo "{$yellow}⚠️  ALERTAS DE AVISO{$reset}\n";
        echo "{$yellow}═══════════════════════════════════════════════════════════════{$reset}\n\n";
        
        foreach ($warnings as $alert) {
            echo "{$yellow}Endpoint:{$reset} {$alert['method']} {$alert['endpoint']}\n";
            echo "{$yellow}Tempo Médio:{$reset} {$alert['avg_duration_ms']}ms (limite: {$alert['threshold']}ms)\n";
            echo "{$yellow}Requisições:{$reset} {$alert['total_requests']}\n";
            echo "{$yellow}Mensagem:{$reset} {$alert['message']}\n";
            echo "\n";
        }
    }
    
    // Mostra endpoints mais lentos
    echo "{$cyan}═══════════════════════════════════════════════════════════════{$reset}\n";
    echo "{$cyan}🐌 Top 10 Endpoints Mais Lentos{$reset}\n";
    echo "{$cyan}═══════════════════════════════════════════════════════════════{$reset}\n\n";
    
    $slowest = $alertService->getSlowestEndpoints($tenantId, 10, $hours);
    
    if (empty($slowest)) {
        echo "{$green}Nenhum endpoint lento encontrado{$reset}\n";
    } else {
        foreach ($slowest as $index => $stat) {
            $avgDuration = (float)($stat['avg_duration_ms'] ?? 0);
            $badge = $avgDuration >= 1000 ? $red : ($avgDuration >= 500 ? $yellow : $green);
            echo ($index + 1) . ". {$badge}{$stat['method']} {$stat['endpoint']}{$reset} - {$avgDuration}ms ({$stat['total_requests']} req)\n";
        }
    }
    
    exit(count($critical) > 0 ? 1 : 0);
    
} catch (\Exception $e) {
    echo "{$red}❌ Erro: {$e->getMessage()}{$reset}\n";
    exit(1);
}


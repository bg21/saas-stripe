<?php

/**
 * Script CLI para limpar métricas de performance antigas
 * 
 * Uso:
 *   php scripts/cleanup_performance_metrics.php              - Remove métricas com mais de 30 dias (padrão)
 *   php scripts/cleanup_performance_metrics.php 60           - Remove métricas com mais de 60 dias
 *   php scripts/cleanup_performance_metrics.php 30 --dry-run - Simula sem deletar
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\PerformanceMetric;
use App\Utils\Database;

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$reset = "\033[0m";

// Parâmetros
$retentionDays = isset($argv[1]) ? (int)$argv[1] : (int)Config::get('PERFORMANCE_METRICS_RETENTION_DAYS', '30');
$dryRun = in_array('--dry-run', $argv) || in_array('-n', $argv);

echo "{$cyan}🧹 Limpeza de Métricas de Performance{$reset}\n";
echo str_repeat("=", 70) . "\n\n";

if ($dryRun) {
    echo "{$yellow}⚠️  MODO DRY-RUN: Nenhuma alteração será feita{$reset}\n\n";
}

echo "Retenção: {$blue}{$retentionDays} dias{$reset}\n";
echo "Data limite: {$blue}" . date('Y-m-d H:i:s', strtotime("-{$retentionDays} days")) . "{$reset}\n\n";

try {
    $db = Database::getInstance();
    $metricModel = new PerformanceMetric();
    
    // Conta métricas que serão removidas
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
    $countSql = "SELECT COUNT(*) as total FROM performance_metrics WHERE created_at < :cutoff_date";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute(['cutoff_date' => $cutoffDate]);
    $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
    $totalToDelete = (int)($countResult['total'] ?? 0);
    
    echo "Métricas encontradas para remoção: {$yellow}{$totalToDelete}{$reset}\n\n";
    
    if ($totalToDelete === 0) {
        echo "{$green}✅ Nenhuma métrica antiga encontrada. Nada a fazer.{$reset}\n";
        exit(0);
    }
    
    if ($dryRun) {
        echo "{$yellow}⚠️  DRY-RUN: Seriam removidas {$totalToDelete} métricas{$reset}\n";
        exit(0);
    }
    
    // Confirmação
    echo "{$yellow}⚠️  ATENÇÃO: Esta operação irá remover {$totalToDelete} métricas permanentemente!{$reset}\n";
    echo "Deseja continuar? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
        echo "{$red}❌ Operação cancelada pelo usuário{$reset}\n";
        exit(1);
    }
    
    echo "\n{$blue}🗑️  Removendo métricas...{$reset}\n";
    
    // Remove métricas antigas
    $deleteSql = "DELETE FROM performance_metrics WHERE created_at < :cutoff_date";
    $deleteStmt = $db->prepare($deleteSql);
    $deleteStmt->execute(['cutoff_date' => $cutoffDate]);
    $deleted = $deleteStmt->rowCount();
    
    echo "{$green}✅ Removidas {$deleted} métricas com sucesso!{$reset}\n";
    
    // Estatísticas finais
    $remainingSql = "SELECT COUNT(*) as total FROM performance_metrics";
    $remainingStmt = $db->prepare($remainingSql);
    $remainingStmt->execute();
    $remainingResult = $remainingStmt->fetch(\PDO::FETCH_ASSOC);
    $remaining = (int)($remainingResult['total'] ?? 0);
    
    echo "\n{$cyan}📊 Estatísticas:{$reset}\n";
    echo "  - Removidas: {$red}{$deleted}{$reset}\n";
    echo "  - Restantes: {$green}{$remaining}{$reset}\n";
    
} catch (\Exception $e) {
    echo "{$red}❌ Erro: {$e->getMessage()}{$reset}\n";
    exit(1);
}

echo "\n{$green}✅ Limpeza concluída com sucesso!{$reset}\n";


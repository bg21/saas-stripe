<?php

/**
 * Script CLI para gerenciar backups do banco de dados
 * 
 * Uso:
 *   php scripts/backup.php create          - Cria um novo backup
 *   php scripts/backup.php list            - Lista backups disponíveis
 *   php scripts/backup.php stats           - Mostra estatísticas de backups
 *   php scripts/backup.php clean           - Remove backups antigos
 *   php scripts/backup.php restore <id>    - Restaura um backup específico
 *   php scripts/backup.php get <id>         - Mostra informações de um backup
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Services\BackupService;

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$reset = "\033[0m";

// Função para exibir ajuda
function showHelp(): void
{
    global $cyan, $reset;
    
    echo "{$cyan}📦 Sistema de Backup Automático{$reset}\n";
    echo str_repeat("=", 70) . "\n\n";
    echo "Uso: php scripts/backup.php <comando> [opções]\n\n";
    echo "Comandos disponíveis:\n";
    echo "  create              Cria um novo backup do banco de dados\n";
    echo "  list [limit]        Lista backups disponíveis (padrão: 50)\n";
    echo "  stats               Mostra estatísticas de backups\n";
    echo "  clean               Remove backups antigos (baseado em retenção)\n";
    echo "  restore <id>        Restaura um backup específico\n";
    echo "  get <id>            Mostra informações detalhadas de um backup\n";
    echo "  help                Mostra esta ajuda\n\n";
    echo "Exemplos:\n";
    echo "  php scripts/backup.php create\n";
    echo "  php scripts/backup.php list 10\n";
    echo "  php scripts/backup.php restore 1\n";
    echo "  php scripts/backup.php get 1\n\n";
}

// Função para formatar tamanho
function formatSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Função para formatar data
function formatDate(?string $date): string
{
    if (!$date) {
        return 'N/A';
    }
    return date('d/m/Y H:i:s', strtotime($date));
}

// Obtém comando
$command = $argv[1] ?? 'help';

try {
    $backupService = new BackupService();
    
    switch ($command) {
        case 'create':
            echo "{$blue}🔄 Criando backup...{$reset}\n";
            $result = $backupService->createBackup();
            
            echo "{$green}✅ Backup criado com sucesso!{$reset}\n\n";
            echo "ID: {$result['id']}\n";
            echo "Arquivo: {$result['filename']}\n";
            echo "Tamanho: " . formatSize($result['file_size']) . "\n";
            echo "Duração: {$result['duration']}s\n";
            echo "Comprimido: " . ($result['compressed'] ? 'Sim' : 'Não') . "\n";
            echo "Criado em: {$result['created_at']}\n";
            break;
            
        case 'list':
            $limit = isset($argv[2]) ? (int)$argv[2] : 50;
            echo "{$blue}📋 Listando backups (limite: {$limit})...{$reset}\n\n";
            
            $backups = $backupService->listBackups($limit);
            
            if (empty($backups)) {
                echo "{$yellow}⚠️  Nenhum backup encontrado.{$reset}\n";
                break;
            }
            
            printf("%-5s %-30s %-12s %-10s %-20s %-8s\n", 
                'ID', 'Arquivo', 'Tamanho', 'Status', 'Criado em', 'Existe');
            echo str_repeat("-", 90) . "\n";
            
            foreach ($backups as $backup) {
                $status = $backup['status'] === 'success' ? "{$green}✓{$reset}" : "{$red}✗{$reset}";
                $exists = ($backup['file_exists'] ?? false) ? "{$green}Sim{$reset}" : "{$red}Não{$reset}";
                $size = isset($backup['file_size_mb']) 
                    ? formatSize($backup['file_size']) 
                    : formatSize($backup['file_size']);
                
                printf("%-5s %-30s %-12s %-10s %-20s %-8s\n",
                    $backup['id'],
                    substr($backup['filename'], 0, 28),
                    $size,
                    $status,
                    formatDate($backup['created_at']),
                    $exists
                );
            }
            break;
            
        case 'stats':
            echo "{$blue}📊 Estatísticas de Backups{$reset}\n";
            echo str_repeat("=", 70) . "\n\n";
            
            $stats = $backupService->getStatistics();
            
            echo "Total de backups: {$stats['total']}\n";
            echo "{$green}Bem-sucedidos: {$stats['successful']}{$reset}\n";
            echo "{$red}Falhados: {$stats['failed']}{$reset}\n";
            echo "Tamanho total: " . formatSize((int)($stats['total_size_mb'] * 1024 * 1024)) . "\n";
            echo "Retenção: {$stats['retention_days']} dias\n";
            echo "Próxima limpeza: " . formatDate($stats['next_cleanup']) . "\n\n";
            
            if ($stats['last_backup']) {
                echo "{$cyan}Último backup:{$reset}\n";
                echo "  ID: {$stats['last_backup']['id']}\n";
                echo "  Arquivo: {$stats['last_backup']['filename']}\n";
                echo "  Criado em: " . formatDate($stats['last_backup']['created_at']) . "\n";
            } else {
                echo "{$yellow}⚠️  Nenhum backup criado ainda.{$reset}\n";
            }
            break;
            
        case 'clean':
            echo "{$blue}🧹 Removendo backups antigos...{$reset}\n";
            
            $result = $backupService->cleanOldBackups();
            
            echo "{$green}✅ Limpeza concluída!{$reset}\n\n";
            echo "Backups removidos: {$result['deleted']}\n";
            echo "Erros: {$result['errors']}\n";
            echo "Espaço liberado: " . formatSize((int)($result['total_size_mb'] * 1024 * 1024)) . "\n";
            echo "Retenção: {$result['retention_days']} dias\n";
            break;
            
        case 'restore':
            if (!isset($argv[2])) {
                echo "{$red}❌ Erro: ID do backup não fornecido.{$reset}\n";
                echo "Uso: php scripts/backup.php restore <id>\n";
                exit(1);
            }
            
            $backupId = (int)$argv[2];
            
            echo "{$yellow}⚠️  ATENÇÃO: Esta operação irá restaurar o banco de dados!{$reset}\n";
            echo "Deseja continuar? (sim/não): ";
            
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 'sim' && strtolower($line) !== 's' && strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
                echo "{$yellow}Operação cancelada.{$reset}\n";
                exit(0);
            }
            
            echo "\n{$blue}🔄 Restaurando backup #{$backupId}...{$reset}\n";
            
            $result = $backupService->restoreBackup($backupId, false);
            
            echo "{$green}✅ Backup restaurado com sucesso!{$reset}\n\n";
            echo "Duração: {$result['duration']}s\n";
            echo "Mensagem: {$result['message']}\n";
            break;
            
        case 'get':
            if (!isset($argv[2])) {
                echo "{$red}❌ Erro: ID do backup não fornecido.{$reset}\n";
                echo "Uso: php scripts/backup.php get <id>\n";
                exit(1);
            }
            
            $backupId = (int)$argv[2];
            
            echo "{$blue}📄 Informações do Backup #{$backupId}{$reset}\n";
            echo str_repeat("=", 70) . "\n\n";
            
            $backup = $backupService->getBackup($backupId);
            
            if (!$backup) {
                echo "{$red}❌ Backup não encontrado.{$reset}\n";
                exit(1);
            }
            
            echo "ID: {$backup['id']}\n";
            echo "Arquivo: {$backup['filename']}\n";
            echo "Caminho: {$backup['file_path']}\n";
            echo "Tamanho: " . formatSize($backup['file_size']) . "\n";
            echo "Status: " . ($backup['status'] === 'success' ? "{$green}Sucesso{$reset}" : "{$red}Falhou{$reset}") . "\n";
            echo "Duração: {$backup['duration_seconds']}s\n";
            echo "Comprimido: " . ($backup['compressed'] ? 'Sim' : 'Não') . "\n";
            echo "Arquivo existe: " . (($backup['file_exists'] ?? false) ? "{$green}Sim{$reset}" : "{$red}Não{$reset}") . "\n";
            
            if (isset($backup['file_modified'])) {
                echo "Modificado em: " . formatDate($backup['file_modified']) . "\n";
            }
            
            echo "Criado em: " . formatDate($backup['created_at']) . "\n";
            
            if (!empty($backup['error_message'])) {
                echo "\n{$red}Erro:{$reset} {$backup['error_message']}\n";
            }
            break;
            
        case 'help':
        default:
            showHelp();
            break;
    }
    
} catch (\Exception $e) {
    echo "{$red}❌ Erro: {$e->getMessage()}{$reset}\n";
    exit(1);
}


<?php

/**
 * Script de teste para Sistema de Backup Automático
 * 
 * Testa:
 * - Estrutura da tabela backup_logs
 * - BackupService (criação, listagem, estatísticas)
 * - Configurações de backup
 * - Script CLI
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

echo "🧪 Teste de Sistema de Backup Automático\n";
echo str_repeat("=", 70) . "\n\n";

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

// Contadores de testes
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Função para testar e registrar resultado
function testResult(string $description, bool $passed, ?string $error = null): void
{
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    
    $totalTests++;
    
    if ($passed) {
        $passedTests++;
        echo "{$green}✅{$reset} {$description}\n";
    } else {
        $failedTests++;
        echo "{$red}❌{$reset} {$description}\n";
        if ($error) {
            echo "   Erro: {$error}\n";
        }
    }
    echo "\n";
}

// ============================================================================
// TESTE 1: Verificar se tabela backup_logs existe
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: Verificar estrutura da tabela backup_logs\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $db = \App\Utils\Database::getInstance();
    
    // Verifica se tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'backup_logs'");
    $tableExists = $stmt->rowCount() > 0;
    
    testResult(
        "Tabela backup_logs existe",
        $tableExists,
        $tableExists ? null : "Execute: composer run migrate"
    );
    
    if ($tableExists) {
        // Verifica colunas
        $stmt = $db->query("DESCRIBE backup_logs");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $requiredColumns = ['id', 'filename', 'file_path', 'file_size', 'status', 'duration_seconds', 'compressed', 'error_message', 'created_at'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        testResult(
            "Todas as colunas necessárias existem",
            empty($missingColumns),
            empty($missingColumns) ? null : "Colunas faltando: " . implode(', ', $missingColumns)
        );
    }
    
} catch (\Exception $e) {
    testResult(
        "Erro ao verificar tabela backup_logs",
        false,
        $e->getMessage()
    );
}

// ============================================================================
// TESTE 2: Verificar BackupService
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 2: Verificar BackupService\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $backupService = new \App\Services\BackupService();
    
    testResult(
        "BackupService instanciado com sucesso",
        true
    );
    
    // Testa método getStatistics
    try {
        $stats = $backupService->getStatistics();
        testResult(
            "Método getStatistics funciona",
            is_array($stats) && isset($stats['total'])
        );
    } catch (\Exception $e) {
        testResult(
            "Método getStatistics funciona",
            false,
            $e->getMessage()
        );
    }
    
    // Testa método listBackups
    try {
        $backups = $backupService->listBackups(10);
        testResult(
            "Método listBackups funciona",
            is_array($backups)
        );
    } catch (\Exception $e) {
        testResult(
            "Método listBackups funciona",
            false,
            $e->getMessage()
        );
    }
    
} catch (\Exception $e) {
    testResult(
        "BackupService não pode ser instanciado",
        false,
        $e->getMessage()
    );
}

// ============================================================================
// TESTE 3: Verificar BackupLog Model
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 3: Verificar BackupLog Model\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $backupLog = new \App\Models\BackupLog();
    
    testResult(
        "BackupLog model instanciado com sucesso",
        true
    );
    
    // Verifica se método create existe
    testResult(
        "Método create existe no BackupLog",
        method_exists($backupLog, 'create')
    );
    
} catch (\Exception $e) {
    testResult(
        "BackupLog model não pode ser instanciado",
        false,
        $e->getMessage()
    );
}

// ============================================================================
// TESTE 4: Verificar configurações
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 4: Verificar configurações de backup\n";
echo str_repeat("=", 70) . "\n\n";

$backupDir = Config::get('BACKUP_DIR', 'backups');
$retentionDays = Config::get('BACKUP_RETENTION_DAYS', '30');
$compress = Config::get('BACKUP_COMPRESS', 'true');

testResult(
    "BACKUP_DIR configurado",
    !empty($backupDir),
    empty($backupDir) ? "Adicione BACKUP_DIR no .env" : null
);

testResult(
    "BACKUP_RETENTION_DAYS configurado",
    !empty($retentionDays) && is_numeric($retentionDays),
    empty($retentionDays) ? "Adicione BACKUP_RETENTION_DAYS no .env" : null
);

testResult(
    "BACKUP_COMPRESS configurado",
    !empty($compress),
    empty($compress) ? "Adicione BACKUP_COMPRESS no .env" : null
);

// ============================================================================
// TESTE 5: Verificar script CLI
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 5: Verificar script CLI (backup.php)\n";
echo str_repeat("=", 70) . "\n\n";

$scriptPath = __DIR__ . '/backup.php';
testResult(
    "Script backup.php existe",
    file_exists($scriptPath),
    file_exists($scriptPath) ? null : "Arquivo scripts/backup.php não encontrado"
);

if (file_exists($scriptPath)) {
    // Verifica se script é executável (pelo menos existe)
    testResult(
        "Script backup.php é acessível",
        is_readable($scriptPath)
    );
}

// ============================================================================
// TESTE 6: Verificar comandos composer
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 6: Verificar comandos composer\n";
echo str_repeat("=", 70) . "\n\n";

$composerJson = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
$scripts = $composerJson['scripts'] ?? [];

$requiredScripts = ['backup', 'backup:list', 'backup:stats', 'backup:clean'];
$missingScripts = array_diff($requiredScripts, array_keys($scripts));

testResult(
    "Comandos composer de backup configurados",
    empty($missingScripts),
    empty($missingScripts) ? null : "Comandos faltando: " . implode(', ', $missingScripts)
);

// ============================================================================
// TESTE 7: Verificar diretório de backups
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 7: Verificar diretório de backups\n";
echo str_repeat("=", 70) . "\n\n";

$backupDir = Config::get('BACKUP_DIR', 'backups');
$fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $backupDir;

// Tenta criar diretório se não existir
if (!is_dir($fullPath)) {
    @mkdir($fullPath, 0755, true);
}

testResult(
    "Diretório de backups existe ou pode ser criado",
    is_dir($fullPath),
    is_dir($fullPath) ? null : "Não foi possível criar diretório: {$fullPath}"
);

if (is_dir($fullPath)) {
    testResult(
        "Diretório de backups é gravável",
        is_writable($fullPath),
        is_writable($fullPath) ? null : "Diretório não é gravável: {$fullPath}"
    );
}

// ============================================================================
// RESUMO DOS TESTES
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 70) . "\n\n";

echo "Total de testes: {$totalTests}\n";
echo "{$green}Testes passados: {$passedTests}{$reset}\n";
echo "{$red}Testes falhados: {$failedTests}{$reset}\n\n";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "Taxa de sucesso: {$successRate}%\n\n";

if ($failedTests > 0) {
    echo "{$red}❌ Alguns testes falharam! Verifique os logs acima.{$reset}\n\n";
    echo "{$yellow}💡 Dicas:{$reset}\n";
    echo "   1. Execute a migration: composer run migrate\n";
    echo "   2. Configure o .env com as variáveis de backup\n";
    echo "   3. Verifique se o diretório de backups é gravável\n\n";
    exit(1);
} else {
    echo "{$green}✅ Todos os testes passaram!{$reset}\n\n";
    echo "{$blue}ℹ️  Próximos passos:{$reset}\n";
    echo "   1. Execute a migration: composer run migrate\n";
    echo "   2. Crie um backup: composer run backup\n";
    echo "   3. Liste backups: composer run backup:list\n";
    echo "   4. Veja estatísticas: composer run backup:stats\n\n";
    exit(0);
}


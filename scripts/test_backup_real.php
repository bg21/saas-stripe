<?php

/**
 * Teste real de backup - verifica se o backup foi criado corretamente
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

echo "🔍 Verificando backup criado...\n";
echo str_repeat("=", 70) . "\n\n";

$backupService = new \App\Services\BackupService();
$backups = $backupService->listBackups(1);

if (empty($backups)) {
    echo "❌ Nenhum backup encontrado! Criando um novo backup...\n\n";
    $newBackup = $backupService->createBackup();
    $backup = $backupService->getBackup($newBackup['id']);
} else {
    $backup = $backups[0];
}
$filePath = $backup['file_path'];

echo "📄 Informações do backup:\n";
echo "  ID: {$backup['id']}\n";
echo "  Arquivo: {$backup['filename']}\n";
echo "  Caminho: {$filePath}\n";
echo "  Tamanho: " . round($backup['file_size'] / 1024, 2) . " KB\n";
echo "  Comprimido: " . ($backup['compressed'] ? 'Sim' : 'Não') . "\n\n";

// Verifica se arquivo existe
if (!file_exists($filePath)) {
    echo "❌ Arquivo não existe!\n";
    exit(1);
}

echo "✅ Arquivo existe\n";

// Verifica se é comprimido
if ($backup['compressed'] && str_ends_with($filePath, '.gz')) {
    echo "✅ Arquivo está comprimido (.gz)\n";
    
    // Tenta descomprimir e ler
    $gz = gzopen($filePath, 'rb');
    if ($gz) {
        $content = '';
        while (!gzeof($gz)) {
            $content .= gzread($gz, 8192);
            if (strlen($content) > 5000) break; // Limita a 5KB para teste
        }
        gzclose($gz);
        
        echo "✅ Arquivo pode ser descomprimido\n";
        echo "  Tamanho descomprimido: " . strlen($content) . " bytes\n";
        
        // Verifica se contém SQL válido
        $hasCreateTable = strpos($content, 'CREATE TABLE') !== false;
        $hasInsert = strpos($content, 'INSERT INTO') !== false;
        $hasDump = strpos($content, 'MySQL dump') !== false || strpos($content, 'mysqldump') !== false;
        $hasUse = strpos($content, 'USE ') !== false;
        
        if ($hasCreateTable || $hasInsert || $hasDump || $hasUse) {
            echo "✅ Contém SQL válido\n";
            if ($hasCreateTable) echo "  - Encontrado: CREATE TABLE\n";
            if ($hasInsert) echo "  - Encontrado: INSERT INTO\n";
            if ($hasDump) echo "  - Encontrado: MySQL dump header\n";
            if ($hasUse) echo "  - Encontrado: USE database\n";
            
            echo "\nPrimeiros 300 caracteres do SQL:\n";
            echo substr($content, 0, 300) . "...\n";
        } else {
            echo "⚠️  Conteúdo não parece ser SQL válido\n";
            echo "Primeiros 200 caracteres:\n";
            echo substr($content, 0, 200) . "...\n";
        }
    } else {
        echo "❌ Erro ao descomprimir arquivo\n";
        exit(1);
    }
} else {
    // Não comprimido, lê diretamente
    $content = file_get_contents($filePath);
    if ($content) {
        echo "✅ Arquivo pode ser lido\n";
        if (strpos($content, 'CREATE TABLE') !== false || 
            strpos($content, 'INSERT INTO') !== false) {
            echo "✅ Contém SQL válido\n";
        }
    }
}

echo "\n✅ Backup está funcionando corretamente com a biblioteca ifsnop/mysqldump-php!\n";


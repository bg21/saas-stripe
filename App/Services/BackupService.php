<?php

namespace App\Services;

use Config;
use App\Services\Logger;
use App\Models\BackupLog;
use Ifsnop\Mysqldump\Mysqldump;

/**
 * Serviço de backup automático do banco de dados
 * Usa a biblioteca ifsnop/mysqldump-php para criar backups
 */
class BackupService
{
    private string $backupDir;
    private int $retentionDays;
    private bool $compress;

    public function __construct()
    {
        // Diretório de backups (relativo à raiz do projeto)
        $this->backupDir = Config::get('BACKUP_DIR', 'backups');
        $this->retentionDays = (int)Config::get('BACKUP_RETENTION_DAYS', 30);
        $this->compress = Config::get('BACKUP_COMPRESS', 'true') === 'true';

        // Garante que o diretório existe
        $fullPath = $this->getBackupPath();
        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                throw new \RuntimeException("Não foi possível criar diretório de backups: {$fullPath}");
            }
        }
    }

    /**
     * Cria um backup do banco de dados
     * 
     * @param string|null $filename Nome customizado do arquivo (opcional)
     * @return array Informações do backup criado
     */
    public function createBackup(?string $filename = null): array
    {
        $startTime = microtime(true);
        
        // Obtém configurações do banco
        $host = Config::get('DB_HOST', '127.0.0.1');
        $dbName = Config::get('DB_NAME', 'saas_payments');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');
        
        // Gera nome do arquivo se não fornecido
        if ($filename === null) {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$dbName}_{$timestamp}.sql";
        }
        
        // Adiciona extensão .gz se compressão estiver habilitada
        // A biblioteca comprime o conteúdo mas não adiciona a extensão automaticamente
        if ($this->compress && !str_ends_with($filename, '.gz')) {
            $filename .= '.gz';
        }
        
        $filePath = $this->getBackupPath() . DIRECTORY_SEPARATOR . $filename;
        
        try {
            // Configurações para o dump
            $dumpSettings = [
                'compress' => $this->compress ? Mysqldump::GZIP : Mysqldump::NONE,
                'no-data' => false,
                'add-drop-table' => true,
                'single-transaction' => true,
                'lock-tables' => false,
                'add-locks' => true,
                'extended-insert' => true,
                'disable-keys' => true,
                'skip-triggers' => false,
                'add-drop-trigger' => true,
                'routines' => true,
                'hex-blob' => true,
                'databases' => false,
                'add-drop-database' => false,
                'skip-tz-utc' => false,
                'no-create-info' => false,
                'where' => '',
            ];
            
            // Cria instância do Mysqldump
            $dump = new Mysqldump(
                "mysql:host={$host};dbname={$dbName}",
                $user,
                $pass,
                $dumpSettings
            );
            
            // Executa o dump
            $dump->start($filePath);
            
            // Verifica se arquivo foi criado
            if (!file_exists($filePath)) {
                throw new \RuntimeException("Arquivo de backup não foi criado: {$filePath}");
            }
            
            // Obtém informações do arquivo
            $fileSize = filesize($filePath);
            $duration = round(microtime(true) - $startTime, 2);
            
            // Registra no log
            $backupLog = new BackupLog();
            $backupId = $backupLog->create([
                'filename' => $filename,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'status' => 'success',
                'duration_seconds' => $duration,
                'compressed' => $this->compress ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::info("Backup criado com sucesso", [
                'backup_id' => $backupId,
                'filename' => $filename,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'duration' => $duration,
                'compressed' => $this->compress
            ]);
            
            return [
                'id' => $backupId,
                'filename' => $filename,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'duration' => $duration,
                'compressed' => $this->compress,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            // Registra erro no log
            $backupLog = new BackupLog();
            $backupLog->create([
                'filename' => $filename ?? 'unknown',
                'file_path' => $filePath ?? null,
                'file_size' => 0,
                'status' => 'failed',
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'compressed' => $this->compress ? 1 : 0,
                'error_message' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::error("Erro ao criar backup", [
                'error' => $e->getMessage(),
                'filename' => $filename ?? 'unknown'
            ]);
            
            throw $e;
        }
    }

    /**
     * Lista backups disponíveis
     * 
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de backups
     */
    public function listBackups(int $limit = 50, int $offset = 0): array
    {
        $backupLog = new BackupLog();
        $db = \App\Utils\Database::getInstance();
        
        $sql = "SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $backups = $stmt->fetchAll();
        
        // Adiciona informações do arquivo se existir
        foreach ($backups as &$backup) {
            if ($backup['file_path'] && file_exists($backup['file_path'])) {
                $backup['file_exists'] = true;
                $backup['file_size'] = filesize($backup['file_path']);
                $backup['file_size_mb'] = round($backup['file_size'] / 1024 / 1024, 2);
                $backup['file_modified'] = date('Y-m-d H:i:s', filemtime($backup['file_path']));
            } else {
                $backup['file_exists'] = false;
            }
        }
        
        return $backups;
    }

    /**
     * Obtém informações de um backup específico
     * 
     * @param int $backupId ID do backup
     * @return array|null Informações do backup
     */
    public function getBackup(int $backupId): ?array
    {
        $backupLog = new BackupLog();
        $backup = $backupLog->findById($backupId);
        
        if (!$backup) {
            return null;
        }
        
        // Adiciona informações do arquivo se existir
        if ($backup['file_path'] && file_exists($backup['file_path'])) {
            $backup['file_exists'] = true;
            $backup['file_size'] = filesize($backup['file_path']);
            $backup['file_size_mb'] = round($backup['file_size'] / 1024 / 1024, 2);
            $backup['file_modified'] = date('Y-m-d H:i:s', filemtime($backup['file_path']));
        } else {
            $backup['file_exists'] = false;
        }
        
        return $backup;
    }

    /**
     * Restaura um backup
     * 
     * @param int $backupId ID do backup a restaurar
     * @param bool $dropDatabase Se true, dropa o banco antes de restaurar
     * @return array Resultado da restauração
     */
    public function restoreBackup(int $backupId, bool $dropDatabase = false): array
    {
        $backup = $this->getBackup($backupId);
        
        if (!$backup) {
            throw new \RuntimeException("Backup não encontrado: {$backupId}");
        }
        
        if (!$backup['file_exists']) {
            throw new \RuntimeException("Arquivo de backup não encontrado: {$backup['file_path']}");
        }
        
        $startTime = microtime(true);
        $filePath = $backup['file_path'];
        
        try {
            // Obtém configurações do banco
            $host = Config::get('DB_HOST', '127.0.0.1');
            $dbName = Config::get('DB_NAME', 'saas_payments');
            $user = Config::get('DB_USER', 'root');
            $pass = Config::get('DB_PASS', '');
            
            // Se compressão estiver habilitada, descomprime primeiro
            $tempFile = null;
            if ($this->compress && str_ends_with($filePath, '.gz')) {
                $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_restore_' . uniqid() . '.sql';
                
                // Descomprime
                $gz = gzopen($filePath, 'rb');
                $fp = fopen($tempFile, 'wb');
                
                if (!$gz || !$fp) {
                    throw new \RuntimeException("Erro ao descomprimir backup");
                }
                
                while (!gzeof($gz)) {
                    fwrite($fp, gzread($gz, 8192));
                }
                
                gzclose($gz);
                fclose($fp);
                
                $filePath = $tempFile;
            }
            
            // Se precisar dropar o banco
            if ($dropDatabase) {
                $db = new \PDO(
                    "mysql:host={$host};charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
                $db->exec("DROP DATABASE IF EXISTS `{$dbName}`");
                $db->exec("CREATE DATABASE `{$dbName}`");
            }
            
            // Lê o arquivo SQL e executa
            $sql = file_get_contents($filePath);
            if ($sql === false) {
                throw new \RuntimeException("Erro ao ler arquivo de backup");
            }
            
            // Remove comentários e divide em comandos
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            // Conecta ao banco
            $db = new \PDO(
                "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            // Executa comandos SQL
            $commands = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($cmd) => !empty($cmd)
            );
            
            foreach ($commands as $command) {
                if (!empty(trim($command))) {
                    $db->exec($command);
                }
            }
            
            // Limpa arquivo temporário se necessário
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            Logger::info("Backup restaurado com sucesso", [
                'backup_id' => $backupId,
                'duration' => $duration,
                'drop_database' => $dropDatabase
            ]);
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'duration' => $duration,
                'message' => 'Backup restaurado com sucesso'
            ];
            
        } catch (\Exception $e) {
            Logger::error("Erro ao restaurar backup", [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Remove backups antigos (baseado em retenção)
     * 
     * @return array Estatísticas da limpeza
     */
    public function cleanOldBackups(): array
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->retentionDays} days"));
        
        $backupLog = new BackupLog();
        $db = \App\Utils\Database::getInstance();
        
        $sql = "SELECT * FROM backup_logs WHERE created_at < :cutoff_date";
        $stmt = $db->prepare($sql);
        $stmt->execute(['cutoff_date' => $cutoffDate]);
        $oldBackups = $stmt->fetchAll();
        
        $deleted = 0;
        $errors = 0;
        $totalSize = 0;
        
        foreach ($oldBackups as $backup) {
            try {
                // Remove arquivo se existir
                if ($backup['file_path'] && file_exists($backup['file_path'])) {
                    $fileSize = filesize($backup['file_path']);
                    if (unlink($backup['file_path'])) {
                        $totalSize += $fileSize;
                        $deleted++;
                    } else {
                        $errors++;
                    }
                }
                
                // Remove registro do banco
                $backupLog->delete($backup['id']);
                
            } catch (\Exception $e) {
                $errors++;
                Logger::warning("Erro ao remover backup antigo", [
                    'backup_id' => $backup['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Logger::info("Limpeza de backups antigos concluída", [
            'deleted' => $deleted,
            'errors' => $errors,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'retention_days' => $this->retentionDays
        ]);
        
        return [
            'deleted' => $deleted,
            'errors' => $errors,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'retention_days' => $this->retentionDays
        ];
    }

    /**
     * Obtém estatísticas de backups
     * 
     * @return array Estatísticas
     */
    public function getStatistics(): array
    {
        $backupLog = new BackupLog();
        $db = \App\Utils\Database::getInstance();
        
        // Total de backups
        $total = $db->query("SELECT COUNT(*) as total FROM backup_logs")->fetch()['total'];
        
        // Backups bem-sucedidos
        $successful = $db->query("SELECT COUNT(*) as total FROM backup_logs WHERE status = 'success'")->fetch()['total'];
        
        // Backups falhados
        $failed = $db->query("SELECT COUNT(*) as total FROM backup_logs WHERE status = 'failed'")->fetch()['total'];
        
        // Tamanho total dos backups
        $totalSize = $db->query("SELECT SUM(file_size) as total FROM backup_logs WHERE status = 'success'")->fetch()['total'] ?? 0;
        
        // Último backup
        $lastBackup = $db->query("SELECT * FROM backup_logs WHERE status = 'success' ORDER BY created_at DESC LIMIT 1")->fetch();
        
        // Próxima limpeza
        $nextCleanup = date('Y-m-d H:i:s', strtotime("+{$this->retentionDays} days"));
        
        return [
            'total' => (int)$total,
            'successful' => (int)$successful,
            'failed' => (int)$failed,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'last_backup' => $lastBackup ? [
                'id' => $lastBackup['id'],
                'filename' => $lastBackup['filename'],
                'created_at' => $lastBackup['created_at']
            ] : null,
            'retention_days' => $this->retentionDays,
            'next_cleanup' => $nextCleanup
        ];
    }


    /**
     * Obtém caminho completo do diretório de backups
     */
    private function getBackupPath(): string
    {
        $root = dirname(__DIR__, 2);
        return $root . DIRECTORY_SEPARATOR . $this->backupDir;
    }
}


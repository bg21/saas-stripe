<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration para criar tabela de logs de backup
 */
class CreateBackupLogsTable extends AbstractMigration
{
    /**
     * Migrate Up - Cria tabela backup_logs
     */
    public function up()
    {
        $table = $this->table('backup_logs', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Logs de backups do banco de dados'
        ]);

        $table->addColumn('filename', 'string', [
            'limit' => 255,
            'null' => false,
            'comment' => 'Nome do arquivo de backup'
        ])
        ->addColumn('file_path', 'text', [
            'null' => true,
            'comment' => 'Caminho completo do arquivo de backup'
        ])
        ->addColumn('file_size', 'biginteger', [
            'null' => false,
            'default' => 0,
            'comment' => 'Tamanho do arquivo em bytes'
        ])
        ->addColumn('status', 'enum', [
            'values' => ['success', 'failed'],
            'default' => 'success',
            'null' => false,
            'comment' => 'Status do backup'
        ])
        ->addColumn('duration_seconds', 'decimal', [
            'precision' => 10,
            'scale' => 2,
            'null' => false,
            'default' => 0,
            'comment' => 'Duração do backup em segundos'
        ])
        ->addColumn('compressed', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Se o backup foi comprimido (gzip)'
        ])
        ->addColumn('error_message', 'text', [
            'null' => true,
            'comment' => 'Mensagem de erro (se houver)'
        ])
        ->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
            'comment' => 'Data de criação do backup'
        ])
        ->addIndex(['status'], ['name' => 'idx_status'])
        ->addIndex(['created_at'], ['name' => 'idx_created_at'])
        ->create();
    }

    /**
     * Migrate Down - Remove tabela backup_logs
     */
    public function down()
    {
        $this->table('backup_logs')->drop()->save();
    }
}


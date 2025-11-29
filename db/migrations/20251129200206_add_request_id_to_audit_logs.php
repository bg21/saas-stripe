<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration para adicionar coluna request_id na tabela audit_logs
 * 
 * Esta migration adiciona suporte a tracing de requisições, permitindo
 * rastrear todas as ações relacionadas a uma requisição específica.
 */
final class AddRequestIdToAuditLogs extends AbstractMigration
{
    public function up(): void
    {
        // Verifica se a coluna já existe antes de adicionar (idempotência)
        $connection = $this->getAdapter()->getConnection();
        $stmt = $connection->query("SHOW COLUMNS FROM audit_logs LIKE 'request_id'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            // Adiciona coluna request_id (pode ser NULL para logs antigos)
            $this->execute("
                ALTER TABLE audit_logs 
                ADD COLUMN request_id VARCHAR(32) NULL 
                COMMENT 'ID único da requisição para tracing' 
                AFTER id
            ");
        }
        
        // Verifica se o índice já existe antes de criar
        $stmt = $connection->query("SHOW INDEX FROM audit_logs WHERE Key_name = 'idx_request_id'");
        $indexExists = $stmt->rowCount() > 0;
        
        if (!$indexExists) {
            // Adiciona índice para busca rápida por request_id
            $this->execute("
                CREATE INDEX idx_request_id 
                ON audit_logs (request_id)
            ");
        }
        
        // Verifica se o índice composto já existe
        $stmt = $connection->query("SHOW INDEX FROM audit_logs WHERE Key_name = 'idx_tenant_request_id'");
        $compositeIndexExists = $stmt->rowCount() > 0;
        
        if (!$compositeIndexExists) {
            // Índice composto para busca por tenant e request_id
            $this->execute("
                CREATE INDEX idx_tenant_request_id 
                ON audit_logs (tenant_id, request_id)
            ");
        }
    }

    public function down(): void
    {
        // Remove índices
        $this->execute("DROP INDEX IF EXISTS idx_tenant_request_id ON audit_logs");
        $this->execute("DROP INDEX IF EXISTS idx_request_id ON audit_logs");
        
        // Remove coluna
        $this->execute("ALTER TABLE audit_logs DROP COLUMN request_id");
    }
}

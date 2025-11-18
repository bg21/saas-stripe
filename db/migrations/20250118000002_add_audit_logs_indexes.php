<?php

use Phinx\Migration\AbstractMigration;

/**
 * ✅ OTIMIZAÇÃO: Adiciona índices em audit_logs para melhorar performance de queries
 */
class AddAuditLogsIndexes extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('audit_logs');
        
        // ✅ OTIMIZAÇÃO: Adiciona índices para melhorar performance de queries
        
        // Índice composto para queries por tenant e data (mais comum)
        // Usa IF NOT EXISTS via SQL direto para evitar erro se já existir
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_tenant_created 
            ON audit_logs (tenant_id, created_at DESC)
        ");
        
        // Índice para filtro por endpoint (queries de busca)
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_endpoint 
            ON audit_logs (endpoint)
        ");
        
        // Índice composto para método e status (análise de erros)
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_method_status 
            ON audit_logs (method, response_status)
        ");
        
        // Índice para user_id (quando aplicável)
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_user_id 
            ON audit_logs (user_id)
        ");
    }
}


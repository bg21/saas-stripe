<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration para criar tabela application_logs
 * 
 * Armazena logs da aplicação (Monolog) com request_id para tracing
 */
final class CreateApplicationLogsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('application_logs', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Logs da aplicação (Monolog) com request_id para tracing'
        ]);
        
        $table->addColumn('request_id', 'string', [
            'limit' => 32,
            'null' => true,
            'comment' => 'ID único da requisição para tracing'
        ])
        ->addColumn('level', 'string', [
            'limit' => 20,
            'null' => false,
            'comment' => 'Nível do log (DEBUG, INFO, WARNING, ERROR, etc)'
        ])
        ->addColumn('level_value', 'integer', [
            'null' => false,
            'comment' => 'Valor numérico do nível (para ordenação)'
        ])
        ->addColumn('message', 'text', [
            'null' => false,
            'comment' => 'Mensagem do log'
        ])
        ->addColumn('context', 'text', [
            'null' => true,
            'comment' => 'Contexto do log (JSON)'
        ])
        ->addColumn('channel', 'string', [
            'limit' => 50,
            'null' => false,
            'default' => 'saas_payments',
            'comment' => 'Canal do log (channel do Monolog)'
        ])
        ->addColumn('tenant_id', 'integer', [
            'null' => true,
            'signed' => false,
            'limit' => 11,
            'comment' => 'ID do tenant (se disponível)'
        ])
        ->addColumn('user_id', 'integer', [
            'null' => true,
            'limit' => 11,
            'comment' => 'ID do usuário (se disponível)'
        ])
        ->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
            'comment' => 'Data e hora do log'
        ])
        ->addIndex(['request_id'], ['name' => 'idx_request_id'])
        ->addIndex(['level'], ['name' => 'idx_level'])
        ->addIndex(['level_value'], ['name' => 'idx_level_value'])
        ->addIndex(['created_at'], ['name' => 'idx_created_at'])
        ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
        ->addIndex(['request_id', 'created_at'], ['name' => 'idx_request_created'])
        ->addIndex(['tenant_id', 'created_at'], ['name' => 'idx_tenant_created'])
        ->create();
        
        // Adiciona foreign key para tenants (se a tabela existir)
        try {
            $this->execute('
                ALTER TABLE application_logs 
                ADD CONSTRAINT fk_application_logs_tenant_id 
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) 
                ON DELETE SET NULL ON UPDATE CASCADE
            ');
        } catch (\Exception $e) {
            // Ignora se a constraint já existir ou se a tabela tenants não existir
        }
    }

    public function down(): void
    {
        $this->table('application_logs')->drop()->save();
    }
}

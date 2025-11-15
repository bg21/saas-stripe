<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuditLogsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // Tabela de logs de auditoria
        $auditLogs = $this->table('audit_logs', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tabela de logs de auditoria - rastreabilidade de ações'
        ]);
        
        $auditLogs->addColumn('tenant_id', 'integer', [
                'null' => true,
                'signed' => false,
                'limit' => 11,
                'comment' => 'ID do tenant (null para master key)'
            ])
            ->addColumn('user_id', 'integer', [
                'null' => true,
                'limit' => 11,
                'comment' => 'ID do usuário (quando aplicável)'
            ])
            ->addColumn('endpoint', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Endpoint/URL acessada'
            ])
            ->addColumn('method', 'string', [
                'limit' => 10,
                'null' => false,
                'comment' => 'Método HTTP (GET, POST, PUT, DELETE, etc)'
            ])
            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => true,
                'comment' => 'Endereço IP do cliente (suporta IPv4 e IPv6)'
            ])
            ->addColumn('user_agent', 'text', [
                'null' => true,
                'comment' => 'User-Agent do cliente'
            ])
            ->addColumn('request_body', 'text', [
                'null' => true,
                'comment' => 'Corpo da requisição (JSON, limitado a 10KB)'
            ])
            ->addColumn('response_status', 'integer', [
                'null' => false,
                'limit' => 3,
                'comment' => 'Status HTTP da resposta'
            ])
            ->addColumn('response_time', 'integer', [
                'null' => false,
                'limit' => 11,
                'comment' => 'Tempo de resposta em milissegundos'
            ])
            ->addColumn('created_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
                'comment' => 'Data e hora da requisição'
            ])
            ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->addIndex(['endpoint'], ['name' => 'idx_endpoint'])
            ->addIndex(['method'], ['name' => 'idx_method'])
            ->addIndex(['response_status'], ['name' => 'idx_response_status'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->addIndex(['tenant_id', 'created_at'], ['name' => 'idx_tenant_created'])
            ->create();
        
        // Adiciona foreign key após criar a tabela (usando SQL direto)
        $this->execute('ALTER TABLE `audit_logs` ADD CONSTRAINT `fk_audit_logs_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

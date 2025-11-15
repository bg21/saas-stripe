<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUserAuthAndPermissions extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Adiciona suporte a autenticação de usuários e sistema de permissões
     */
    public function change(): void
    {
        // 1. Adiciona coluna role na tabela users
        $users = $this->table('users');
        if (!$users->hasColumn('role')) {
            $users->addColumn('role', 'enum', [
                'values' => ['admin', 'viewer', 'editor'],
                'default' => 'viewer',
                'null' => false,
                'after' => 'status',
                'comment' => 'Role do usuário: admin (todas permissões), editor (editar), viewer (apenas visualizar)'
            ])->update();
        }

        // 2. Tabela de sessões de usuários
        $sessions = $this->table('user_sessions', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Sessões de usuários autenticados - tokens de acesso'
        ]);
        
        $sessions->addColumn('id', 'string', [
            'limit' => 64,
            'null' => false,
            'comment' => 'Token de sessão (hash)'
        ])
        ->addColumn('user_id', 'integer', [
            'null' => false,
            'signed' => false,
            'limit' => 11,
            'comment' => 'ID do usuário'
        ])
        ->addColumn('tenant_id', 'integer', [
            'null' => false,
            'signed' => false,
            'limit' => 11,
            'comment' => 'ID do tenant'
        ])
        ->addColumn('ip_address', 'string', [
            'limit' => 45,
            'null' => true,
            'comment' => 'IP do cliente'
        ])
        ->addColumn('user_agent', 'text', [
            'null' => true,
            'comment' => 'User-Agent do cliente'
        ])
        ->addColumn('expires_at', 'datetime', [
            'null' => false,
            'comment' => 'Data de expiração da sessão'
        ])
        ->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
            'comment' => 'Data de criação'
        ])
        ->addIndex(['user_id'], ['name' => 'idx_user_id'])
        ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
        ->addIndex(['id'], ['unique' => true, 'name' => 'idx_session_id'])
        ->addIndex(['expires_at'], ['name' => 'idx_expires_at'])
        ->create();

        // Adiciona foreign keys após criar a tabela (usando SQL direto para evitar problemas)
        $this->execute('ALTER TABLE `user_sessions` ADD CONSTRAINT `fk_user_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->execute('ALTER TABLE `user_sessions` ADD CONSTRAINT `fk_user_sessions_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');

        // 3. Tabela de permissões específicas (opcional, para controle granular)
        $permissions = $this->table('user_permissions', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Permissões específicas de usuários - controle granular além das roles'
        ]);
        
        $permissions->addColumn('user_id', 'integer', [
            'null' => false,
            'signed' => false,
            'limit' => 11,
            'comment' => 'ID do usuário'
        ])
        ->addColumn('permission', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => 'Nome da permissão (ex: view_subscriptions, create_subscriptions)'
        ])
        ->addColumn('granted', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Se a permissão está concedida (true) ou negada (false)'
        ])
        ->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
            'comment' => 'Data de criação'
        ])
        ->addIndex(['user_id'], ['name' => 'idx_user_id'])
        ->addIndex(['user_id', 'permission'], ['unique' => true, 'name' => 'unique_user_permission'])
        ->create();

        // Adiciona foreign key
        $this->execute('ALTER TABLE `user_permissions` ADD CONSTRAINT `fk_user_permissions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}

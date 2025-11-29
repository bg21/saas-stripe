<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela professional_roles e adiciona campo professional_role_id em professionals
 * 
 * Esta migration cria:
 * 1. professional_roles - Roles específicas da clínica (Admin, Gerente, Veterinário, Atendente, etc.)
 * 2. Adiciona professional_role_id na tabela professionals
 */
final class CreateProfessionalRolesTable extends AbstractMigration
{
    /**
     * Migrate Up - Cria a tabela e adiciona o campo
     */
    public function up(): void
    {
        // 1. Tabela: professional_roles
        $roles = $this->table('professional_roles', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Roles específicas dos profissionais da clínica'
        ]);
        $roles->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('name', 'string', ['limit' => 100, 'null' => false, 'comment' => 'Nome da role (ex: Veterinário, Atendente)'])
              ->addColumn('description', 'text', ['null' => true, 'comment' => 'Descrição da role'])
              ->addColumn('permissions', 'json', ['null' => true, 'comment' => 'Permissões específicas da role (opcional)'])
              ->addColumn('is_active', 'boolean', ['default' => true, 'comment' => 'Se a role está ativa'])
              ->addColumn('sort_order', 'integer', ['default' => 0, 'comment' => 'Ordem de exibição'])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
              ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
              ->addIndex(['is_active'], ['name' => 'idx_is_active'])
              ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
              ->addIndex(['tenant_id', 'name'], ['unique' => true, 'name' => 'idx_tenant_name_unique'])
              ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();

        // 2. Adiciona professional_role_id na tabela professionals
        $professionals = $this->table('professionals');
        $professionals->addColumn('professional_role_id', 'integer', [
            'signed' => false,
            'null' => true,
            'after' => 'user_id',
            'comment' => 'Role específica do profissional na clínica'
        ])
        ->addIndex(['professional_role_id'], ['name' => 'idx_professional_role_id'])
        ->addForeignKey('professional_role_id', 'professional_roles', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE'
        ])
        ->update();
    }

    /**
     * Migrate Down - Remove a tabela e o campo
     */
    public function down(): void
    {
        // Remove o campo professional_role_id da tabela professionals
        $professionals = $this->table('professionals');
        $professionals->removeForeignKey('professional_role_id')
                     ->removeIndex(['professional_role_id'])
                     ->removeColumn('professional_role_id')
                     ->update();

        // Remove a tabela professional_roles
        $this->table('professional_roles')->drop()->save();
    }
}

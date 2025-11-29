<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela de tipos de exames
 * 
 * Esta migration cria a tabela exam_types para armazenar os tipos de exames
 * que podem ser realizados na clínica (ex: Exame de Sangue Completo, Raio-X, etc.)
 */
final class CreateExamTypesTable extends AbstractMigration
{
    /**
     * Migrate Up - Cria a tabela
     */
    public function up(): void
    {
        $examTypes = $this->table('exam_types', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Tipos de exames que podem ser realizados na clínica'
        ]);
        
        $examTypes->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('name', 'string', ['limit' => 255, 'null' => false, 'comment' => 'Nome do tipo de exame'])
                  ->addColumn('category', 'enum', [
                      'values' => ['blood', 'urine', 'imaging', 'other'],
                      'null' => false,
                      'comment' => 'Categoria do exame: sangue, urina, imagem, outro'
                  ])
                  ->addColumn('description', 'text', ['null' => true, 'comment' => 'Descrição do tipo de exame'])
                  ->addColumn('status', 'enum', [
                      'values' => ['active', 'inactive'],
                      'default' => 'active',
                      'null' => false,
                      'comment' => 'Status do tipo de exame'
                  ])
                  ->addColumn('created_at', 'timestamp', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'null' => false
                  ])
                  ->addColumn('updated_at', 'timestamp', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP',
                      'null' => false
                  ])
                  ->addColumn('deleted_at', 'timestamp', [
                      'null' => true,
                      'default' => null,
                      'comment' => 'Soft delete'
                  ])
                  ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
                  ->addIndex(['status'], ['name' => 'idx_status'])
                  ->addIndex(['category'], ['name' => 'idx_category'])
                  ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
                  ->addForeignKey('tenant_id', 'tenants', 'id', [
                      'delete' => 'CASCADE',
                      'update' => 'CASCADE'
                  ])
                  ->create();
    }

    /**
     * Migrate Down - Remove a tabela
     */
    public function down(): void
    {
        $this->table('exam_types')->drop()->save();
    }
}

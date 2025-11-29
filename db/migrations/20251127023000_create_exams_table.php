<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cria a tabela de exames
 * 
 * Esta migration cria a tabela exams para armazenar os exames
 * realizados ou agendados para os pets
 */
final class CreateExamsTable extends AbstractMigration
{
    public function up(): void
    {
        $exams = $this->table('exams', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Exames realizados ou agendados para pets'
        ]);

        $exams->addColumn('tenant_id', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('pet_id', 'integer', ['signed' => false, 'null' => false, 'comment' => 'ID do pet'])
              ->addColumn('client_id', 'integer', ['signed' => false, 'null' => false, 'comment' => 'ID do cliente/tutor'])
              ->addColumn('professional_id', 'integer', ['signed' => false, 'null' => true, 'comment' => 'ID do profissional responsável'])
              ->addColumn('exam_type_id', 'integer', ['signed' => false, 'null' => true, 'comment' => 'ID do tipo de exame'])
              ->addColumn('exam_date', 'date', ['null' => false, 'comment' => 'Data do exame'])
              ->addColumn('exam_time', 'time', ['null' => true, 'comment' => 'Hora do exame'])
              ->addColumn('status', 'enum', [
                  'values' => ['pending', 'scheduled', 'completed', 'cancelled'],
                  'default' => 'pending',
                  'null' => false,
                  'comment' => 'Status do exame'
              ])
              ->addColumn('notes', 'text', ['null' => true, 'comment' => 'Observações sobre o exame'])
              ->addColumn('results', 'text', ['null' => true, 'comment' => 'Resultados do exame'])
              ->addColumn('cancellation_reason', 'text', ['null' => true, 'comment' => 'Motivo do cancelamento'])
              ->addColumn('cancelled_by', 'integer', ['signed' => false, 'null' => true, 'comment' => 'ID do usuário que cancelou'])
              ->addColumn('cancelled_at', 'timestamp', ['null' => true, 'comment' => 'Data/hora do cancelamento'])
              ->addColumn('completed_at', 'timestamp', ['null' => true, 'comment' => 'Data/hora da conclusão'])
              ->addColumn('metadata', 'text', ['null' => true, 'comment' => 'Metadados adicionais em JSON'])
              ->addColumn('created_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP',
                  'null' => false
              ])
              ->addColumn('updated_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP',
                  'update' => 'CURRENT_TIMESTAMP',
                  'null' => false
              ])
              ->addColumn('deleted_at', 'timestamp', ['null' => true])
              ->addIndex(['tenant_id'], ['name' => 'idx_tenant_id'])
              ->addIndex(['pet_id'], ['name' => 'idx_pet_id'])
              ->addIndex(['client_id'], ['name' => 'idx_client_id'])
              ->addIndex(['professional_id'], ['name' => 'idx_professional_id'])
              ->addIndex(['exam_type_id'], ['name' => 'idx_exam_type_id'])
              ->addIndex(['exam_date'], ['name' => 'idx_exam_date'])
              ->addIndex(['status'], ['name' => 'idx_status'])
              ->addIndex(['deleted_at'], ['name' => 'idx_deleted_at'])
              ->addForeignKey('tenant_id', 'tenants', 'id', [
                  'delete' => 'CASCADE',
                  'update' => 'CASCADE',
                  'constraint' => 'fk_exam_tenant'
              ])
              ->addForeignKey('pet_id', 'pets', 'id', [
                  'delete' => 'CASCADE',
                  'update' => 'CASCADE',
                  'constraint' => 'fk_exam_pet'
              ])
              ->addForeignKey('client_id', 'clients', 'id', [
                  'delete' => 'CASCADE',
                  'update' => 'CASCADE',
                  'constraint' => 'fk_exam_client'
              ])
              ->addForeignKey('professional_id', 'professionals', 'id', [
                  'delete' => 'SET_NULL',
                  'update' => 'CASCADE',
                  'constraint' => 'fk_exam_professional'
              ])
              ->addForeignKey('exam_type_id', 'exam_types', 'id', [
                  'delete' => 'SET_NULL',
                  'update' => 'CASCADE',
                  'constraint' => 'fk_exam_exam_type'
              ])
              ->create();
    }

    public function down(): void
    {
        $this->table('exams')->drop()->save();
    }
}


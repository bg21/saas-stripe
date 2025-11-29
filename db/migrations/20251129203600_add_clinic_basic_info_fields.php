<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adiciona campos de informações básicas da clínica
 * 
 * Nome, telefone, endereço, logo, etc.
 */
final class AddClinicBasicInfoFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('clinic_configurations');
        
        // Verifica se as colunas já existem antes de adicionar
        if (!$table->hasColumn('clinic_name')) {
            $table->addColumn('clinic_name', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'tenant_id',
                'comment' => 'Nome da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_phone')) {
            $table->addColumn('clinic_phone', 'string', [
                'limit' => 50,
                'null' => true,
                'after' => 'clinic_name',
                'comment' => 'Telefone da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_email')) {
            $table->addColumn('clinic_email', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'clinic_phone',
                'comment' => 'Email da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_address')) {
            $table->addColumn('clinic_address', 'text', [
                'null' => true,
                'after' => 'clinic_email',
                'comment' => 'Endereço completo da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_city')) {
            $table->addColumn('clinic_city', 'string', [
                'limit' => 100,
                'null' => true,
                'after' => 'clinic_address',
                'comment' => 'Cidade da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_state')) {
            $table->addColumn('clinic_state', 'string', [
                'limit' => 50,
                'null' => true,
                'after' => 'clinic_city',
                'comment' => 'Estado da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_zip_code')) {
            $table->addColumn('clinic_zip_code', 'string', [
                'limit' => 20,
                'null' => true,
                'after' => 'clinic_state',
                'comment' => 'CEP da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_logo')) {
            $table->addColumn('clinic_logo', 'string', [
                'limit' => 500,
                'null' => true,
                'after' => 'clinic_zip_code',
                'comment' => 'Caminho do arquivo do logo da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_description')) {
            $table->addColumn('clinic_description', 'text', [
                'null' => true,
                'after' => 'clinic_logo',
                'comment' => 'Descrição da clínica'
            ]);
        }
        
        if (!$table->hasColumn('clinic_website')) {
            $table->addColumn('clinic_website', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'clinic_description',
                'comment' => 'Website da clínica'
            ]);
        }
        
        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('clinic_configurations');
        
        $columnsToRemove = [
            'clinic_website',
            'clinic_description',
            'clinic_logo',
            'clinic_zip_code',
            'clinic_state',
            'clinic_city',
            'clinic_address',
            'clinic_email',
            'clinic_phone',
            'clinic_name'
        ];
        
        foreach ($columnsToRemove as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        
        $table->update();
    }
}

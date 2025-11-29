<?php

use Phinx\Migration\AbstractMigration;

class AddCpfToProfessionals extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('professionals');
        
        $table->addColumn('cpf', 'string', [
            'limit' => 14,
            'null' => true,
            'after' => 'crmv',
            'comment' => 'CPF do profissional (formato: 000.000.000-00)'
        ])
        ->addIndex(['cpf'], ['name' => 'idx_professionals_cpf'])
        ->update();
    }
}


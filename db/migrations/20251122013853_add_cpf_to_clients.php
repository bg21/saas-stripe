<?php

use Phinx\Migration\AbstractMigration;

class AddCpfToClients extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('clients');
        
        $table->addColumn('cpf', 'string', [
            'limit' => 14,
            'null' => true,
            'after' => 'name',
            'comment' => 'CPF do cliente (formato: 000.000.000-00)'
        ])
        ->addIndex(['cpf'], ['name' => 'idx_cpf'])
        ->update();
    }
}

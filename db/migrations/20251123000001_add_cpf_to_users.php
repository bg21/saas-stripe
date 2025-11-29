<?php

use Phinx\Migration\AbstractMigration;

class AddCpfToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        
        $table->addColumn('cpf', 'string', [
            'limit' => 14,
            'null' => true,
            'after' => 'name',
            'comment' => 'CPF do usuário (formato: 000.000.000-00)'
        ])
        ->addIndex(['cpf'], ['name' => 'idx_users_cpf'])
        ->update();
    }
}


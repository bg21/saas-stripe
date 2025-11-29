<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adiciona campo results_file na tabela exams para armazenar PDF de resultados
 */
final class AddResultsFileToExams extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('exams');
        $table->addColumn('results_file', 'string', [
            'limit' => 500,
            'null' => true,
            'comment' => 'Caminho do arquivo PDF com os resultados do exame'
        ])
        ->update();
    }

    public function down(): void
    {
        $table = $this->table('exams');
        $table->removeColumn('results_file')
        ->update();
    }
}

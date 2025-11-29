<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSlugToTenants extends AbstractMigration
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
        $table = $this->table('tenants');
        
        // Adiciona coluna slug
        if (!$table->hasColumn('slug')) {
            $table->addColumn('slug', 'string', [
                'limit' => 100,
                'null' => true, // Permite null temporariamente para tenants existentes
                'after' => 'name',
                'comment' => 'Slug único do tenant (ex: cao-que-mia)'
            ])->update();
        }
        
        // Cria índice único para slug
        if (!$table->hasIndex('slug')) {
            $table->addIndex('slug', [
                'unique' => true,
                'name' => 'idx_slug'
            ])->update();
        }
        
        // Gera slugs para tenants existentes que não têm slug
        $this->execute("
            UPDATE tenants 
            SET slug = CONCAT('tenant-', id) 
            WHERE slug IS NULL
        ");
        
        // Torna slug obrigatório após popular
        $table->changeColumn('slug', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => 'Slug único do tenant (ex: cao-que-mia)'
        ])->update();
    }
}

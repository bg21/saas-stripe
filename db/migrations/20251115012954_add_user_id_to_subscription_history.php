<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUserIdToSubscriptionHistory extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     */
    public function change(): void
    {
        $table = $this->table('subscription_history');
        
        // Adiciona coluna user_id para rastrear quem fez a mudança (quando via API com usuário autenticado)
        $table->addColumn('user_id', 'integer', [
                'null' => true,
                'signed' => false,
                'limit' => 11,
                'after' => 'changed_by',
                'comment' => 'ID do usuário que fez a mudança (quando via API com autenticação de usuário)'
            ])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->update();
        
        // Adiciona foreign key para users (opcional, pode ser NULL)
        $this->execute('ALTER TABLE `subscription_history` ADD CONSTRAINT `fk_subscription_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

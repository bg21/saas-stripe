<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * ✅ CORREÇÃO: Adiciona constraints UNIQUE e corrige DEFAULT do campo granted
 * 
 * Esta migration:
 * 1. Adiciona constraint UNIQUE(tenant_id, email) na tabela users (se não existir)
 * 2. Adiciona constraint UNIQUE(user_id, permission) na tabela user_permissions (se não existir)
 * 3. Altera DEFAULT do campo granted de 1 para 0 na tabela user_permissions
 */
final class AddDatabaseConstraints extends AbstractMigration
{
    /**
     * Migrate Up - Adiciona constraints e corrige DEFAULT
     */
    public function up(): void
    {
        // 1. Adiciona constraint UNIQUE(tenant_id, email) na tabela users
        // ✅ CORREÇÃO PROBLEMA #19
        try {
            $this->execute("
                ALTER TABLE users 
                ADD UNIQUE KEY unique_tenant_email (tenant_id, email)
            ");
        } catch (\Exception $e) {
            // Se já existir, apenas loga (não é erro crítico)
            if (strpos($e->getMessage(), 'Duplicate key name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
            $this->output->writeln("AVISO: Constraint unique_tenant_email já existe na tabela users.");
        }

        // 2. Adiciona constraint UNIQUE(user_id, permission) na tabela user_permissions
        // ✅ CORREÇÃO PROBLEMA #20
        try {
            $this->execute("
                ALTER TABLE user_permissions 
                ADD UNIQUE KEY unique_user_permission (user_id, permission)
            ");
        } catch (\Exception $e) {
            // Se já existir, apenas loga (não é erro crítico)
            if (strpos($e->getMessage(), 'Duplicate key name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
            $this->output->writeln("AVISO: Constraint unique_user_permission já existe na tabela user_permissions.");
        }

        // 3. Altera DEFAULT do campo granted de 1 para 0
        // ✅ CORREÇÃO PROBLEMA #21
        $this->execute("
            ALTER TABLE user_permissions 
            MODIFY COLUMN granted tinyint(1) NOT NULL DEFAULT 0 
            COMMENT 'Se a permissão está concedida (1) ou negada (0)'
        ");
    }

    /**
     * Migrate Down - Reverte alterações
     */
    public function down(): void
    {
        // Remove constraints UNIQUE (se existirem)
        try {
            $this->execute("ALTER TABLE users DROP INDEX unique_tenant_email");
        } catch (\Exception $e) {
            // Ignora se não existir
        }

        try {
            $this->execute("ALTER TABLE user_permissions DROP INDEX unique_user_permission");
        } catch (\Exception $e) {
            // Ignora se não existir
        }

        // Reverte DEFAULT do campo granted para 1
        $this->execute("
            ALTER TABLE user_permissions 
            MODIFY COLUMN granted tinyint(1) NOT NULL DEFAULT 1 
            COMMENT 'Se a permissão está concedida (true) ou negada (false)'
        ");
    }
}

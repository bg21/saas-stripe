<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adiciona suporte a soft deletes nas tabelas appointments, pets e clients
 * 
 * Esta migration:
 * 1. Adiciona campo deleted_at (TIMESTAMP NULL) nas tabelas appointments, pets e clients
 * 2. Adiciona índices para melhorar performance de queries com soft deletes
 * 
 * Migration idempotente: usa try-catch para evitar erro se coluna já existir
 */
final class AddSoftDeletesToAppointmentsPetsClients extends AbstractMigration
{
    /**
     * Migrate Up - Adiciona campo deleted_at
     */
    public function up(): void
    {
        // Adiciona deleted_at na tabela appointments (se não existir)
        $this->execute("
            ALTER TABLE `appointments` 
            ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Data de exclusão lógica (soft delete)'
        ");
        
        // Adiciona índice se não existir
        try {
            $this->execute("
                CREATE INDEX IF NOT EXISTS `idx_appointments_deleted_at` ON `appointments` (`deleted_at`)
            ");
        } catch (\Exception $e) {
            // Índice já existe, ignora
        }
        
        // Adiciona deleted_at na tabela pets (se não existir)
        $this->execute("
            ALTER TABLE `pets` 
            ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Data de exclusão lógica (soft delete)'
        ");
        
        // Adiciona índice se não existir
        try {
            $this->execute("
                CREATE INDEX IF NOT EXISTS `idx_pets_deleted_at` ON `pets` (`deleted_at`)
            ");
        } catch (\Exception $e) {
            // Índice já existe, ignora
        }
        
        // Adiciona deleted_at na tabela clients (se não existir)
        $this->execute("
            ALTER TABLE `clients` 
            ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Data de exclusão lógica (soft delete)'
        ");
        
        // Adiciona índice se não existir
        try {
            $this->execute("
                CREATE INDEX IF NOT EXISTS `idx_clients_deleted_at` ON `clients` (`deleted_at`)
            ");
        } catch (\Exception $e) {
            // Índice já existe, ignora
        }
    }

    /**
     * Migrate Down - Remove campo deleted_at
     */
    public function down(): void
    {
        // Remove índice e coluna deleted_at da tabela appointments
        try {
            $this->execute("DROP INDEX IF EXISTS `idx_appointments_deleted_at` ON `appointments`");
            $this->execute("ALTER TABLE `appointments` DROP COLUMN IF EXISTS `deleted_at`");
        } catch (\Exception $e) {
            // Ignora erro se não existir
        }
        
        // Remove índice e coluna deleted_at da tabela pets
        try {
            $this->execute("DROP INDEX IF EXISTS `idx_pets_deleted_at` ON `pets`");
            $this->execute("ALTER TABLE `pets` DROP COLUMN IF EXISTS `deleted_at`");
        } catch (\Exception $e) {
            // Ignora erro se não existir
        }
        
        // Remove índice e coluna deleted_at da tabela clients
        try {
            $this->execute("DROP INDEX IF EXISTS `idx_clients_deleted_at` ON `clients`");
            $this->execute("ALTER TABLE `clients` DROP COLUMN IF EXISTS `deleted_at`");
        } catch (\Exception $e) {
            // Ignora erro se não existir
        }
    }
}

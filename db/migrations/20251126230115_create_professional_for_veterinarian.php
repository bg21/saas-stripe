<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration para criar profissional associado ao usuário veterinário de teste
 * 
 * Cria um registro na tabela professionals para o usuário veterinario@clinica.com
 * 
 * Execute: vendor/bin/phinx migrate
 */
final class CreateProfessionalForVeterinarian extends AbstractMigration
{
    public function up(): void
    {
        // Busca o primeiro tenant disponível
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
        
        if (empty($tenants)) {
            echo "⚠️  Nenhum tenant ativo encontrado. Usando tenant_id = 1 (assumindo que existe).\n";
            $tenantId = 1;
        } else {
            $tenantId = (int)$tenants[0]['id'];
        }
        
        echo "👨‍⚕️ Criando profissional para o veterinário de teste (tenant_id: {$tenantId})\n\n";
        
        // Busca o usuário veterinário
        $vetUser = $adapter->fetchAll("SELECT id FROM users WHERE tenant_id = {$tenantId} AND email = 'veterinario@clinica.com' LIMIT 1");
        
        if (empty($vetUser)) {
            echo "⚠️  Usuário veterinário não encontrado. Execute a migração create_test_users primeiro.\n";
            return;
        }
        
        $vetUserId = (int)$vetUser[0]['id'];
        
        // Verifica se o profissional já existe
        $checkProfessional = $adapter->fetchAll("SELECT id FROM professionals WHERE tenant_id = {$tenantId} AND user_id = {$vetUserId}");
        
        if (!empty($checkProfessional)) {
            echo "ℹ️  Profissional já existe para o veterinário (user_id: {$vetUserId}, professional_id: {$checkProfessional[0]['id']})\n";
            return;
        }
        
        $now = date('Y-m-d H:i:s');
        
        // Cria o profissional
        $this->table('professionals')->insert([
            [
                'tenant_id' => $tenantId,
                'user_id' => $vetUserId,
                'crmv' => 'SP-12345',
                'specialties' => null, // Pode ser preenchido depois
                'default_consultation_duration' => 30,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ])->saveData();
        
        // Busca o ID do profissional criado
        $professional = $adapter->fetchAll("SELECT id FROM professionals WHERE tenant_id = {$tenantId} AND user_id = {$vetUserId} LIMIT 1");
        $professionalId = !empty($professional) ? (int)$professional[0]['id'] : null;
        
        echo "✅ Profissional criado com sucesso!\n";
        echo "   - Professional ID: {$professionalId}\n";
        echo "   - User ID: {$vetUserId}\n";
        echo "   - CRMV: SP-12345\n";
        echo "   - Status: active\n\n";
    }
    
    public function down(): void
    {
        // Busca o primeiro tenant
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
        $tenantId = empty($tenants) ? 1 : (int)$tenants[0]['id'];
        
        // Busca o user_id do veterinário
        $vetUser = $adapter->fetchAll("SELECT id FROM users WHERE tenant_id = {$tenantId} AND email = 'veterinario@clinica.com' LIMIT 1");
        
        if (!empty($vetUser)) {
            $vetUserId = (int)$vetUser[0]['id'];
            // Remove o profissional associado
            $this->execute("DELETE FROM professionals WHERE tenant_id = {$tenantId} AND user_id = {$vetUserId}");
            echo "🗑️  Profissional do veterinário removido.\n";
        }
    }
}

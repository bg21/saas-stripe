<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seed para criar usuários de exemplo
 * 
 * Cria usuários de teste com diferentes roles para testar o sistema de permissões
 */
class UserSeed extends AbstractSeed
{
    public function run(): void
    {
        // Busca o primeiro tenant usando query direta
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT * FROM tenants LIMIT 1");
        
        if (empty($tenants)) {
            echo "⚠️  Nenhum tenant encontrado. Execute primeiro o InitialSeed ou crie um tenant.\n";
            return;
        }
        
        $tenant = $tenants[0];
        $tenantId = (int)$tenant['id'];
        
        echo "📝 Criando usuários de exemplo para o tenant: {$tenant['name']} (ID: {$tenantId})\n\n";
        
        // Hash das senhas (bcrypt)
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $editorPassword = password_hash('editor123', PASSWORD_BCRYPT);
        $viewerPassword = password_hash('viewer123', PASSWORD_BCRYPT);
        
        $now = date('Y-m-d H:i:s');
        
        // Usuários de exemplo
        $users = [
            [
                'tenant_id' => $tenantId,
                'email' => 'admin@example.com',
                'password_hash' => $adminPassword,
                'name' => 'Administrador',
                'status' => 'active',
                'role' => 'admin',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'email' => 'editor@example.com',
                'password_hash' => $editorPassword,
                'name' => 'Editor',
                'status' => 'active',
                'role' => 'editor',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'email' => 'viewer@example.com',
                'password_hash' => $viewerPassword,
                'name' => 'Visualizador',
                'status' => 'active',
                'role' => 'viewer',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];
        
        // Insere usuários (tenta inserir, ignora se já existir)
        foreach ($users as $user) {
            try {
                $this->table('users')->insert($user)->saveData();
                echo "✅ Usuário criado: {$user['email']} (Role: {$user['role']})\n";
            } catch (\Exception $e) {
                // Se der erro de duplicata, apenas informa
                if (strpos($e->getMessage(), 'Duplicate entry') !== false || 
                    strpos($e->getMessage(), 'unique_tenant_email') !== false) {
                    echo "ℹ️  Usuário já existe: {$user['email']}\n";
                } else {
                    echo "⚠️  Erro ao criar usuário {$user['email']}: {$e->getMessage()}\n";
                }
            }
        }
        
        echo "\n✨ Seed de usuários concluído!\n\n";
        echo "📋 Resumo:\n";
        echo "   - Admin: admin@example.com / admin123 (todas as permissões)\n";
        echo "   - Editor: editor@example.com / editor123 (pode criar/editar)\n";
        echo "   - Viewer: viewer@example.com / viewer123 (apenas visualizar)\n\n";
        echo "💡 Use estes usuários para testar o sistema de autenticação e permissões.\n";
    }
}


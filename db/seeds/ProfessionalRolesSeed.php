<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seed para criar roles padrão de profissionais
 */
class ProfessionalRolesSeed extends AbstractSeed
{
    public function run(): void
    {
        // Obtém todos os tenants
        $stmt = $this->getAdapter()->getConnection()->prepare("SELECT id FROM tenants WHERE deleted_at IS NULL");
        $stmt->execute();
        $tenantRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($tenantRows)) {
            echo "⚠️  Nenhum tenant encontrado. Execute primeiro o seed de tenants.\n";
            return;
        }
        
        // Roles padrão
        $defaultRoles = [
            [
                'name' => 'Administrador',
                'description' => 'Administrador da clínica com acesso total ao sistema',
                'sort_order' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Gerente',
                'description' => 'Gerente da clínica com permissões de gestão',
                'sort_order' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Veterinário',
                'description' => 'Veterinário responsável por atendimentos e consultas',
                'sort_order' => 3,
                'is_active' => true
            ],
            [
                'name' => 'Atendente',
                'description' => 'Atendente responsável por recepção e agendamentos',
                'sort_order' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Auxiliar',
                'description' => 'Auxiliar de veterinário ou atendente',
                'sort_order' => 5,
                'is_active' => true
            ]
        ];
        
        $rolesTable = $this->table('professional_roles');
        $created = 0;
        $skipped = 0;
        
        foreach ($tenantRows as $tenant) {
            $tenantId = (int)$tenant['id'];
            
            foreach ($defaultRoles as $roleData) {
                // Verifica se já existe role com este nome para este tenant
                $checkStmt = $this->getAdapter()->getConnection()->prepare(
                    "SELECT id FROM professional_roles WHERE tenant_id = :tenant_id AND name = :name AND deleted_at IS NULL"
                );
                $checkStmt->execute(['tenant_id' => $tenantId, 'name' => $roleData['name']]);
                $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $skipped++;
                    continue;
                }
                
                // Insere a role
                $rolesTable->insert([
                    'tenant_id' => $tenantId,
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'permissions' => null,
                    'is_active' => $roleData['is_active'] ? 1 : 0,
                    'sort_order' => $roleData['sort_order'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ])->saveData();
                
                $created++;
            }
        }
        
        echo "✅ Roles criadas: {$created}\n";
        if ($skipped > 0) {
            echo "⚠️  Roles já existentes (ignoradas): {$skipped}\n";
        }
    }
}


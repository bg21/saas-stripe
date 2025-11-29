<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed para inserir tipos de exames de exemplo
 * 
 * Insere alguns tipos de exames comuns em clínicas veterinárias
 */
final class SeedExamTypes extends AbstractMigration
{
    public function up(): void
    {
        // Busca o primeiro tenant disponível
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
        
        if (empty($tenants)) {
            echo "⚠️  Nenhum tenant ativo encontrado. Criando tipos de exames para tenant_id = 1 (assumindo que existe).\n";
            $tenantId = 1;
        } else {
            $tenantId = (int)$tenants[0]['id'];
        }
        
        echo "🔬 Criando tipos de exames para o tenant_id: {$tenantId}\n\n";
        
        $now = date('Y-m-d H:i:s');
        
        // Tipos de exames de exemplo
        $examTypes = [
            // Exames de Sangue
            [
                'tenant_id' => $tenantId,
                'name' => 'Hemograma Completo',
                'category' => 'blood',
                'description' => 'Análise completa do sangue incluindo contagem de células vermelhas, brancas e plaquetas',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Bioquímica Sérica',
                'category' => 'blood',
                'description' => 'Avaliação de funções hepáticas, renais e metabólicas através de análise do soro sanguíneo',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Glicemia',
                'category' => 'blood',
                'description' => 'Medição dos níveis de glicose no sangue',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Perfil Renal',
                'category' => 'blood',
                'description' => 'Avaliação da função renal através de análise sanguínea',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Perfil Hepático',
                'category' => 'blood',
                'description' => 'Avaliação da função hepática através de análise sanguínea',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            
            // Exames de Urina
            [
                'tenant_id' => $tenantId,
                'name' => 'Urinálise Completa',
                'category' => 'urine',
                'description' => 'Análise física, química e microscópica da urina',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Cultura de Urina',
                'category' => 'urine',
                'description' => 'Identificação de bactérias e teste de sensibilidade a antibióticos',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Relação Proteína/Creatinina Urinária',
                'category' => 'urine',
                'description' => 'Avaliação da perda de proteínas pelos rins',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            
            // Exames de Imagem
            [
                'tenant_id' => $tenantId,
                'name' => 'Raio-X (Radiografia)',
                'category' => 'imaging',
                'description' => 'Exame de imagem para avaliação de ossos, articulações e órgãos internos',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ultrassonografia Abdominal',
                'category' => 'imaging',
                'description' => 'Exame de imagem para avaliação de órgãos abdominais',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ultrassonografia Cardíaca (Ecocardiograma)',
                'category' => 'imaging',
                'description' => 'Avaliação da estrutura e função cardíaca através de ultrassom',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Raio-X de Tórax',
                'category' => 'imaging',
                'description' => 'Avaliação radiográfica do tórax e pulmões',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            
            // Outros
            [
                'tenant_id' => $tenantId,
                'name' => 'Parasitológico de Fezes',
                'category' => 'other',
                'description' => 'Identificação de parasitas intestinais através de análise de fezes',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Citologia',
                'category' => 'other',
                'description' => 'Análise microscópica de células para diagnóstico de doenças',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Biópsia',
                'category' => 'other',
                'description' => 'Coleta e análise de tecido para diagnóstico histopatológico',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Teste de Função da Tireoide',
                'category' => 'other',
                'description' => 'Avaliação dos níveis hormonais da tireoide',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];
        
        // Verifica se já existem tipos de exames para este tenant
        $existing = $adapter->fetchAll("SELECT COUNT(*) as count FROM exam_types WHERE tenant_id = {$tenantId}");
        $count = !empty($existing) ? (int)$existing[0]['count'] : 0;
        
        if ($count > 0) {
            echo "ℹ️  Já existem {$count} tipo(s) de exame(s) para este tenant. Pulando inserção.\n";
            return;
        }
        
        // Insere os tipos de exames
        $inserted = 0;
        foreach ($examTypes as $examType) {
            $this->table('exam_types')->insert([$examType])->saveData();
            echo "✅ Tipo de exame criado: {$examType['name']} ({$examType['category']})\n";
            $inserted++;
        }
        
        echo "\n✅ Total de {$inserted} tipos de exames criados com sucesso!\n";
    }

    public function down(): void
    {
        // Remove todos os tipos de exames criados por este seed
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
        
        if (empty($tenants)) {
            return;
        }
        
        $tenantId = (int)$tenants[0]['id'];
        
        $examTypeNames = [
            'Hemograma Completo',
            'Bioquímica Sérica',
            'Glicemia',
            'Perfil Renal',
            'Perfil Hepático',
            'Urinálise Completa',
            'Cultura de Urina',
            'Relação Proteína/Creatinina Urinária',
            'Raio-X (Radiografia)',
            'Ultrassonografia Abdominal',
            'Ultrassonografia Cardíaca (Ecocardiograma)',
            'Raio-X de Tórax',
            'Parasitológico de Fezes',
            'Citologia',
            'Biópsia',
            'Teste de Função da Tireoide'
        ];
        
        foreach ($examTypeNames as $name) {
            $adapter->execute("DELETE FROM exam_types WHERE tenant_id = {$tenantId} AND name = " . $adapter->quote($name));
        }
        
        echo "🗑️  Tipos de exames removidos.\n";
    }
}

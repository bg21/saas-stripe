<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed para inserir exames de exemplo
 * 
 * Insere alguns exames com diferentes status para demonstração
 */
final class SeedExams extends AbstractMigration
{
    public function up(): void
    {
        // Busca o primeiro tenant disponível
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
        
        if (empty($tenants)) {
            echo "⚠️  Nenhum tenant ativo encontrado. Criando exames para tenant_id = 1 (assumindo que existe).\n";
            $tenantId = 1;
        } else {
            $tenantId = (int)$tenants[0]['id'];
        }
        
        echo "🔬 Criando exames de exemplo para o tenant_id: {$tenantId}\n\n";
        
        // Busca pets existentes
        $pets = $adapter->fetchAll("SELECT id, client_id FROM pets WHERE tenant_id = {$tenantId} AND deleted_at IS NULL LIMIT 10");
        
        if (empty($pets)) {
            echo "⚠️  Nenhum pet encontrado. É necessário criar pets antes de criar exames.\n";
            return;
        }
        
        // Busca profissionais existentes
        $professionals = $adapter->fetchAll("SELECT id FROM professionals WHERE tenant_id = {$tenantId} AND deleted_at IS NULL LIMIT 5");
        
        // Busca tipos de exames existentes
        $examTypes = $adapter->fetchAll("SELECT id FROM exam_types WHERE tenant_id = {$tenantId} AND status = 'active' AND deleted_at IS NULL LIMIT 10");
        
        if (empty($examTypes)) {
            echo "⚠️  Nenhum tipo de exame encontrado. É necessário criar tipos de exames antes de criar exames.\n";
            return;
        }
        
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        
        // Gera datas para os exames (alguns no passado, alguns no futuro)
        $today = new \DateTime();
        $pastDate1 = (clone $today)->modify('-15 days')->format('Y-m-d');
        $pastDate2 = (clone $today)->modify('-7 days')->format('Y-m-d');
        $futureDate1 = (clone $today)->modify('+3 days')->format('Y-m-d');
        $futureDate2 = (clone $today)->modify('+7 days')->format('Y-m-d');
        $futureDate3 = (clone $today)->modify('+10 days')->format('Y-m-d');
        
        // Exames de exemplo
        $exams = [];
        
        // Exame completado (no passado)
        if (!empty($pets[0]) && !empty($examTypes[0])) {
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pets[0]['id'],
                'client_id' => (int)$pets[0]['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examTypes[0]['id'],
                'exam_date' => $pastDate1,
                'exam_time' => '09:00:00',
                'status' => 'completed',
                'notes' => 'Exame realizado com sucesso. Pet apresentou bom estado geral.',
                'results' => 'Resultados dentro dos parâmetros normais. Nenhuma alteração significativa detectada.',
                'completed_at' => $pastDate1 . ' 10:30:00',
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Exame completado (mais recente)
        if (!empty($pets[0]) && !empty($examTypes[1])) {
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pets[0]['id'],
                'client_id' => (int)$pets[0]['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examTypes[1]['id'],
                'exam_date' => $pastDate2,
                'exam_time' => '14:30:00',
                'status' => 'completed',
                'notes' => 'Exame de rotina. Pet colaborativo durante o procedimento.',
                'results' => 'Valores normais. Recomendado retorno em 6 meses.',
                'completed_at' => $pastDate2 . ' 15:00:00',
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Exame agendado (próximos dias)
        if (!empty($pets[0]) && !empty($examTypes[2])) {
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pets[0]['id'],
                'client_id' => (int)$pets[0]['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examTypes[2]['id'],
                'exam_date' => $futureDate1,
                'exam_time' => '10:00:00',
                'status' => 'scheduled',
                'notes' => 'Exame agendado para acompanhamento pós-cirúrgico.',
                'results' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Exame pendente
        if (!empty($pets[1] ?? $pets[0]) && !empty($examTypes[3] ?? $examTypes[0])) {
            $pet = $pets[1] ?? $pets[0];
            $examType = $examTypes[3] ?? $examTypes[0];
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pet['id'],
                'client_id' => (int)$pet['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examType['id'],
                'exam_date' => $futureDate2,
                'exam_time' => '11:30:00',
                'status' => 'pending',
                'notes' => 'Exame solicitado pelo tutor. Aguardando confirmação de horário.',
                'results' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Exame agendado (outro pet)
        if (!empty($pets[1] ?? $pets[0]) && !empty($examTypes[4] ?? $examTypes[1])) {
            $pet = $pets[1] ?? $pets[0];
            $examType = $examTypes[4] ?? $examTypes[1];
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pet['id'],
                'client_id' => (int)$pet['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examType['id'],
                'exam_date' => $futureDate3,
                'exam_time' => '15:00:00',
                'status' => 'scheduled',
                'notes' => 'Exame de rotina anual. Pet em bom estado geral.',
                'results' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Exame cancelado
        if (!empty($pets[2] ?? $pets[0]) && !empty($examTypes[5] ?? $examTypes[0])) {
            $pet = $pets[2] ?? $pets[0];
            $examType = $examTypes[5] ?? $examTypes[0];
            $cancelDate = (clone $today)->modify('-5 days')->format('Y-m-d H:i:s');
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pet['id'],
                'client_id' => (int)$pet['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examType['id'],
                'exam_date' => $pastDate2,
                'exam_time' => '16:00:00',
                'status' => 'cancelled',
                'notes' => 'Exame cancelado a pedido do tutor.',
                'results' => null,
                'cancellation_reason' => 'Tutor não pôde comparecer no horário agendado.',
                'cancelled_at' => $cancelDate,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Exame pendente (outro tipo)
        if (!empty($pets[0]) && !empty($examTypes[6] ?? $examTypes[2])) {
            $examType = $examTypes[6] ?? $examTypes[2];
            $exams[] = [
                'tenant_id' => $tenantId,
                'pet_id' => (int)$pets[0]['id'],
                'client_id' => (int)$pets[0]['client_id'],
                'professional_id' => !empty($professionals[0]) ? (int)$professionals[0]['id'] : null,
                'exam_type_id' => (int)$examType['id'],
                'exam_date' => $futureDate1,
                'exam_time' => '08:30:00',
                'status' => 'pending',
                'notes' => 'Exame solicitado para investigação de sintomas.',
                'results' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Insere os exames
        foreach ($exams as $exam) {
            $this->table('exams')->insert([$exam])->saveData();
            $inserted++;
            echo "✅ Exame criado: Pet #{$exam['pet_id']} - {$exam['exam_date']} {$exam['exam_time']} - Status: {$exam['status']}\n";
        }
        
        echo "\n✅ Total de {$inserted} exames criados com sucesso!\n";
    }
    
    public function down(): void
    {
        // Busca o primeiro tenant
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
        $tenantId = empty($tenants) ? 1 : (int)$tenants[0]['id'];
        
        // Remove todos os exames criados por este seed
        // Como não temos uma forma fácil de identificar quais foram criados por este seed,
        // vamos remover apenas os que têm as características dos exames de exemplo
        // (com notes específicos ou datas recentes)
        
        $adapter->execute("DELETE FROM exams WHERE tenant_id = {$tenantId} AND (
            notes LIKE '%Exame realizado com sucesso%' OR
            notes LIKE '%Exame de rotina%' OR
            notes LIKE '%Exame agendado para acompanhamento%' OR
            notes LIKE '%Exame solicitado pelo tutor%' OR
            notes LIKE '%Exame cancelado a pedido%' OR
            notes LIKE '%Exame solicitado para investigação%'
        )");
        
        echo "🗑️  Exames de exemplo removidos.\n";
    }
}


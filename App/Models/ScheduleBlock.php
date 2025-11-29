<?php

namespace App\Models;

/**
 * Model para gerenciar bloqueios de agenda dos profissionais
 */
class ScheduleBlock extends BaseModel
{
    protected string $table = 'schedule_blocks';
    
    /**
     * Busca bloqueios de um profissional em um período
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param string $startDate Data de início (Y-m-d)
     * @param string $endDate Data de fim (Y-m-d)
     * @return array
     */
    public function findByProfessionalAndPeriod(int $tenantId, int $professionalId, string $startDate, string $endDate): array
    {
        // Busca bloqueios que se sobrepõem ao período solicitado
        // Um bloqueio se sobrepõe se: start_datetime < end_date AND end_datetime > start_date
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND start_datetime < :end_datetime 
                AND end_datetime > :start_datetime
                ORDER BY start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'start_datetime' => $startDate . ' 00:00:00',
            'end_datetime' => $endDate . ' 23:59:59'
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Verifica se há bloqueio em um horário específico
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param string $datetime Data e hora (Y-m-d H:i:s)
     * @return bool
     */
    public function hasBlock(int $tenantId, int $professionalId, string $datetime): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND start_datetime <= :block_datetime 
                AND end_datetime > :block_datetime2";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'block_datetime' => $datetime,
            'block_datetime2' => $datetime
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result && (int)$result['count'] > 0;
    }
    
    /**
     * Busca bloqueios futuros de um profissional
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @return array
     */
    public function findFutureBlocks(int $tenantId, int $professionalId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND end_datetime >= NOW()
                ORDER BY start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}


<?php

namespace App\Models;

/**
 * Model para gerenciar horários de trabalho dos profissionais
 */
class ProfessionalSchedule extends BaseModel
{
    protected string $table = 'professional_schedules';
    
    /**
     * Busca agenda de um profissional
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @return array
     */
    public function findByProfessional(int $tenantId, int $professionalId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND is_available = 1
                ORDER BY day_of_week ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca horário de um dia específico
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param int $dayOfWeek Dia da semana (0=domingo, 1=segunda, ..., 6=sábado)
     * @return array|null
     */
    public function findByDay(int $tenantId, int $professionalId, int $dayOfWeek): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND day_of_week = :day_of_week
                AND is_available = 1
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'day_of_week' => $dayOfWeek
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Salva ou atualiza horário de um dia
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @param int $dayOfWeek Dia da semana (0=domingo, 1=segunda, ..., 6=sábado)
     * @param string $startTime Hora de início (H:i:s)
     * @param string $endTime Hora de fim (H:i:s)
     * @param bool $isActive Se o horário está ativo
     * @return int ID do registro (criado ou atualizado)
     */
    public function saveSchedule(int $tenantId, int $professionalId, int $dayOfWeek, string $startTime, string $endTime, bool $isActive = true): int
    {
        // Verifica se já existe
        $existing = $this->findByDay($tenantId, $professionalId, $dayOfWeek);
        
        if ($existing) {
            // Atualiza usando SQL direto
            $sql = "UPDATE {$this->table} 
                    SET start_time = :start_time, 
                        end_time = :end_time, 
                        is_available = :is_available,
                        updated_at = NOW()
                    WHERE tenant_id = :tenant_id 
                    AND professional_id = :professional_id 
                    AND day_of_week = :day_of_week";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'professional_id' => $professionalId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => $isActive ? 1 : 0
            ]);
            
            return (int)$existing['id'];
        } else {
            // Insere
            $sql = "INSERT INTO {$this->table} 
                    (tenant_id, professional_id, day_of_week, start_time, end_time, is_available, created_at, updated_at) 
                    VALUES (:tenant_id, :professional_id, :day_of_week, :start_time, :end_time, :is_available, NOW(), NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'professional_id' => $professionalId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => $isActive ? 1 : 0
            ]);
            
            return (int)$this->db->lastInsertId();
        }
    }
    
    /**
     * Remove todos os horários de um profissional
     * 
     * @param int $tenantId ID do tenant
     * @param int $professionalId ID do profissional
     * @return int Número de registros removidos
     */
    public function deleteByProfessional(int $tenantId, int $professionalId): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ]);
        
        return $stmt->rowCount();
    }
}


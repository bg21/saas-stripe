<?php

namespace App\Models;

/**
 * Model para gerenciar bloqueios de agenda
 */
class ScheduleBlock extends BaseModel
{
    protected string $table = 'schedule_blocks';

    /**
     * Verifica se há bloqueio em um horário específico
     * 
     * @param int $professionalId ID do profissional
     * @param \DateTime $datetime Data e hora a verificar
     * @return bool
     */
    public function hasBlock(int $professionalId, \DateTime $datetime): bool
    {
        $blocks = $this->findAll([
            'professional_id' => $professionalId
        ]);
        
        foreach ($blocks as $block) {
            $start = new \DateTime($block['start_datetime']);
            $end = new \DateTime($block['end_datetime']);
            
            if ($datetime >= $start && $datetime <= $end) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Lista bloqueios de um profissional em um período
     * 
     * @param int $professionalId ID do profissional
     * @param \DateTime|null $startDate Data inicial (opcional)
     * @param \DateTime|null $endDate Data final (opcional)
     * @return array
     */
    public function findByProfessional(int $professionalId, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $conditions = ['professional_id' => $professionalId];
        
        // Se datas fornecidas, filtra por período
        if ($startDate && $endDate) {
            // Usa LIKE para buscar por data (simplificado)
            // Em produção, usar BETWEEN seria melhor
            $blocks = $this->findAll($conditions);
            $filtered = [];
            
            foreach ($blocks as $block) {
                $blockStart = new \DateTime($block['start_datetime']);
                $blockEnd = new \DateTime($block['end_datetime']);
                
                // Verifica sobreposição
                if (($blockStart >= $startDate && $blockStart <= $endDate) ||
                    ($blockEnd >= $startDate && $blockEnd <= $endDate) ||
                    ($blockStart <= $startDate && $blockEnd >= $endDate)) {
                    $filtered[] = $block;
                }
            }
            
            return $filtered;
        }
        
        return $this->findAll($conditions, ['start_datetime' => 'ASC']);
    }
}


<?php

namespace App\Models;

/**
 * Model para gerenciar configurações da clínica
 */
class ClinicConfiguration extends BaseModel
{
    protected string $table = 'clinic_configurations';
    
    /**
     * Busca configuração por tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array|null
     */
    public function findByTenant(int $tenantId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :tenant_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Cria ou atualiza configuração do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $data Dados da configuração
     * @return int ID da configuração (criada ou atualizada)
     */
    public function saveConfiguration(int $tenantId, array $data): int
    {
        $existing = $this->findByTenant($tenantId);
        
        // Remove tenant_id do array de dados (já está no WHERE)
        unset($data['tenant_id']);
        
        // Adiciona updated_at
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if ($existing) {
            // Atualiza
            $this->update($existing['id'], $data);
            return (int)$existing['id'];
        } else {
            // Cria
            $data['tenant_id'] = $tenantId;
            $data['created_at'] = date('Y-m-d H:i:s');
            return $this->insert($data);
        }
    }
    
    /**
     * Valida dados de configuração
     * 
     * @param array $data Dados a validar
     * @return array Array com erros (vazio se válido)
     */
    public function validate(array $data): array
    {
        $errors = [];
        
        // Valida informações básicas da clínica
        if (isset($data['clinic_name']) && strlen($data['clinic_name']) > 255) {
            $errors['clinic_name'] = 'Nome da clínica deve ter no máximo 255 caracteres';
        }
        
        if (isset($data['clinic_phone']) && !empty($data['clinic_phone'])) {
            // Remove caracteres não numéricos para validação
            $phone = preg_replace('/[^0-9]/', '', $data['clinic_phone']);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $errors['clinic_phone'] = 'Telefone inválido';
            }
        }
        
        if (isset($data['clinic_email']) && !empty($data['clinic_email'])) {
            if (!filter_var($data['clinic_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['clinic_email'] = 'Email inválido';
            }
            if (strlen($data['clinic_email']) > 255) {
                $errors['clinic_email'] = 'Email deve ter no máximo 255 caracteres';
            }
        }
        
        if (isset($data['clinic_zip_code']) && !empty($data['clinic_zip_code'])) {
            $zipCode = preg_replace('/[^0-9]/', '', $data['clinic_zip_code']);
            if (strlen($zipCode) !== 8) {
                $errors['clinic_zip_code'] = 'CEP deve ter 8 dígitos';
            }
        }
        
        if (isset($data['clinic_website']) && !empty($data['clinic_website'])) {
            if (!filter_var($data['clinic_website'], FILTER_VALIDATE_URL)) {
                $errors['clinic_website'] = 'URL do website inválida';
            }
            if (strlen($data['clinic_website']) > 255) {
                $errors['clinic_website'] = 'Website deve ter no máximo 255 caracteres';
            }
        }
        
        // Valida default_appointment_duration
        if (isset($data['default_appointment_duration'])) {
            $duration = (int)$data['default_appointment_duration'];
            if ($duration < 5 || $duration > 240) {
                $errors['default_appointment_duration'] = 'Duração deve estar entre 5 e 240 minutos';
            }
        }
        
        // Valida time_slot_interval
        if (isset($data['time_slot_interval'])) {
            $interval = (int)$data['time_slot_interval'];
            if ($interval < 5 || $interval > 60) {
                $errors['time_slot_interval'] = 'Intervalo deve estar entre 5 e 60 minutos';
            }
        }
        
        // Valida cancellation_hours
        if (isset($data['cancellation_hours'])) {
            $hours = (int)$data['cancellation_hours'];
            if ($hours < 0 || $hours > 168) {
                $errors['cancellation_hours'] = 'Horas de cancelamento devem estar entre 0 e 168 (7 dias)';
            }
        }
        
        // Valida horários (se fornecidos)
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            $openingKey = "opening_time_{$day}";
            $closingKey = "closing_time_{$day}";
            
            if (isset($data[$openingKey]) && isset($data[$closingKey])) {
                $opening = $data[$openingKey];
                $closing = $data[$closingKey];
                
                if ($opening && $closing) {
                    // Valida formato de hora
                    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:00)?$/', $opening)) {
                        $errors[$openingKey] = 'Formato de hora inválido (use HH:MM)';
                    }
                    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:00)?$/', $closing)) {
                        $errors[$closingKey] = 'Formato de hora inválido (use HH:MM)';
                    }
                    
                    // Valida se abertura é antes do fechamento
                    if ($opening && $closing && strtotime($opening) >= strtotime($closing)) {
                        $errors[$closingKey] = 'Horário de fechamento deve ser posterior ao de abertura';
                    }
                }
            }
        }
        
        return $errors;
    }
}


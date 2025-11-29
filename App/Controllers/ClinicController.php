<?php

namespace App\Controllers;

use App\Models\ClinicConfiguration;
use App\Utils\PermissionHelper;
use App\Utils\RequestCache;
use App\Utils\ResponseHelper;
use Flight;

/**
 * Controller para gerenciar configurações da clínica
 */
class ClinicController
{
    private ClinicConfiguration $configModel;

    public function __construct()
    {
        $this->configModel = new ClinicConfiguration();
    }

    /**
     * Obtém configurações da clínica
     * GET /v1/clinic/configuration
     */
    public function getConfiguration(): void
    {
        try {
            PermissionHelper::require('manage_clinic_settings');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_clinic_configuration']);
                return;
            }
            
            $config = $this->configModel->findByTenant($tenantId);
            
            if (!$config) {
                // Retorna configuração padrão se não existir
                $config = $this->getDefaultConfiguration();
            }
            
            ResponseHelper::sendSuccess($config);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter configurações da clínica',
                'CLINIC_CONFIG_GET_ERROR',
                ['action' => 'get_clinic_configuration', 'tenant_id' => $tenantId ?? null]
            );
        }
    }

    /**
     * Atualiza configurações da clínica
     * PUT /v1/clinic/configuration
     */
    public function updateConfiguration(): void
    {
        try {
            PermissionHelper::require('manage_clinic_settings');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_clinic_configuration']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_clinic_configuration', 'tenant_id' => $tenantId]);
                return;
            }
            
            if (empty($data)) {
                ResponseHelper::sendError(400, 'Dados inválidos', 'Dados de configuração são obrigatórios', 'INVALID_DATA', [], ['action' => 'update_clinic_configuration', 'tenant_id' => $tenantId]);
                return;
            }
            
            // Valida dados
            $errors = $this->configModel->validate($data);
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError('Dados de configuração inválidos', $errors, ['action' => 'update_clinic_configuration', 'tenant_id' => $tenantId]);
                return;
            }
            
            // Normaliza horários (adiciona :00 se necessário)
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                $openingKey = "opening_time_{$day}";
                $closingKey = "closing_time_{$day}";
                
                if (isset($data[$openingKey]) && $data[$openingKey] && strlen($data[$openingKey]) === 5) {
                    $data[$openingKey] .= ':00';
                }
                
                if (isset($data[$closingKey]) && $data[$closingKey] && strlen($data[$closingKey]) === 5) {
                    $data[$closingKey] .= ':00';
                }
                
                // Converte string vazia para null
                if (isset($data[$openingKey]) && $data[$openingKey] === '') {
                    $data[$openingKey] = null;
                }
                if (isset($data[$closingKey]) && $data[$closingKey] === '') {
                    $data[$closingKey] = null;
                }
            }
            
            // Converte booleanos
            if (isset($data['allow_online_booking'])) {
                $data['allow_online_booking'] = $data['allow_online_booking'] ? 1 : 0;
            }
            if (isset($data['require_confirmation'])) {
                $data['require_confirmation'] = $data['require_confirmation'] ? 1 : 0;
            }
            
            // Converte inteiros
            if (isset($data['default_appointment_duration'])) {
                $data['default_appointment_duration'] = (int)$data['default_appointment_duration'];
            }
            if (isset($data['time_slot_interval'])) {
                $data['time_slot_interval'] = (int)$data['time_slot_interval'];
            }
            if (isset($data['cancellation_hours'])) {
                $data['cancellation_hours'] = (int)$data['cancellation_hours'];
            }
            
            // Salva configuração
            $configId = $this->configModel->saveConfiguration($tenantId, $data);
            
            // Busca configuração atualizada
            $updated = $this->configModel->findByTenant($tenantId);
            
            ResponseHelper::sendSuccess($updated, 200, 'Configurações atualizadas com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao atualizar configurações da clínica',
                'CLINIC_CONFIG_UPDATE_ERROR',
                ['action' => 'update_clinic_configuration', 'tenant_id' => $tenantId ?? null]
            );
        }
    }
    
    /**
     * Faz upload do logo da clínica
     * POST /v1/clinic/logo
     */
    public function uploadLogo(): void
    {
        try {
            PermissionHelper::require('manage_clinic_settings');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'upload_clinic_logo']);
                return;
            }
            
            // Verifica se há arquivo enviado
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                ResponseHelper::sendValidationError('Arquivo não enviado ou com erro', ['logo' => 'Arquivo é obrigatório'], ['action' => 'upload_clinic_logo']);
                return;
            }
            
            $file = $_FILES['logo'];
            
            // Valida tipo de arquivo (apenas imagens)
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                ResponseHelper::sendValidationError('Tipo de arquivo inválido', ['logo' => 'Apenas imagens (JPG, PNG, GIF, WEBP) são permitidas'], ['action' => 'upload_clinic_logo']);
                return;
            }
            
            // Valida tamanho (máximo 5MB)
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxSize) {
                ResponseHelper::sendValidationError('Arquivo muito grande', ['logo' => 'O arquivo deve ter no máximo 5MB'], ['action' => 'upload_clinic_logo']);
                return;
            }
            
            // Busca configuração atual para remover logo antigo
            $config = $this->configModel->findByTenant($tenantId);
            
            // Cria diretório de uploads se não existir
            $uploadDir = __DIR__ . '/../../storage/clinic-logos/' . $tenantId . '/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            
            // Gera nome único para o arquivo
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            $filename = 'logo_' . $tenantId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . $filename;
            
            // Move arquivo
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                ResponseHelper::sendGenericError(new \Exception('Erro ao salvar arquivo'), 'Erro ao fazer upload do logo', 'CLINIC_LOGO_UPLOAD_ERROR', ['action' => 'upload_clinic_logo']);
                return;
            }
            
            // Remove arquivo anterior se existir
            if ($config && !empty($config['clinic_logo'])) {
                $oldFilePath = __DIR__ . '/../../' . $config['clinic_logo'];
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }
            
            // Salva caminho relativo no banco
            $relativePath = 'storage/clinic-logos/' . $tenantId . '/' . $filename;
            $this->configModel->saveConfiguration($tenantId, ['clinic_logo' => $relativePath]);
            
            $updated = $this->configModel->findByTenant($tenantId);
            
            ResponseHelper::sendSuccess([
                'logo_path' => $relativePath,
                'logo_url' => '/' . $relativePath,
                'config' => $updated
            ], 200, 'Logo enviado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao fazer upload do logo',
                'CLINIC_LOGO_UPLOAD_ERROR',
                ['action' => 'upload_clinic_logo']
            );
        }
    }
    
    /**
     * Retorna configuração padrão
     * 
     * @return array
     */
    private function getDefaultConfiguration(): array
    {
        return [
            'id' => null,
            'tenant_id' => Flight::get('tenant_id'),
            'clinic_name' => null,
            'clinic_phone' => null,
            'clinic_email' => null,
            'clinic_address' => null,
            'clinic_city' => null,
            'clinic_state' => null,
            'clinic_zip_code' => null,
            'clinic_logo' => null,
            'clinic_description' => null,
            'clinic_website' => null,
            'opening_time_monday' => '08:00:00',
            'closing_time_monday' => '18:00:00',
            'opening_time_tuesday' => '08:00:00',
            'closing_time_tuesday' => '18:00:00',
            'opening_time_wednesday' => '08:00:00',
            'closing_time_wednesday' => '18:00:00',
            'opening_time_thursday' => '08:00:00',
            'closing_time_thursday' => '18:00:00',
            'opening_time_friday' => '08:00:00',
            'closing_time_friday' => '18:00:00',
            'opening_time_saturday' => '08:00:00',
            'closing_time_saturday' => '12:00:00',
            'opening_time_sunday' => null,
            'closing_time_sunday' => null,
            'default_appointment_duration' => 30,
            'time_slot_interval' => 15,
            'allow_online_booking' => 1,
            'require_confirmation' => 0,
            'cancellation_hours' => 24,
            'metadata' => null,
            'created_at' => null,
            'updated_at' => null
        ];
    }
}


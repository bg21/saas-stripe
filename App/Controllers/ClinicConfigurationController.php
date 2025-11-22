<?php

namespace App\Controllers;

use App\Models\ClinicConfiguration;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

/**
 * Controller para gerenciar configurações da clínica veterinária
 */
class ClinicConfigurationController
{
    private ClinicConfiguration $configModel;

    public function __construct(ClinicConfiguration $configModel)
    {
        $this->configModel = $configModel;
    }

    /**
     * Obtém configurações da clínica
     * GET /v1/clinic/configuration
     */
    public function get(): void
    {
        try {
            PermissionHelper::require('manage_clinic_settings');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_clinic_config']);
                return;
            }
            
            $config = $this->configModel->findByTenant($tenantId);
            
            if (!$config) {
                ResponseHelper::sendNotFoundError('Configurações não encontradas', ['action' => 'get_clinic_config', 'tenant_id' => $tenantId]);
                return;
            }
            
            ResponseHelper::sendSuccess($config, 'Configurações obtidas com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter configurações', 'CLINIC_CONFIG_GET_ERROR', ['action' => 'get_clinic_config', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }

    /**
     * Atualiza configurações da clínica
     * PUT /v1/clinic/configuration
     */
    public function update(): void
    {
        try {
            PermissionHelper::require('manage_clinic_settings');
            
            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'update_clinic_config']);
                return;
            }
            
            $data = RequestCache::getJsonInput();
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                ResponseHelper::sendInvalidJsonError(['action' => 'update_clinic_config']);
                return;
            }
            
            // Campos permitidos para atualização
            $allowedFields = [
                'opening_time_monday', 'closing_time_monday',
                'opening_time_tuesday', 'closing_time_tuesday',
                'opening_time_wednesday', 'closing_time_wednesday',
                'opening_time_thursday', 'closing_time_thursday',
                'opening_time_friday', 'closing_time_friday',
                'opening_time_saturday', 'closing_time_saturday',
                'opening_time_sunday', 'closing_time_sunday',
                'default_appointment_duration',
                'time_slot_interval',
                'allow_online_booking',
                'require_confirmation',
                'cancellation_hours',
                'metadata'
            ];
            
            // Filtra apenas campos permitidos
            $updateData = array_intersect_key($data ?? [], array_flip($allowedFields));
            
            if (empty($updateData)) {
                ResponseHelper::sendValidationError('Nenhum campo válido para atualização', [], ['action' => 'update_clinic_config']);
                return;
            }
            
            $configId = $this->configModel->createOrUpdate($tenantId, $updateData);
            $config = $this->configModel->findById($configId);
            
            ResponseHelper::sendSuccess($config, 'Configurações atualizadas com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao atualizar configurações', 'CLINIC_CONFIG_UPDATE_ERROR', ['action' => 'update_clinic_config', 'tenant_id' => Flight::get('tenant_id')]);
        }
    }
}


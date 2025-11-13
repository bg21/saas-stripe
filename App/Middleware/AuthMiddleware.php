<?php

namespace App\Middleware;

use App\Models\Tenant;
use App\Services\Logger;
use Config;

/**
 * Middleware de autenticação via Bearer Token (API Key)
 */
class AuthMiddleware
{
    private Tenant $tenantModel;

    public function __construct(Tenant $tenantModel)
    {
        $this->tenantModel = $tenantModel;
    }

    /**
     * Valida autenticação e injeta tenant no request
     */
    public function handle(): ?array
    {
        // Tenta obter headers de várias formas
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback para CLI ou quando getallheaders não está disponível
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }
        
        // Também verifica diretamente no $_SERVER
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$authHeader) {
            return $this->unauthorized('Token de autenticação não fornecido');
        }

        // Extrai Bearer token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Formato de token inválido');
        }

        $apiKey = $matches[1];

        // Verifica API master key (para endpoints administrativos)
        $masterKey = Config::get('API_MASTER_KEY');
        if ($masterKey && $apiKey === $masterKey) {
            Logger::debug("Autenticação via master key");
            return ['tenant_id' => null, 'is_master' => true];
        }

        // Busca tenant pela API key
        $tenant = $this->tenantModel->findByApiKey($apiKey);

        if (!$tenant) {
            Logger::warning("Tentativa de autenticação com API key inválida");
            return $this->unauthorized('Token inválido');
        }

        if ($tenant['status'] !== 'active') {
            Logger::warning("Tentativa de autenticação com tenant inativo", [
                'tenant_id' => $tenant['id']
            ]);
            return $this->unauthorized('Tenant inativo');
        }

        Logger::debug("Autenticação bem-sucedida", ['tenant_id' => $tenant['id']]);

        return [
            'tenant_id' => $tenant['id'],
            'tenant' => $tenant,
            'is_master' => false
        ];
    }

    /**
     * Retorna resposta de não autorizado
     */
    private function unauthorized(string $message): array
    {
        return [
            'error' => true,
            'message' => $message,
            'code' => 401
        ];
    }
}


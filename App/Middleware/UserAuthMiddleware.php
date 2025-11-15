<?php

namespace App\Middleware;

use App\Models\UserSession;
use App\Services\Logger;
use Flight;

/**
 * Middleware de autenticação de usuários via Session ID
 * 
 * Valida sessões de usuários autenticados e injeta dados no Flight
 */
class UserAuthMiddleware
{
    private UserSession $sessionModel;

    public function __construct()
    {
        $this->sessionModel = new UserSession();
    }

    /**
     * Valida autenticação de usuário e injeta dados no request
     * 
     * @return array|null Dados do usuário autenticado ou null se inválido
     */
    public function handle(): ?array
    {
        $sessionId = $this->getSessionId();

        if (!$sessionId) {
            return $this->unauthorized('Token de sessão não fornecido');
        }

        $session = $this->sessionModel->validate($sessionId);

        if (!$session) {
            Logger::warning("Tentativa de acesso com sessão inválida", [
                'session_id' => substr($sessionId, 0, 20) . '...'
            ]);
            return $this->unauthorized('Sessão inválida ou expirada');
        }

        // Injeta dados no Flight
        Flight::set('user_id', (int)$session['user_id']);
        Flight::set('user_role', $session['role'] ?? 'viewer');
        Flight::set('user_email', $session['email']);
        Flight::set('user_name', $session['name']);
        Flight::set('tenant_id', (int)$session['tenant_id']);
        Flight::set('tenant_name', $session['tenant_name']);
        Flight::set('is_user_auth', true);
        Flight::set('is_master', false);

        Logger::debug("Autenticação de usuário bem-sucedida", [
            'user_id' => $session['user_id'],
            'tenant_id' => $session['tenant_id']
        ]);

        return [
            'user_id' => (int)$session['user_id'],
            'user_role' => $session['role'] ?? 'viewer',
            'tenant_id' => (int)$session['tenant_id']
        ];
    }

    /**
     * Obtém session ID do header Authorization
     * 
     * @return string|null Session ID ou null se não encontrado
     */
    private function getSessionId(): ?string
    {
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

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$authHeader) {
            return null;
        }

        // Suporta "Bearer {token}" ou apenas "{token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        return trim($authHeader);
    }

    /**
     * Retorna resposta de não autorizado
     * 
     * @param string $message Mensagem de erro
     * @return array Resposta de erro
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


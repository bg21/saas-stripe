<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;
use App\Services\Logger;
use Flight;
use Config;

/**
 * Controller para autenticação de usuários
 */
class AuthController
{
    private User $userModel;
    private UserSession $sessionModel;
    private Tenant $tenantModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->sessionModel = new UserSession();
        $this->tenantModel = new Tenant();
    }

    /**
     * Login de usuário
     * POST /v1/auth/login
     * Body: { "email": "...", "password": "...", "tenant_id": 1 }
     */
    public function login(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $tenantId = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;

            if (empty($email) || empty($password) || empty($tenantId)) {
                Flight::halt(400, json_encode([
                    'error' => 'Dados inválidos',
                    'message' => 'Email, senha e tenant_id são obrigatórios'
                ]));
                return;
            }

            // Busca usuário
            $user = $this->userModel->findByEmailAndTenant($email, $tenantId);

            if (!$user) {
                Logger::warning("Tentativa de login com email não encontrado", [
                    'email' => $email,
                    'tenant_id' => $tenantId
                ]);
                Flight::halt(401, json_encode([
                    'error' => 'Credenciais inválidas',
                    'message' => 'Email ou senha incorretos'
                ]));
                return;
            }

            // Verifica senha
            if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
                Logger::warning("Tentativa de login com senha incorreta", [
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                Flight::halt(401, json_encode([
                    'error' => 'Credenciais inválidas',
                    'message' => 'Email ou senha incorretos'
                ]));
                return;
            }

            // Verifica status do usuário
            if ($user['status'] !== 'active') {
                Logger::warning("Tentativa de login com usuário inativo", [
                    'user_id' => $user['id']
                ]);
                Flight::halt(403, json_encode([
                    'error' => 'Usuário inativo',
                    'message' => 'Sua conta está inativa. Entre em contato com o administrador.'
                ]));
                return;
            }

            // Verifica tenant
            $tenant = $this->tenantModel->findById($tenantId);
            if (!$tenant || $tenant['status'] !== 'active') {
                Logger::warning("Tentativa de login com tenant inativo", [
                    'tenant_id' => $tenantId
                ]);
                Flight::halt(403, json_encode([
                    'error' => 'Tenant inativo',
                    'message' => 'O tenant está inativo. Entre em contato com o suporte.'
                ]));
                return;
            }

            // Cria sessão
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $sessionId = $this->sessionModel->create($user['id'], $tenantId, $ipAddress, $userAgent);

            Logger::info("Login bem-sucedido", [
                'user_id' => $user['id'],
                'email' => $email,
                'tenant_id' => $tenantId
            ]);

            // Retorna dados do usuário e token
            Flight::json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                        'role' => $user['role'] ?? 'viewer'
                    ],
                    'tenant' => [
                        'id' => $tenant['id'],
                        'name' => $tenant['name']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao fazer login", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            http_response_code(500);
            Flight::json([
                'error' => 'Erro interno',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Ocorreu um erro ao processar o login'
            ], 500);
        }
    }

    /**
     * Logout
     * POST /v1/auth/logout
     */
    public function logout(): void
    {
        try {
            $sessionId = $this->getSessionId();

            if ($sessionId) {
                $this->sessionModel->deleteSession($sessionId);
                Logger::info("Logout realizado", ['session_id' => substr($sessionId, 0, 20) . '...']);
            }

            Flight::json([
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao fazer logout", [
                'error' => $e->getMessage()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro interno',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Ocorreu um erro ao processar o logout'
            ]));
        }
    }

    /**
     * Verifica sessão atual
     * GET /v1/auth/me
     */
    public function me(): void
    {
        try {
            $sessionId = $this->getSessionId();

            if (!$sessionId) {
                Flight::halt(401, json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Token de sessão não fornecido'
                ]));
                return;
            }

            $session = $this->sessionModel->validate($sessionId);

            if (!$session) {
                Flight::halt(401, json_encode([
                    'error' => 'Sessão inválida',
                    'message' => 'Sessão inválida ou expirada. Faça login novamente.'
                ]));
                return;
            }

            Flight::json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => (int)$session['user_id'],
                        'email' => $session['email'],
                        'name' => $session['name'],
                        'role' => $session['role'] ?? 'viewer'
                    ],
                    'tenant' => [
                        'id' => (int)$session['tenant_id'],
                        'name' => $session['tenant_name']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao verificar sessão", [
                'error' => $e->getMessage()
            ]);
            Flight::halt(500, json_encode([
                'error' => 'Erro interno',
                'message' => Config::isDevelopment() ? $e->getMessage() : 'Ocorreu um erro ao verificar a sessão'
            ]));
        }
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
}


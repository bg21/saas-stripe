<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;
use App\Services\Logger;
use App\Middleware\LoginRateLimitMiddleware;
use Flight;
use Config;

/**
 * Controller para autenticação de usuários
 * 
 * Gerencia login, logout e verificação de sessão
 */
class AuthController
{
    private User $userModel;
    private UserSession $sessionModel;
    private Tenant $tenantModel;
    private LoginRateLimitMiddleware $loginRateLimit;

    public function __construct()
    {
        $this->userModel = new User();
        $this->sessionModel = new UserSession();
        $this->tenantModel = new Tenant();
        
        // Inicializa rate limiter para login
        $rateLimiterService = new \App\Services\RateLimiterService();
        $this->loginRateLimit = new LoginRateLimitMiddleware($rateLimiterService);
    }

    /**
     * Login de usuário
     * POST /v1/auth/login
     * Body: { "email": "...", "password": "...", "tenant_id": 1 }
     */
    public function login(): void
    {
        try {
            // Verifica rate limit ANTES de processar qualquer coisa
            if (!$this->loginRateLimit->check()) {
                return; // Resposta já foi enviada pelo middleware
            }
            
            // Obtém dados do request
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError(400, 'Dados inválidos', 'JSON inválido no corpo da requisição');
                return;
            }
            
            $data = $data ?? [];
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $tenantId = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;

            // Validação robusta de entrada
            $validationErrors = $this->validateLoginInput($email, $password, $tenantId);
            if (!empty($validationErrors)) {
                $this->sendError(400, 'Dados inválidos', 'Por favor, verifique os dados informados', ['errors' => $validationErrors]);
                return;
            }

            // Busca usuário
            $user = $this->userModel->findByEmailAndTenant($email, $tenantId);

            if (!$user) {
                // Registra tentativa falha para rate limiting
                $this->loginRateLimit->recordFailedAttempt($email);
                
                Logger::warning("Tentativa de login com email não encontrado", [
                    'email' => $email,
                    'tenant_id' => $tenantId,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                // Não revela se o email existe ou não (segurança)
                $this->sendError(401, 'Credenciais inválidas', 'Email ou senha incorretos');
                return;
            }

            // Verifica senha
            if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
                // Registra tentativa falha para rate limiting
                $this->loginRateLimit->recordFailedAttempt($email);
                
                Logger::warning("Tentativa de login com senha incorreta", [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                // Não revela se o email existe ou não (segurança)
                $this->sendError(401, 'Credenciais inválidas', 'Email ou senha incorretos');
                return;
            }

            // Verifica status do usuário
            if ($user['status'] !== 'active') {
                Logger::warning("Tentativa de login com usuário inativo", [
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                $this->sendError(403, 'Usuário inativo', 'Sua conta está inativa. Entre em contato com o administrador.');
                return;
            }

            // Verifica tenant
            $tenant = $this->tenantModel->findById($tenantId);
            if (!$tenant) {
                Logger::warning("Tentativa de login com tenant inexistente", [
                    'tenant_id' => $tenantId,
                    'email' => $email
                ]);
                $this->sendError(403, 'Tenant inválido', 'O tenant informado não existe.');
                return;
            }
            
            if ($tenant['status'] !== 'active') {
                Logger::warning("Tentativa de login com tenant inativo", [
                    'tenant_id' => $tenantId,
                    'email' => $email
                ]);
                $this->sendError(403, 'Tenant inativo', 'O tenant está inativo. Entre em contato com o suporte.');
                return;
            }

            // Cria sessão
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $sessionId = $this->sessionModel->create($user['id'], $tenantId, $ipAddress, $userAgent);

            Logger::info("Login bem-sucedido", [
                'user_id' => $user['id'],
                'email' => $email,
                'tenant_id' => $tenantId,
                'ip' => $ipAddress
            ]);

            // Retorna dados do usuário e token
            Flight::json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'user' => [
                        'id' => (int)$user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                        'role' => $user['role'] ?? 'viewer'
                    ],
                    'tenant' => [
                        'id' => (int)$tenant['id'],
                        'name' => $tenant['name']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao fazer login", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendError(500, 'Erro interno', Config::isDevelopment() ? $e->getMessage() : 'Ocorreu um erro ao processar o login');
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
                $deleted = $this->sessionModel->deleteSession($sessionId);
                if ($deleted) {
                    Logger::info("Logout realizado", [
                        'session_id' => substr($sessionId, 0, 20) . '...',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                }
            }

            Flight::json([
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao fazer logout", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendError(500, 'Erro interno', Config::isDevelopment() ? $e->getMessage() : 'Ocorreu um erro ao processar o logout');
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
                $this->sendError(401, 'Não autenticado', 'Token de sessão não fornecido');
                return;
            }

            $session = $this->sessionModel->validate($sessionId);

            if (!$session) {
                $this->sendError(401, 'Sessão inválida', 'Sessão inválida ou expirada. Faça login novamente.');
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendError(500, 'Erro interno', Config::isDevelopment() ? $e->getMessage() : 'Ocorreu um erro ao verificar a sessão');
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
    
    /**
     * Valida dados de entrada do login
     * 
     * @param string $email Email do usuário
     * @param string $password Senha do usuário
     * @param int $tenantId ID do tenant
     * @return array Array de erros (vazio se válido)
     */
    private function validateLoginInput(string $email, string $password, int $tenantId): array
    {
        $errors = [];
        
        // Valida email
        if (empty($email)) {
            $errors['email'] = 'Email é obrigatório';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        } elseif (strlen($email) > 255) {
            $errors['email'] = 'Email muito longo (máximo 255 caracteres)';
        }
        
        // Valida senha
        if (empty($password)) {
            $errors['password'] = 'Senha é obrigatória';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Senha deve ter no mínimo 6 caracteres';
        } elseif (strlen($password) > 128) {
            $errors['password'] = 'Senha muito longa (máximo 128 caracteres)';
        }
        
        // Valida tenant_id
        if (empty($tenantId) || $tenantId <= 0) {
            $errors['tenant_id'] = 'Tenant ID é obrigatório e deve ser um número positivo';
        }
        
        return $errors;
    }
    
    /**
     * Envia resposta de erro padronizada
     * 
     * @param int $statusCode Código HTTP
     * @param string $error Tipo do erro
     * @param string $message Mensagem amigável
     * @param array $extra Dados extras (opcional)
     */
    private function sendError(int $statusCode, string $error, string $message, array $extra = []): void
    {
        $response = [
            'error' => $error,
            'message' => $message
        ];
        
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }
        
        Flight::json($response, $statusCode);
        Flight::stop();
    }
}

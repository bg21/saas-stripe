<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;
use App\Services\Logger;
use App\Services\AnomalyDetectionService;
use App\Middleware\LoginRateLimitMiddleware;
use App\Utils\Validator;
use App\Utils\ErrorHandler;
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
    private AnomalyDetectionService $anomalyDetection;

    public function __construct()
    {
        $this->userModel = new User();
        $this->sessionModel = new UserSession();
        $this->tenantModel = new Tenant();
        
        // Inicializa rate limiter para login
        $rateLimiterService = new \App\Services\RateLimiterService();
        $this->loginRateLimit = new LoginRateLimitMiddleware($rateLimiterService);
        
        // Inicializa detecção de anomalias
        $this->anomalyDetection = new AnomalyDetectionService();
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
            // ✅ OTIMIZAÇÃO: Usa RequestCache para evitar múltiplas leituras
            $data = \App\Utils\RequestCache::getJsonInput();
            
            // ✅ SEGURANÇA: Valida se JSON foi decodificado corretamente
            if ($data === null) {
                // Verifica se houve erro no JSON (RequestCache já valida, mas verifica novamente)
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->sendError(400, 'Dados inválidos', 'JSON inválido no corpo da requisição: ' . json_last_error_msg());
                    return;
                }
                // Se não há dados mas JSON é válido (corpo vazio), inicializa array vazio
                $data = [];
            }
            
            // Validação rigorosa usando Validator
            $validationErrors = Validator::validateLogin($data);
            if (!empty($validationErrors)) {
                $this->sendError(400, 'Dados inválidos', 'Por favor, verifique os dados informados', ['errors' => $validationErrors]);
                return;
            }
            
            // Sanitiza após validação
            $email = trim($data['email']);
            $password = $data['password'];
            // ✅ CORREÇÃO: Cast seguro de tenant_id usando filter_var
            $tenantId = filter_var($data['tenant_id'], FILTER_VALIDATE_INT);
            if ($tenantId === false || $tenantId <= 0) {
                $this->sendError(400, 'Dados inválidos', 'tenant_id deve ser um número inteiro positivo');
                return;
            }
            
            // Identificador para detecção de anomalias (email + IP)
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $identifier = $email . '_' . $ip;
            
            // Verifica se está bloqueado
            $blockStatus = $this->anomalyDetection->isBlocked($identifier);
            if ($blockStatus['blocked']) {
                $blockedUntil = date('Y-m-d H:i:s', $blockStatus['blocked_until']);
                Logger::warning("Tentativa de login bloqueada", [
                    'email' => $email,
                    'ip' => $ip,
                    'blocked_until' => $blockedUntil,
                    'reason' => $blockStatus['reason']
                ]);
                
                $this->sendError(429, 'Acesso temporariamente bloqueado', 
                    "Muitas tentativas falhadas. Acesso bloqueado até {$blockedUntil}. " .
                    "Por favor, tente novamente mais tarde.");
                return;
            }

            // Busca usuário
            $user = $this->userModel->findByEmailAndTenant($email, $tenantId);

            if (!$user) {
                // Registra tentativa falha para rate limiting
                $this->loginRateLimit->recordFailedAttempt($email);
                
                // Registra tentativa falha para detecção de anomalias
                $anomalyResult = $this->anomalyDetection->recordFailedAttempt($identifier, 'login_failed', [
                    'email' => $email,
                    'tenant_id' => $tenantId
                ]);
                
                Logger::warning("Tentativa de login com email não encontrado", [
                    'email' => $email,
                    'tenant_id' => $tenantId,
                    'ip' => $ip,
                    'remaining_attempts' => $anomalyResult['remaining_attempts']
                ]);
                
                // Não revela se o email existe ou não (segurança)
                $message = 'Email ou senha incorretos';
                if ($anomalyResult['remaining_attempts'] < 3) {
                    $message .= ". Restam {$anomalyResult['remaining_attempts']} tentativas antes do bloqueio.";
                }
                
                $this->sendError(401, 'Credenciais inválidas', $message);
                return;
            }

            // Verifica senha
            if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
                // Registra tentativa falha para rate limiting
                $this->loginRateLimit->recordFailedAttempt($email);
                
                // Registra tentativa falha para detecção de anomalias
                $anomalyResult = $this->anomalyDetection->recordFailedAttempt($identifier, 'login_failed', [
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                
                Logger::warning("Tentativa de login com senha incorreta", [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $ip,
                    'remaining_attempts' => $anomalyResult['remaining_attempts']
                ]);
                
                // Não revela se o email existe ou não (segurança)
                $message = 'Email ou senha incorretos';
                if ($anomalyResult['remaining_attempts'] < 3) {
                    $message .= ". Restam {$anomalyResult['remaining_attempts']} tentativas antes do bloqueio.";
                }
                
                $this->sendError(401, 'Credenciais inválidas', $message);
                return;
            }
            
            // Login bem-sucedido - registra sucesso e reseta contadores
            $this->anomalyDetection->recordSuccessfulLogin($identifier);

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
            $response = ErrorHandler::prepareErrorResponse($e, 'Ocorreu um erro ao processar o login', 'LOGIN_ERROR');
            $this->sendError(500, 'Erro interno', $response['message'], $response);
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
            $response = ErrorHandler::prepareErrorResponse($e, 'Ocorreu um erro ao processar o logout', 'LOGOUT_ERROR');
            $this->sendError(500, 'Erro interno', $response['message'], $response);
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
            $response = ErrorHandler::prepareErrorResponse($e, 'Ocorreu um erro ao verificar a sessão', 'SESSION_VERIFY_ERROR');
            $this->sendError(500, 'Erro interno', $response['message'], $response);
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

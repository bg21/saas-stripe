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
use App\Utils\ResponseHelper;
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
                    ResponseHelper::sendInvalidJsonError(['action' => 'login']);
                    return;
                }
                // Se não há dados mas JSON é válido (corpo vazio), inicializa array vazio
                $data = [];
            }
            
            // Validação rigorosa usando Validator
            $validationErrors = Validator::validateLogin($data);
            if (!empty($validationErrors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $validationErrors,
                    ['action' => 'login']
                );
                return;
            }
            
            // Sanitiza após validação
            $email = trim($data['email']);
            $password = $data['password'];
            
            // ✅ NOVO: Aceita tenant_slug OU tenant_id
            $tenantId = null;
            if (isset($data['tenant_slug']) && !empty($data['tenant_slug'])) {
                // Busca tenant pelo slug
                $tenant = $this->tenantModel->findBySlug(trim($data['tenant_slug']));
                if (!$tenant) {
                    ResponseHelper::sendValidationError(
                        'Clínica não encontrada',
                        ['tenant_slug' => 'Clínica não encontrada. Verifique o slug informado.'],
                        ['action' => 'login']
                    );
                    return;
                }
                if ($tenant['status'] !== 'active') {
                    ResponseHelper::sendForbiddenError('A clínica está inativa. Entre em contato com o administrador.', [
                        'action' => 'login'
                    ]);
                    return;
                }
                $tenantId = (int)$tenant['id'];
            } elseif (isset($data['tenant_id'])) {
                // Usa tenant_id (compatibilidade com versão anterior)
                $tenantId = filter_var($data['tenant_id'], FILTER_VALIDATE_INT);
                if ($tenantId === false || $tenantId <= 0) {
                    ResponseHelper::sendValidationError(
                        'tenant_id deve ser um número inteiro positivo',
                        ['tenant_id' => 'Deve ser um número inteiro positivo'],
                        ['action' => 'login']
                    );
                    return;
                }
            } else {
                ResponseHelper::sendValidationError(
                    'tenant_id ou tenant_slug é obrigatório',
                    ['tenant_id' => 'Obrigatório (ou forneça tenant_slug)', 'tenant_slug' => 'Obrigatório (ou forneça tenant_id)'],
                    ['action' => 'login']
                );
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
                
                ResponseHelper::sendError(
                    429,
                    'Acesso temporariamente bloqueado',
                    "Muitas tentativas falhadas. Acesso bloqueado até {$blockedUntil}. Por favor, tente novamente mais tarde.",
                    'TOO_MANY_ATTEMPTS',
                    [],
                    ['action' => 'login', 'email' => $email, 'ip' => $ip, 'blocked_until' => $blockedUntil]
                );
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
                
                ResponseHelper::sendUnauthorizedError($message, [
                    'action' => 'login',
                    'email' => $email,
                    'remaining_attempts' => $anomalyResult['remaining_attempts']
                ]);
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
                
                ResponseHelper::sendUnauthorizedError($message, [
                    'action' => 'login',
                    'user_id' => $user['id'],
                    'email' => $email,
                    'remaining_attempts' => $anomalyResult['remaining_attempts']
                ]);
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
                ResponseHelper::sendForbiddenError('Sua conta está inativa. Entre em contato com o administrador.', [
                    'action' => 'login',
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                return;
            }

            // Verifica tenant
            $tenant = $this->tenantModel->findById($tenantId);
            if (!$tenant) {
                Logger::warning("Tentativa de login com tenant inexistente", [
                    'tenant_id' => $tenantId,
                    'email' => $email
                ]);
                ResponseHelper::sendForbiddenError('O tenant informado não existe.', [
                    'action' => 'login',
                    'tenant_id' => $tenantId,
                    'email' => $email
                ]);
                return;
            }
            
            if ($tenant['status'] !== 'active') {
                Logger::warning("Tentativa de login com tenant inativo", [
                    'tenant_id' => $tenantId,
                    'email' => $email
                ]);
                ResponseHelper::sendForbiddenError('O tenant está inativo. Entre em contato com o suporte.', [
                    'action' => 'login',
                    'tenant_id' => $tenantId,
                    'email' => $email
                ]);
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
            ResponseHelper::sendSuccess([
                'session_id' => $sessionId,
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role'] ?? 'viewer'
                ],
                'tenant' => [
                    'id' => (int)$tenant['id'],
                    'name' => $tenant['name'],
                    'slug' => $tenant['slug'] ?? null
                ]
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Ocorreu um erro ao processar o login',
                'LOGIN_ERROR',
                ['action' => 'login']
            );
        }
    }

    /**
     * Registro de tenant e primeiro usuário (dono da clínica)
     * POST /v1/auth/register
     * 
     * Body JSON:
     * {
     *   "clinic_name": "Cão que Mia",
     *   "clinic_slug": "cao-que-mia",  // opcional, será gerado automaticamente se não fornecido
     *   "email": "admin@clinica.com",
     *   "password": "senha123",
     *   "name": "Nome do Dono"  // opcional
     * }
     */
    public function register(): void
    {
        try {
            // Obtém dados do request
            $data = \App\Utils\RequestCache::getJsonInput();
            
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'register']);
                    return;
                }
                $data = [];
            }
            
            // Validação usando Validator
            $validationErrors = Validator::validateRegister($data);
            if (!empty($validationErrors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $validationErrors,
                    ['action' => 'register']
                );
                return;
            }
            
            // Sanitiza dados
            $clinicName = trim($data['clinic_name']);
            $clinicSlug = isset($data['clinic_slug']) && !empty($data['clinic_slug']) 
                ? trim($data['clinic_slug']) 
                : null;
            $email = trim($data['email']);
            $password = $data['password'];
            $name = isset($data['name']) ? trim($data['name']) : null;
            
            // Verifica se email já existe em qualquer tenant
            $existingUser = $this->userModel->findBy('email', $email);
            if ($existingUser) {
                ResponseHelper::sendError(
                    409,
                    'Email já cadastrado',
                    'Este email já está cadastrado no sistema',
                    'EMAIL_ALREADY_EXISTS',
                    [],
                    ['action' => 'register', 'email' => $email]
                );
                return;
            }
            
            // Verifica se slug já existe (se fornecido)
            if ($clinicSlug !== null) {
                if (!\App\Utils\SlugHelper::isValid($clinicSlug)) {
                    ResponseHelper::sendValidationError(
                        'Slug inválido',
                        ['clinic_slug' => 'Formato inválido. Use apenas letras minúsculas, números e hífens'],
                        ['action' => 'register']
                    );
                    return;
                }
                
                if ($this->tenantModel->slugExists($clinicSlug)) {
                    ResponseHelper::sendError(
                        409,
                        'Slug já existe',
                        'Este slug já está em uso. Escolha outro ou deixe em branco para gerar automaticamente.',
                        'SLUG_ALREADY_EXISTS',
                        [],
                        ['action' => 'register', 'slug' => $clinicSlug]
                    );
                    return;
                }
            }
            
            // ✅ Usa transação para garantir atomicidade
            $db = \App\Utils\Database::getInstance();
            try {
                $db->beginTransaction();
                
                // 1. Cria tenant
                $tenantId = $this->tenantModel->create($clinicName, $clinicSlug);
                
                // 2. Cria usuário admin vinculado ao tenant
                $userId = $this->userModel->create(
                    $tenantId,
                    $email,
                    $password,
                    $name,
                    'admin' // Primeiro usuário sempre é admin
                );
                
                // 3. Cria sessão para o usuário recém-criado
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $sessionId = $this->sessionModel->create($userId, $tenantId, $ipAddress, $userAgent);
                
                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            
            // Busca dados criados
            $tenant = $this->tenantModel->findById($tenantId);
            $user = $this->userModel->findById($userId);
            
            Logger::info("Registro de tenant e usuário realizado", [
                'tenant_id' => $tenantId,
                'tenant_slug' => $tenant['slug'] ?? null,
                'user_id' => $userId,
                'email' => $email,
                'ip' => $ipAddress
            ]);
            
            // Retorna dados do tenant, usuário e sessão
            ResponseHelper::sendCreated([
                'session_id' => $sessionId,
                'tenant' => [
                    'id' => (int)$tenant['id'],
                    'name' => $tenant['name'],
                    'slug' => $tenant['slug'] ?? null
                ],
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role'] ?? 'admin'
                ]
            ], 'Registro realizado com sucesso. Você já está logado!');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao realizar registro',
                'REGISTER_ERROR',
                ['action' => 'register']
            );
        }
    }
    
    /**
     * Registro de funcionário em tenant existente
     * POST /v1/auth/register-employee
     * 
     * Body JSON:
     * {
     *   "tenant_slug": "cao-que-mia",
     *   "email": "funcionario@clinica.com",
     *   "password": "senha123",
     *   "name": "Nome do Funcionário"  // opcional
     * }
     */
    public function registerEmployee(): void
    {
        try {
            // Obtém dados do request
            $data = \App\Utils\RequestCache::getJsonInput();
            
            if ($data === null) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ResponseHelper::sendInvalidJsonError(['action' => 'register_employee']);
                    return;
                }
                $data = [];
            }
            
            // Validação básica
            $errors = [];
            if (!isset($data['tenant_slug']) || empty(trim($data['tenant_slug']))) {
                $errors['tenant_slug'] = 'Obrigatório';
            }
            if (!isset($data['email']) || empty(trim($data['email']))) {
                $errors['email'] = 'Obrigatório';
            } elseif (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Formato de email inválido';
            }
            if (!isset($data['password']) || empty($data['password'])) {
                $errors['password'] = 'Obrigatório';
            } else {
                $passwordError = Validator::validatePasswordStrength($data['password']);
                if ($passwordError) {
                    $errors['password'] = $passwordError;
                }
            }
            
            if (!empty($errors)) {
                ResponseHelper::sendValidationError(
                    'Por favor, verifique os dados informados',
                    $errors,
                    ['action' => 'register_employee']
                );
                return;
            }
            
            // Sanitiza dados
            $tenantSlug = trim($data['tenant_slug']);
            $email = trim($data['email']);
            $password = $data['password'];
            $name = isset($data['name']) ? trim($data['name']) : null;
            
            // Busca tenant pelo slug
            $tenant = $this->tenantModel->findBySlug($tenantSlug);
            if (!$tenant) {
                ResponseHelper::sendNotFoundError('Clínica não encontrada. Verifique o slug informado.', [
                    'action' => 'register_employee',
                    'tenant_slug' => $tenantSlug
                ]);
                return;
            }
            
            if ($tenant['status'] !== 'active') {
                ResponseHelper::sendForbiddenError('A clínica está inativa. Entre em contato com o administrador.', [
                    'action' => 'register_employee'
                ]);
                return;
            }
            
            $tenantId = (int)$tenant['id'];
            
            // Verifica se email já existe no tenant
            $existingUser = $this->userModel->findByEmailAndTenant($email, $tenantId);
            if ($existingUser) {
                ResponseHelper::sendError(
                    409,
                    'Email já cadastrado',
                    'Este email já está cadastrado nesta clínica',
                    'EMAIL_ALREADY_EXISTS',
                    [],
                    ['action' => 'register_employee', 'tenant_id' => $tenantId, 'email' => $email]
                );
                return;
            }
            
            // Cria usuário (role padrão: viewer)
            $userId = $this->userModel->create(
                $tenantId,
                $email,
                $password,
                $name,
                'viewer' // Funcionários começam como viewer (admin pode promover depois)
            );
            
            // Busca usuário criado
            $user = $this->userModel->findById($userId);
            
            Logger::info("Registro de funcionário realizado", [
                'tenant_id' => $tenantId,
                'tenant_slug' => $tenantSlug,
                'user_id' => $userId,
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
            ResponseHelper::sendCreated([
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role'] ?? 'viewer'
                ],
                'tenant' => [
                    'id' => (int)$tenant['id'],
                    'name' => $tenant['name'],
                    'slug' => $tenant['slug'] ?? null
                ]
            ], 'Funcionário registrado com sucesso. Faça login para acessar o sistema.');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao registrar funcionário',
                'REGISTER_EMPLOYEE_ERROR',
                ['action' => 'register_employee']
            );
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

            ResponseHelper::sendSuccess(null, 200, 'Logout realizado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Ocorreu um erro ao processar o logout',
                'LOGOUT_ERROR',
                ['action' => 'logout']
            );
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
                ResponseHelper::sendUnauthorizedError('Token de sessão não fornecido', ['action' => 'verify_session']);
                return;
            }

            $session = $this->sessionModel->validate($sessionId);

            if (!$session) {
                ResponseHelper::sendUnauthorizedError('Sessão inválida ou expirada. Faça login novamente.', ['action' => 'verify_session']);
                return;
            }

            ResponseHelper::sendSuccess([
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
            ]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Ocorreu um erro ao verificar a sessão',
                'SESSION_VERIFY_ERROR',
                ['action' => 'verify_session']
            );
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
    
}

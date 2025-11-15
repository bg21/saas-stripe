# 🎨 Dashboard Separado com Controle de Permissões Individuais

## ✅ Por que Dashboard Separado é Melhor?

**Você está certo!** O FlightPHP **NÃO precisa** estar integrado ao dashboard. Na verdade, o FlightPHP serve apenas como **API REST**, e o dashboard pode ser **completamente separado**.

### ❌ Por que eu disse que precisava ser integrado?

Eu estava pensando em **simplicidade de desenvolvimento**, mas isso não é uma limitação técnica. Vou explicar:

---

## 🏗️ Arquitetura: Dashboard Separado

```
┌─────────────────────┐         HTTP/REST API         ┌─────────────────────┐
│                     │  ──────────────────────────> │                     │
│  Dashboard          │                              │  Sistema de         │
│  (HTML/CSS/JS)      │  <────────────────────────── │  Pagamentos         │
│  ou                 │         JSON Response        │  (FlightPHP)         │
│  (React/Vue/etc)    │                              │  (Apenas API)       │
│                     │                              │                     │
└─────────────────────┘                              └─────────────────────┘
     ↑                                                       ↑
     │                                                       │
     │                                                       │
     └─────────────────── Mesmo Banco de Dados ──────────────┘
```

### Vantagens do Dashboard Separado:

1. ✅ **Separação de responsabilidades**
   - API foca apenas em lógica de negócio
   - Dashboard foca apenas em apresentação

2. ✅ **Escalabilidade independente**
   - Pode escalar API e Dashboard separadamente
   - Pode colocar Dashboard em CDN

3. ✅ **Tecnologia independente**
   - Dashboard pode ser React, Vue, HTML puro, Laravel, etc.
   - API continua sendo FlightPHP

4. ✅ **Deploy independente**
   - Pode atualizar Dashboard sem afetar API
   - Pode ter múltiplos Dashboards (admin, cliente, etc.)

5. ✅ **Controle de permissões individual**
   - Cada usuário tem suas próprias credenciais
   - Permissões granulares por funcionalidade

---

## 🔐 Sistema de Autenticação e Permissões

### Problema Atual

O sistema atual autentica apenas por **API Key do Tenant**, não por **usuário individual**. Isso significa:
- ❌ Todos que têm a API Key veem os mesmos dados
- ❌ Não há controle de permissões individuais
- ❌ Não há rastreamento de quem fez o quê

### Solução: Autenticação de Usuários + Permissões

Precisamos criar:

1. **Sistema de Login de Usuários**
   - Endpoint: `POST /v1/auth/login`
   - Retorna: Token JWT ou Session ID

2. **Sistema de Permissões**
   - Tabela `user_permissions` ou `roles`
   - Middleware que verifica permissões

3. **Endpoints Protegidos por Usuário**
   - Cada endpoint verifica se o usuário tem permissão
   - Filtra dados por usuário (se necessário)

---

## 📊 Estrutura de Banco de Dados para Permissões

### Opção 1: Permissões Simples (Recomendado para começar)

```sql
-- Adicionar coluna de role na tabela users
ALTER TABLE users ADD COLUMN role ENUM('admin', 'viewer', 'editor') DEFAULT 'viewer';

-- Tabela de permissões específicas (opcional, para controle mais granular)
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission VARCHAR(100) NOT NULL,
    granted BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Opção 2: Sistema de Roles Completo (Mais flexível)

```sql
-- Tabela de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de permissões
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relação role-permission
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relação user-role
ALTER TABLE users ADD COLUMN role_id INT;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;
```

**Para começar, vamos com a Opção 1 (mais simples).**

---

## 🔧 Implementação: Sistema de Autenticação de Usuários

### 1. Migration: Adicionar Role e Token

```php
<?php
// db/migrations/XXXXXX_add_user_auth.php

use Phinx\Migration\AbstractMigration;

class AddUserAuth extends AbstractMigration
{
    public function change()
    {
        // Adiciona coluna role
        $table = $this->table('users');
        $table->addColumn('role', 'enum', [
            'values' => ['admin', 'viewer', 'editor'],
            'default' => 'viewer',
            'after' => 'status'
        ])->update();

        // Tabela de tokens de sessão
        $sessions = $this->table('user_sessions', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        $sessions->addColumn('id', 'string', ['limit' => 64])
                 ->addColumn('user_id', 'integer')
                 ->addColumn('tenant_id', 'integer')
                 ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                 ->addColumn('user_agent', 'text', ['null' => true])
                 ->addColumn('expires_at', 'datetime')
                 ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                 ->addIndex(['user_id'])
                 ->addIndex(['tenant_id'])
                 ->addIndex(['id'], ['unique' => true])
                 ->addIndex(['expires_at'])
                 ->create();

        // Tabela de permissões (opcional, para controle granular)
        $permissions = $this->table('user_permissions', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        $permissions->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                   ->addColumn('user_id', 'integer', ['signed' => false])
                   ->addColumn('permission', 'string', ['limit' => 100])
                   ->addColumn('granted', 'boolean', ['default' => true])
                   ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                   ->addIndex(['user_id'])
                   ->addIndex(['user_id', 'permission'], ['unique' => true])
                   ->create();
    }
}
```

### 2. Model: UserSession

```php
<?php
// App/Models/UserSession.php

namespace App\Models;

class UserSession extends BaseModel
{
    protected string $table = 'user_sessions';

    /**
     * Cria uma nova sessão
     */
    public function create(int $userId, int $tenantId, ?string $ipAddress = null, ?string $userAgent = null): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt
        ]);

        return $sessionId;
    }

    /**
     * Valida sessão
     */
    public function validate(string $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.email, u.name, u.role, u.status as user_status, t.status as tenant_status
             FROM {$this->table} s
             INNER JOIN users u ON s.user_id = u.id
             INNER JOIN tenants t ON s.tenant_id = t.id
             WHERE s.id = :session_id 
             AND s.expires_at > NOW()
             AND u.status = 'active'
             AND t.status = 'active'"
        );
        $stmt->execute(['session_id' => $sessionId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Remove sessão
     */
    public function delete(string $sessionId): void
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :session_id");
        $stmt->execute(['session_id' => $sessionId]);
    }

    /**
     * Limpa sessões expiradas
     */
    public function cleanExpired(): void
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE expires_at < NOW()");
        $stmt->execute();
    }
}
```

### 3. Model: UserPermission

```php
<?php
// App/Models/UserPermission.php

namespace App\Models;

class UserPermission extends BaseModel
{
    protected string $table = 'user_permissions';

    /**
     * Verifica se usuário tem permissão
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        // Primeiro verifica role do usuário
        $userModel = new User();
        $user = $userModel->findById($userId);
        
        if (!$user) {
            return false;
        }

        // Admins têm todas as permissões
        if ($user['role'] === 'admin') {
            return true;
        }

        // Verifica permissão específica
        $stmt = $this->db->prepare(
            "SELECT granted FROM {$this->table} 
             WHERE user_id = :user_id AND permission = :permission"
        );
        $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
        $result = $stmt->fetch();

        return $result && $result['granted'] === 1;
    }

    /**
     * Concede permissão
     */
    public function grant(int $userId, string $permission): void
    {
        $this->insert([
            'user_id' => $userId,
            'permission' => $permission,
            'granted' => true
        ], true); // true = ON DUPLICATE KEY UPDATE
    }

    /**
     * Revoga permissão
     */
    public function revoke(int $userId, string $permission): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} 
             WHERE user_id = :user_id AND permission = :permission"
        );
        $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
    }
}
```

### 4. Controller: AuthController

```php
<?php
// App/Controllers/AuthController.php

namespace App\Controllers;

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;
use Flight;
use Config;

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
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $tenantId = (int)($data['tenant_id'] ?? 0);

        if (empty($email) || empty($password) || empty($tenantId)) {
            http_response_code(400);
            Flight::json(['error' => 'Email, senha e tenant_id são obrigatórios'], 400);
            return;
        }

        // Busca usuário
        $user = $this->userModel->findByEmailAndTenant($email, $tenantId);

        if (!$user) {
            http_response_code(401);
            Flight::json(['error' => 'Credenciais inválidas'], 401);
            return;
        }

        // Verifica senha
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            http_response_code(401);
            Flight::json(['error' => 'Credenciais inválidas'], 401);
            return;
        }

        // Verifica status
        if ($user['status'] !== 'active') {
            http_response_code(403);
            Flight::json(['error' => 'Usuário inativo'], 403);
            return;
        }

        // Verifica tenant
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant || $tenant['status'] !== 'active') {
            http_response_code(403);
            Flight::json(['error' => 'Tenant inativo'], 403);
            return;
        }

        // Cria sessão
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $sessionId = $this->sessionModel->create($user['id'], $tenantId, $ipAddress, $userAgent);

        // Retorna dados do usuário e token
        Flight::json([
            'success' => true,
            'data' => [
                'session_id' => $sessionId,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ],
                'tenant' => [
                    'id' => $tenant['id'],
                    'name' => $tenant['name']
                ]
            ]
        ]);
    }

    /**
     * Logout
     * POST /v1/auth/logout
     */
    public function logout(): void
    {
        $sessionId = $this->getSessionId();

        if ($sessionId) {
            $this->sessionModel->delete($sessionId);
        }

        Flight::json(['success' => true, 'message' => 'Logout realizado com sucesso']);
    }

    /**
     * Verifica sessão atual
     * GET /v1/auth/me
     */
    public function me(): void
    {
        $sessionId = $this->getSessionId();

        if (!$sessionId) {
            http_response_code(401);
            Flight::json(['error' => 'Não autenticado'], 401);
            return;
        }

        $session = $this->sessionModel->validate($sessionId);

        if (!$session) {
            http_response_code(401);
            Flight::json(['error' => 'Sessão inválida ou expirada'], 401);
            return;
        }

        Flight::json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $session['user_id'],
                    'email' => $session['email'],
                    'name' => $session['name'],
                    'role' => $session['role']
                ],
                'tenant' => [
                    'id' => $session['tenant_id']
                ]
            ]
        ]);
    }

    /**
     * Obtém session ID do header
     */
    private function getSessionId(): ?string
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
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
```

### 5. Middleware: UserAuthMiddleware

```php
<?php
// App/Middleware/UserAuthMiddleware.php

namespace App\Middleware;

use App\Models\UserSession;
use Flight;

class UserAuthMiddleware
{
    private UserSession $sessionModel;

    public function __construct()
    {
        $this->sessionModel = new UserSession();
    }

    /**
     * Valida autenticação de usuário
     */
    public function handle(): ?array
    {
        $sessionId = $this->getSessionId();

        if (!$sessionId) {
            return $this->unauthorized('Token de sessão não fornecido');
        }

        $session = $this->sessionModel->validate($sessionId);

        if (!$session) {
            return $this->unauthorized('Sessão inválida ou expirada');
        }

        // Injeta dados no Flight
        Flight::set('user_id', (int)$session['user_id']);
        Flight::set('user_role', $session['role']);
        Flight::set('tenant_id', (int)$session['tenant_id']);

        return [
            'user_id' => (int)$session['user_id'],
            'user_role' => $session['role'],
            'tenant_id' => (int)$session['tenant_id']
        ];
    }

    /**
     * Obtém session ID
     */
    private function getSessionId(): ?string
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$authHeader) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        return trim($authHeader);
    }

    private function unauthorized(string $message): array
    {
        return [
            'error' => true,
            'message' => $message,
            'code' => 401
        ];
    }
}
```

### 6. Middleware: PermissionMiddleware

```php
<?php
// App/Middleware/PermissionMiddleware.php

namespace App\Middleware;

use App\Models\UserPermission;
use Flight;

class PermissionMiddleware
{
    private UserPermission $permissionModel;

    public function __construct()
    {
        $this->permissionModel = new UserPermission();
    }

    /**
     * Verifica se usuário tem permissão
     */
    public function check(string $permission): bool
    {
        $userId = Flight::get('user_id');

        if (!$userId) {
            return false;
        }

        return $this->permissionModel->hasPermission($userId, $permission);
    }

    /**
     * Middleware que bloqueia se não tiver permissão
     */
    public function require(string $permission): ?array
    {
        if (!$this->check($permission)) {
            http_response_code(403);
            Flight::json([
                'error' => 'Acesso negado',
                'message' => "Você não tem permissão para: {$permission}"
            ], 403);
            Flight::stop();
            return null;
        }

        return ['allowed' => true];
    }
}
```

---

## 🎨 Dashboard Separado: Exemplo HTML

### Estrutura do Dashboard

```
dashboard/
├── index.html
├── login.html
├── css/
│   └── style.css
├── js/
│   ├── api.js          ← Cliente HTTP para API
│   ├── auth.js         ← Gerenciamento de sessão
│   └── dashboard.js     ← Lógica do dashboard
└── pages/
    ├── subscriptions.html
    ├── customers.html
    └── audit-logs.html
```

### Exemplo: api.js (Cliente HTTP)

```javascript
// dashboard/js/api.js

class PaymentsAPI {
    constructor(baseUrl) {
        this.baseUrl = baseUrl || 'http://localhost:8080';
        this.sessionId = localStorage.getItem('session_id');
    }

    setSession(sessionId) {
        this.sessionId = sessionId;
        localStorage.setItem('session_id', sessionId);
    }

    clearSession() {
        this.sessionId = null;
        localStorage.removeItem('session_id');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        if (this.sessionId) {
            headers['Authorization'] = `Bearer ${this.sessionId}`;
        }

        const config = {
            ...options,
            headers
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    // Sessão expirada, redireciona para login
                    this.clearSession();
                    window.location.href = '/login.html';
                    return;
                }
                throw new Error(data.message || 'Erro na requisição');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Métodos de autenticação
    async login(email, password, tenantId) {
        const response = await this.request('/v1/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password, tenant_id: tenantId })
        });
        
        if (response.success && response.data.session_id) {
            this.setSession(response.data.session_id);
        }
        
        return response;
    }

    async logout() {
        await this.request('/v1/auth/logout', { method: 'POST' });
        this.clearSession();
    }

    async getMe() {
        return this.request('/v1/auth/me');
    }

    // Métodos de dados
    async getSubscriptions() {
        return this.request('/v1/subscriptions');
    }

    async getCustomers() {
        return this.request('/v1/customers');
    }

    async getStats() {
        return this.request('/v1/stats');
    }

    async getAuditLogs() {
        return this.request('/v1/audit-logs');
    }
}

// Instância global
const api = new PaymentsAPI();
```

### Exemplo: login.html

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="js/api.js"></script>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">Login Dashboard</h3>
                        
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant ID</label>
                                <input type="number" class="form-control" id="tenant_id" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="password" required>
                            </div>
                            <div id="errorAlert" class="alert alert-danger d-none"></div>
                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const tenantId = parseInt(document.getElementById('tenant_id').value);
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorAlert = document.getElementById('errorAlert');

            try {
                const response = await api.login(email, password, tenantId);
                
                if (response.success) {
                    window.location.href = '/index.html';
                } else {
                    errorAlert.textContent = response.error || 'Erro ao fazer login';
                    errorAlert.classList.remove('d-none');
                }
            } catch (error) {
                errorAlert.textContent = error.message;
                errorAlert.classList.remove('d-none');
            }
        });
    </script>
</body>
</html>
```

---

## 🔧 Configurar Rotas no FlightPHP

No `public/index.php`, adicione:

```php
// ... código existente ...

// Auth Controller
$authController = new \App\Controllers\AuthController();

// Rotas de autenticação (públicas)
$app->route('POST /v1/auth/login', [$authController, 'login']);
$app->route('POST /v1/auth/logout', [$authController, 'logout']);
$app->route('GET /v1/auth/me', [$authController, 'me']);

// Middleware de autenticação de usuário (para rotas que precisam)
$userAuthMiddleware = new \App\Middleware\UserAuthMiddleware();
$permissionMiddleware = new \App\Middleware\PermissionMiddleware();

// Exemplo: Rota protegida por usuário
$app->before('start', function() use ($userAuthMiddleware) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Rotas que precisam de autenticação de usuário
    $userAuthRoutes = ['/v1/dashboard', '/v1/subscriptions', '/v1/customers'];
    
    if (in_array($requestUri, $userAuthRoutes) || strpos($requestUri, '/v1/dashboard/') === 0) {
        $result = $userAuthMiddleware->handle();
        
        if ($result && isset($result['error'])) {
            Flight::json(['error' => $result['message']], 401);
            Flight::stop();
            exit;
        }
    }
});

// Exemplo: Rota que precisa de permissão específica
$app->route('GET /v1/dashboard/subscriptions', function() use ($permissionMiddleware) {
    // Verifica permissão
    $permissionMiddleware->require('view_subscriptions');
    
    // Continua com a lógica...
    $subscriptionController = new \App\Controllers\SubscriptionController(...);
    $subscriptionController->list();
});
```

---

## 📋 Permissões Padrão

Defina permissões como:

- `view_subscriptions` - Ver assinaturas
- `create_subscriptions` - Criar assinaturas
- `cancel_subscriptions` - Cancelar assinaturas
- `view_customers` - Ver clientes
- `view_audit_logs` - Ver logs de auditoria
- `manage_users` - Gerenciar usuários
- `manage_permissions` - Gerenciar permissões

---

## ✅ Resumo

**Por que Dashboard Separado é melhor:**
- ✅ FlightPHP serve apenas como API
- ✅ Dashboard pode ser qualquer tecnologia
- ✅ Escalabilidade independente
- ✅ Controle de permissões individuais
- ✅ Cada usuário tem suas próprias credenciais

**O que implementamos:**
1. ✅ Sistema de autenticação de usuários (login/logout)
2. ✅ Sistema de sessões (tokens)
3. ✅ Sistema de permissões (roles + permissões específicas)
4. ✅ Middleware de autenticação de usuários
5. ✅ Middleware de verificação de permissões
6. ✅ Exemplo de dashboard separado (HTML/JS)

**Próximos passos:**
1. Criar migration para adicionar `role` e tabelas de sessão/permissões
2. Implementar os models e controllers
3. Configurar rotas no FlightPHP
4. Criar seu dashboard separado (HTML, React, Vue, etc.)
5. Pronto! 🎉


# 🎨 Dashboard com FlightPHP + HTML/CSS + Bootstrap

## ✅ Sim, é totalmente possível!

Você pode criar um dashboard/painel administrativo **integrado no mesmo projeto FlightPHP** usando **HTML/CSS puro + Bootstrap**.

---

## 🏗️ Arquitetura

Como você já usa **FlightPHP**, o dashboard fica **integrado no mesmo projeto**:

```
┌─────────────────────────────────┐
│  Sistema de Pagamentos          │
│  (FlightPHP)                    │
│                                 │
│  ├─ API REST (/v1/*)           │  ← Endpoints JSON
│  └─ Dashboard (/dashboard/*)    │  ← Views HTML
└─────────────────────────────────┘
```

**Vantagens:**
- ✅ Mesma autenticação
- ✅ Compartilha models e services
- ✅ Tudo em um lugar
- ✅ Fácil de manter

---

## 📁 Estrutura de Pastas

```
projeto/
├── App/
│   ├── Controllers/
│   │   ├── DashboardController.php  ← Novo controller
│   │   └── ...
│   ├── Models/
│   └── Services/
├── public/
│   ├── index.php
│   └── assets/              ← Novo: CSS, JS
│       ├── css/
│       │   └── dashboard.css
│       └── js/
│           └── dashboard.js
└── views/                    ← Novo: Templates HTML
    └── dashboard/
        ├── layout.php
        ├── index.php
        ├── subscriptions.php
        ├── customers.php
        └── audit-logs.php
```

---

## 🔧 Configuração do FlightPHP para Views

### 1. Adicionar Helper de Renderização

No `public/index.php`, adicione após a inicialização do FlightPHP:

```php
// ... código existente após $app = new Engine(); ...

// Configurar caminho das views
Flight::set('flight.views.path', __DIR__ . '/../views');

// Helper para renderizar views com layout
Flight::map('renderView', function($template, $data = [], $layout = 'dashboard/layout') {
    // Extrai variáveis para o escopo da view
    extract($data);
    
    // Captura conteúdo da view
    ob_start();
    $viewPath = __DIR__ . '/../views/' . $template . '.php';
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        echo "View não encontrada: $template";
    }
    $content = ob_get_clean();
    
    // Renderiza layout com conteúdo
    if ($layout) {
        $layoutPath = __DIR__ . '/../views/' . $layout . '.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            echo $content; // Fallback se layout não existir
        }
    } else {
        echo $content;
    }
});
```

---

## 🎯 Criando o Dashboard Controller

```php
<?php
// App/Controllers/DashboardController.php

namespace App\Controllers;

use App\Services\StripeService;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\AuditLog;
use Flight;
use Config;

class DashboardController
{
    private StripeService $stripeService;
    private Customer $customerModel;
    private Subscription $subscriptionModel;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->customerModel = new Customer();
        $this->subscriptionModel = new Subscription();
    }

    /**
     * Dashboard principal
     * GET /dashboard
     */
    public function index(): void
    {
        $this->checkAuth();
        
        $tenantId = Flight::get('tenant_id');
        
        // Busca estatísticas
        $stats = $this->getStats($tenantId);
        
        // Últimas assinaturas
        $subscriptions = $this->subscriptionModel->findByTenant($tenantId);
        $recentSubscriptions = array_slice($subscriptions, 0, 5);
        
        // Últimos clientes
        $customers = $this->customerModel->findByTenant($tenantId);
        $recentCustomers = array_slice($customers, 0, 5);
        
        Flight::renderView('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentSubscriptions' => $recentSubscriptions,
            'recentCustomers' => $recentCustomers
        ]);
    }

    /**
     * Lista de assinaturas
     * GET /dashboard/subscriptions
     */
    public function subscriptions(): void
    {
        $this->checkAuth();
        
        $tenantId = Flight::get('tenant_id');
        $subscriptions = $this->subscriptionModel->findByTenant($tenantId);
        
        Flight::renderView('dashboard/subscriptions', [
            'title' => 'Assinaturas',
            'subscriptions' => $subscriptions
        ]);
    }

    /**
     * Histórico de assinatura
     * GET /dashboard/subscriptions/:id/history
     */
    public function subscriptionHistory(string $id): void
    {
        $this->checkAuth();
        
        $tenantId = Flight::get('tenant_id');
        $subscription = $this->subscriptionModel->findById((int)$id);
        
        if (!$subscription || $subscription['tenant_id'] != $tenantId) {
            Flight::error(new \Exception('Assinatura não encontrada'), 404);
            return;
        }
        
        $historyModel = new SubscriptionHistory();
        $history = $historyModel->findBySubscription((int)$id, $tenantId, 100, 0);
        
        Flight::renderView('dashboard/subscription-history', [
            'title' => 'Histórico da Assinatura',
            'subscription' => $subscription,
            'history' => $history
        ]);
    }

    /**
     * Lista de clientes
     * GET /dashboard/customers
     */
    public function customers(): void
    {
        $this->checkAuth();
        
        $tenantId = Flight::get('tenant_id');
        $customers = $this->customerModel->findByTenant($tenantId);
        
        Flight::renderView('dashboard/customers', [
            'title' => 'Clientes',
            'customers' => $customers
        ]);
    }

    /**
     * Logs de auditoria
     * GET /dashboard/audit-logs
     */
    public function auditLogs(): void
    {
        $this->checkAuth();
        
        $tenantId = Flight::get('tenant_id');
        $isMaster = Flight::get('is_master') === true;
        
        $auditLogModel = new AuditLog();
        
        if ($isMaster) {
            $logs = $auditLogModel->findAllLogs([], 100, 0);
            $total = $auditLogModel->countAllLogs([]);
        } else {
            $logs = $auditLogModel->findByTenant($tenantId, [], 100, 0);
            $total = $auditLogModel->countByTenant($tenantId, []);
        }
        
        Flight::renderView('dashboard/audit-logs', [
            'title' => 'Logs de Auditoria',
            'logs' => $logs,
            'total' => $total
        ]);
    }

    /**
     * Calcula estatísticas
     */
    private function getStats(int $tenantId): array
    {
        $customers = $this->customerModel->findByTenant($tenantId);
        $subscriptions = $this->subscriptionModel->findByTenant($tenantId);
        
        $activeSubscriptions = 0;
        $mrr = 0;
        
        foreach ($subscriptions as $sub) {
            if ($sub['status'] === 'active') {
                $activeSubscriptions++;
                $mrr += (float)($sub['amount'] ?? 0);
            }
        }
        
        return [
            'total_customers' => count($customers),
            'total_subscriptions' => count($subscriptions),
            'active_subscriptions' => $activeSubscriptions,
            'mrr' => round($mrr, 2)
        ];
    }

    /**
     * Verifica autenticação
     */
    private function checkAuth(): void
    {
        $tenantId = Flight::get('tenant_id');
        
        if ($tenantId === null) {
            // Redireciona para login ou retorna erro
            Flight::json(['error' => 'Não autenticado'], 401);
            exit;
        }
    }
}
```

---

## 🎨 Criando as Views (HTML + Bootstrap)

### Layout Base

```php
<?php
// views/dashboard/layout.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard') ?> - Sistema de Pagamentos</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">
                <i class="bi bi-credit-card"></i> Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard">
                            <i class="bi bi-speedometer2"></i> Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard/subscriptions">
                            <i class="bi bi-receipt"></i> Assinaturas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard/customers">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard/audit-logs">
                            <i class="bi bi-journal-text"></i> Logs
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Conteúdo -->
    <div class="container-fluid mt-4">
        <?= $content ?? '' ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JS Customizado -->
    <script src="/assets/js/dashboard.js"></script>
</body>
</html>
```

### Dashboard Principal

```php
<?php
// views/dashboard/index.php
ob_start();
?>

<div class="row mb-4">
    <h1 class="h2">Dashboard</h1>
</div>

<!-- Cards de Estatísticas -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Total de Clientes</h6>
                        <h2 class="mb-0"><?= $stats['total_customers'] ?></h2>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Assinaturas Ativas</h6>
                        <h2 class="mb-0"><?= $stats['active_subscriptions'] ?></h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Total de Assinaturas</h6>
                        <h2 class="mb-0"><?= $stats['total_subscriptions'] ?></h2>
                    </div>
                    <i class="bi bi-receipt fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">MRR</h6>
                        <h2 class="mb-0">R$ <?= number_format($stats['mrr'], 2, ',', '.') ?></h2>
                    </div>
                    <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Últimas Assinaturas -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Últimas Assinaturas</h5>
                <a href="/dashboard/subscriptions" class="btn btn-sm btn-outline-primary">
                    Ver Todas
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recentSubscriptions)): ?>
                    <p class="text-muted mb-0">Nenhuma assinatura encontrada.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSubscriptions as $sub): ?>
                                <tr>
                                    <td>#<?= $sub['id'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $sub['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($sub['status']) ?>
                                        </span>
                                    </td>
                                    <td>R$ <?= number_format($sub['amount'], 2, ',', '.') ?></td>
                                    <td>
                                        <a href="/dashboard/subscriptions/<?= $sub['id'] ?>/history" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Últimos Clientes -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Últimos Clientes</h5>
                <a href="/dashboard/customers" class="btn btn-sm btn-outline-primary">
                    Ver Todos
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recentCustomers)): ?>
                    <p class="text-muted mb-0">Nenhum cliente encontrado.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentCustomers as $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($customer['name'] ?? 'Sem nome') ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                            </div>
                            <span class="badge bg-primary rounded-pill">
                                ID: <?= $customer['id'] ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
```

---

## 🔧 Configurar Rotas no FlightPHP

No `public/index.php`, adicione as rotas do dashboard (após as rotas da API):

```php
// ... código existente ...

// Dashboard Controller
$dashboardController = new \App\Controllers\DashboardController($stripeService);

// Rotas do Dashboard
// Nota: Dashboard também precisa de autenticação (via middleware existente)
$app->route('GET /dashboard', [$dashboardController, 'index']);
$app->route('GET /dashboard/subscriptions', [$dashboardController, 'subscriptions']);
$app->route('GET /dashboard/subscriptions/@id/history', [$dashboardController, 'subscriptionHistory']);
$app->route('GET /dashboard/customers', [$dashboardController, 'customers']);
$app->route('GET /dashboard/audit-logs', [$dashboardController, 'auditLogs']);
```

**Importante:** Adicione `/dashboard` nas rotas públicas se quiser criar uma página de login separada, ou mantenha a autenticação via API Key.

---

## 🎨 CSS Customizado (Opcional)

```css
/* public/assets/css/dashboard.css */

body {
    background-color: #f8f9fa;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
}

.navbar-brand {
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.badge {
    font-weight: 500;
}
```

---

## 🔐 Autenticação no Dashboard

O dashboard usa a **mesma autenticação da API**. O middleware `AuthMiddleware` já valida a API Key.

**Para acessar o dashboard:**
1. Envie a API Key no header: `Authorization: Bearer {api_key}`
2. Ou crie uma página de login que armazena a API Key em sessão

### Opção: Página de Login Simples

```php
<?php
// App/Controllers/DashboardController.php

public function login(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $apiKey = $_POST['api_key'] ?? '';
        
        // Valida API Key
        $tenantModel = new \App\Models\Tenant();
        $tenant = $tenantModel->findByApiKey($apiKey);
        
        if ($tenant && $tenant['status'] === 'active') {
            // Armazena em sessão
            session_start();
            $_SESSION['tenant_id'] = $tenant['id'];
            $_SESSION['api_key'] = $apiKey;
            
            Flight::redirect('/dashboard');
            return;
        }
        
        Flight::renderView('dashboard/login', [
            'title' => 'Login',
            'error' => 'API Key inválida'
        ], null);
        return;
    }
    
    Flight::renderView('dashboard/login', ['title' => 'Login'], null);
}
```

```php
<?php
// views/dashboard/login.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">
                            <i class="bi bi-credit-card"></i> Login Dashboard
                        </h3>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API Key</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="api_key" 
                                       name="api_key" 
                                       required
                                       placeholder="Digite sua API Key">
                                <small class="form-text text-muted">
                                    Use a API Key do seu tenant
                                </small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```

---

## ✅ Resumo

**Sim, você pode criar um dashboard!**

**Com FlightPHP + HTML/CSS + Bootstrap:**
- ✅ Dashboard integrado no mesmo projeto
- ✅ Usa os mesmos models e services
- ✅ Mesma autenticação da API
- ✅ Views simples em PHP
- ✅ Bootstrap para UI
- ✅ Fácil de manter e estender

**Próximos passos:**
1. Criar `App/Controllers/DashboardController.php`
2. Criar pasta `views/dashboard/`
3. Adicionar helper `renderView` no `public/index.php`
4. Adicionar rotas do dashboard
5. Criar views HTML com Bootstrap
6. Pronto! 🎉

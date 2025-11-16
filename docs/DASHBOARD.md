# 🎨 Dashboard Administrativo - Guia Completo

**Versão:** 1.0.3  
**Última Atualização:** 2025-01-16

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Opção 1: Dashboard Integrado (FlightPHP)](#opção-1-dashboard-integrado-flightphp)
4. [Opção 2: Dashboard Separado (Recomendado)](#opção-2-dashboard-separado-recomendado)
5. [Estrutura de Pastas](#estrutura-de-pastas)
6. [Autenticação](#autenticação)
7. [Verificação de Permissões](#verificação-de-permissões)
8. [Exemplos de Páginas](#exemplos-de-páginas)

---

## 🎯 Visão Geral

Você pode criar um dashboard administrativo de **duas formas**:

1. **Dashboard Integrado** - Dentro do mesmo projeto FlightPHP
2. **Dashboard Separado** - Projeto completamente separado (recomendado)

Ambas as opções funcionam perfeitamente. A escolha depende das suas necessidades.

---

## 🏗️ Arquitetura

### Opção 1: Dashboard Integrado

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

**Desvantagens:**
- ❌ Acopla front-end ao backend
- ❌ Mais difícil de escalar separadamente

### Opção 2: Dashboard Separado (Recomendado)

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
     └─────────────────── Mesmo Banco de Dados ──────────────┘
```

**Vantagens:**
- ✅ Separação completa de responsabilidades
- ✅ Pode usar qualquer tecnologia front-end
- ✅ Fácil de escalar separadamente
- ✅ Pode ter múltiplos dashboards
- ✅ Deploy independente

**Desvantagens:**
- ❌ Precisa gerenciar dois projetos
- ❌ CORS precisa estar configurado

**Recomendação:** Use **Opção 2 (Dashboard Separado)** para maior flexibilidade.

---

## 📁 Opção 1: Dashboard Integrado (FlightPHP)

### Estrutura de Pastas

```
public/
├── index.php              ← Roteamento principal
└── dashboard/
    ├── index.html         ← Dashboard principal
    ├── login.html         ← Página de login
    ├── css/
    │   └── style.css
    ├── js/
    │   ├── api.js         ← Cliente HTTP para API
    │   ├── auth.js        ← Gerenciamento de sessão
    │   └── dashboard.js   ← Lógica do dashboard
    └── pages/
        ├── subscriptions.html
        ├── customers.html
        └── audit-logs.html
```

### Rotas no FlightPHP

```php
// public/index.php

// Dashboard (após autenticação)
$app->route('GET /dashboard', function() {
    // Verifica se usuário está logado
    // Renderiza dashboard/index.html
});

$app->route('GET /dashboard/*', function() {
    // Serve arquivos estáticos do dashboard
});
```

### Autenticação

Usa a mesma autenticação do sistema (Session ID via `AuthController`).

---

## 📁 Opção 2: Dashboard Separado (Recomendado)

### Estrutura de Pastas

```
dashboard/                    ← Projeto separado
├── index.html
├── login.html
├── css/
│   └── style.css
├── js/
│   ├── api.js              ← Cliente HTTP para API
│   ├── auth.js             ← Gerenciamento de sessão
│   └── dashboard.js         ← Lógica do dashboard
└── pages/
    ├── subscriptions.html
    ├── customers.html
    └── audit-logs.html
```

### Configuração

**1. Configurar API no `js/api.js`:**

```javascript
const API_CONFIG = {
    baseUrl: 'http://localhost:8080',  // URL do backend
    apiKey: null,  // Não usado (usa Session ID)
    sessionId: null  // Preenchido após login
};
```

**2. Autenticação:**

```javascript
// js/auth.js
async function login(email, password, tenantId) {
    const response = await fetch(`${API_CONFIG.baseUrl}/v1/auth/login`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            email,
            password,
            tenant_id: tenantId
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        // Salva Session ID
        localStorage.setItem('session_id', data.data.token);
        API_CONFIG.sessionId = data.data.token;
        return true;
    }
    
    return false;
}
```

**3. Verificação de Permissões no Front-end:**

```javascript
// js/dashboard.js
async function checkPermission(permission) {
    // Verifica permissão do usuário atual
    const user = await getCurrentUser();
    
    if (user.role === 'admin') {
        return true;  // Admin tem todas as permissões
    }
    
    // Busca permissões do usuário
    const response = await fetch(
        `${API_CONFIG.baseUrl}/v1/users/${user.id}/permissions`,
        {
            headers: {
                'Authorization': `Bearer ${API_CONFIG.sessionId}`
            }
        }
    );
    
    const data = await response.json();
    return data.data.permissions.some(p => p.permission === permission && p.granted);
}
```

---

## 🔐 Autenticação

### Fluxo de Login

1. Usuário acessa `/login.html`
2. Preenche email, senha e tenant_id
3. Front-end faz `POST /v1/auth/login`
4. Backend retorna Session ID
5. Front-end salva Session ID (localStorage ou cookie)
6. Redireciona para dashboard

### Verificação de Sessão

```javascript
// Verifica se usuário está logado
async function checkAuth() {
    const sessionId = localStorage.getItem('session_id');
    
    if (!sessionId) {
        window.location.href = '/login.html';
        return false;
    }
    
    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/v1/auth/me`, {
            headers: {
                'Authorization': `Bearer ${sessionId}`
            }
        });
        
        if (!response.ok) {
            // Sessão inválida
            localStorage.removeItem('session_id');
            window.location.href = '/login.html';
            return false;
        }
        
        const data = await response.json();
        return data.data.user;
    } catch (error) {
        localStorage.removeItem('session_id');
        window.location.href = '/login.html';
        return false;
    }
}
```

---

## 🔒 Verificação de Permissões

### No Front-end

```javascript
// Verifica se usuário tem permissão antes de mostrar botão
async function renderPage() {
    const user = await checkAuth();
    
    // Verifica permissões
    const canCreate = await checkPermission('create_subscriptions');
    const canCancel = await checkPermission('cancel_subscriptions');
    
    // Mostra/esconde botões baseado em permissões
    if (canCreate) {
        document.getElementById('btn-create').style.display = 'block';
    } else {
        document.getElementById('btn-create').style.display = 'none';
    }
    
    if (canCancel) {
        document.getElementById('btn-cancel').style.display = 'block';
    } else {
        document.getElementById('btn-cancel').style.display = 'none';
    }
}
```

### No Backend

O backend já verifica permissões automaticamente. Se o usuário não tiver permissão, retorna 403.

---

## 📄 Exemplos de Páginas

### Página de Login

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Login</h2>
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant ID</label>
                                <input type="number" class="form-control" id="tenant_id" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/auth.js"></script>
    <script src="js/login.js"></script>
</body>
</html>
```

### Dashboard Principal

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Dashboard</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3" id="userInfo"></span>
                <button class="btn btn-outline-light" onclick="logout()">Sair</button>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <h1>Dashboard</h1>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Assinaturas</h5>
                        <p class="card-text" id="subscriptionsCount">-</p>
                        <a href="pages/subscriptions.html" class="btn btn-primary">Ver</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Clientes</h5>
                        <p class="card-text" id="customersCount">-</p>
                        <a href="pages/customers.html" class="btn btn-primary">Ver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>
```

---

## 📋 Páginas Necessárias

### Páginas Públicas
- `login.html` - Login de usuários

### Páginas Autenticadas
- `index.html` - Dashboard principal
- `pages/subscriptions.html` - Gerenciamento de assinaturas
- `pages/customers.html` - Gerenciamento de clientes
- `pages/audit-logs.html` - Logs de auditoria
- `pages/users.html` - Gerenciamento de usuários (apenas admin)
- `pages/permissions.html` - Gerenciamento de permissões (apenas admin)

---

## 🔗 Referências

- **[Views do Front-End](VIEWS_FRONTEND.md)** - Documentação completa de todas as views
- **[Formulários Bootstrap](FORMULARIOS_BOOTSTRAP.md)** - Formulários detalhados
- **[Rotas da API](ROTAS_API.md)** - Endpoints disponíveis
- **[Sistema de Permissões](SISTEMA_PERMISSOES.md)** - Como funciona o sistema de permissões

---

**Recomendação:** Use **Dashboard Separado** para maior flexibilidade e escalabilidade.


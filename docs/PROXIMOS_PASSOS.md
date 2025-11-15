# 🎯 Próximos Passos - Sistema de Pagamentos SaaS

## ✅ O que já está implementado

1. ✅ **Sistema de Autenticação de Usuários**
   - Login, logout, verificação de sessão
   - Suporte a Session ID e API Key
   - Middleware de autenticação funcionando

2. ✅ **Sistema de Permissões (Estrutura)**
   - Models: `UserSession`, `UserPermission`
   - Middleware: `PermissionMiddleware`
   - Roles: admin, editor, viewer
   - Permissões específicas por funcionalidade

3. ✅ **Banco de Dados**
   - Tabelas criadas: `users`, `user_sessions`, `user_permissions`
   - Migration executada
   - Seeds de usuários criados

---

## 🚀 Próximos Passos Recomendados

### Prioridade ALTA 🔴

#### 1. Integrar Verificação de Permissões nos Controllers

**Status:** ❌ Não implementado

**O que fazer:**
- Adicionar verificação de permissões nos controllers existentes
- Usar `PermissionMiddleware` para proteger endpoints
- Exemplo: `view_subscriptions`, `create_subscriptions`, `cancel_subscriptions`

**Controllers que precisam de permissões:**
- `SubscriptionController` - precisa de `view_subscriptions`, `create_subscriptions`, `update_subscriptions`, `cancel_subscriptions`
- `CustomerController` - precisa de `view_customers`, `create_customers`, `update_customers`
- `AuditLogController` - precisa de `view_audit_logs`
- Outros controllers conforme necessário

**Como implementar:**
```php
// No controller
$permissionMiddleware = new \App\Middleware\PermissionMiddleware();

// Antes de executar ação
if (!$permissionMiddleware->check('view_subscriptions')) {
    Flight::halt(403, json_encode([
        'error' => 'Acesso negado',
        'message' => 'Você não tem permissão para visualizar assinaturas'
    ]));
    return;
}
```

---

#### 2. Criar UserController (CRUD de Usuários)

**Status:** ❌ Não implementado

**O que fazer:**
- Criar endpoints para gerenciar usuários
- Apenas admins podem criar/editar/remover usuários
- Viewers e editors podem apenas ver a lista de usuários

**Endpoints necessários:**
- `GET /v1/users` - Listar usuários do tenant
- `GET /v1/users/:id` - Obter usuário específico
- `POST /v1/users` - Criar novo usuário (apenas admin)
- `PUT /v1/users/:id` - Atualizar usuário (apenas admin)
- `DELETE /v1/users/:id` - Desativar usuário (apenas admin)
- `PUT /v1/users/:id/role` - Atualizar role do usuário (apenas admin)

**Permissões necessárias:**
- `view_users` - Ver lista de usuários
- `create_users` - Criar usuários (apenas admin)
- `update_users` - Atualizar usuários (apenas admin)
- `manage_permissions` - Gerenciar permissões (apenas admin)

---

#### 3. Criar PermissionController (Gerenciar Permissões)

**Status:** ❌ Não implementado

**O que fazer:**
- Criar endpoints para gerenciar permissões de usuários
- Apenas admins podem gerenciar permissões

**Endpoints necessários:**
- `GET /v1/users/:id/permissions` - Listar permissões de um usuário
- `POST /v1/users/:id/permissions` - Conceder permissão
- `DELETE /v1/users/:id/permissions/:permission` - Revogar permissão
- `GET /v1/permissions` - Listar todas as permissões disponíveis

---

### Prioridade MÉDIA 🟡

#### 4. Criar Dashboard Separado

**Status:** ❌ Não implementado (documentação criada)

**O que fazer:**
- Criar dashboard HTML/CSS/Bootstrap separado
- Usar autenticação de usuários (Session ID)
- Implementar verificação de permissões no frontend
- Criar páginas: login, dashboard, assinaturas, clientes, logs

**Estrutura sugerida:**
```
dashboard/
├── index.html
├── login.html
├── css/
│   └── style.css
├── js/
│   ├── api.js
│   ├── auth.js
│   └── dashboard.js
└── pages/
    ├── subscriptions.html
    ├── customers.html
    └── audit-logs.html
```

**Referência:** `docs/DASHBOARD_SEPARADO_PERMISSOES.md`

---

#### 5. Melhorar Sistema de Permissões

**Status:** ⚠️ Básico implementado

**O que fazer:**
- Adicionar mais permissões específicas
- Criar sistema de grupos de permissões
- Adicionar permissões por recurso (ex: pode editar apenas suas próprias assinaturas)
- Adicionar permissões temporárias (com expiração)

---

### Prioridade BAIXA 🟢

#### 6. Refresh Tokens

**Status:** ❌ Não implementado

**O que fazer:**
- Implementar sistema de refresh tokens
- Tokens de acesso de curta duração (1 hora)
- Refresh tokens de longa duração (30 dias)
- Endpoint: `POST /v1/auth/refresh`

---

#### 7. Recuperação de Senha

**Status:** ❌ Não implementado

**O que fazer:**
- Endpoint para solicitar reset de senha
- Envio de email com token de reset
- Endpoint para resetar senha com token
- Validação de token e expiração

---

#### 8. 2FA (Autenticação de Dois Fatores)

**Status:** ❌ Não implementado

**O que fazer:**
- Integração com Google Authenticator ou similar
- QR Code para configuração
- Verificação de código 2FA no login

---

## 📋 Checklist de Implementação Sugerida

### Fase 1: Integração de Permissões (Prioridade ALTA)

- [ ] Adicionar verificação de permissões em `SubscriptionController`
  - [ ] `view_subscriptions` - GET /v1/subscriptions
  - [ ] `create_subscriptions` - POST /v1/subscriptions
  - [ ] `update_subscriptions` - PUT /v1/subscriptions/:id
  - [ ] `cancel_subscriptions` - DELETE /v1/subscriptions/:id
  - [ ] `reactivate_subscriptions` - POST /v1/subscriptions/:id/reactivate

- [ ] Adicionar verificação de permissões em `CustomerController`
  - [ ] `view_customers` - GET /v1/customers
  - [ ] `create_customers` - POST /v1/customers
  - [ ] `update_customers` - PUT /v1/customers/:id

- [ ] Adicionar verificação de permissões em `AuditLogController`
  - [ ] `view_audit_logs` - GET /v1/audit-logs

- [ ] Criar helper para verificar permissões (opcional)
  - [ ] Função global `hasPermission($permission)`
  - [ ] Função global `requirePermission($permission)`

---

### Fase 2: Gerenciamento de Usuários (Prioridade ALTA)

- [ ] Criar `UserController`
  - [ ] `GET /v1/users` - Listar usuários
  - [ ] `GET /v1/users/:id` - Obter usuário
  - [ ] `POST /v1/users` - Criar usuário
  - [ ] `PUT /v1/users/:id` - Atualizar usuário
  - [ ] `DELETE /v1/users/:id` - Desativar usuário
  - [ ] `PUT /v1/users/:id/role` - Atualizar role

- [ ] Criar `PermissionController`
  - [ ] `GET /v1/users/:id/permissions` - Listar permissões
  - [ ] `POST /v1/users/:id/permissions` - Conceder permissão
  - [ ] `DELETE /v1/users/:id/permissions/:permission` - Revogar permissão
  - [ ] `GET /v1/permissions` - Listar todas as permissões

- [ ] Adicionar rotas no `public/index.php`
- [ ] Criar testes para UserController
- [ ] Criar testes para PermissionController

---

### Fase 3: Dashboard Separado (Prioridade MÉDIA)

- [ ] Criar estrutura de pastas do dashboard
- [ ] Criar `api.js` - Cliente HTTP para API
- [ ] Criar `auth.js` - Gerenciamento de sessão
- [ ] Criar página de login
- [ ] Criar página principal (dashboard)
- [ ] Criar página de assinaturas
- [ ] Criar página de clientes
- [ ] Criar página de logs de auditoria
- [ ] Implementar verificação de permissões no frontend
- [ ] Adicionar tratamento de erros
- [ ] Adicionar loading states

---

## 🎯 Recomendação: Ordem de Implementação

### Opção 1: Foco em Segurança (Recomendado)
1. **Integrar Permissões nos Controllers** (1-2 dias)
   - Proteger endpoints existentes
   - Garantir que apenas usuários autorizados acessem recursos

2. **Criar UserController** (1 dia)
   - Permitir gerenciamento de usuários
   - Necessário para produção

3. **Criar PermissionController** (1 dia)
   - Permitir gerenciamento de permissões
   - Necessário para produção

4. **Criar Dashboard** (2-3 dias)
   - Interface para usuários
   - Facilita uso do sistema

### Opção 2: Foco em Interface
1. **Criar Dashboard** (2-3 dias)
   - Interface visual primeiro
   - Testar autenticação na prática

2. **Integrar Permissões** (1-2 dias)
   - Proteger endpoints
   - Garantir segurança

3. **Criar Controllers de Gerenciamento** (2 dias)
   - UserController e PermissionController

---

## 📊 Análise do Sistema Atual

### ✅ Pontos Fortes
- Sistema de autenticação funcionando
- Estrutura de permissões criada
- Testes básicos passando
- Documentação completa

### ⚠️ Pontos de Atenção
- Permissões não estão sendo verificadas nos controllers
- Não há endpoints para gerenciar usuários
- Não há endpoints para gerenciar permissões
- Dashboard não foi criado

### 🎯 Próximo Passo Imediato

**Recomendação:** Começar pela **integração de permissões nos controllers**, pois:
1. ✅ É rápido de implementar (1-2 dias)
2. ✅ Aumenta a segurança do sistema
3. ✅ É necessário antes de criar o dashboard
4. ✅ Não quebra funcionalidades existentes (API Key continua funcionando)

---

## 💡 Exemplo de Implementação Rápida

### Passo 1: Criar Helper de Permissões

```php
// App/Utils/PermissionHelper.php
namespace App\Utils;

use App\Middleware\PermissionMiddleware;
use Flight;

class PermissionHelper
{
    public static function require(string $permission): void
    {
        $middleware = new PermissionMiddleware();
        $middleware->require($permission);
    }
    
    public static function check(string $permission): bool
    {
        $middleware = new PermissionMiddleware();
        return $middleware->check($permission);
    }
}
```

### Passo 2: Usar nos Controllers

```php
// App/Controllers/SubscriptionController.php
use App\Utils\PermissionHelper;

public function list(): void
{
    PermissionHelper::require('view_subscriptions');
    
    // ... resto do código ...
}
```

---

## ✅ Resumo

**Próximo passo recomendado:**
1. **Integrar verificação de permissões nos controllers existentes**
   - Começar por `SubscriptionController` e `CustomerController`
   - Usar `PermissionMiddleware` ou criar helper
   - Testar com diferentes roles

**Depois:**
2. Criar `UserController` para gerenciar usuários
3. Criar `PermissionController` para gerenciar permissões
4. Criar dashboard separado

**Tempo estimado:**
- Fase 1 (Permissões): 1-2 dias
- Fase 2 (Gerenciamento): 2 dias
- Fase 3 (Dashboard): 2-3 dias

**Total: 5-7 dias de desenvolvimento**

---

Quer que eu comece implementando a integração de permissões nos controllers?


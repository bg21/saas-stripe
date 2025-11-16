# 🔐 Sistema de Permissões (RBAC) - Documentação Completa

**Versão:** 1.0.3  
**Última Atualização:** 2025-01-16

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Autenticação](#autenticação)
4. [Roles e Permissões](#roles-e-permissões)
5. [Controllers Implementados](#controllers-implementados)
6. [Helper de Permissões](#helper-de-permissões)
7. [Testes](#testes)
8. [Exemplos de Uso](#exemplos-de-uso)

---

## 🎯 Visão Geral

O sistema possui **duas camadas de autenticação** que trabalham juntas:

1. **Tenant (Multi-tenancy)** - Identifica qual SaaS está fazendo a requisição
2. **Usuários + Permissões (RBAC)** - Identifica qual usuário dentro daquele tenant e suas permissões

### Tipos de Autenticação

- **API Key (Tenant)** - Para requisições programáticas do seu SaaS
- **Session ID (Usuário)** - Para usuários logados no dashboard
- **Master Key** - Para acesso administrativo total

---

## 🏗️ Arquitetura

### Hierarquia do Sistema

```
TENANT (SaaS)
  │
  ├─ Usuário 1 (admin@empresa.com)
  │  ├─ Role: admin
  │  └─ Permissões: Todas (implicitas)
  │
  ├─ Usuário 2 (editor@empresa.com)
  │  ├─ Role: editor
  │  └─ Permissões: view_subscriptions, create_subscriptions, update_subscriptions, ...
  │
  └─ Usuário 3 (viewer@empresa.com)
     ├─ Role: viewer
     └─ Permissões: view_subscriptions, view_customers
```

### Componentes

- **Models:**
  - `User` - Usuários do sistema
  - `UserSession` - Sessões de usuários
  - `UserPermission` - Permissões granulares

- **Middleware:**
  - `AuthMiddleware` - Autenticação (API Key, Session ID, Master Key)
  - `UserAuthMiddleware` - Validação de sessões de usuários
  - `PermissionMiddleware` - Verificação de permissões

- **Controllers:**
  - `AuthController` - Login, logout, verificação de sessão
  - `UserController` - CRUD de usuários
  - `PermissionController` - Gerenciamento de permissões

- **Utils:**
  - `PermissionHelper` - Helper para verificação de permissões

---

## 🔑 Autenticação

### API Key (Tenant)

**Uso:** Requisições programáticas do seu SaaS (backend)

```php
Authorization: Bearer {API_KEY}
```

**Comportamento:**
- ✅ Não verifica permissões (acesso total do tenant)
- ✅ Funciona normalmente em todos os endpoints
- ✅ Compatível com código existente

### Session ID (Usuário)

**Uso:** Usuários logados no dashboard

```php
Authorization: Bearer {SESSION_ID}
```

**Comportamento:**
- ✅ Verifica permissões antes de executar ações
- ✅ Bloqueia se não tiver permissão (403)
- ✅ Registra tentativas de acesso negado

### Master Key

**Uso:** Acesso administrativo total

```php
Authorization: Bearer {MASTER_KEY}
```

**Comportamento:**
- ✅ Acesso total (sem verificação de permissões)
- ✅ Pode ver todos os tenants
- ✅ Usado apenas para administração do sistema

---

## 👥 Roles e Permissões

### Roles Disponíveis

1. **Admin** - Acesso total
2. **Editor** - Pode criar/editar, não pode cancelar
3. **Viewer** - Apenas visualização

### Permissões Disponíveis (11 total)

#### Assinaturas
- `view_subscriptions` - Visualizar assinaturas
- `create_subscriptions` - Criar assinaturas
- `update_subscriptions` - Atualizar assinaturas
- `cancel_subscriptions` - Cancelar assinaturas
- `reactivate_subscriptions` - Reativar assinaturas

#### Clientes
- `view_customers` - Visualizar clientes
- `create_customers` - Criar clientes
- `update_customers` - Atualizar clientes

#### Auditoria
- `view_audit_logs` - Visualizar logs de auditoria

#### Administrativas
- `manage_users` - Gerenciar usuários
- `manage_permissions` - Gerenciar permissões

### Permissões por Role

| Permissão | Admin | Editor | Viewer |
|-----------|-------|--------|--------|
| `view_subscriptions` | ✅ | ✅ | ✅ |
| `create_subscriptions` | ✅ | ✅ | ❌ |
| `update_subscriptions` | ✅ | ✅ | ❌ |
| `cancel_subscriptions` | ✅ | ❌ | ❌ |
| `reactivate_subscriptions` | ✅ | ❌ | ❌ |
| `view_customers` | ✅ | ✅ | ✅ |
| `create_customers` | ✅ | ✅ | ❌ |
| `update_customers` | ✅ | ✅ | ❌ |
| `view_audit_logs` | ✅ | ❌ | ❌ |
| `manage_users` | ✅ | ❌ | ❌ |
| `manage_permissions` | ✅ | ❌ | ❌ |

**Nota:** Admin tem todas as permissões implicitamente (não precisa verificar no banco).

---

## 🎮 Controllers Implementados

### AuthController

**Endpoints:**
- `POST /v1/auth/login` - Login de usuário
- `POST /v1/auth/logout` - Logout de usuário
- `GET /v1/auth/me` - Informações do usuário autenticado

**Status:** ✅ Implementado e testado

### UserController

**Endpoints:**
- `GET /v1/users` - Listar usuários do tenant
- `GET /v1/users/:id` - Obter usuário específico
- `POST /v1/users` - Criar novo usuário (apenas admin)
- `PUT /v1/users/:id` - Atualizar usuário (apenas admin)
- `DELETE /v1/users/:id` - Desativar usuário (apenas admin)
- `PUT /v1/users/:id/role` - Atualizar role do usuário (apenas admin)

**Status:** ✅ Implementado e testado

**Validações de Segurança:**
- Usuário não pode desativar a si mesmo
- Não é possível remover o último admin do tenant
- Não é possível alterar a própria role de admin

### PermissionController

**Endpoints:**
- `GET /v1/permissions` - Listar todas as permissões disponíveis
- `GET /v1/users/:id/permissions` - Listar permissões de um usuário
- `POST /v1/users/:id/permissions` - Conceder permissão (apenas admin)
- `DELETE /v1/users/:id/permissions/:permission` - Revogar permissão (apenas admin)

**Status:** ✅ Implementado e testado

### Controllers com Verificação de Permissões

**SubscriptionController:**
- `create()` - `create_subscriptions`
- `list()` - `view_subscriptions`
- `get()` - `view_subscriptions`
- `update()` - `update_subscriptions`
- `cancel()` - `cancel_subscriptions`
- `reactivate()` - `reactivate_subscriptions`
- `history()` - `view_subscriptions`

**CustomerController:**
- `create()` - `create_customers`
- `list()` - `view_customers`
- `get()` - `view_customers`
- `update()` - `update_customers`
- `listInvoices()` - `view_customers`
- `listPaymentMethods()` - `view_customers`
- `updatePaymentMethod()` - `update_customers`
- `deletePaymentMethod()` - `update_customers`
- `setDefaultPaymentMethod()` - `update_customers`

**AuditLogController:**
- `list()` - `view_audit_logs` (exceto master key)
- `get()` - `view_audit_logs` (exceto master key)

---

## 🛠️ Helper de Permissões

### PermissionHelper

**Localização:** `App/Utils/PermissionHelper.php`

**Métodos:**

```php
// Exige permissão (bloqueia se não tiver)
PermissionHelper::require('view_subscriptions');

// Verifica se tem permissão (retorna bool)
if (PermissionHelper::check('view_subscriptions')) {
    // Faz algo
}

// Verifica múltiplas permissões (OR)
if (PermissionHelper::checkAny(['view_subscriptions', 'view_customers'])) {
    // Faz algo
}

// Verifica múltiplas permissões (AND)
if (PermissionHelper::checkAll(['view_subscriptions', 'create_subscriptions'])) {
    // Faz algo
}

// Verifica tipo de autenticação
if (PermissionHelper::isUserAuth()) {
    // É autenticação de usuário
}

if (PermissionHelper::isApiKeyAuth()) {
    // É autenticação via API Key
}

if (PermissionHelper::isMasterKey()) {
    // É master key
}
```

### Lógica de Verificação

1. **Se for API Key:** Não verifica permissões (continua normalmente)
2. **Se for Session ID:** Verifica permissões antes de executar
3. **Se for Master Key:** Não verifica permissões (acesso total)

---

## 🧪 Testes

### Resultados dos Testes

**Total:** 16 testes  
**Passados:** 16 (100%)  
**Falhados:** 0

### Casos Testados

1. ✅ API Key funciona normalmente (sem verificação de permissões)
2. ✅ Admin tem todas as permissões
3. ✅ Editor funciona parcialmente (pode criar/editar, não pode cancelar)
4. ✅ Viewer só pode visualizar (bloqueio correto para ações)
5. ✅ Permissões são verificadas corretamente
6. ✅ Bloqueios funcionam corretamente (403 quando necessário)

### Testes por Tipo de Autenticação

| Tipo | Visualizar | Criar | Editar | Cancelar | Logs |
|------|------------|-------|--------|----------|------|
| API Key | ✅ | ✅ | ✅ | ✅ | ✅ |
| Admin | ✅ | ✅ | ✅ | ✅ | ✅ |
| Editor | ✅ | ✅ | ✅ | ❌ | ❌ |
| Viewer | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## 💡 Exemplos de Uso

### No Controller

```php
public function list(): void
{
    try {
        // Verifica permissão (só verifica se for autenticação de usuário)
        PermissionHelper::require('view_subscriptions');
        
        $tenantId = Flight::get('tenant_id');
        $subscriptionModel = new \App\Models\Subscription();
        $subscriptions = $subscriptionModel->findByTenant($tenantId);

        Flight::json([
            'success' => true,
            'data' => $subscriptions,
            'count' => count($subscriptions)
        ]);
    } catch (\Exception $e) {
        Logger::error("Erro ao listar assinaturas", ['error' => $e->getMessage()]);
        Flight::json(['error' => 'Erro ao listar assinaturas'], 500);
    }
}
```

### Verificação Condicional

```php
// Verifica se tem permissão (retorna bool)
if (PermissionHelper::check('view_subscriptions')) {
    // Faz algo
}

// Verifica múltiplas permissões (OR)
if (PermissionHelper::checkAny(['view_subscriptions', 'view_customers'])) {
    // Faz algo
}

// Verifica múltiplas permissões (AND)
if (PermissionHelper::checkAll(['view_subscriptions', 'create_subscriptions'])) {
    // Faz algo
}
```

### Login de Usuário

```bash
curl -X POST http://localhost:8080/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "senha123",
    "tenant_id": 1
  }'
```

**Resposta:**
```json
{
  "success": true,
  "data": {
    "token": "session_id_xxxxx",
    "user": {
      "id": 1,
      "email": "admin@example.com",
      "name": "Administrador",
      "role": "admin"
    }
  }
}
```

### Usar Session ID

```bash
curl -X GET http://localhost:8080/v1/subscriptions \
  -H "Authorization: Bearer {SESSION_ID}"
```

---

## 📊 Estatísticas

### Implementação
- **Controllers:** 3 (AuthController, UserController, PermissionController)
- **Endpoints:** 13 endpoints implementados
- **Métodos protegidos:** 18 métodos com verificação de permissões
- **Permissões:** 11 permissões disponíveis
- **Roles:** 3 roles (admin, editor, viewer)

### Testes
- **Testes realizados:** 16
- **Taxa de sucesso:** 100%
- **Cobertura:** Todos os tipos de autenticação testados

---

## ✅ Conclusão

O sistema de permissões está **100% implementado e testado**.

**Pontos importantes:**
- ✅ API Key continua funcionando normalmente (sem verificação de permissões)
- ✅ Session ID verifica permissões antes de executar ações
- ✅ Master key tem acesso total (sem verificação de permissões)
- ✅ 18 métodos protegidos com verificação de permissões
- ✅ Sistema de roles funcionando (admin, editor, viewer)
- ✅ Logs de auditoria registrando tentativas de acesso negado

**Status:** ✅ Pronto para produção

---

**Referências:**
- [Arquitetura de Autenticação](ARQUITETURA_AUTENTICACAO.md) - Detalhes da arquitetura
- [Rotas da API](ROTAS_API.md) - Endpoints de autenticação e permissões


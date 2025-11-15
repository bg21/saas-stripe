# 🏗️ Arquitetura de Autenticação: Tenant + Usuários + Permissões

## ✅ Sim, teremos AMBOS!

O sistema terá **duas camadas de autenticação** que trabalham juntas:

```
┌─────────────────────────────────────────────────────────┐
│  CAMADA 1: TENANT (Multi-tenancy)                       │
│  └─ Identifica QUAL SaaS está fazendo a requisição     │
│     Exemplo: "SaaS de E-commerce", "SaaS de CRM"        │
└─────────────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────────────┐
│  CAMADA 2: USUÁRIOS + PERMISSÕES                        │
│  └─ Identifica QUAL USUÁRIO dentro daquele tenant      │
│     Exemplo: "admin@empresa.com", "viewer@empresa.com"  │
│     └─ Cada usuário tem suas próprias PERMISSÕES        │
└─────────────────────────────────────────────────────────┘
```

---

## 🎯 Hierarquia do Sistema

```
TENANT (SaaS)
  │
  ├─ Usuário 1 (admin@empresa.com)
  │  ├─ Role: admin
  │  └─ Permissões: TODAS (view, create, edit, delete)
  │
  ├─ Usuário 2 (editor@empresa.com)
  │  ├─ Role: editor
  │  └─ Permissões: view_subscriptions, create_subscriptions, edit_subscriptions
  │
  └─ Usuário 3 (viewer@empresa.com)
     ├─ Role: viewer
     └─ Permissões: view_subscriptions, view_customers
```

---

## 🔐 Como Funciona na Prática

### Cenário 1: API Key do Tenant (Autenticação de Sistema)

**Uso:** Quando seu SaaS faz requisições programáticas (backend para backend)

```
Seu SaaS (Backend) → API Key do Tenant → Sistema de Pagamentos
```

**Exemplo:**
```php
// No seu SaaS, você faz uma requisição
$ch = curl_init('https://api-pagamentos.com/v1/subscriptions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer {API_KEY_DO_TENANT}'
]);
```

**Quando usar:**
- ✅ Integração backend-backend
- ✅ Webhooks
- ✅ Scripts automatizados
- ✅ Quando não há usuário logado

**O que acontece:**
- Sistema identifica o Tenant
- Retorna dados daquele Tenant
- **NÃO há controle de permissões individuais**

---

### Cenário 2: Autenticação de Usuário (Dashboard)

**Uso:** Quando um usuário acessa o dashboard

```
Usuário → Login (email/senha) → Token de Sessão → Sistema de Pagamentos
```

**Exemplo:**
```javascript
// No dashboard, usuário faz login
const response = await fetch('https://api-pagamentos.com/v1/auth/login', {
    method: 'POST',
    body: JSON.stringify({
        tenant_id: 1,
        email: 'admin@empresa.com',
        password: 'senha123'
    })
});

// Recebe token de sessão
const sessionId = response.data.session_id;

// Usa token em requisições subsequentes
const subscriptions = await fetch('https://api-pagamentos.com/v1/subscriptions', {
    headers: {
        'Authorization': `Bearer ${sessionId}`
    }
});
```

**O que acontece:**
1. Sistema identifica o Tenant (via `tenant_id` no login)
2. Sistema identifica o Usuário (via email/senha)
3. Sistema verifica Permissões do usuário
4. Retorna apenas dados que o usuário tem permissão de ver

---

## 📊 Fluxo de Autenticação Completo

### Fluxo 1: API Key (Tenant)

```
1. Requisição chega com: Authorization: Bearer {API_KEY}
2. Sistema busca Tenant pela API Key
3. Se encontrado e ativo → Acesso liberado
4. Retorna dados do Tenant (sem filtro de usuário)
```

### Fluxo 2: Sessão de Usuário (Dashboard)

```
1. Usuário faz login: POST /v1/auth/login
   Body: { tenant_id, email, password }
   
2. Sistema valida:
   - Tenant existe e está ativo?
   - Usuário existe neste tenant?
   - Senha está correta?
   - Usuário está ativo?
   
3. Se válido:
   - Cria sessão (token)
   - Retorna: { session_id, user, tenant }
   
4. Próximas requisições:
   - Authorization: Bearer {session_id}
   - Sistema valida sessão
   - Sistema identifica Tenant + Usuário
   - Sistema verifica Permissões
   - Retorna dados filtrados por permissões
```

---

## 🗄️ Estrutura do Banco de Dados

```sql
-- CAMADA 1: TENANT (já existe)
tenants
├─ id
├─ name
├─ api_key          ← Autenticação de sistema
└─ status

-- CAMADA 2: USUÁRIOS (já existe, mas vamos adicionar role)
users
├─ id
├─ tenant_id        ← Pertence a um tenant
├─ email
├─ password_hash
├─ name
├─ status
└─ role             ← NOVO: admin, viewer, editor

-- NOVO: Sessões de usuários
user_sessions
├─ id (token)
├─ user_id
├─ tenant_id
├─ expires_at
└─ created_at

-- NOVO: Permissões específicas (opcional)
user_permissions
├─ id
├─ user_id
├─ permission       ← Ex: "view_subscriptions"
└─ granted
```

---

## 🔄 Como os Dois Sistemas Trabalham Juntos

### Exemplo Real: Dashboard de Assinaturas

**Cenário:** Usuário "editor@empresa.com" acessa dashboard

```javascript
// 1. Login
POST /v1/auth/login
{
    "tenant_id": 1,
    "email": "editor@empresa.com",
    "password": "senha123"
}

// Resposta:
{
    "session_id": "abc123...",
    "user": {
        "id": 5,
        "email": "editor@empresa.com",
        "role": "editor"
    },
    "tenant": {
        "id": 1,
        "name": "Empresa XYZ"
    }
}

// 2. Buscar assinaturas
GET /v1/subscriptions
Headers: Authorization: Bearer abc123...

// O que acontece internamente:
// 1. Valida sessão → identifica user_id=5, tenant_id=1
// 2. Verifica permissões → user tem "view_subscriptions"?
// 3. Se SIM → retorna assinaturas do tenant_id=1
// 4. Se NÃO → retorna 403 Forbidden
```

---

## 🎯 Quando Usar Cada Tipo de Autenticação

### Use API Key (Tenant) quando:

- ✅ **Backend para Backend**
  - Seu SaaS fazendo requisições programáticas
  - Scripts automatizados
  - Webhooks

- ✅ **Sem usuário logado**
  - Processos em background
  - Tarefas agendadas

- ✅ **Acesso total do Tenant**
  - Precisa acessar todos os dados do tenant
  - Não precisa de controle de permissões individuais

**Exemplo:**
```php
// Seu SaaS criando uma assinatura automaticamente
$api->createSubscription([
    'customer_id' => 123,
    'price_id' => 'price_xxx'
]);
// Usa API Key do tenant
```

---

### Use Autenticação de Usuário quando:

- ✅ **Dashboard/Interface Web**
  - Usuário acessando painel administrativo
  - Precisa saber quem está fazendo a ação

- ✅ **Controle de Permissões**
  - Alguns usuários podem ver, outros não
  - Alguns podem criar, outros só visualizar

- ✅ **Auditoria por Usuário**
  - Precisa rastrear quem fez o quê
  - Logs de ações por usuário

**Exemplo:**
```javascript
// Usuário logado no dashboard
// Só pode ver assinaturas se tiver permissão
if (user.hasPermission('view_subscriptions')) {
    showSubscriptions();
} else {
    showError('Você não tem permissão');
}
```

---

## 🔒 Segurança: Duas Camadas de Proteção

### Camada 1: Tenant Isolation

```
Tenant A → Só vê dados do Tenant A
Tenant B → Só vê dados do Tenant B
```

**Como funciona:**
- Cada requisição identifica o Tenant
- Queries sempre filtram por `tenant_id`
- Impossível acessar dados de outro tenant

### Camada 2: User Permissions

```
Usuário Admin → Vê tudo, pode fazer tudo
Usuário Editor → Vê e edita, mas não deleta
Usuário Viewer → Só vê, não pode modificar
```

**Como funciona:**
- Cada ação verifica permissão do usuário
- Middleware bloqueia ações não permitidas
- Retorna 403 Forbidden se sem permissão

---

## 📋 Exemplo Completo: Criar Assinatura

### Via API Key (Backend)

```php
// Seu SaaS (backend) criando assinatura
POST /v1/subscriptions
Headers: Authorization: Bearer {API_KEY_TENANT}

Body: {
    "customer_id": 123,
    "price_id": "price_xxx"
}

// Sistema:
// 1. Valida API Key → identifica Tenant
// 2. Cria assinatura para aquele Tenant
// 3. Retorna sucesso
// ✅ SEM verificação de permissões (é o próprio sistema)
```

### Via Dashboard (Usuário)

```javascript
// Usuário no dashboard criando assinatura
POST /v1/subscriptions
Headers: Authorization: Bearer {SESSION_ID}

Body: {
    "customer_id": 123,
    "price_id": "price_xxx"
}

// Sistema:
// 1. Valida Sessão → identifica User + Tenant
// 2. Verifica permissão: user tem "create_subscriptions"?
// 3. Se SIM → cria assinatura
// 4. Se NÃO → retorna 403 Forbidden
// ✅ COM verificação de permissões
```

---

## 🎨 Resumo Visual

```
┌─────────────────────────────────────────────────┐
│           REQUISIÇÃO CHEGA                      │
└─────────────────────────────────────────────────┘
                    ↓
        ┌───────────────────────┐
        │  Tem Authorization?     │
        └───────────────────────┘
                    ↓
        ┌───────────────────────────┐
        │  É API Key?               │
        │  (Bearer {64_chars})     │
        └───────────────────────────┘
                    ↓
            ┌───────┴───────┐
            │               │
         SIM │               │ NÃO
            │               │
            ↓               ↓
    ┌──────────────┐  ┌──────────────┐
    │ API Key      │  │ Session ID? │
    │ Auth         │  │ (Bearer     │
    │              │  │  {token})    │
    └──────────────┘  └──────────────┘
            │               │
            │               ↓
            │      ┌──────────────┐
            │      │ Valida       │
            │      │ Sessão       │
            │      └──────────────┘
            │               │
            │               ↓
            │      ┌──────────────┐
            │      │ Identifica   │
            │      │ User + Tenant│
            │      └──────────────┘
            │               │
            │               ↓
            │      ┌──────────────┐
            │      │ Verifica     │
            │      │ Permissões   │
            │      └──────────────┘
            │               │
            └───────┬───────┘
                    ↓
        ┌───────────────────────┐
        │  Processa Requisição  │
        │  (com filtros)       │
        └───────────────────────┘
```

---

## ✅ Resumo Final

**SIM, teremos AMBOS:**

1. **Tenant (API Key)**
   - ✅ Autenticação de sistema
   - ✅ Backend para backend
   - ✅ Sem controle de permissões individuais
   - ✅ Acesso total do tenant

2. **Usuários + Permissões**
   - ✅ Autenticação de usuário
   - ✅ Dashboard/Interface web
   - ✅ Controle de permissões individuais
   - ✅ Auditoria por usuário

**Eles trabalham juntos:**
- Tenant isola dados entre diferentes SaaS
- Usuários + Permissões controlam acesso dentro do mesmo tenant
- Você pode usar um ou outro, dependendo do caso

**Exemplo prático:**
- Seu SaaS usa **API Key** para criar assinaturas automaticamente
- Seus usuários usam **Login** para acessar o dashboard
- Ambos acessam os mesmos dados, mas com níveis diferentes de controle

---

## 🚀 Próximos Passos

1. ✅ Manter autenticação por API Key (já existe)
2. ✅ Adicionar autenticação de usuários (implementar)
3. ✅ Adicionar sistema de permissões (implementar)
4. ✅ Criar endpoints de autenticação (`/v1/auth/*`)
5. ✅ Criar middleware de permissões
6. ✅ Dashboard usar autenticação de usuários

**Resultado:** Sistema completo com duas camadas de segurança! 🔒


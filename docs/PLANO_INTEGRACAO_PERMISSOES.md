# 📋 Plano de Integração de Permissões nos Controllers

## 🔍 Análise Completa do Sistema

### Situação Atual

1. **Autenticação Dupla Funcionando:**
   - ✅ API Key (Tenant) - Sem verificação de permissões
   - ✅ Session ID (Usuário) - Com verificação de permissões (estrutura criada)

2. **Controllers Existentes:**
   - `SubscriptionController` - 7 métodos
   - `CustomerController` - 9 métodos
   - `AuditLogController` - 2 métodos
   - Outros controllers (Product, Price, Payment, etc.)

3. **Sistema de Permissões:**
   - ✅ Models criados (`UserPermission`)
   - ✅ Middleware criado (`PermissionMiddleware`)
   - ✅ Roles definidas (admin, editor, viewer)
   - ❌ **NÃO está sendo usado nos controllers**

---

## 🎯 Estratégia de Implementação

### Regra Geral

**IMPORTANTE:** Permissões só devem ser verificadas quando:
- Autenticação é via **Session ID** (usuário logado)
- `Flight::get('is_user_auth') === true`

**NÃO verificar permissões quando:**
- Autenticação é via **API Key** (tenant)
- `Flight::get('is_user_auth') === false`
- `Flight::get('is_master') === true` (master key)

### Por quê?

- **API Key** = Seu SaaS fazendo requisições programáticas (backend)
- **Session ID** = Usuário acessando dashboard (precisa de permissões)

---

## 📊 Mapeamento de Permissões por Endpoint

### SubscriptionController

| Método | Endpoint | Permissão Necessária | Quando Verificar |
|--------|----------|---------------------|------------------|
| `create()` | `POST /v1/subscriptions` | `create_subscriptions` | Se `is_user_auth === true` |
| `list()` | `GET /v1/subscriptions` | `view_subscriptions` | Se `is_user_auth === true` |
| `get()` | `GET /v1/subscriptions/:id` | `view_subscriptions` | Se `is_user_auth === true` |
| `update()` | `PUT /v1/subscriptions/:id` | `update_subscriptions` | Se `is_user_auth === true` |
| `cancel()` | `DELETE /v1/subscriptions/:id` | `cancel_subscriptions` | Se `is_user_auth === true` |
| `reactivate()` | `POST /v1/subscriptions/:id/reactivate` | `reactivate_subscriptions` | Se `is_user_auth === true` |
| `history()` | `GET /v1/subscriptions/:id/history` | `view_subscriptions` | Se `is_user_auth === true` |

### CustomerController

| Método | Endpoint | Permissão Necessária | Quando Verificar |
|--------|----------|---------------------|------------------|
| `create()` | `POST /v1/customers` | `create_customers` | Se `is_user_auth === true` |
| `list()` | `GET /v1/customers` | `view_customers` | Se `is_user_auth === true` |
| `get()` | `GET /v1/customers/:id` | `view_customers` | Se `is_user_auth === true` |
| `update()` | `PUT /v1/customers/:id` | `update_customers` | Se `is_user_auth === true` |
| `listInvoices()` | `GET /v1/customers/:id/invoices` | `view_customers` | Se `is_user_auth === true` |
| `listPaymentMethods()` | `GET /v1/customers/:id/payment-methods` | `view_customers` | Se `is_user_auth === true` |
| `updatePaymentMethod()` | `PUT /v1/customers/:id/payment-methods/:pm_id` | `update_customers` | Se `is_user_auth === true` |
| `deletePaymentMethod()` | `DELETE /v1/customers/:id/payment-methods/:pm_id` | `update_customers` | Se `is_user_auth === true` |
| `setDefaultPaymentMethod()` | `POST /v1/customers/:id/payment-methods/:pm_id/set-default` | `update_customers` | Se `is_user_auth === true` |

### AuditLogController

| Método | Endpoint | Permissão Necessária | Quando Verificar |
|--------|----------|---------------------|------------------|
| `list()` | `GET /v1/audit-logs` | `view_audit_logs` | Se `is_user_auth === true` |
| `get()` | `GET /v1/audit-logs/:id` | `view_audit_logs` | Se `is_user_auth === true` |

**Nota:** Master key pode ver todos os logs sem verificação de permissões.

---

## 🛠️ Implementação: Helper de Permissões

### Opção 1: Helper Estático (Recomendado)

```php
// App/Utils/PermissionHelper.php
namespace App\Utils;

use App\Middleware\PermissionMiddleware;
use Flight;

class PermissionHelper
{
    /**
     * Verifica se deve verificar permissões
     * (apenas para autenticação de usuários, não API Key)
     */
    private static function shouldCheckPermissions(): bool
    {
        return Flight::get('is_user_auth') === true;
    }

    /**
     * Exige permissão (bloqueia se não tiver)
     * 
     * @param string $permission Nome da permissão
     * @return void Retorna void se tiver permissão, ou bloqueia a requisição
     */
    public static function require(string $permission): void
    {
        // Se não é autenticação de usuário, não verifica permissões
        if (!self::shouldCheckPermissions()) {
            return;
        }

        $middleware = new PermissionMiddleware();
        $middleware->require($permission);
    }

    /**
     * Verifica se tem permissão (retorna bool)
     * 
     * @param string $permission Nome da permissão
     * @return bool True se tem permissão ou se não precisa verificar
     */
    public static function check(string $permission): bool
    {
        // Se não é autenticação de usuário, sempre retorna true
        if (!self::shouldCheckPermissions()) {
            return true;
        }

        $middleware = new PermissionMiddleware();
        return $middleware->check($permission);
    }
}
```

### Vantagens:
- ✅ Simples de usar: `PermissionHelper::require('view_subscriptions')`
- ✅ Automático: Não verifica se for API Key
- ✅ Não quebra código existente
- ✅ Fácil de testar

---

## 📝 Exemplo de Uso nos Controllers

### Antes (Sem Permissões):

```php
public function list(): void
{
    try {
        $tenantId = Flight::get('tenant_id');
        $subscriptionModel = new \App\Models\Subscription();
        $subscriptions = $subscriptionModel->findByTenant($tenantId);

        Flight::json([
            'success' => true,
            'data' => $subscriptions,
            'count' => count($subscriptions)
        ]);
    } catch (\Exception $e) {
        // ...
    }
}
```

### Depois (Com Permissões):

```php
public function list(): void
{
    try {
        // Verifica permissão (só verifica se for autenticação de usuário)
        \App\Utils\PermissionHelper::require('view_subscriptions');
        
        $tenantId = Flight::get('tenant_id');
        $subscriptionModel = new \App\Models\Subscription();
        $subscriptions = $subscriptionModel->findByTenant($tenantId);

        Flight::json([
            'success' => true,
            'data' => $subscriptions,
            'count' => count($subscriptions)
        ]);
    } catch (\Exception $e) {
        // ...
    }
}
```

---

## 🔄 Fluxo de Verificação

```
Requisição chega
    ↓
Middleware de Autenticação
    ↓
É Session ID? ──SIM──→ Flight::set('is_user_auth', true)
    │                      ↓
    │                  Controller chama PermissionHelper::require()
    │                      ↓
    │                  Verifica permissão do usuário
    │                      ↓
    │                  Tem permissão? ──SIM──→ Continua
    │                      │
    │                      └──NÃO──→ Retorna 403
    │
    └──NÃO──→ É API Key? ──SIM──→ Flight::set('is_user_auth', false)
                                      ↓
                                  Controller chama PermissionHelper::require()
                                      ↓
                                  shouldCheckPermissions() retorna false
                                      ↓
                                  Não verifica permissões
                                      ↓
                                  Continua normalmente
```

---

## ✅ Checklist de Implementação

### Fase 1: Criar Helper
- [ ] Criar `App/Utils/PermissionHelper.php`
- [ ] Implementar método `require()`
- [ ] Implementar método `check()`
- [ ] Implementar método `shouldCheckPermissions()`
- [ ] Testar helper isoladamente

### Fase 2: Integrar em SubscriptionController
- [ ] Adicionar `require('view_subscriptions')` em `list()`
- [ ] Adicionar `require('view_subscriptions')` em `get()`
- [ ] Adicionar `require('create_subscriptions')` em `create()`
- [ ] Adicionar `require('update_subscriptions')` em `update()`
- [ ] Adicionar `require('cancel_subscriptions')` em `cancel()`
- [ ] Adicionar `require('reactivate_subscriptions')` em `reactivate()`
- [ ] Adicionar `require('view_subscriptions')` em `history()`
- [ ] Testar com API Key (deve funcionar normalmente)
- [ ] Testar com Session ID de admin (deve funcionar)
- [ ] Testar com Session ID de viewer (deve bloquear criações/edições)

### Fase 3: Integrar em CustomerController
- [ ] Adicionar `require('view_customers')` em `list()`
- [ ] Adicionar `require('view_customers')` em `get()`
- [ ] Adicionar `require('create_customers')` em `create()`
- [ ] Adicionar `require('update_customers')` em `update()`
- [ ] Adicionar `require('view_customers')` em `listInvoices()`
- [ ] Adicionar `require('view_customers')` em `listPaymentMethods()`
- [ ] Adicionar `require('update_customers')` em `updatePaymentMethod()`
- [ ] Adicionar `require('update_customers')` em `deletePaymentMethod()`
- [ ] Adicionar `require('update_customers')` em `setDefaultPaymentMethod()`
- [ ] Testar com diferentes roles

### Fase 4: Integrar em AuditLogController
- [ ] Adicionar `require('view_audit_logs')` em `list()`
- [ ] Adicionar `require('view_audit_logs')` em `get()`
- [ ] Manter lógica de master key (pode ver tudo)
- [ ] Testar com diferentes roles

### Fase 5: Testes
- [ ] Testar todos os endpoints com API Key (deve funcionar)
- [ ] Testar todos os endpoints com Session ID de admin (deve funcionar)
- [ ] Testar todos os endpoints com Session ID de editor (deve funcionar parcialmente)
- [ ] Testar todos os endpoints com Session ID de viewer (deve bloquear ações)
- [ ] Verificar logs de auditoria

---

## 🧪 Casos de Teste

### Teste 1: API Key (Tenant)
```
Requisição: GET /v1/subscriptions
Header: Authorization: Bearer {API_KEY}
Resultado Esperado: ✅ Deve funcionar (sem verificação de permissões)
```

### Teste 2: Session ID - Admin
```
Login: admin@example.com / admin123
Requisição: GET /v1/subscriptions
Header: Authorization: Bearer {SESSION_ID}
Resultado Esperado: ✅ Deve funcionar (admin tem todas as permissões)
```

### Teste 3: Session ID - Viewer
```
Login: viewer@example.com / viewer123
Requisição: GET /v1/subscriptions
Header: Authorization: Bearer {SESSION_ID}
Resultado Esperado: ✅ Deve funcionar (viewer pode visualizar)
```

### Teste 4: Session ID - Viewer (Bloqueio)
```
Login: viewer@example.com / viewer123
Requisição: POST /v1/subscriptions
Header: Authorization: Bearer {SESSION_ID}
Resultado Esperado: ❌ Deve retornar 403 (viewer não pode criar)
```

### Teste 5: Session ID - Editor
```
Login: editor@example.com / editor123
Requisição: POST /v1/subscriptions
Header: Authorization: Bearer {SESSION_ID}
Resultado Esperado: ✅ Deve funcionar (editor pode criar)
```

### Teste 6: Session ID - Editor (Bloqueio)
```
Login: editor@example.com / editor123
Requisição: DELETE /v1/subscriptions/:id
Header: Authorization: Bearer {SESSION_ID}
Resultado Esperado: ❌ Deve retornar 403 (editor não pode cancelar)
```

---

## 🚨 Pontos de Atenção

### 1. Compatibilidade com API Key
- ✅ **CRÍTICO:** API Key deve continuar funcionando sem verificação de permissões
- ✅ Usar `Flight::get('is_user_auth')` para diferenciar
- ✅ Se `is_user_auth === false`, não verificar permissões

### 2. Master Key
- ✅ Master key deve ter acesso total
- ✅ Não verificar permissões para master key
- ✅ Verificar `Flight::get('is_master') === true`

### 3. Performance
- ✅ Verificação de permissões deve ser rápida
- ✅ Cachear permissões do usuário (opcional, futuro)
- ✅ Evitar múltiplas queries no banco

### 4. Logs
- ✅ Registrar tentativas de acesso negado
- ✅ Registrar verificação de permissões
- ✅ Manter logs de auditoria

---

## 📋 Resumo da Estratégia

1. **Criar Helper** (`PermissionHelper`)
   - Verifica se é autenticação de usuário
   - Só verifica permissões se for usuário
   - API Key continua funcionando normalmente

2. **Integrar nos Controllers**
   - Adicionar `PermissionHelper::require()` no início de cada método
   - Começar por `SubscriptionController`
   - Depois `CustomerController`
   - Depois `AuditLogController`

3. **Testar**
   - Testar com API Key (deve funcionar)
   - Testar com diferentes roles (admin, editor, viewer)
   - Verificar bloqueios corretos

4. **Documentar**
   - Atualizar documentação
   - Adicionar exemplos de uso

---

## ✅ Próximo Passo Imediato

**Criar o Helper de Permissões** e testar isoladamente antes de integrar nos controllers.

Quer que eu comece criando o helper?


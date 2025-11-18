# 🔒 AUDITORIA DE SEGURANÇA COMPLEMENTAR - Sistema SaaS Payments

**Data:** 2025-01-15  
**Auditor:** Especialista Sênior em Segurança da Informação  
**Escopo:** Análise complementar de vulnerabilidades não cobertas na auditoria inicial

---

## 📋 SUMÁRIO EXECUTIVO

Esta auditoria complementar identificou **6 vulnerabilidades adicionais** (3 de média severidade e 3 de baixa severidade) que não foram cobertas na auditoria inicial.

**Status Atual:** 🟡 **ATENÇÃO NECESSÁRIA** - Embora a maioria das vulnerabilidades críticas tenha sido corrigida, ainda existem pontos de melhoria importantes.

---

## ⚠️ VULNERABILIDADES DE MÉDIA SEVERIDADE

### 1. **IDOR em Métodos Auxiliares - A01:2021 Broken Access Control (OWASP Top 10)**

**Severidade:** 🟡 **MÉDIA**  
**CWE:** CWE-639  
**Status:** ✅ **CORRIGIDO**

**Problema:**
Alguns métodos em `CustomerController` usam `findById()` diretamente antes de validar o tenant, criando uma janela de oportunidade para IDOR. Embora a validação seja feita posteriormente, a ordem de verificação não é ideal.

**Localização:**
- `App/Controllers/CustomerController.php:369` - `listInvoices()`
- `App/Controllers/CustomerController.php:469` - `listPaymentMethods()`
- `App/Controllers/CustomerController.php:573` - `updatePaymentMethod()`
- `App/Controllers/CustomerController.php:679` - `deletePaymentMethod()`
- `App/Controllers/CustomerController.php:746` - `setDefaultPaymentMethod()`

**Código Vulnerável:**
```php
$customerModel = new \App\Models\Customer();
$customer = $customerModel->findById((int)$id);

// Valida se customer existe e pertence ao tenant
if (!$customer || $customer['tenant_id'] != $tenantId) {
    http_response_code(404);
    Flight::json(['error' => 'Cliente não encontrado'], 404);
    return;
}
```

**Risco:**
- Acesso a recursos de outros tenants pode ser possível se houver race conditions
- Informações podem ser expostas antes da validação de tenant
- Padrão inconsistente com outros métodos que usam `findByTenantAndId()`

**Correção Aplicada:**
✅ Substituído `findById()` por `findByTenantAndId()` em todos os métodos do `CustomerController`:
- `listInvoices()`
- `listPaymentMethods()`
- `updatePaymentMethod()`
- `deletePaymentMethod()`
- `setDefaultPaymentMethod()`

✅ Também corrigido em `CheckoutController` e `BillingPortalController`.

**Localização das Correções:**
- `App/Controllers/CustomerController.php` - todos os métodos auxiliares
- `App/Controllers/CheckoutController.php:73`
- `App/Controllers/BillingPortalController.php:48`

---

### 2. **Falta de Validação de URLs em Checkout - A03:2021 Injection / SSRF (OWASP Top 10)**

**Severidade:** 🟡 **MÉDIA**  
**CWE:** CWE-918 (SSRF), CWE-601 (Open Redirect)  
**Status:** ✅ **CORRIGIDO**

**Problema:**
O `CheckoutController` aceita `success_url` e `cancel_url` sem validação adequada, permitindo:
- SSRF (Server-Side Request Forgery) se as URLs forem usadas em requisições HTTP
- Open Redirect se as URLs forem usadas para redirecionamento
- Phishing através de URLs maliciosas

**Localização:**
- `App/Controllers/CheckoutController.php:37-40`

**Código Vulnerável:**
```php
// Validações básicas
if (empty($data['success_url']) || empty($data['cancel_url'])) {
    Flight::json(['error' => 'success_url e cancel_url são obrigatórios'], 400);
    return;
}
// URLs são passadas diretamente para o Stripe sem validação
```

**Risco:**
- SSRF: URLs podem apontar para recursos internos (ex: `http://localhost/admin`, `file:///etc/passwd`)
- Open Redirect: URLs podem redirecionar usuários para sites maliciosos
- Phishing: URLs podem imitar o domínio legítimo

**Correção Aplicada:**
✅ Implementado método `validateRedirectUrl()` em `CheckoutController` e `BillingPortalController` com:
- Validação de esquema (apenas HTTPS em produção, HTTP apenas para localhost em desenvolvimento)
- Bloqueio de esquemas perigosos (file, ftp, gopher, javascript, data, vbscript)
- Proteção contra SSRF (bloqueia IPs privados e localhost em produção)
- Validação de comprimento máximo (2048 caracteres)
- Validação aplicada a `success_url`, `cancel_url` e `return_url`

✅ Adicionada validação de tamanho máximo de `line_items` (máximo 100 itens) para prevenir DoS.

**Localização das Correções:**
- `App/Controllers/CheckoutController.php:42-51, 78-82, 211-259`
- `App/Controllers/BillingPortalController.php:56-61, 143-191`

---

### 3. **Ausência de Proteção contra Timing Attacks - A07:2021 Identification and Authentication Failures (OWASP Top 10)**

**Severidade:** 🟡 **MÉDIA**  
**CWE:** CWE-208 (Timing Attack)  
**Status:** ✅ **CORRIGIDO**

**Problema:**
A comparação de tokens e senhas não usa comparação de tempo constante, permitindo timing attacks que podem revelar informações sobre tokens válidos.

**Localização:**
- `App/Models/UserSession.php` - validação de tokens
- `App/Models/Tenant.php` - validação de API keys
- `App/Controllers/AuthController.php` - validação de senhas

**Risco:**
- Timing attacks podem revelar se um token/API key existe no banco
- Ataques podem identificar caracteres corretos de senhas através de diferenças de tempo
- Enumeração de usuários/tenants válidos

**Correção Aplicada:**
✅ Substituída comparação de master key usando `===` por `hash_equals()` em `public/index.php:333`:
- Antes: `if ($masterKey && $token === $masterKey)`
- Depois: `if ($masterKey && hash_equals($masterKey, $token))`

✅ `password_verify()` já é usado no `User` model e é seguro contra timing attacks.

✅ `hash_equals()` já é usado em `SecurityHelper` para tokens CSRF.

**Localização da Correção:**
- `public/index.php:333` - comparação de master key

**Nota:** A comparação de API keys no banco de dados é feita via query SQL, que já é segura. A única comparação em memória era a master key, que foi corrigida.

---

## 📝 VULNERABILIDADES DE BAIXA SEVERIDADE

### 4. **Validação Insuficiente de Tipos JSON**

**Severidade:** 🟢 **BAIXA**  
**CWE:** CWE-20 (Input Validation)  
**Status:** ✅ **CORRIGIDO**

**Problema:**
Alguns lugares usam `json_decode()` sem validar se o resultado é um array ou objeto, podendo causar erros de tipo.

**Localização:**
- Múltiplos controllers que usam `json_decode(file_get_contents('php://input'), true)`

**Correção Aplicada:**
✅ Melhorada validação de JSON em `App/Utils/RequestCache::getJsonInput()`:
- Valida tamanho máximo do JSON (1MB) para prevenir DoS
- Valida se houve erro no `json_decode()` usando `json_last_error()`
- Valida se o resultado é um array (não objeto ou outro tipo)
- Retorna `null` se qualquer validação falhar

✅ Adicionado método `Validator::validateJsonDecode()` para validação reutilizável.

✅ Validação aplicada em `AuthController` para garantir JSON válido.

**Localização das Correções:**
- `App/Utils/RequestCache.php:38-63` - validação melhorada
- `App/Utils/Validator.php:660-678` - método de validação
- `App/Controllers/AuthController.php:60-69` - validação no login

---

### 5. **Limites de Arrays Não Validados em Todos os Endpoints**

**Severidade:** 🟢 **BAIXA**  
**CWE:** CWE-400 (Resource Exhaustion)  
**Status:** ✅ **PARCIALMENTE CORRIGIDO**

**Problema:**
Embora `metadata` tenha validação de tamanho, outros arrays (como `line_items` em checkout) podem não ter limites adequados, permitindo DoS através de arrays muito grandes.

**Localização:**
- `App/Controllers/CheckoutController.php` - `line_items`
- Outros endpoints que aceitam arrays

**Correção Aplicada:**
✅ Criado método `Validator::validateArraySize()` para validação reutilizável de tamanho de arrays.

✅ Validação aplicada em:
- `CheckoutController` - `line_items` (máximo 100 itens) ✅
- `InvoiceItemController` - `tax_rates` (máximo 50 itens) ✅ (métodos create e update)

✅ Metadata já tem validação de tamanho (máximo 50 chaves) via `Validator::validateMetadata()`.

**Localização das Correções:**
- `App/Utils/Validator.php:639-658` - método `validateArraySize()`
- `App/Controllers/CheckoutController.php:79-82` - validação de line_items
- `App/Controllers/InvoiceItemController.php:77-83, 401-407` - validação de tax_rates

**Nota:** Outros arrays podem precisar de validação conforme novos endpoints forem adicionados. A estrutura está pronta para uso.

---

### 6. **Exposição de Informações em Mensagens de Erro de Desenvolvimento**

**Severidade:** 🟢 **BAIXA**  
**CWE:** CWE-209 (Information Exposure)  
**Status:** ✅ **CORRIGIDO**

**Problema:**
Algumas mensagens de erro em modo desenvolvimento podem expor informações sensíveis mesmo quando não deveriam.

**Localização:**
- `public/index.php:232` - expõe `server_keys` em desenvolvimento
- Vários controllers com `Config::isDevelopment() ? $e->getMessage() : null`

**Correção Aplicada:**
✅ Revisadas mensagens de erro em `public/index.php`:
- Substituído `server_keys` por `server_keys_count` e `has_authorization` (não expõe nomes de variáveis)
- Substituído `token_received` por `token_length` e `token_format_valid` (não expõe conteúdo do token)

✅ Informações sensíveis não são mais expostas mesmo em modo desenvolvimento.

**Localização das Correções:**
- `public/index.php:254-259` - mensagem de erro de autenticação
- `public/index.php:354-359` - mensagem de erro de token inválido

---

## ✅ MELHORIAS ADICIONAIS RECOMENDADAS

### 1. **Implementar CSRF Protection para Formulários HTML**

Embora APIs REST com Bearer tokens não precisem de CSRF, formulários HTML ainda devem ter proteção.

**Implementação:**
- Gerar tokens CSRF em sessão
- Validar tokens em todas as ações que modificam estado
- Incluir tokens em formulários e requisições AJAX

### 2. **Adicionar Validação de Rate Limiting por IP em Login**

O `AnomalyDetectionService` já existe, mas pode ser melhorado com rate limiting específico para IPs em endpoints de autenticação.

### 3. **Implementar Logging de Tentativas de Ataque**

Expandir o `AnomalyDetectionService` para detectar mais padrões:
- Tentativas de SQL Injection (padrões em queries)
- Tentativas de XSS (padrões em inputs)
- Tentativas de Path Traversal (padrões em paths)

### 4. **Adicionar Validação de Content-Type**

Validar que requisições POST/PUT/PATCH tenham `Content-Type: application/json`:

```php
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') === false) {
        Flight::json(['error' => 'Content-Type deve ser application/json'], 415);
        return;
    }
}
```

### 5. **Implementar Validação de Tamanho de Query String**

Limitar tamanho de query strings para prevenir DoS:

```php
$queryString = $_SERVER['QUERY_STRING'] ?? '';
if (strlen($queryString) > 2048) {
    Flight::json(['error' => 'Query string muito longa'], 414);
    return;
}
```

---

## 🎯 PLANO DE AÇÃO RECOMENDADO

### ✅ Fase 1 - Médio (Próximas 2 Semanas) - **CONCLUÍDA**
1. ✅ Corrigir IDOR em métodos auxiliares do CustomerController
2. ✅ Implementar validação de URLs em CheckoutController
3. ✅ Adicionar proteção contra timing attacks

### ✅ Fase 2 - Baixo (Próximo Mês) - **CONCLUÍDA**
4. ✅ Melhorar validação de tipos JSON
5. ✅ Adicionar limites de arrays em endpoints críticos (parcialmente - estrutura criada)
6. ✅ Revisar exposição de informações em modo desenvolvimento

### Fase 3 - Melhorias (Contínuo)
7. Implementar CSRF protection para formulários
8. Expandir detecção de anomalias
9. Adicionar validação de Content-Type
10. Implementar validação de tamanho de query string

---

## 📊 RESUMO DE VULNERABILIDADES

| Severidade | Quantidade | Status |
|------------|------------|--------|
| 🔴 Crítica | 0 | ✅ Todas corrigidas |
| 🟡 Média | 3 | ✅ Todas corrigidas |
| 🟢 Baixa | 3 | ✅ 2 corrigidas, 1 parcialmente corrigida |
| **Total** | **6** | **5 corrigidas, 1 parcialmente corrigida** |

---

## 📚 REFERÊNCIAS

- OWASP Top 10 2021: https://owasp.org/Top10/
- CWE Database: https://cwe.mitre.org/
- OWASP SSRF Prevention: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html
- OWASP Timing Attack: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

---

**FIM DO RELATÓRIO COMPLEMENTAR**


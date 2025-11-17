# 🔐 Guia de Implementação e Uso do Sistema de Login

## 📋 Visão Geral

O sistema de login implementado segue as melhores práticas de segurança e está integrado com o FlightPHP. Ele oferece:

- ✅ Autenticação segura com bcrypt
- ✅ Proteção contra brute force (rate limiting)
- ✅ Validação robusta de entrada
- ✅ Sessões com expiração automática
- ✅ Logs detalhados de segurança
- ✅ Isolamento por tenant

---

## 🏗️ Arquitetura

### Componentes Principais

```
┌─────────────────────────────────────────┐
│         AuthController                  │
│  - login()                              │
│  - logout()                             │
│  - me()                                 │
└─────────────────────────────────────────┘
              │
              ├─► LoginRateLimitMiddleware (proteção brute force)
              ├─► User Model (validação de credenciais)
              ├─► UserSession Model (gerenciamento de sessões)
              └─► Tenant Model (validação de tenant)
```

### Fluxo de Autenticação

```
1. Cliente → POST /v1/auth/login
   ↓
2. LoginRateLimitMiddleware::check()
   ├─ Verifica se IP não está bloqueado
   └─ Se bloqueado → Retorna 429 (Too Many Requests)
   ↓
3. AuthController::login()
   ├─ Valida entrada (email, senha, tenant_id)
   ├─ Busca usuário no banco
   ├─ Verifica senha (bcrypt)
   ├─ Verifica status (usuário e tenant ativos)
   └─ Cria sessão
   ↓
4. Retorna session_id
   ↓
5. Cliente usa session_id em requisições:
   Authorization: Bearer {session_id}
```

---

## 🔒 Segurança Implementada

### 1. Rate Limiting no Login

**Proteção contra Brute Force:**
- ✅ **5 tentativas por IP a cada 15 minutos**
- ✅ **10 tentativas por IP a cada 1 hora**
- ✅ Bloqueio automático após exceder limites
- ✅ Mensagem clara de quando poderá tentar novamente

**Como funciona:**
```php
// App/Middleware/LoginRateLimitMiddleware.php
- Verifica IP do cliente
- Conta tentativas falhas
- Bloqueia após exceder limite
- Retorna 429 com tempo de retry
```

### 2. Validação de Entrada

**Validações implementadas:**
- ✅ Email válido (formato e tamanho)
- ✅ Senha com mínimo de 6 caracteres
- ✅ Tenant ID válido (número positivo)
- ✅ Sanitização de dados (trim, etc.)

### 3. Hash de Senhas

**Usando bcrypt:**
```php
// App/Models/User.php
password_hash($password, PASSWORD_BCRYPT)
password_verify($password, $hash)
```

### 4. Sessões Seguras

**Características:**
- ✅ Tokens de 64 caracteres hexadecimais (32 bytes aleatórios)
- ✅ Expiração automática (padrão: 24 horas)
- ✅ Validação com JOIN em users e tenants
- ✅ Verificação de status ativo

---

## 📡 Endpoints da API

### 1. POST /v1/auth/login

**Autentica um usuário e retorna session_id.**

**Request:**
```json
{
  "email": "usuario@exemplo.com",
  "password": "senha123",
  "tenant_id": 1
}
```

**Response (Sucesso - 200):**
```json
{
  "success": true,
  "data": {
    "session_id": "abc123def456...",
    "user": {
      "id": 1,
      "email": "usuario@exemplo.com",
      "name": "João Silva",
      "role": "admin"
    },
    "tenant": {
      "id": 1,
      "name": "Empresa XYZ"
    }
  }
}
```

**Response (Erro - 400):**
```json
{
  "error": "Dados inválidos",
  "message": "Por favor, verifique os dados informados",
  "errors": {
    "email": "Email inválido",
    "password": "Senha deve ter no mínimo 6 caracteres"
  }
}
```

**Response (Erro - 401):**
```json
{
  "error": "Credenciais inválidas",
  "message": "Email ou senha incorretos"
}
```

**Response (Rate Limit - 429):**
```json
{
  "error": "Muitas tentativas de login",
  "message": "Você excedeu o limite de tentativas de login. Tente novamente mais tarde.",
  "retry_after": 3600,
  "retry_after_formatted": "1 horas"
}
```

---

### 2. POST /v1/auth/logout

**Encerra a sessão do usuário.**

**Headers:**
```
Authorization: Bearer {session_id}
```

**Response (Sucesso - 200):**
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

---

### 3. GET /v1/auth/me

**Retorna dados do usuário autenticado.**

**Headers:**
```
Authorization: Bearer {session_id}
```

**Response (Sucesso - 200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "usuario@exemplo.com",
      "name": "João Silva",
      "role": "admin"
    },
    "tenant": {
      "id": 1,
      "name": "Empresa XYZ"
    }
  }
}
```

**Response (Erro - 401):**
```json
{
  "error": "Sessão inválida",
  "message": "Sessão inválida ou expirada. Faça login novamente."
}
```

---

## 💻 Exemplos de Uso

### JavaScript (Fetch API)

```javascript
// Login
async function login(email, password, tenantId) {
  const response = await fetch('https://api.exemplo.com/v1/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      email: email,
      password: password,
      tenant_id: tenantId
    })
  });
  
  const data = await response.json();
  
  if (response.ok) {
    // Salva session_id
    localStorage.setItem('session_id', data.data.session_id);
    return data.data;
  } else {
    throw new Error(data.message || 'Erro ao fazer login');
  }
}

// Usar em requisições autenticadas
async function getSubscriptions() {
  const sessionId = localStorage.getItem('session_id');
  
  const response = await fetch('https://api.exemplo.com/v1/subscriptions', {
    headers: {
      'Authorization': `Bearer ${sessionId}`
    }
  });
  
  return await response.json();
}

// Logout
async function logout() {
  const sessionId = localStorage.getItem('session_id');
  
  await fetch('https://api.exemplo.com/v1/auth/logout', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${sessionId}`
    }
  });
  
  localStorage.removeItem('session_id');
}
```

### PHP (cURL)

```php
// Login
function login($email, $password, $tenantId) {
    $ch = curl_init('https://api.exemplo.com/v1/auth/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'password' => $password,
        'tenant_id' => $tenantId
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200) {
        return $data['data']['session_id'];
    }
    
    throw new Exception($data['message'] ?? 'Erro ao fazer login');
}

// Usar em requisições autenticadas
function getSubscriptions($sessionId) {
    $ch = curl_init('https://api.exemplo.com/v1/subscriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $sessionId
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

---

## 🔧 Configuração

### Rate Limiting

Os limites podem ser ajustados em `App/Middleware/LoginRateLimitMiddleware.php`:

```php
private const MAX_ATTEMPTS_PER_15MIN = 5;  // Tentativas a cada 15 min
private const MAX_ATTEMPTS_PER_HOUR = 10;   // Tentativas por hora
```

### Duração da Sessão

A duração padrão da sessão é de 24 horas. Pode ser alterada em `App/Models/UserSession.php`:

```php
public function create(..., int $hours = 24): string
```

---

## 📊 Logs de Segurança

O sistema registra automaticamente:

- ✅ Tentativas de login bem-sucedidas
- ✅ Tentativas de login falhas (com IP e email parcial)
- ✅ Bloqueios por rate limit
- ✅ Tentativas com usuários/tenants inativos
- ✅ Logouts

**Exemplo de log:**
```
[INFO] Login bem-sucedido
  user_id: 1
  email: usuario@exemplo.com
  tenant_id: 1
  ip: 192.168.1.100

[WARNING] Tentativa de login com senha incorreta
  user_id: 1
  email: usuario@exemplo.com
  ip: 192.168.1.100

[WARNING] Tentativa de login bloqueada por rate limit
  ip: 192.168.1.100
  attempts_15min: 6
  attempts_1hour: 11
```

---

## 🚨 Tratamento de Erros

### Códigos HTTP

- **200** - Sucesso
- **400** - Dados inválidos (validação)
- **401** - Não autenticado / Credenciais inválidas
- **403** - Usuário/Tenant inativo
- **429** - Rate limit excedido
- **500** - Erro interno do servidor

### Mensagens de Erro

Todas as respostas de erro seguem o padrão:
```json
{
  "error": "Tipo do erro",
  "message": "Mensagem amigável para o usuário",
  "errors": {} // Detalhes de validação (quando aplicável)
}
```

---

## ✅ Boas Práticas

1. **Sempre use HTTPS em produção**
   - Nunca envie credenciais via HTTP

2. **Armazene session_id com segurança**
   - Use `localStorage` ou `sessionStorage` no frontend
   - Não exponha em logs ou URLs

3. **Implemente refresh automático**
   - Verifique se a sessão ainda é válida periodicamente
   - Faça logout automático se expirar

4. **Trate rate limiting**
   - Mostre mensagem clara ao usuário
   - Implemente retry após o tempo indicado

5. **Valide dados no frontend também**
   - Não confie apenas na validação do backend
   - Melhore UX com validação em tempo real

---

## 🔄 Integração com Middleware de Autenticação

O sistema já está integrado com o middleware global em `public/index.php`:

```php
// O middleware verifica automaticamente:
// 1. Se é Session ID (usuário) → valida sessão
// 2. Se é API Key (tenant) → valida API key
// 3. Se é Master Key → permite acesso total
```

**Não é necessário fazer nada adicional** - o middleware já detecta e valida automaticamente o tipo de token.

---

## 📚 Referências

- [FlightPHP Documentation](https://docs.flightphp.com/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [PHP password_hash()](https://www.php.net/manual/pt_BR/function.password-hash.php)

---

## 🎯 Resumo

O sistema de login está **pronto para produção** com:

✅ Autenticação segura
✅ Proteção contra brute force
✅ Validação robusta
✅ Logs detalhados
✅ Integração completa com o sistema

**Basta usar os endpoints e seguir as boas práticas!** 🚀


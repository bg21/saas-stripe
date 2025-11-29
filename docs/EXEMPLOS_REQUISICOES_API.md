# Exemplos de Requisições e Respostas da API

Este documento contém exemplos práticos de requisições e respostas para os principais endpoints da API.

---

## Autenticação

### Login de Usuário

**Requisição:**
```http
POST /v1/auth/login
Content-Type: application/json

{
  "email": "admin@exemplo.com",
  "password": "senha123",
  "tenant_id": 1
}
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@exemplo.com",
      "role": "admin"
    },
    "session_id": "sess_abc123xyz",
    "expires_at": "2025-12-01T10:00:00Z"
  }
}
```

---

## Clientes

### Criar Cliente

**Requisição:**
```http
POST /v1/customers
Authorization: Bearer sua_api_key
Content-Type: application/json

{
  "email": "cliente@exemplo.com",
  "name": "João Silva",
  "phone": "+5511999999999",
  "metadata": {
    "source": "website"
  }
}
```

**Resposta (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "stripe_customer_id": "cus_abc123xyz",
    "email": "cliente@exemplo.com",
    "name": "João Silva",
    "phone": "+5511999999999",
    "created": "2025-11-29T10:00:00Z"
  }
}
```

### Listar Clientes

**Requisição:**
```http
GET /v1/customers?page=1&limit=20&search=joão
Authorization: Bearer sua_api_key
```

**Resposta (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "stripe_customer_id": "cus_abc123xyz",
      "email": "cliente@exemplo.com",
      "name": "João Silva",
      "created": "2025-11-29T10:00:00Z"
    }
  ],
  "meta": {
    "total": 1,
    "page": 1,
    "limit": 20,
    "total_pages": 1
  }
}
```

### Obter Cliente

**Requisição:**
```http
GET /v1/customers/1
Authorization: Bearer sua_api_key
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "stripe_customer_id": "cus_abc123xyz",
    "email": "cliente@exemplo.com",
    "name": "João Silva",
    "phone": "+5511999999999",
    "metadata": {
      "source": "website"
    },
    "created": "2025-11-29T10:00:00Z"
  }
}
```

---

## Assinaturas

### Criar Assinatura

**Requisição:**
```http
POST /v1/subscriptions
Authorization: Bearer sua_api_key
Content-Type: application/json

{
  "customer_id": 1,
  "price_id": "price_abc123xyz",
  "payment_method_id": "pm_abc123xyz",
  "metadata": {
    "plan": "professional"
  }
}
```

**Resposta (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "stripe_subscription_id": "sub_abc123xyz",
    "customer_id": 1,
    "status": "active",
    "current_period_start": "2025-11-29T10:00:00Z",
    "current_period_end": "2025-12-29T10:00:00Z",
    "cancel_at_period_end": false,
    "created": "2025-11-29T10:00:00Z"
  }
}
```

### Listar Assinaturas

**Requisição:**
```http
GET /v1/subscriptions?status=active&page=1&limit=20
Authorization: Bearer sua_api_key
```

**Resposta (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "stripe_subscription_id": "sub_abc123xyz",
      "customer_id": 1,
      "status": "active",
      "current_period_start": "2025-11-29T10:00:00Z",
      "current_period_end": "2025-12-29T10:00:00Z"
    }
  ],
  "meta": {
    "total": 1,
    "page": 1,
    "limit": 20,
    "total_pages": 1,
    "stats": {
      "active": 1,
      "canceled": 0,
      "past_due": 0
    }
  }
}
```

### Cancelar Assinatura

**Requisição:**
```http
DELETE /v1/subscriptions/1
Authorization: Bearer sua_api_key
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "canceled",
    "canceled_at": "2025-11-29T10:00:00Z",
    "cancel_at_period_end": true
  }
}
```

---

## Agendamentos

### Criar Agendamento

**Requisição:**
```http
POST /v1/appointments
Authorization: Bearer sua_api_key
Content-Type: application/json

{
  "professional_id": 1,
  "client_id": 1,
  "pet_id": 1,
  "appointment_date": "2025-12-01",
  "appointment_time": "14:00",
  "notes": "Consulta de rotina",
  "metadata": {
    "source": "website"
  }
}
```

**Resposta (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "professional_id": 1,
    "client_id": 1,
    "pet_id": 1,
    "appointment_date": "2025-12-01",
    "appointment_time": "14:00",
    "status": "scheduled",
    "notes": "Consulta de rotina",
    "created": "2025-11-29T10:00:00Z"
  }
}
```

### Buscar Horários Disponíveis

**Requisição:**
```http
GET /v1/appointments/available-slots?professional_id=1&date=2025-12-01
Authorization: Bearer sua_api_key
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "date": "2025-12-01",
    "professional_id": 1,
    "available_slots": [
      "09:00",
      "09:30",
      "10:00",
      "10:30",
      "14:00",
      "14:30",
      "15:00"
    ]
  }
}
```

### Confirmar Agendamento

**Requisição:**
```http
POST /v1/appointments/1/confirm
Authorization: Bearer sua_api_key
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "confirmed",
    "confirmed_at": "2025-11-29T10:00:00Z",
    "confirmed_by": 1
  }
}
```

---

## Checkout

### Criar Sessão de Checkout

**Requisição:**
```http
POST /v1/checkout
Authorization: Bearer sua_api_key
Content-Type: application/json

{
  "mode": "subscription",
  "price_id": "price_abc123xyz",
  "customer_id": 1,
  "success_url": "https://exemplo.com/success",
  "cancel_url": "https://exemplo.com/cancel",
  "metadata": {
    "tenant_id": 1
  }
}
```

**Resposta (201):**
```json
{
  "success": true,
  "data": {
    "id": "cs_abc123xyz",
    "url": "https://checkout.stripe.com/c/pay/cs_abc123xyz",
    "expires_at": "2025-11-29T10:15:00Z"
  }
}
```

---

## Produtos e Preços

### Criar Produto

**Requisição:**
```http
POST /v1/products
Authorization: Bearer sua_api_key
Content-Type: application/json

{
  "name": "Plano Profissional",
  "description": "Plano com recursos avançados",
  "metadata": {
    "plan_type": "professional"
  }
}
```

**Resposta (201):**
```json
{
  "success": true,
  "data": {
    "id": "prod_abc123xyz",
    "name": "Plano Profissional",
    "description": "Plano com recursos avançados",
    "active": true,
    "created": "2025-11-29T10:00:00Z"
  }
}
```

### Criar Preço

**Requisição:**
```http
POST /v1/prices
Authorization: Bearer sua_api_key
Content-Type: application/json

{
  "product_id": "prod_abc123xyz",
  "unit_amount": 9900,
  "currency": "brl",
  "recurring": {
    "interval": "month"
  },
  "metadata": {
    "tenant_id": 1
  }
}
```

**Resposta (201):**
```json
{
  "success": true,
  "data": {
    "id": "price_abc123xyz",
    "product_id": "prod_abc123xyz",
    "unit_amount": 9900,
    "currency": "brl",
    "recurring": {
      "interval": "month",
      "interval_count": 1
    },
    "active": true,
    "created": "2025-11-29T10:00:00Z"
  }
}
```

---

## Erros Comuns

### Erro de Validação (400)

**Resposta:**
```json
{
  "error": "Dados inválidos",
  "message": "Por favor, verifique os dados informados",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": "Email é obrigatório",
    "appointment_date": "Data deve ser no futuro"
  }
}
```

### Erro de Autenticação (401)

**Resposta:**
```json
{
  "error": "Não autenticado",
  "message": "Token de autenticação ausente ou inválido",
  "code": "UNAUTHORIZED"
}
```

### Recurso Não Encontrado (404)

**Resposta:**
```json
{
  "error": "Recurso não encontrado",
  "message": "Cliente não encontrado",
  "code": "NOT_FOUND"
}
```

### Rate Limit (429)

**Resposta:**
```json
{
  "error": "Limite excedido",
  "message": "Muitas requisições. Tente novamente em alguns segundos",
  "code": "RATE_LIMIT_EXCEEDED"
}
```

---

## Códigos de Status HTTP

| Código | Significado | Quando Usar |
|--------|-------------|-------------|
| 200 | OK | Requisição bem-sucedida |
| 201 | Created | Recurso criado com sucesso |
| 400 | Bad Request | Dados inválidos |
| 401 | Unauthorized | Não autenticado |
| 403 | Forbidden | Sem permissão |
| 404 | Not Found | Recurso não encontrado |
| 409 | Conflict | Conflito (ex: duplicado) |
| 429 | Too Many Requests | Rate limit excedido |
| 500 | Internal Server Error | Erro interno |

---

**Última Atualização:** 2025-11-29


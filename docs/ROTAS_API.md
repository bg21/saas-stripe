# Documentação Completa de Rotas da API

Este documento lista todas as rotas disponíveis na API, seus métodos HTTP, parâmetros, autenticação necessária e descrições detalhadas.

## 📋 Índice

- [Autenticação](#autenticação)
- [Rotas Públicas](#rotas-públicas)
- [Clientes (Customers)](#clientes-customers)
- [Checkout](#checkout)
- [Assinaturas (Subscriptions)](#assinaturas-subscriptions)
- [Webhooks](#webhooks)
- [Portal de Cobrança](#portal-de-cobrança)
- [Faturas (Invoices)](#faturas-invoices)
- [Preços (Prices)](#preços-prices)
- [Produtos (Products)](#produtos-products)
- [Pagamentos (Payment Intents)](#pagamentos-payment-intents)
- [Reembolsos (Refunds)](#reembolsos-refunds)
- [Estatísticas (Stats)](#estatísticas-stats)
- [Cupons (Coupons)](#cupons-coupons)
- [Códigos Promocionais](#códigos-promocionais)
- [Setup Intents](#setup-intents)
- [Subscription Items](#subscription-items)
- [Tax Rates](#tax-rates)
- [Invoice Items](#invoice-items)
- [Balance Transactions](#balance-transactions)
- [Disputes](#disputes)
- [Charges](#charges)
- [Audit Logs](#audit-logs)
- [Autenticação de Usuários](#autenticação-de-usuários)
- [Usuários](#usuários)
- [Permissões](#permissões)

---

## Autenticação

Todas as rotas (exceto as públicas) requerem autenticação via **Bearer Token** no header:

```
Authorization: Bearer sua_api_key_aqui
```

**Tipos de autenticação:**
- **API Key (Tenant)**: Token gerado para cada tenant (usado pela maioria dos endpoints)
- **Session ID (Usuário)**: Token retornado após login de usuário (`/v1/auth/login`)
- **Master Key**: Chave master configurada no `.env` (acesso total)

---

## Rotas Públicas

### GET `/`
**Descrição:** Informações básicas da API  
**Autenticação:** Não requer  
**Resposta:**
```json
{
  "name": "SaaS Payments API",
  "version": "1.0.0",
  "status": "ok",
  "endpoints": { ... }
}
```

### GET `/health`
**Descrição:** Health check básico  
**Autenticação:** Não requer  
**Resposta:** Status da API

### GET `/health/detailed`
**Descrição:** Health check detalhado (DB, Redis, Stripe)  
**Autenticação:** Não requer  
**Resposta:** Status detalhado de todos os serviços

### GET `/api-docs`
**Descrição:** Especificação OpenAPI/Swagger  
**Autenticação:** Não requer  
**Resposta:** JSON com especificação OpenAPI

### GET `/api-docs/ui`
**Descrição:** Interface Swagger UI  
**Autenticação:** Não requer  
**Resposta:** HTML da interface Swagger

---

## Clientes (Customers)

### POST `/v1/customers`
**Descrição:** Cria um novo cliente  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "email": "cliente@exemplo.com",
  "name": "Nome do Cliente",
  "metadata": {}
}
```
**Resposta:** Cliente criado com ID local e `stripe_customer_id`

### GET `/v1/customers`
**Descrição:** Lista todos os clientes do tenant  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:** Array de clientes

### GET `/v1/customers/:id`
**Descrição:** Obtém um cliente específico  
**Autenticação:** Requer (API Key ou Session ID)  
**Parâmetros:**
- `id` (path): ID do cliente no banco local

### PUT `/v1/customers/:id`
**Descrição:** Atualiza dados de um cliente  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "email": "novo@email.com",
  "name": "Novo Nome",
  "metadata": {}
}
```

### GET `/v1/customers/:id/invoices`
**Descrição:** Lista faturas de um cliente  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:** Array de faturas do Stripe

### GET `/v1/customers/:id/payment-methods`
**Descrição:** Lista métodos de pagamento de um cliente  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:** Array de métodos de pagamento

### PUT `/v1/customers/:id/payment-methods/:pm_id`
**Descrição:** Atualiza um método de pagamento  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "billing_details": {
    "name": "Nome",
    "email": "email@exemplo.com"
  }
}
```

### DELETE `/v1/customers/:id/payment-methods/:pm_id`
**Descrição:** Remove um método de pagamento  
**Autenticação:** Requer (API Key ou Session ID)

### POST `/v1/customers/:id/payment-methods/:pm_id/set-default`
**Descrição:** Define um método de pagamento como padrão  
**Autenticação:** Requer (API Key ou Session ID)

---

## Checkout

### POST `/v1/checkout`
**Descrição:** Cria uma sessão de checkout do Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "customer_id": 1,
  "price_id": "price_xxxxx",
  "success_url": "https://seu-site.com/success?session_id={CHECKOUT_SESSION_ID}",
  "cancel_url": "https://seu-site.com/cancel",
  "metadata": {}
}
```
**Resposta:**
```json
{
  "success": true,
  "data": {
    "session_id": "cs_test_xxxxx",
    "url": "https://checkout.stripe.com/c/pay/..."
  }
}
```

### GET `/v1/checkout/:id`
**Descrição:** Obtém informações de uma sessão de checkout  
**Autenticação:** Requer (API Key ou Session ID)  
**Parâmetros:**
- `id` (path): ID da sessão de checkout (`cs_test_xxxxx`)

---

## Assinaturas (Subscriptions)

### POST `/v1/subscriptions`
**Descrição:** Cria uma nova assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "customer_id": 1,
  "price_id": "price_xxxxx",
  "trial_period_days": 14,
  "payment_behavior": "default_incomplete",
  "metadata": {}
}
```

### GET `/v1/subscriptions`
**Descrição:** Lista todas as assinaturas do tenant  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:** Array de assinaturas

### GET `/v1/subscriptions/:id`
**Descrição:** Obtém uma assinatura específica  
**Autenticação:** Requer (API Key ou Session ID)  
**Parâmetros:**
- `id` (path): ID da assinatura no banco local

### PUT `/v1/subscriptions/:id`
**Descrição:** Atualiza uma assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "price_id": "price_novo",
  "cancel_at_period_end": false,
  "metadata": {}
}
```

### DELETE `/v1/subscriptions/:id`
**Descrição:** Cancela uma assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Query Parameters:**
- `immediately` (opcional): Se `true`, cancela imediatamente. Se `false` ou omitido, cancela no final do período

### POST `/v1/subscriptions/:id/reactivate`
**Descrição:** Reativa uma assinatura cancelada  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/subscriptions/:id/history`
**Descrição:** Obtém histórico de mudanças de uma assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:** Array com histórico de mudanças

### GET `/v1/subscriptions/:id/history/stats`
**Descrição:** Obtém estatísticas do histórico de uma assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:** Estatísticas agregadas do histórico

---

## Webhooks

### POST `/v1/webhook`
**Descrição:** Endpoint para receber webhooks do Stripe  
**Autenticação:** Não requer (validação via signature do Stripe)  
**Headers:**
- `Stripe-Signature`: Assinatura do webhook (enviada pelo Stripe)

**Nota:** Configure este endpoint no Stripe Dashboard ou use Stripe CLI para desenvolvimento local.

---

## Portal de Cobrança

### POST `/v1/billing-portal`
**Descrição:** Cria uma sessão do portal de cobrança do Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "customer_id": 1,
  "return_url": "https://seu-site.com/dashboard"
}
```
**Resposta:**
```json
{
  "success": true,
  "data": {
    "url": "https://billing.stripe.com/session/..."
  }
}
```

---

## Faturas (Invoices)

### GET `/v1/invoices/:id`
**Descrição:** Obtém uma fatura específica do Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Parâmetros:**
- `id` (path): ID da fatura no Stripe (`in_xxxxx`)

---

## Preços (Prices)

### GET `/v1/prices`
**Descrição:** Lista todos os preços do Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Query Parameters:**
- `active` (opcional): Filtrar por preços ativos (`true`/`false`)
- `product` (opcional): Filtrar por produto (`prod_xxxxx`)

### POST `/v1/prices`
**Descrição:** Cria um novo preço no Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "product": "prod_xxxxx",
  "unit_amount": 2999,
  "currency": "brl",
  "recurring": {
    "interval": "month"
  }
}
```

### GET `/v1/prices/:id`
**Descrição:** Obtém um preço específico  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/prices/:id`
**Descrição:** Atualiza um preço (apenas metadata)  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "metadata": {}
}
```

---

## Produtos (Products)

### GET `/v1/products`
**Descrição:** Lista todos os produtos do Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Query Parameters:**
- `limit` (opcional): Número máximo de resultados (padrão: 10)
- `starting_after` (opcional): ID do produto para paginação
- `ending_before` (opcional): ID do produto para paginação reversa
- `active` (opcional): Filtrar por produtos ativos (`true`/`false`)

**Resposta:**
```json
{
  "success": true,
  "data": [
    {
      "id": "prod_xxxxx",
      "name": "Plano Premium",
      "description": "Descrição do plano",
      "active": true,
      "images": [],
      "created": "2024-01-01 10:00:00",
      "updated": "2024-01-01 10:00:00",
      "metadata": {
        "tenant_id": "3"
      }
    }
  ],
  "has_more": false,
  "count": 5
}
```

### POST `/v1/products`
**Descrição:** Cria um novo produto no Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "name": "Plano Premium",
  "description": "Descrição do plano",
  "metadata": {}
}
```

### GET `/v1/products/:id`
**Descrição:** Obtém um produto específico  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/products/:id`
**Descrição:** Atualiza um produto  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "name": "Novo Nome",
  "description": "Nova descrição",
  "metadata": {}
}
```

### DELETE `/v1/products/:id`
**Descrição:** Remove um produto (arquiva)  
**Autenticação:** Requer (API Key ou Session ID)

---

## Pagamentos (Payment Intents)

### POST `/v1/payment-intents`
**Descrição:** Cria um Payment Intent para pagamento único  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "amount": 2999,
  "currency": "brl",
  "customer_id": 1,
  "description": "Descrição do pagamento",
  "metadata": {}
}
```

---

## Reembolsos (Refunds)

### POST `/v1/refunds`
**Descrição:** Cria um reembolso  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "charge_id": "ch_xxxxx",
  "amount": 2999,
  "reason": "requested_by_customer",
  "metadata": {}
}
```

---

## Estatísticas (Stats)

### GET `/v1/stats`
**Descrição:** Obtém estatísticas gerais do tenant  
**Autenticação:** Requer (API Key ou Session ID)  
**Resposta:**
```json
{
  "customers": {
    "total": 100,
    "active": 80
  },
  "subscriptions": {
    "total": 50,
    "active": 45
  },
  "revenue": {
    "total": 10000.00,
    "currency": "BRL"
  }
}
```

---

## Cupons (Coupons)

### POST `/v1/coupons`
**Descrição:** Cria um cupom de desconto  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "id": "desconto10",
  "percent_off": 10,
  "duration": "once",
  "metadata": {}
}
```

### GET `/v1/coupons`
**Descrição:** Lista todos os cupons  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/coupons/:id`
**Descrição:** Obtém um cupom específico  
**Autenticação:** Requer (API Key ou Session ID)

### DELETE `/v1/coupons/:id`
**Descrição:** Remove um cupom  
**Autenticação:** Requer (API Key ou Session ID)

---

## Códigos Promocionais

### POST `/v1/promotion-codes`
**Descrição:** Cria um código promocional  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "coupon": "desconto10",
  "code": "PROMO10",
  "metadata": {}
}
```

### GET `/v1/promotion-codes`
**Descrição:** Lista códigos promocionais  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/promotion-codes/:id`
**Descrição:** Obtém um código promocional específico  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/promotion-codes/:id`
**Descrição:** Atualiza um código promocional  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "active": true,
  "metadata": {}
}
```

---

## Setup Intents

### POST `/v1/setup-intents`
**Descrição:** Cria um Setup Intent para salvar método de pagamento sem cobrança  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "customer_id": 1,
  "payment_method_types": ["card"]
}
```

### GET `/v1/setup-intents/:id`
**Descrição:** Obtém um Setup Intent específico  
**Autenticação:** Requer (API Key ou Session ID)

### POST `/v1/setup-intents/:id/confirm`
**Descrição:** Confirma um Setup Intent  
**Autenticação:** Requer (API Key ou Session ID)

---

## Subscription Items

### POST `/v1/subscriptions/:subscription_id/items`
**Descrição:** Adiciona um item a uma assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "price_id": "price_xxxxx",
  "quantity": 1
}
```

### GET `/v1/subscriptions/:subscription_id/items`
**Descrição:** Lista itens de uma assinatura  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/subscription-items/:id`
**Descrição:** Obtém um item de assinatura específico  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/subscription-items/:id`
**Descrição:** Atualiza um item de assinatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "price_id": "price_novo",
  "quantity": 2
}
```

### DELETE `/v1/subscription-items/:id`
**Descrição:** Remove um item de assinatura  
**Autenticação:** Requer (API Key ou Session ID)

---

## Tax Rates

### POST `/v1/tax-rates`
**Descrição:** Cria uma taxa de imposto  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "display_name": "IVA",
  "percentage": 21,
  "inclusive": false
}
```

### GET `/v1/tax-rates`
**Descrição:** Lista taxas de imposto  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/tax-rates/:id`
**Descrição:** Obtém uma taxa de imposto específica  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/tax-rates/:id`
**Descrição:** Atualiza uma taxa de imposto  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "active": true,
  "metadata": {}
}
```

---

## Invoice Items

### POST `/v1/invoice-items`
**Descrição:** Cria um item de fatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "customer_id": 1,
  "amount": 1000,
  "currency": "brl",
  "description": "Item adicional"
}
```

### GET `/v1/invoice-items`
**Descrição:** Lista itens de fatura  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/invoice-items/:id`
**Descrição:** Obtém um item de fatura específico  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/invoice-items/:id`
**Descrição:** Atualiza um item de fatura  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "amount": 2000,
  "description": "Nova descrição"
}
```

### DELETE `/v1/invoice-items/:id`
**Descrição:** Remove um item de fatura  
**Autenticação:** Requer (API Key ou Session ID)

---

## Balance Transactions

### GET `/v1/balance-transactions`
**Descrição:** Lista transações de saldo do Stripe  
**Autenticação:** Requer (API Key ou Session ID)  
**Query Parameters:**
- `limit` (opcional): Número de resultados (padrão: 10)
- `starting_after` (opcional): ID para paginação

### GET `/v1/balance-transactions/:id`
**Descrição:** Obtém uma transação de saldo específica  
**Autenticação:** Requer (API Key ou Session ID)

---

## Disputes

### GET `/v1/disputes`
**Descrição:** Lista disputas/chargebacks  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/disputes/:id`
**Descrição:** Obtém uma disputa específica  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/disputes/:id`
**Descrição:** Atualiza uma disputa (adiciona evidências)  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "evidence": {
    "customer_communication": "...",
    "uncategorized_file": "file_xxxxx"
  }
}
```

---

## Charges

### GET `/v1/charges`
**Descrição:** Lista cobranças  
**Autenticação:** Requer (API Key ou Session ID)

### GET `/v1/charges/:id`
**Descrição:** Obtém uma cobrança específica  
**Autenticação:** Requer (API Key ou Session ID)

### PUT `/v1/charges/:id`
**Descrição:** Atualiza uma cobrança (apenas metadata)  
**Autenticação:** Requer (API Key ou Session ID)  
**Body:**
```json
{
  "metadata": {}
}
```

---

## Audit Logs

### GET `/v1/audit-logs`
**Descrição:** Lista logs de auditoria  
**Autenticação:** Requer (API Key ou Session ID)  
**Query Parameters:**
- `limit` (opcional): Número de resultados
- `offset` (opcional): Offset para paginação
- `action` (opcional): Filtrar por ação
- `user_id` (opcional): Filtrar por usuário

### GET `/v1/audit-logs/:id`
**Descrição:** Obtém um log de auditoria específico  
**Autenticação:** Requer (API Key ou Session ID)

---

## Autenticação de Usuários

### POST `/v1/auth/login`
**Descrição:** Faz login de um usuário  
**Autenticação:** Não requer (pública)  
**Body:**
```json
{
  "email": "usuario@exemplo.com",
  "password": "senha123",
  "tenant_id": 1
}
```
**Resposta:**
```json
{
  "success": true,
  "data": {
    "token": "session_id_xxxxx",
    "user": {
      "id": 1,
      "email": "usuario@exemplo.com",
      "name": "Nome do Usuário",
      "role": "admin"
    }
  }
}
```

### POST `/v1/auth/logout`
**Descrição:** Faz logout de um usuário (invalida sessão)  
**Autenticação:** Requer (Session ID)

### GET `/v1/auth/me`
**Descrição:** Obtém informações do usuário autenticado  
**Autenticação:** Requer (Session ID)  
**Resposta:** Dados do usuário atual

---

## Usuários

**Nota:** Todas as rotas de usuários requerem permissão de **admin**.

### GET `/v1/users`
**Descrição:** Lista todos os usuários do tenant  
**Autenticação:** Requer (Session ID com role admin)

### GET `/v1/users/:id`
**Descrição:** Obtém um usuário específico  
**Autenticação:** Requer (Session ID com role admin)

### POST `/v1/users`
**Descrição:** Cria um novo usuário  
**Autenticação:** Requer (Session ID com role admin)  
**Body:**
```json
{
  "email": "novo@exemplo.com",
  "password": "senha123",
  "name": "Nome do Usuário",
  "role": "editor"
}
```

### PUT `/v1/users/:id`
**Descrição:** Atualiza um usuário  
**Autenticação:** Requer (Session ID com role admin)  
**Body:**
```json
{
  "name": "Novo Nome",
  "email": "novo@email.com",
  "status": "active"
}
```

### DELETE `/v1/users/:id`
**Descrição:** Remove um usuário  
**Autenticação:** Requer (Session ID com role admin)

### PUT `/v1/users/:id/role`
**Descrição:** Atualiza o role de um usuário  
**Autenticação:** Requer (Session ID com role admin)  
**Body:**
```json
{
  "role": "admin"
}
```

---

## Permissões

**Nota:** Todas as rotas de permissões requerem permissão de **admin**.

### GET `/v1/permissions`
**Descrição:** Lista todas as permissões disponíveis  
**Autenticação:** Requer (Session ID com role admin)

### GET `/v1/users/:id/permissions`
**Descrição:** Lista permissões de um usuário  
**Autenticação:** Requer (Session ID com role admin)

### POST `/v1/users/:id/permissions`
**Descrição:** Concede uma permissão a um usuário  
**Autenticação:** Requer (Session ID com role admin)  
**Body:**
```json
{
  "permission": "create_customers"
}
```

### DELETE `/v1/users/:id/permissions/:permission`
**Descrição:** Revoga uma permissão de um usuário  
**Autenticação:** Requer (Session ID com role admin)

---

## 📝 Notas Importantes

### IDs nos Paths

- **IDs numéricos** (ex: `/v1/customers/1`): IDs do banco de dados local
- **IDs com prefixo** (ex: `/v1/checkout/cs_test_xxxxx`): IDs do Stripe

### Formato de Resposta

Todas as rotas retornam JSON no formato:

```json
{
  "success": true,
  "data": { ... }
}
```

Em caso de erro:

```json
{
  "error": "Mensagem de erro",
  "message": "Detalhes adicionais (apenas em desenvolvimento)"
}
```

### Códigos de Status HTTP

- `200`: Sucesso
- `201`: Criado com sucesso
- `400`: Erro de validação
- `401`: Não autenticado
- `403`: Sem permissão
- `404`: Não encontrado
- `429`: Rate limit excedido
- `500`: Erro interno do servidor

### Rate Limiting

A API possui rate limiting configurado. Em caso de exceder o limite, você receberá status `429` com headers:
- `X-RateLimit-Limit`: Limite de requisições
- `X-RateLimit-Remaining`: Requisições restantes
- `X-RateLimit-Reset`: Timestamp de reset

---

## 🔗 Arquivos Relacionados

- **Controllers:** `App/Controllers/`
- **Rotas:** `public/index.php`
- **Documentação Swagger:** `/api-docs/ui`
- **SDK PHP:** `sdk/PaymentsClient.php`
- **Exemplos Front-end:** `docs/exemplos/`

---

**Última atualização:** Baseado no código em `public/index.php` e controllers em `App/Controllers/`


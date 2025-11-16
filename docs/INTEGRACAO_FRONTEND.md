# 🌐 Guia Completo de Integração Front-End

## 📋 Índice

1. [Visão Geral da Arquitetura](#visão-geral-da-arquitetura)
2. [Autenticação](#autenticação)
3. [Configuração de CORS](#configuração-de-cors)
4. [Estrutura da API](#estrutura-da-api)
5. [Exemplos Práticos](#exemplos-práticos)
6. [SDK e Clientes](#sdk-e-clientes)
7. [Fluxo Completo de Checkout](#fluxo-completo-de-checkout)
8. [Webhooks](#webhooks)
9. [Tratamento de Erros](#tratamento-de-erros)
10. [Boas Práticas](#boas-práticas)
11. [Segurança](#segurança)

---

## 🏗️ Visão Geral da Arquitetura

### Arquitetura Separada (Front-End + Back-End)

```
┌─────────────────┐         HTTP/HTTPS          ┌─────────────────┐
│                 │    ──────────────────────>   │                 │
│   Front-End     │    <──────────────────────   │   Back-End      │
│   (React/Vue/   │         JSON Responses        │   (PHP API)     │
│   Angular/etc)  │                               │                 │
│                 │                               │                 │
│  - Interface    │                               │  - Controllers  │
│  - UX/UI        │                               │  - Services     │
│  - Validações   │                               │  - Models       │
│  - Estado       │                               │  - Stripe API   │
└─────────────────┘                               └─────────────────┘
        │                                                  │
        │                                                  │
        └──────────────────────────────────────────────────┘
                          Stripe Checkout
                          (Redirecionamento)
```

### Características da Integração

- **Backend**: API REST em PHP (FlightPHP) rodando em servidor separado
- **Frontend**: Qualquer framework (React, Vue, Angular, Vanilla JS, etc.)
- **Comunicação**: HTTP/HTTPS com JSON
- **Autenticação**: Bearer Token (API Key ou Session ID)
- **CORS**: Configurado para permitir requisições de qualquer origem (ajustável)

---

## 🔐 Autenticação

### Dois Tipos de Autenticação

O sistema suporta **dois tipos de autenticação**:

#### 1. **API Key (Tenant)** - Para Integração de Sistemas

Usado quando o front-end faz requisições em nome do sistema SaaS (tenant).

```javascript
// Exemplo: Front-end fazendo requisições com API Key do tenant
const API_BASE_URL = 'https://pagamentos.seudominio.com';
const API_KEY = 'sua_api_key_do_tenant_aqui';

// Headers para todas as requisições
const headers = {
    'Authorization': `Bearer ${API_KEY}`,
    'Content-Type': 'application/json'
};
```

#### 2. **Session ID (Usuário)** - Para Autenticação de Usuários

Usado quando o front-end precisa autenticar usuários específicos do sistema.

```javascript
// 1. Login do usuário
const loginResponse = await fetch(`${API_BASE_URL}/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        email: 'usuario@example.com',
        password: 'senha123'
    })
});

const { data } = await loginResponse.json();
const sessionId = data.session_id; // Guarde este token

// 2. Usar Session ID nas requisições subsequentes
const headers = {
    'Authorization': `Bearer ${sessionId}`,
    'Content-Type': 'application/json'
};
```

### Como Obter a API Key

A API Key é gerada quando você cria um tenant no sistema. Você pode:

1. **Criar via script** (`scripts/setup_tenant.php`)
2. **Criar via banco de dados** (inserir na tabela `tenants`)
3. **Criar via endpoint administrativo** (se implementado)

A API Key é única por tenant e deve ser mantida em **segredo**.

---

## 🌍 Configuração de CORS

### Configuração Atual

O backend já está configurado com CORS básico em `public/index.php`:

```php
// Middleware de CORS
$app->before('start', function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
});
```

### ⚠️ Ajuste para Produção

Para produção, **restrinja as origens permitidas**:

```php
// Exemplo: Permitir apenas seu domínio
$allowedOrigins = [
    'https://meu-saas.com',
    'https://www.meu-saas.com',
    'https://app.meu-saas.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
```

Ou configure via variável de ambiente:

```env
# .env
CORS_ALLOWED_ORIGINS=https://meu-saas.com,https://app.meu-saas.com
```

---

## 📡 Estrutura da API

### Base URL

```
https://pagamentos.seudominio.com
```

### Formato de Resposta

Todas as respostas seguem o padrão:

```json
{
    "success": true,
    "data": {
        // Dados da resposta
    },
    "message": "Operação realizada com sucesso"
}
```

### Formato de Erro

```json
{
    "success": false,
    "error": "Mensagem de erro",
    "code": "ERROR_CODE",
    "debug": {
        // Apenas em desenvolvimento
    }
}
```

### Status HTTP

- `200` - Sucesso
- `201` - Criado com sucesso
- `400` - Erro de validação
- `401` - Não autenticado
- `403` - Sem permissão
- `404` - Não encontrado
- `429` - Rate limit excedido
- `500` - Erro interno do servidor

---

## 💻 Exemplos Práticos

### Cliente JavaScript/TypeScript Básico

```javascript
// api-client.js
class PaymentsAPI {
    constructor(baseUrl, apiKey) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.apiKey = apiKey;
    }

    async request(method, endpoint, data = null) {
        const url = `${this.baseUrl}${endpoint}`;
        const options = {
            method,
            headers: {
                'Authorization': `Bearer ${this.apiKey}`,
                'Content-Type': 'application/json'
            }
        };

        if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || `HTTP ${response.status}`);
        }

        return result;
    }

    // Customers
    async createCustomer(email, name, metadata = {}) {
        return this.request('POST', '/v1/customers', {
            email,
            name,
            metadata
        });
    }

    async getCustomer(customerId) {
        return this.request('GET', `/v1/customers/${customerId}`);
    }

    async listCustomers() {
        return this.request('GET', '/v1/customers');
    }

    // Checkout
    async createCheckout(customerId, priceId, successUrl, cancelUrl, metadata = {}) {
        return this.request('POST', '/v1/checkout', {
            customer_id: customerId,
            price_id: priceId,
            success_url: successUrl,
            cancel_url: cancelUrl,
            metadata
        });
    }

    // Subscriptions
    async createSubscription(customerId, priceId, trialPeriodDays = null, metadata = {}) {
        const data = {
            customer_id: customerId,
            price_id: priceId,
            metadata
        };
        if (trialPeriodDays) {
            data.trial_period_days = trialPeriodDays;
        }
        return this.request('POST', '/v1/subscriptions', data);
    }

    async cancelSubscription(subscriptionId, immediately = false) {
        const endpoint = `/v1/subscriptions/${subscriptionId}`;
        return this.request('DELETE', immediately ? `${endpoint}?immediately=true` : endpoint);
    }

    // Prices
    async listPrices() {
        return this.request('GET', '/v1/prices');
    }
}

// Uso
const api = new PaymentsAPI(
    'https://pagamentos.seudominio.com',
    'sua_api_key_aqui'
);
```

### Exemplo React Hook

```jsx
// usePayments.js
import { useState, useCallback } from 'react';
import { PaymentsAPI } from './api-client';

const api = new PaymentsAPI(
    process.env.REACT_APP_API_BASE_URL,
    process.env.REACT_APP_API_KEY
);

export function usePayments() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const createCustomer = useCallback(async (email, name) => {
        setLoading(true);
        setError(null);
        try {
            const result = await api.createCustomer(email, name);
            return result.data;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const createCheckout = useCallback(async (customerId, priceId, successUrl, cancelUrl) => {
        setLoading(true);
        setError(null);
        try {
            const result = await api.createCheckout(customerId, priceId, successUrl, cancelUrl);
            // Redireciona para o checkout
            window.location.href = result.data.url;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        loading,
        error,
        createCustomer,
        createCheckout,
        // ... outros métodos
    };
}
```

### Exemplo Vue.js Composable

```javascript
// composables/usePayments.js
import { ref } from 'vue';
import { PaymentsAPI } from '@/services/api-client';

const api = new PaymentsAPI(
    import.meta.env.VITE_API_BASE_URL,
    import.meta.env.VITE_API_KEY
);

export function usePayments() {
    const loading = ref(false);
    const error = ref(null);

    const createCustomer = async (email, name) => {
        loading.value = true;
        error.value = null;
        try {
            const result = await api.createCustomer(email, name);
            return result.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const createCheckout = async (customerId, priceId, successUrl, cancelUrl) => {
        loading.value = true;
        error.value = null;
        try {
            const result = await api.createCheckout(customerId, priceId, successUrl, cancelUrl);
            window.location.href = result.data.url;
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        createCustomer,
        createCheckout,
    };
}
```

---

## 🛒 Fluxo Completo de Checkout

### Fluxo 1: Checkout com Redirecionamento (Recomendado)

```javascript
// 1. Usuário seleciona um plano
async function handleSubscribe(priceId) {
    try {
        // 2. Criar ou obter customer
        let customer;
        const existingCustomerId = localStorage.getItem('customer_id');
        
        if (existingCustomerId) {
            customer = await api.getCustomer(existingCustomerId);
        } else {
            const email = document.getElementById('email').value;
            const name = document.getElementById('name').value;
            const result = await api.createCustomer(email, name);
            customer = result.data;
            localStorage.setItem('customer_id', customer.id);
        }

        // 3. Criar sessão de checkout
        const checkoutResult = await api.createCheckout(
            customer.id,
            priceId,
            `${window.location.origin}/success`,
            `${window.location.origin}/cancel`
        );

        // 4. Redirecionar para Stripe Checkout
        window.location.href = checkoutResult.data.url;
    } catch (error) {
        console.error('Erro ao criar checkout:', error);
        alert('Erro ao processar pagamento. Tente novamente.');
    }
}
```

### Fluxo 2: Checkout com Payment Method (Salvar Cartão)

```javascript
// 1. Criar Setup Intent para salvar cartão sem cobrar
async function savePaymentMethod(customerId) {
    try {
        const result = await api.request('POST', '/v1/setup-intents', {
            customer_id: customerId
        });

        // 2. Usar Stripe.js para coletar dados do cartão
        const stripe = Stripe('pk_test_...');
        const { setupIntent, error } = await stripe.confirmCardSetup(
            result.data.client_secret,
            {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: 'Nome do Cliente'
                    }
                }
            }
        );

        if (error) {
            throw new Error(error.message);
        }

        // 3. Confirmar setup intent no backend
        await api.request('POST', `/v1/setup-intents/${setupIntent.id}/confirm`);

        // 4. Agora pode criar assinatura usando o payment method salvo
        await createSubscription(customerId, priceId);
    } catch (error) {
        console.error('Erro ao salvar método de pagamento:', error);
    }
}
```

### Página de Sucesso (Callback)

```javascript
// success.html ou success.jsx
async function handleSuccess() {
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');

    if (sessionId) {
        try {
            // Verificar status do checkout
            const result = await api.getCheckout(sessionId);
            
            if (result.data.payment_status === 'paid') {
                // Pagamento confirmado
                // Redirecionar para dashboard ou página de boas-vindas
                window.location.href = '/dashboard';
            } else {
                // Pagamento pendente ou falhou
                alert('Pagamento ainda não foi confirmado.');
            }
        } catch (error) {
            console.error('Erro ao verificar checkout:', error);
        }
    }
}
```

---

## 🔔 Webhooks

### Configuração de Webhook no Stripe

1. Acesse [Stripe Dashboard](https://dashboard.stripe.com/webhooks)
2. Adicione endpoint: `https://pagamentos.seudominio.com/v1/webhook`
3. Selecione eventos:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - E outros conforme necessário

### Recebendo Webhooks no Front-End

**⚠️ IMPORTANTE**: Webhooks são recebidos pelo **backend**, não pelo front-end.

O front-end deve **consultar o backend** para saber o status atualizado:

```javascript
// Polling (não recomendado para produção)
async function checkSubscriptionStatus(subscriptionId) {
    const result = await api.getSubscription(subscriptionId);
    return result.data.status; // active, canceled, past_due, etc.
}

// Ou usar WebSockets/Server-Sent Events (se implementado)
// Ou consultar quando o usuário acessa a página
```

### Alternativa: WebSockets ou Server-Sent Events

Se você quiser atualizações em tempo real, implemente no backend:

```php
// Exemplo: Endpoint para Server-Sent Events
$app->route('GET /v1/subscriptions/:id/stream', function($id) use ($app) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // Envia atualizações quando houver mudanças
    while (true) {
        $subscription = $subscriptionModel->find($id);
        echo "data: " . json_encode($subscription) . "\n\n";
        flush();
        sleep(5); // Verifica a cada 5 segundos
    }
});
```

---

## ⚠️ Tratamento de Erros

### Cliente com Tratamento de Erros Robusto

```javascript
class PaymentsAPI {
    // ... código anterior ...

    async request(method, endpoint, data = null) {
        const url = `${this.baseUrl}${endpoint}`;
        const options = {
            method,
            headers: {
                'Authorization': `Bearer ${this.apiKey}`,
                'Content-Type': 'application/json'
            }
        };

        if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                // Tratamento específico por status
                switch (response.status) {
                    case 401:
                        throw new AuthenticationError('Não autenticado. Verifique sua API key.');
                    case 403:
                        throw new PermissionError('Sem permissão para esta ação.');
                    case 404:
                        throw new NotFoundError(result.error || 'Recurso não encontrado.');
                    case 429:
                        throw new RateLimitError('Muitas requisições. Tente novamente mais tarde.');
                    case 500:
                        throw new ServerError('Erro interno do servidor. Tente novamente mais tarde.');
                    default:
                        throw new APIError(result.error || `Erro HTTP ${response.status}`);
                }
            }

            return result;
        } catch (error) {
            // Erro de rede
            if (error instanceof TypeError && error.message.includes('fetch')) {
                throw new NetworkError('Erro de conexão. Verifique sua internet.');
            }
            throw error;
        }
    }
}

// Classes de erro customizadas
class APIError extends Error {
    constructor(message, code = null) {
        super(message);
        this.name = 'APIError';
        this.code = code;
    }
}

class AuthenticationError extends APIError {
    constructor(message) {
        super(message, 'AUTH_ERROR');
        this.name = 'AuthenticationError';
    }
}

class PermissionError extends APIError {
    constructor(message) {
        super(message, 'PERMISSION_ERROR');
        this.name = 'PermissionError';
    }
}

class RateLimitError extends APIError {
    constructor(message) {
        super(message, 'RATE_LIMIT_ERROR');
        this.name = 'RateLimitError';
    }
}
```

### Uso com Try/Catch

```javascript
try {
    const customer = await api.createCustomer('email@example.com', 'Nome');
    console.log('Cliente criado:', customer);
} catch (error) {
    if (error instanceof AuthenticationError) {
        // Redirecionar para login ou mostrar erro de autenticação
        showError('Sua sessão expirou. Faça login novamente.');
    } else if (error instanceof RateLimitError) {
        // Mostrar mensagem de rate limit
        showError('Muitas requisições. Aguarde um momento.');
    } else {
        // Erro genérico
        showError('Erro ao processar solicitação. Tente novamente.');
    }
    console.error('Erro:', error);
}
```

---

## 📦 SDK e Clientes

### SDK PHP (Já Implementado)

Existe um SDK PHP em `sdk/PaymentsClient.php`. Veja `sdk/README.md` para detalhes.

### Criando SDK para Outras Linguagens

#### SDK TypeScript/JavaScript

```typescript
// payments-sdk.ts
export class PaymentsClient {
    constructor(
        private baseUrl: string,
        private apiKey: string
    ) {}

    private async request<T>(
        method: string,
        endpoint: string,
        data?: any
    ): Promise<{ success: boolean; data: T; message?: string }> {
        const response = await fetch(`${this.baseUrl}${endpoint}`, {
            method,
            headers: {
                'Authorization': `Bearer ${this.apiKey}`,
                'Content-Type': 'application/json'
            },
            body: data ? JSON.stringify(data) : undefined
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || `HTTP ${response.status}`);
        }

        return result;
    }

    async createCustomer(email: string, name?: string, metadata?: Record<string, any>) {
        return this.request('POST', '/v1/customers', { email, name, metadata });
    }

    // ... outros métodos
}
```

#### SDK Python

```python
# payments_client.py
import requests
from typing import Optional, Dict, Any

class PaymentsClient:
    def __init__(self, base_url: str, api_key: str):
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json'
        }

    def _request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> Dict:
        url = f"{self.base_url}{endpoint}"
        response = requests.request(
            method,
            url,
            headers=self.headers,
            json=data
        )
        response.raise_for_status()
        return response.json()

    def create_customer(self, email: str, name: Optional[str] = None, metadata: Optional[Dict] = None) -> Dict:
        return self._request('POST', '/v1/customers', {
            'email': email,
            'name': name,
            'metadata': metadata or {}
        })

    # ... outros métodos
```

---

## ✅ Boas Práticas

### 1. **Armazenamento Seguro de API Key**

❌ **NÃO faça isso:**
```javascript
// NUNCA exponha a API key no código do front-end público
const API_KEY = 'sk_live_...'; // ❌ Qualquer um pode ver no código fonte
```

✅ **Faça isso:**
```javascript
// Use variáveis de ambiente (não commitadas)
const API_KEY = process.env.REACT_APP_API_KEY;

// Ou use um backend proxy (recomendado para produção)
// Front-end → Seu Backend → API de Pagamentos
```

### 2. **Backend Proxy (Recomendado para Produção)**

Crie um backend intermediário que faz as requisições:

```
Front-End → Seu Backend (Node.js/PHP/etc) → API de Pagamentos
```

**Vantagens:**
- API Key nunca exposta no front-end
- Validações adicionais
- Cache
- Rate limiting customizado

### 3. **Tratamento de Erros Consistente**

```javascript
// Crie um interceptor global
api.interceptors.response.use(
    response => response,
    error => {
        // Log de erros
        console.error('API Error:', error);
        
        // Notificar usuário
        showNotification(error.message, 'error');
        
        // Retry automático para erros de rede
        if (error instanceof NetworkError) {
            return retryRequest(error.config);
        }
        
        throw error;
    }
);
```

### 4. **Cache de Dados**

```javascript
// Cache de customers, prices, etc.
const cache = new Map();

async function getCachedPrices() {
    const cacheKey = 'prices';
    if (cache.has(cacheKey)) {
        return cache.get(cacheKey);
    }
    
    const result = await api.listPrices();
    cache.set(cacheKey, result.data);
    
    // Expira após 5 minutos
    setTimeout(() => cache.delete(cacheKey), 5 * 60 * 1000);
    
    return result.data;
}
```

### 5. **Loading States**

```javascript
const [loading, setLoading] = useState(false);
const [error, setError] = useState(null);

async function handleAction() {
    setLoading(true);
    setError(null);
    try {
        await api.someAction();
    } catch (err) {
        setError(err.message);
    } finally {
        setLoading(false);
    }
}
```

---

## 🔒 Segurança

### 1. **HTTPS Obrigatório em Produção**

Nunca use HTTP em produção. Sempre use HTTPS.

### 2. **Validação de Dados no Front-End**

Valide dados antes de enviar:

```javascript
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateCustomerData(data) {
    if (!validateEmail(data.email)) {
        throw new Error('Email inválido');
    }
    if (!data.name || data.name.length < 2) {
        throw new Error('Nome deve ter pelo menos 2 caracteres');
    }
    return true;
}
```

### 3. **Rate Limiting no Front-End**

Implemente rate limiting no front-end também:

```javascript
class RateLimiter {
    constructor(maxRequests, windowMs) {
        this.maxRequests = maxRequests;
        this.windowMs = windowMs;
        this.requests = [];
    }

    canMakeRequest() {
        const now = Date.now();
        this.requests = this.requests.filter(time => now - time < this.windowMs);
        
        if (this.requests.length >= this.maxRequests) {
            return false;
        }
        
        this.requests.push(now);
        return true;
    }
}

const limiter = new RateLimiter(10, 60000); // 10 requests por minuto

if (!limiter.canMakeRequest()) {
    throw new Error('Muitas requisições. Aguarde um momento.');
}
```

### 4. **Sanitização de Inputs**

```javascript
function sanitizeInput(input) {
    return input.trim().replace(/[<>]/g, '');
}
```

---

## 📚 Recursos Adicionais

### Documentação da API

- **Swagger UI**: `https://pagamentos.seudominio.com/api-docs/ui`
- **OpenAPI Spec**: `https://pagamentos.seudominio.com/api-docs`
- **README.md**: Veja a documentação completa de endpoints

### Endpoints Principais

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/v1/customers` | POST | Criar cliente |
| `/v1/customers/:id` | GET | Obter cliente |
| `/v1/checkout` | POST | Criar checkout |
| `/v1/subscriptions` | POST | Criar assinatura |
| `/v1/prices` | GET | Listar preços |
| `/v1/auth/login` | POST | Login de usuário |
| `/health` | GET | Health check |

### Exemplos Completos

Veja `sdk/exemplo_uso.php` para exemplos em PHP.

---

## 🚀 Próximos Passos

1. **Configure CORS** para seus domínios específicos
2. **Crie um SDK** para sua linguagem preferida
3. **Implemente tratamento de erros** robusto
4. **Teste em ambiente de desenvolvimento** antes de produção
5. **Configure webhooks** no Stripe Dashboard
6. **Implemente backend proxy** se necessário para segurança

---

## ❓ Dúvidas Frequentes

### Posso usar a API Key diretamente no front-end?

**Não recomendado para produção.** Use um backend proxy ou variáveis de ambiente que não sejam commitadas.

### Como atualizar o status de uma assinatura em tempo real?

Use polling, WebSockets, ou Server-Sent Events. O backend processa webhooks do Stripe e atualiza o status.

### Posso usar múltiplas API Keys?

Sim, cada tenant tem sua própria API Key. Você pode ter múltiplos tenants.

### Como fazer logout de um usuário?

```javascript
await api.request('POST', '/v1/auth/logout');
localStorage.removeItem('session_id');
```

---

**Última atualização**: 2025-01-16


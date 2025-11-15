# 🚀 Guia de Integração - Sistema de Pagamentos SaaS

Este guia explica como integrar este sistema de pagamentos base em seu próprio SaaS.

> 💡 **Quer criar um Dashboard/Painel?** Consulte também: [docs/DASHBOARD_FLIGHTPHP.md](DASHBOARD_FLIGHTPHP.md)

## 📋 Índice

1. [Pré-requisitos](#pré-requisitos)
2. [Instalação](#instalação)
3. [Configuração Inicial](#configuração-inicial)
4. [Criando seu Primeiro Tenant](#criando-seu-primeiro-tenant)
5. [Integração no Frontend](#integração-no-frontend)
6. [Fluxo Completo de Integração](#fluxo-completo-de-integração)
7. [Exemplos Práticos](#exemplos-práticos)
8. [Boas Práticas](#boas-práticas)
9. [Troubleshooting](#troubleshooting)

---

## 📦 Pré-requisitos

### Servidor
- PHP 8.2 ou superior
- MySQL 8.0 ou superior
- Composer
- Extensões PHP: `pdo`, `pdo_mysql`, `json`, `curl`, `mbstring`
- (Opcional) Redis para cache e rate limiting

### Contas Externas
- Conta Stripe (teste ou produção)
- Chaves de API do Stripe:
  - Secret Key (`sk_test_...` ou `sk_live_...`)
  - Webhook Secret (`whsec_...`)

---

## 🔧 Instalação

### 1. Clone ou Copie o Projeto

```bash
# Se você já tem o projeto
cd saas-stripe

# Ou clone de um repositório
git clone <seu-repositorio> saas-payments
cd saas-payments
```

### 2. Instale as Dependências

```bash
composer install
```

### 3. Configure o Ambiente

```bash
# Copie o template de ambiente
cp env.template .env

# Edite o .env com suas configurações
nano .env  # ou use seu editor preferido
```

**Configurações mínimas no `.env`:**

```env
APP_ENV=production
DB_HOST=127.0.0.1
DB_NAME=seu_banco_pagamentos
DB_USER=seu_usuario
DB_PASS=sua_senha
STRIPE_SECRET=sk_live_xxx  # ou sk_test_xxx para testes
STRIPE_WEBHOOK_SECRET=whsec_xxx
API_MASTER_KEY=sua_chave_master_segura_aqui
```

### 4. Configure o Banco de Dados

```bash
# Execute as migrations
composer run migrate

# (Opcional) Execute seeds para dados de teste
composer run seed
```

---

## ⚙️ Configuração Inicial

### 1. Criar seu Primeiro Tenant (SaaS)

Um **tenant** representa seu SaaS que usará este sistema de pagamentos.

#### Opção A: Via API (Recomendado)

Crie um endpoint administrativo no seu SaaS principal ou use um script:

```php
<?php
// scripts/create_tenant.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Tenant;

$tenantModel = new Tenant();
$tenantId = $tenantModel->create('Meu SaaS', null); // null = gera API key automaticamente

$tenant = $tenantModel->findById($tenantId);
echo "Tenant criado!\n";
echo "ID: {$tenant['id']}\n";
echo "API Key: {$tenant['api_key']}\n";
echo "⚠️  GUARDE ESTA API KEY! Ela não será exibida novamente.\n";
```

#### Opção B: Via SQL Direto

```sql
INSERT INTO tenants (name, api_key, status) 
VALUES (
    'Meu SaaS',
    'sua_api_key_64_caracteres_hexadecimais_aqui',
    'active'
);
```

**Gerar API Key segura:**
```php
$apiKey = bin2hex(random_bytes(32)); // 64 caracteres hexadecimais
```

### 2. Configurar Webhook no Stripe

1. Acesse: https://dashboard.stripe.com/webhooks
2. Clique em "Add endpoint"
3. URL do endpoint: `https://seu-dominio.com/v1/webhook`
4. Selecione eventos:
   - `checkout.session.completed`
   - `invoice.paid`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
5. Copie o **Webhook Secret** e adicione no `.env`

---

## 🎯 Criando seu Primeiro Tenant

### Passo a Passo Completo

```php
<?php
// scripts/setup_meu_saas.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Tenant;

echo "=== Setup do Meu SaaS ===\n\n";

// 1. Criar tenant
$tenantModel = new Tenant();
$tenantId = $tenantModel->create('Meu SaaS', null);
$tenant = $tenantModel->findById($tenantId);

echo "✅ Tenant criado:\n";
echo "   ID: {$tenant['id']}\n";
echo "   Nome: {$tenant['name']}\n";
echo "   API Key: {$tenant['api_key']}\n";
echo "   Status: {$tenant['status']}\n\n";

echo "📝 IMPORTANTE:\n";
echo "   - Guarde a API Key em local seguro\n";
echo "   - Use esta API Key no seu frontend/backend\n";
echo "   - Configure no seu SaaS principal\n\n";
```

Execute:
```bash
php scripts/setup_meu_saas.php
```

---

## 🌐 Integração no Frontend

### Exemplo: React/Next.js

```typescript
// lib/payments-api.ts
const API_BASE_URL = 'https://seu-dominio-pagamentos.com';
const API_KEY = 'sua_api_key_aqui'; // Do tenant criado

export class PaymentsAPI {
  private headers = {
    'Authorization': `Bearer ${API_KEY}`,
    'Content-Type': 'application/json'
  };

  // Criar cliente
  async createCustomer(email: string, name: string) {
    const response = await fetch(`${API_BASE_URL}/v1/customers`, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify({ email, name })
    });
    return response.json();
  }

  // Criar checkout session
  async createCheckout(customerId: number, priceId: string) {
    const response = await fetch(`${API_BASE_URL}/v1/checkout`, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify({
        customer_id: customerId,
        price_id: priceId,
        success_url: 'https://meu-saas.com/success',
        cancel_url: 'https://meu-saas.com/cancel'
      })
    });
    return response.json();
  }

  // Listar assinaturas
  async listSubscriptions() {
    const response = await fetch(`${API_BASE_URL}/v1/subscriptions`, {
      headers: this.headers
    });
    return response.json();
  }

  // Obter histórico de assinatura
  async getSubscriptionHistory(subscriptionId: number) {
    const response = await fetch(
      `${API_BASE_URL}/v1/subscriptions/${subscriptionId}/history`,
      { headers: this.headers }
    );
    return response.json();
  }
}
```

### Exemplo: Uso no Componente React

```tsx
// components/CheckoutButton.tsx
import { PaymentsAPI } from '@/lib/payments-api';

export function CheckoutButton({ customerId, priceId }) {
  const api = new PaymentsAPI();

  const handleCheckout = async () => {
    try {
      const result = await api.createCheckout(customerId, priceId);
      
      if (result.success && result.data.url) {
        // Redireciona para o Stripe Checkout
        window.location.href = result.data.url;
      }
    } catch (error) {
      console.error('Erro ao criar checkout:', error);
    }
  };

  return (
    <button onClick={handleCheckout}>
      Assinar Agora
    </button>
  );
}
```

---

## 🔄 Fluxo Completo de Integração

### Cenário: Usuário se Registra e Assina

```
1. Usuário se registra no seu SaaS
   ↓
2. Seu SaaS cria customer no sistema de pagamentos
   POST /v1/customers
   {
     "email": "usuario@example.com",
     "name": "João Silva"
   }
   ↓
3. Seu SaaS cria checkout session
   POST /v1/checkout
   {
     "customer_id": 1,
     "price_id": "price_xxx",
     "success_url": "https://meu-saas.com/success",
     "cancel_url": "https://meu-saas.com/cancel"
   }
   ↓
4. Usuário é redirecionado para Stripe Checkout
   ↓
5. Usuário completa pagamento
   ↓
6. Stripe envia webhook para /v1/webhook
   ↓
7. Sistema processa webhook e atualiza assinatura
   ↓
8. Seu SaaS pode consultar status da assinatura
   GET /v1/subscriptions
```

### Exemplo Prático em PHP (Backend do seu SaaS)

```php
<?php
// No seu SaaS principal (ex: Laravel, Symfony, etc.)

class PaymentService
{
    private $apiBaseUrl = 'https://seu-dominio-pagamentos.com';
    private $apiKey = 'sua_api_key_aqui';
    
    public function createCustomer($email, $name)
    {
        $ch = curl_init("{$this->apiBaseUrl}/v1/customers");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $email,
                'name' => $name
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            return json_decode($response, true);
        }
        
        throw new Exception("Erro ao criar customer: $response");
    }
    
    public function createCheckout($customerId, $priceId, $userId)
    {
        $ch = curl_init("{$this->apiBaseUrl}/v1/checkout");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'customer_id' => $customerId,
                'price_id' => $priceId,
                'success_url' => "https://meu-saas.com/success?user_id={$userId}",
                'cancel_url' => "https://meu-saas.com/cancel?user_id={$userId}",
                'metadata' => [
                    'user_id' => $userId,
                    'saas_id' => 'meu_saas_id'
                ]
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        throw new Exception("Erro ao criar checkout: $response");
    }
    
    public function getSubscriptionStatus($subscriptionId)
    {
        $ch = curl_init("{$this->apiBaseUrl}/v1/subscriptions/{$subscriptionId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
}

// Uso no seu controller
class SubscriptionController
{
    public function subscribe(Request $request)
    {
        $user = auth()->user();
        $paymentService = new PaymentService();
        
        // 1. Criar customer no sistema de pagamentos
        $customer = $paymentService->createCustomer(
            $user->email,
            $user->name
        );
        
        // 2. Salvar customer_id no seu banco
        $user->payment_customer_id = $customer['data']['id'];
        $user->save();
        
        // 3. Criar checkout session
        $checkout = $paymentService->createCheckout(
            $customer['data']['id'],
            $request->price_id,
            $user->id
        );
        
        // 4. Redirecionar para Stripe
        return redirect($checkout['data']['url']);
    }
    
    public function success(Request $request)
    {
        // Verificar status da assinatura
        // Atualizar status do usuário no seu SaaS
        // etc.
    }
}
```

---

## 📚 Exemplos Práticos

### 1. Verificar se Usuário tem Assinatura Ativa

```php
// No seu SaaS
public function checkUserSubscription($userId)
{
    $user = User::find($userId);
    
    if (!$user->payment_customer_id) {
        return false;
    }
    
    $paymentService = new PaymentService();
    $subscriptions = $paymentService->listSubscriptions($user->payment_customer_id);
    
    foreach ($subscriptions['data'] as $subscription) {
        if ($subscription['status'] === 'active') {
            return true;
        }
    }
    
    return false;
}
```

### 2. Cancelar Assinatura

```php
public function cancelSubscription($subscriptionId)
{
    $ch = curl_init("{$this->apiBaseUrl}/v1/subscriptions/{$subscriptionId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$this->apiKey}",
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}
```

### 3. Obter Histórico de Mudanças

```php
public function getSubscriptionHistory($subscriptionId)
{
    $ch = curl_init("{$this->apiBaseUrl}/v1/subscriptions/{$subscriptionId}/history");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$this->apiKey}",
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}
```

---

## ✅ Boas Práticas

### 1. Segurança

- ✅ **Nunca exponha a API Key no frontend**
  - Use no backend do seu SaaS
  - Ou crie um proxy endpoint no seu backend

- ✅ **Use HTTPS sempre**
  - Especialmente para webhooks
  - Validação de signature do Stripe

- ✅ **Valide webhooks**
  - O sistema já valida automaticamente
  - Não processe webhooks sem validação

### 2. Tratamento de Erros

```php
try {
    $result = $paymentService->createCheckout(...);
} catch (\Exception $e) {
    // Log do erro
    Log::error('Erro ao criar checkout', [
        'error' => $e->getMessage(),
        'user_id' => $userId
    ]);
    
    // Retornar erro amigável ao usuário
    return response()->json([
        'error' => 'Erro ao processar pagamento. Tente novamente.'
    ], 500);
}
```

### 3. Sincronização de Dados

```php
// Criar job para sincronizar status de assinaturas
class SyncSubscriptionStatus
{
    public function handle()
    {
        $users = User::whereNotNull('payment_customer_id')->get();
        
        foreach ($users as $user) {
            $subscriptions = $paymentService->listSubscriptions($user->payment_customer_id);
            
            $hasActive = false;
            foreach ($subscriptions['data'] as $sub) {
                if ($sub['status'] === 'active') {
                    $hasActive = true;
                    break;
                }
            }
            
            // Atualizar status no seu banco
            $user->is_subscribed = $hasActive;
            $user->save();
        }
    }
}
```

### 4. Webhooks - Processar Eventos no seu SaaS

Você pode criar um webhook handler no seu SaaS para receber notificações:

```php
// No seu SaaS (ex: Laravel)
Route::post('/webhooks/payments', function (Request $request) {
    // O sistema de pagamentos já processou o webhook
    // Aqui você pode fazer ações adicionais no seu SaaS
    
    $event = $request->input('event_type');
    $subscriptionId = $request->input('subscription_id');
    
    switch ($event) {
        case 'subscription.created':
            // Notificar usuário, enviar email, etc.
            break;
            
        case 'subscription.canceled':
            // Desativar acesso do usuário
            break;
            
        case 'invoice.paid':
            // Ativar recursos premium
            break;
    }
    
    return response()->json(['success' => true]);
});
```

---

## 🔍 Troubleshooting

### Problema: "Não autenticado" (401)

**Solução:**
- Verifique se a API Key está correta
- Verifique se o header `Authorization: Bearer {api_key}` está sendo enviado
- Verifique se o tenant está com status `active`

### Problema: Webhook não está sendo recebido

**Solução:**
1. Verifique se a URL está acessível publicamente
2. Verifique se o `STRIPE_WEBHOOK_SECRET` está correto
3. Teste localmente com Stripe CLI:
   ```bash
   stripe listen --forward-to http://localhost:8080/v1/webhook
   ```

### Problema: Assinatura não aparece após checkout

**Solução:**
1. Verifique os logs: `tail -f app.log`
2. Verifique se o webhook foi processado
3. Consulte o histórico: `GET /v1/subscriptions/:id/history`

### Problema: Rate Limit (429)

**Solução:**
- Configure limites maiores no `.env` se necessário
- Implemente retry com backoff exponencial
- Use cache quando possível

---

## 📊 Monitoramento

### Logs

Os logs são salvos em `app.log` (configurável no `.env`):

```bash
tail -f app.log
```

### Audit Logs

Consulte logs de auditoria:
```bash
GET /v1/audit-logs
```

### Health Check

Verifique status do sistema:
```bash
GET /health
```

---

## 🚀 Deploy em Produção

### 1. Configurações de Produção

```env
APP_ENV=production
STRIPE_SECRET=sk_live_xxx  # Chave de produção
STRIPE_WEBHOOK_SECRET=whsec_xxx  # Secret de produção
API_MASTER_KEY=chave_muito_segura_aqui
```

### 2. Segurança

- ✅ Use HTTPS
- ✅ Configure firewall
- ✅ Limite acesso ao banco de dados
- ✅ Use senhas fortes
- ✅ Rotacione API keys periodicamente

### 3. Performance

- ✅ Configure Redis para cache
- ✅ Use CDN para assets estáticos
- ✅ Configure rate limiting adequado
- ✅ Monitore performance

### 4. Backup

```bash
# Configure backup automático do banco
mysqldump -u usuario -p banco > backup_$(date +%Y%m%d).sql
```

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Consulte os logs
2. Verifique a documentação da API
3. Teste com scripts em `tests/Manual/`

---

**Pronto!** Seu sistema de pagamentos está integrado e pronto para uso! 🎉


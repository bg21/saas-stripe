# 🔄 Fluxo de Checkout em Produção

## 📋 Visão Geral

Em produção, o fluxo de pagamento funciona da seguinte forma:

1. **Cliente acessa seu sistema** → Clica em "Assinar" ou "Pagar"
2. **Seu backend cria sessão de checkout** → `POST /v1/checkout`
3. **Cliente é redirecionado para o Stripe Checkout** → URL retornada pela API
4. **Cliente insere dados do cartão no Stripe** → **Dados NUNCA passam pelo seu servidor** ✅
5. **Stripe processa o pagamento** → Validação e cobrança
6. **Stripe envia webhook** → `checkout.session.completed` para seu servidor
7. **Seu sistema processa o webhook** → Salva payment method automaticamente

---

## 🔐 Segurança: Dados do Cartão

### ✅ O que acontece:
- Cliente insere dados do cartão **diretamente no Stripe**
- Dados do cartão **NUNCA passam pelo seu servidor**
- Seu servidor **NUNCA vê** número do cartão, CVV, etc.
- Stripe retorna apenas um **Payment Method ID** (token seguro)

### 🛡️ Benefícios:
- **PCI Compliance**: Você não precisa ser PCI compliant
- **Segurança**: Dados sensíveis ficam apenas no Stripe
- **Conformidade**: Stripe cuida de todas as regulamentações

---

## 📝 Fluxo Detalhado

### 1. Cliente Solicita Checkout

**Frontend:**
```javascript
// Cliente clica em "Assinar Plano Premium"
const response = await fetch('/v1/checkout', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + apiKey,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    customer_id: 'cus_xxx', // ID do customer no Stripe
    line_items: [
      {
        price: 'price_xxx', // ID do preço
        quantity: 1
      }
    ],
    mode: 'subscription',
    success_url: 'https://seusite.com/success?session_id={CHECKOUT_SESSION_ID}',
    cancel_url: 'https://seusite.com/cancel',
    payment_method_collection: 'always' // IMPORTANTE: Salva o cartão
  })
});

const { data } = await response.json();
// Redireciona para o Stripe Checkout
window.location.href = data.url;
```

**Backend (`POST /v1/checkout`):**
```php
// App/Controllers/CheckoutController.php
// Cria sessão de checkout no Stripe
$session = $stripeService->createCheckoutSession($data);
// Retorna URL do checkout
return ['url' => $session->url];
```

---

### 2. Cliente no Stripe Checkout

**O que acontece:**
- Cliente é redirecionado para `https://checkout.stripe.com/...`
- Cliente vê formulário seguro do Stripe
- Cliente insere:
  - Número do cartão: `4242 4242 4242 4242`
  - Data de expiração: `12/25`
  - CVV: `123`
  - Nome no cartão: `João Silva`
- Cliente clica em "Pagar"

**⚠️ IMPORTANTE:** Todos esses dados ficam no Stripe, não no seu servidor!

---

### 3. Stripe Processa Pagamento

**O que o Stripe faz:**
1. Valida dados do cartão
2. Processa pagamento/autorização
3. Cria Payment Method (token seguro)
4. Anexa Payment Method ao Customer
5. Cria Subscription (se mode='subscription')
6. Envia webhook `checkout.session.completed`

---

### 4. Webhook Chega ao Seu Servidor

**Stripe envia POST para:** `https://seusite.com/v1/webhook`

**Payload do webhook:**
```json
{
  "type": "checkout.session.completed",
  "data": {
    "object": {
      "id": "cs_test_xxx",
      "customer": "cus_xxx",
      "payment_method": "pm_xxx", // Payment Method ID (token seguro)
      "subscription": "sub_xxx",
      "mode": "subscription"
    }
  }
}
```

---

### 5. Seu Sistema Processa o Webhook

**Código atual (`App/Services/PaymentService.php`):**

```php
private function handleCheckoutCompleted(\Stripe\Event $event): void
{
    $session = $event->data->object;
    $customerId = $session->customer;
    
    // Busca customer no banco
    $customer = $this->customerModel->findByStripeId($customerId);
    
    // Obtém payment method da sessão
    $paymentMethodId = $fullSession->payment_method;
    
    // ✅ SALVA AUTOMATICAMENTE o payment method
    if ($paymentMethodId) {
        $this->stripeService->attachPaymentMethodToCustomer(
            $paymentMethodId, 
            $customerId
        );
        // Payment method é anexado ao customer e definido como padrão
    }
    
    // Se for subscription, cria/atualiza no banco
    if ($fullSession->mode === 'subscription') {
        $subscription = $this->stripeService->getSubscription(
            $fullSession->subscription
        );
        $this->subscriptionModel->createOrUpdate(...);
    }
}
```

**O que acontece:**
1. ✅ Payment Method é **anexado ao Customer** no Stripe
2. ✅ Payment Method é **definido como padrão** no Stripe
3. ✅ Subscription é **criada/atualizada** no banco de dados
4. ✅ Customer pode usar esse cartão para futuras cobranças

---

## 🎯 Resumo

### ✅ Em Produção:
- Cliente insere cartão **no Stripe** (não no seu site)
- Dados do cartão **nunca passam pelo seu servidor**
- Stripe retorna apenas **Payment Method ID** (token seguro)
- Webhook **salva automaticamente** o payment method
- Próximas cobranças usam o cartão salvo automaticamente

### ❌ NÃO em Produção:
- Cliente **não** insere cartão no seu site
- Seu servidor **não** recebe dados do cartão
- Você **não** precisa ser PCI compliant
- Você **não** precisa armazenar dados sensíveis

---

## 🔧 Configuração Necessária

### 1. Webhook no Stripe Dashboard

1. Acesse: https://dashboard.stripe.com/webhooks
2. Adicione endpoint: `https://seusite.com/v1/webhook`
3. Selecione evento: `checkout.session.completed`
4. Copie o **Webhook Secret**
5. Configure no `.env`: `STRIPE_WEBHOOK_SECRET=whsec_xxx`

### 2. Payment Method Collection

Ao criar checkout, sempre use:
```json
{
  "payment_method_collection": "always"
}
```

Isso garante que o Stripe salve o cartão para futuras cobranças.

---

## 📊 Comparação: Teste vs Produção

| Aspecto | Teste (Atual) | Produção |
|---------|---------------|----------|
| **Criação de Assinatura** | Direto no Stripe com `trial_period_days` | Via Checkout do Stripe |
| **Payment Method** | Não necessário (trial) | Coletado no Checkout |
| **Dados do Cartão** | Não coletados | Coletados pelo Stripe |
| **Webhook** | Simulado/Manual | Automático do Stripe |
| **Salvamento de Cartão** | Manual (se necessário) | Automático via webhook |

---

## ✅ Conclusão

**Sim, em produção será via Checkout do Stripe!**

- Cliente coloca cartão **no Stripe**
- Stripe **salva o cartão** automaticamente
- Seu sistema **recebe webhook** e processa
- Payment method é **anexado ao customer** automaticamente
- Próximas cobranças são **automáticas** usando o cartão salvo

**Seu código já está preparado para isso!** ✅

O método `handleCheckoutCompleted()` já faz tudo automaticamente quando o webhook chega.


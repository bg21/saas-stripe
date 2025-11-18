# 🐛 Debug de Webhooks - Subscription não está sendo salva

**Problema:** O checkout funciona e a subscription é criada no Stripe, mas não é salva no banco de dados local.

---

## 🔍 Causas Possíveis

### 1. Webhook não está configurado no Stripe

O Stripe precisa saber para onde enviar os eventos. Em desenvolvimento local, isso é um problema porque `localhost` não é acessível da internet.

### 2. Webhook não está sendo recebido

Mesmo configurado, o webhook pode não estar chegando ao servidor.

### 3. Erro no processamento do webhook

O webhook pode estar chegando, mas há um erro ao processar.

---

## ✅ Soluções

### Solução 1: Usar Stripe CLI (Recomendado para Desenvolvimento)

O Stripe CLI permite testar webhooks localmente sem precisar expor seu servidor na internet.

#### Instalação

**Windows:**
```powershell
# Via Scoop
scoop install stripe

# Ou baixe de: https://github.com/stripe/stripe-cli/releases
```

**Linux/Mac:**
```bash
# Via Homebrew (Mac)
brew install stripe/stripe-cli/stripe

# Ou baixe de: https://github.com/stripe/stripe-cli/releases
```

#### Configuração

1. **Login no Stripe CLI:**
   ```bash
   stripe login
   ```

2. **Inicie o forwarding de webhooks:**
   ```bash
   stripe listen --forward-to http://localhost:8080/v1/webhook
   ```

   Isso vai:
   - Criar um webhook endpoint temporário no Stripe
   - Encaminhar todos os eventos para seu servidor local
   - Mostrar o `webhook signing secret` (começa com `whsec_`)

3. **Configure o secret no `.env`:**
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxxxx  # Use o secret mostrado pelo CLI
   ```

4. **Teste enviando um evento:**
   ```bash
   stripe trigger checkout.session.completed
   ```

---

### Solução 2: Usar ngrok (Para Testes Reais)

Se quiser testar com webhooks reais do Stripe:

1. **Instale o ngrok:**
   ```bash
   # Windows: baixe de https://ngrok.com/download
   # Mac: brew install ngrok
   # Linux: baixe de https://ngrok.com/download
   ```

2. **Exponha seu servidor:**
   ```bash
   ngrok http 8080
   ```

3. **Configure o webhook no Stripe Dashboard:**
   - Acesse: https://dashboard.stripe.com/test/webhooks
   - Clique em "Add endpoint"
   - URL: `https://seu-id.ngrok.io/v1/webhook`
   - Eventos: Selecione `checkout.session.completed`
   - Copie o `Signing secret` (começa com `whsec_`)

4. **Configure no `.env`:**
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxxxx
   ```

---

### Solução 3: Verificar Logs

Verifique se o webhook está sendo recebido e processado:

1. **Verifique os logs do servidor:**
   ```bash
   tail -f logs/app.log
   ```

2. **Procure por:**
   - `"Webhook recebido"` - Indica que o webhook chegou
   - `"Checkout completado e processado"` - Indica que foi processado
   - `"Assinatura criada/atualizada após checkout"` - Indica que a subscription foi salva
   - `"Erro ao processar webhook"` - Indica que houve erro

3. **Verifique a tabela de eventos:**
   ```sql
   SELECT * FROM stripe_events 
   ORDER BY created_at DESC 
   LIMIT 10;
   ```

   Isso mostra se os eventos estão chegando e se foram processados.

---

### Solução 4: Verificar Configuração do Webhook

1. **Verifique se o endpoint está correto:**
   - Deve ser: `http://seu-servidor/v1/webhook`
   - Não deve ter autenticação (o Stripe valida via signature)

2. **Verifique se o secret está correto:**
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxxxx
   ```

3. **Verifique se o evento está sendo enviado:**
   - No Stripe Dashboard → Webhooks → Seu endpoint
   - Veja os eventos recentes
   - Verifique se há erros (status 4xx ou 5xx)

---

## 🔍 Debug Passo a Passo

### 1. Verificar se o webhook está sendo chamado

Adicione logs temporários no `WebhookController.php`:

```php
public function handle(): void
{
    Logger::info("=== WEBHOOK RECEBIDO ===", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'payload_size' => strlen(file_get_contents('php://input'))
    ]);
    
    // ... resto do código
}
```

### 2. Verificar se o evento está sendo processado

No `PaymentService.php`, adicione logs:

```php
// Se for modo subscription, cria/atualiza assinatura no banco
if ($fullSession->mode === 'subscription' && $fullSession->subscription) {
    Logger::info("=== PROCESSANDO SUBSCRIPTION ===", [
        'mode' => $fullSession->mode,
        'has_subscription' => !empty($fullSession->subscription),
        'subscription_id' => is_string($fullSession->subscription) 
            ? $fullSession->subscription 
            : $fullSession->subscription->id
    ]);
    
    // ... resto do código
}
```

### 3. Verificar se há erros no banco

```sql
-- Verificar se há subscriptions no banco
SELECT * FROM subscriptions ORDER BY created_at DESC LIMIT 10;

-- Verificar eventos processados
SELECT * FROM stripe_events 
WHERE event_type = 'checkout.session.completed' 
ORDER BY created_at DESC 
LIMIT 10;

-- Verificar se há erros nos logs (se tiver tabela de logs)
SELECT * FROM audit_logs 
WHERE action LIKE '%webhook%' OR action LIKE '%subscription%'
ORDER BY created_at DESC 
LIMIT 20;
```

---

## 🧪 Teste Manual

Você pode testar manualmente criando uma subscription diretamente:

```php
// scripts/test_subscription.php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\PaymentService;
use App\Services\StripeService;
use App\Models\Subscription;
use App\Models\Customer;

$paymentService = new PaymentService(
    new StripeService(),
    new Customer(),
    new Subscription()
);

// Obtenha um customer_id e subscription_id do Stripe
$customerId = 1; // ID do customer no seu banco
$stripeSubscriptionId = 'sub_xxxxx'; // ID da subscription no Stripe

$stripeService = new StripeService();
$subscription = $stripeService->getSubscription($stripeSubscriptionId);

$customerModel = new Customer();
$customer = $customerModel->findById($customerId);

$subscriptionModel = new Subscription();
$subscriptionId = $subscriptionModel->createOrUpdate(
    $customer['tenant_id'],
    $customer['id'],
    $subscription->toArray()
);

echo "Subscription salva com ID: $subscriptionId\n";
```

---

## ✅ Checklist

- [ ] Webhook configurado no Stripe (ou usando Stripe CLI)
- [ ] `STRIPE_WEBHOOK_SECRET` configurado no `.env`
- [ ] Servidor rodando e acessível
- [ ] Logs mostram "Webhook recebido"
- [ ] Logs mostram "Checkout completado e processado"
- [ ] Logs mostram "Assinatura criada/atualizada após checkout"
- [ ] Tabela `subscriptions` tem registros
- [ ] Tabela `stripe_events` tem eventos processados

---

## 🎯 Próximos Passos

1. Use o Stripe CLI para testar localmente
2. Verifique os logs para ver onde está falhando
3. Se necessário, adicione os logs de debug mencionados acima
4. Verifique se o customer existe no banco antes do checkout

---

**Última Atualização:** 2025-01-XX

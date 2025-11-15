# 📋 Resumo Rápido - Integração no Seu SaaS

## 🎯 Passo a Passo Simplificado

### 1️⃣ Criar seu Tenant (5 minutos)

```bash
php scripts/setup_tenant.php "Nome do Seu SaaS"
```

**Resultado:** Você receberá uma API Key única. **GUARDE ELA!**

### 2️⃣ Configurar no Seu SaaS

#### Opção A: Usar SDK PHP (Recomendado)

```php
// No seu SaaS
require_once 'sdk/PaymentsClient.php';
use PaymentsSDK\PaymentsClient;

$payments = new PaymentsClient(
    'https://pagamentos.seudominio.com',
    'sua_api_key_aqui'
);
```

#### Opção B: Requisições HTTP Diretas

```php
// No seu SaaS
$ch = curl_init('https://pagamentos.seudominio.com/v1/customers');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer sua_api_key_aqui',
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'usuario@example.com',
        'name' => 'João Silva'
    ])
]);
$response = curl_exec($ch);
```

### 3️⃣ Fluxo Básico de Integração

```
┌─────────────────┐
│  Seu SaaS       │
│  (Frontend)     │
└────────┬────────┘
         │
         │ 1. Usuário clica "Assinar"
         ▼
┌─────────────────┐
│  Seu Backend    │
│  (Laravel/etc)  │
└────────┬────────┘
         │
         │ 2. POST /v1/customers
         │    (cria customer)
         ▼
┌─────────────────┐
│  Sistema de     │
│  Pagamentos     │
└────────┬────────┘
         │
         │ 3. POST /v1/checkout
         │    (cria checkout)
         ▼
┌─────────────────┐
│  Stripe         │
│  Checkout       │
└────────┬────────┘
         │
         │ 4. Usuário paga
         ▼
┌─────────────────┐
│  Webhook        │
│  /v1/webhook    │
└────────┬────────┘
         │
         │ 5. Assinatura criada
         ▼
┌─────────────────┐
│  Seu SaaS       │
│  (Atualiza      │
│   status)       │
└─────────────────┘
```

### 4️⃣ Exemplo Prático Completo

```php
// No seu SaaS (ex: Laravel Controller)

class SubscriptionController extends Controller
{
    private PaymentsClient $payments;
    
    public function __construct()
    {
        $this->payments = new PaymentsClient(
            config('payments.api_url'),
            config('payments.api_key')
        );
    }
    
    public function subscribe(Request $request)
    {
        $user = auth()->user();
        
        // 1. Criar ou buscar customer
        $customer = $this->payments->createCustomer(
            $user->email,
            $user->name,
            ['user_id' => $user->id]
        );
        
        // Salvar customer_id no seu banco
        $user->payment_customer_id = $customer['data']['id'];
        $user->save();
        
        // 2. Criar checkout
        $checkout = $this->payments->createCheckout(
            $customer['data']['id'],
            $request->price_id, // Price ID do Stripe
            route('subscription.success'),
            route('subscription.cancel'),
            ['user_id' => $user->id]
        );
        
        // 3. Redirecionar para Stripe
        return redirect($checkout['data']['url']);
    }
    
    public function checkStatus()
    {
        $user = auth()->user();
        
        if (!$user->payment_customer_id) {
            return ['has_subscription' => false];
        }
        
        // Verificar assinaturas
        $subscriptions = $this->payments->listSubscriptions();
        
        foreach ($subscriptions['data'] as $sub) {
            if ($sub['status'] === 'active') {
                return [
                    'has_subscription' => true,
                    'subscription_id' => $sub['id'],
                    'status' => $sub['status']
                ];
            }
        }
        
        return ['has_subscription' => false];
    }
}
```

## 🔑 Pontos Importantes

### ✅ O que fazer:
- ✅ Guarde a API Key em variável de ambiente
- ✅ Use HTTPS em produção
- ✅ Trate erros adequadamente
- ✅ Valide webhooks (já feito automaticamente)
- ✅ Monitore logs

### ❌ O que NÃO fazer:
- ❌ Não exponha API Key no frontend
- ❌ Não use HTTP em produção
- ❌ Não ignore erros
- ❌ Não processe webhooks sem validação

## 📚 Documentação Completa

- **Guia Completo:** [docs/GUIA_INTEGRACAO_SAAS.md](GUIA_INTEGRACAO_SAAS.md)
- **SDK PHP:** [sdk/README.md](../sdk/README.md)
- **Exemplos:** [sdk/exemplo_uso.php](../sdk/exemplo_uso.php)

## 🆘 Precisa de Ajuda?

1. Consulte os logs: `tail -f app.log`
2. Teste endpoints: `tests/Manual/`
3. Verifique documentação: `docs/`

---

**Pronto para integrar!** 🚀


# 📦 SDK PHP - Cliente para API de Pagamentos

Cliente PHP para facilitar a integração do sistema de pagamentos no seu SaaS.

## 📥 Instalação

### Opção 1: Copiar arquivo diretamente

```bash
# Copie PaymentsClient.php para seu projeto
cp sdk/PaymentsClient.php /caminho/do/seu/saas/
```

### Opção 2: Via Composer (se publicar como pacote)

```json
{
    "require": {
        "seu-namespace/payments-client": "^1.0"
    }
}
```

## 🚀 Uso Básico

```php
require_once 'PaymentsClient.php';

use PaymentsSDK\PaymentsClient;

// Inicializa cliente
$payments = new PaymentsClient(
    'https://pagamentos.seudominio.com',
    'sua_api_key_aqui'
);

// Criar cliente
$customer = $payments->createCustomer('email@example.com', 'Nome');

// Criar checkout
$checkout = $payments->createCheckout(
    $customer['data']['id'],
    'price_xxx',
    'https://meu-saas.com/success',
    'https://meu-saas.com/cancel'
);

// Redirecionar usuário
header('Location: ' . $checkout['data']['url']);
```

## 📚 Métodos Disponíveis

### Customers
- `createCustomer($email, $name, $metadata)`
- `listCustomers()`
- `getCustomer($customerId)`
- `updateCustomer($customerId, $data)`

### Checkout
- `createCheckout($customerId, $priceId, $successUrl, $cancelUrl, $metadata)`
- `getCheckout($checkoutId)`

### Subscriptions
- `createSubscription($customerId, $priceId, $trialPeriodDays, $metadata)`
- `listSubscriptions()`
- `getSubscription($subscriptionId)`
- `updateSubscription($subscriptionId, $data)`
- `cancelSubscription($subscriptionId, $immediately)`
- `reactivateSubscription($subscriptionId)`
- `getSubscriptionHistory($subscriptionId, $limit, $offset)`

### Stats
- `getStats($period)`

### Audit Logs
- `listAuditLogs($filters, $limit, $offset)`
- `getAuditLog($logId)`

## 🔍 Exemplos

Veja `exemplo_uso.php` para exemplos completos.

## ⚠️ Tratamento de Erros

```php
try {
    $customer = $payments->createCustomer('email@example.com', 'Nome');
} catch (\Exception $e) {
    // Tratar erro
    error_log("Erro ao criar customer: " . $e->getMessage());
    // Retornar erro amigável ao usuário
}
```


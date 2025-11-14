# 📋 O que Falta Implementar Relacionado ao Stripe

## ✅ O que JÁ está Implementado

### Customers
- ✅ `createCustomer()` - Criar cliente
- ✅ `getCustomer()` - Obter cliente
- ✅ `updateCustomer()` - Atualizar cliente
- ✅ `listCustomers()` - Listar clientes

### Subscriptions
- ✅ `createSubscription()` - Criar assinatura
- ✅ `getSubscription()` - Obter assinatura
- ✅ `updateSubscription()` - Atualizar assinatura
- ✅ `cancelSubscription()` - Cancelar assinatura
- ✅ `reactivateSubscription()` - Reativar assinatura

### Checkout
- ✅ `createCheckoutSession()` - Criar sessão de checkout
- ✅ `getCheckoutSession()` - Obter sessão de checkout

### Payment Intents
- ✅ `createPaymentIntent()` - Criar payment intent
- ✅ `getPaymentIntent()` - Obter payment intent

### Refunds
- ✅ `refundPayment()` - Reembolsar pagamento

### Invoices
- ✅ `getInvoice()` - Obter fatura
- ✅ `listInvoices()` - Listar faturas de um customer

### Payment Methods
- ✅ `listPaymentMethods()` - Listar métodos de pagamento
- ✅ `attachPaymentMethodToCustomer()` - Anexar e definir como padrão

### Prices & Products
- ✅ `listPrices()` - Listar preços/products

### Coupons
- ✅ `createCoupon()` - Criar cupom
- ✅ `getCoupon()` - Obter cupom
- ✅ `listCoupons()` - Listar cupons
- ✅ `deleteCoupon()` - Deletar cupom

### Billing Portal
- ✅ `createBillingPortalSession()` - Criar sessão do portal

### Webhooks
- ✅ `validateWebhook()` - Validar assinatura de webhook

---

## ❌ O que FALTA Implementar (Relacionado ao Stripe)

### 🔴 Alta Prioridade

#### 1. **Gerenciamento de Payment Methods**
**Por quê?** Necessário para permitir que clientes gerenciem seus métodos de pagamento.

**O que implementar:**
- `deletePaymentMethod()` - Deletar método de pagamento
- `updatePaymentMethod()` - Atualizar método de pagamento (ex: alterar billing address)
- `detachPaymentMethod()` - Desanexar método de pagamento de um customer
- `setDefaultPaymentMethod()` - Definir método de pagamento como padrão (separado do attach)
- Endpoint: `DELETE /v1/customers/:id/payment-methods/:pm_id`
- Endpoint: `PUT /v1/customers/:id/payment-methods/:pm_id`
- Endpoint: `POST /v1/customers/:id/payment-methods/:pm_id/set-default`

**Impacto:** Alto - Essencial para gestão completa de métodos de pagamento.

---

#### 2. **Products (Criar e Gerenciar)**
**Por quê?** Atualmente só listamos products, mas não podemos criar ou atualizar.

**O que implementar:**
- `createProduct()` - Criar produto
- `updateProduct()` - Atualizar produto
- `getProduct()` - Obter produto específico
- `deleteProduct()` - Deletar produto (soft delete)
- Endpoint: `POST /v1/products`
- Endpoint: `PUT /v1/products/:id`
- Endpoint: `GET /v1/products/:id`
- Endpoint: `DELETE /v1/products/:id`

**Impacto:** Médio-Alto - Necessário para gerenciar catálogo de produtos.

---

#### 3. **Prices (Criar e Gerenciar)**
**Por quê?** Atualmente só listamos prices, mas não podemos criar ou atualizar.

**O que implementar:**
- `createPrice()` - Criar preço
- `updatePrice()` - Atualizar preço (apenas metadata)
- `getPrice()` - Obter preço específico
- Endpoint: `POST /v1/prices`
- Endpoint: `PUT /v1/prices/:id` (apenas metadata)
- Endpoint: `GET /v1/prices/:id`

**Impacto:** Médio-Alto - Necessário para gerenciar preços dinamicamente.

---

### 🟡 Média Prioridade

#### 4. **Tax Rates (Taxas e Impostos)**
**Por quê?** Importante para compliance fiscal e cálculo correto de impostos.

**O que implementar:**
- `createTaxRate()` - Criar taxa de imposto
- `updateTaxRate()` - Atualizar taxa de imposto
- `listTaxRates()` - Listar taxas de imposto
- `getTaxRate()` - Obter taxa específica
- Endpoint: `POST /v1/tax-rates`
- Endpoint: `GET /v1/tax-rates`
- Endpoint: `GET /v1/tax-rates/:id`
- Endpoint: `PUT /v1/tax-rates/:id`

**Impacto:** Médio - Importante para negócios que precisam calcular impostos.

---

#### 5. **Promotion Codes (Códigos Promocionais)**
**Por quê?** Permite criar códigos promocionais que os clientes podem resgatar (usando cupons subjacentes).

**O que implementar:**
- `createPromotionCode()` - Criar código promocional
- `updatePromotionCode()` - Atualizar código promocional
- `listPromotionCodes()` - Listar códigos promocionais
- `getPromotionCode()` - Obter código específico
- Endpoint: `POST /v1/promotion-codes`
- Endpoint: `GET /v1/promotion-codes`
- Endpoint: `GET /v1/promotion-codes/:id`

**Impacto:** Médio - Útil para campanhas de marketing e promoções.

---

#### 6. **Setup Intents**
**Por quê?** Permite salvar métodos de pagamento sem processar um pagamento (útil para trial periods).

**O que implementar:**
- `createSetupIntent()` - Criar setup intent
- `getSetupIntent()` - Obter setup intent
- `confirmSetupIntent()` - Confirmar setup intent
- Endpoint: `POST /v1/setup-intents`
- Endpoint: `GET /v1/setup-intents/:id`
- Endpoint: `POST /v1/setup-intents/:id/confirm`

**Impacto:** Médio - Útil para trial periods e salvar cartões antes do primeiro pagamento.

---

#### 7. **Subscription Items (Gerenciar Itens de Assinatura)**
**Por quê?** Permite gerenciar múltiplos itens em uma assinatura (ex: plano base + add-ons).

**O que implementar:**
- `createSubscriptionItem()` - Adicionar item à assinatura
- `updateSubscriptionItem()` - Atualizar item da assinatura
- `deleteSubscriptionItem()` - Remover item da assinatura
- `listSubscriptionItems()` - Listar itens de uma assinatura
- Endpoint: `POST /v1/subscriptions/:id/items`
- Endpoint: `PUT /v1/subscriptions/:id/items/:item_id`
- Endpoint: `DELETE /v1/subscriptions/:id/items/:item_id`
- Endpoint: `GET /v1/subscriptions/:id/items`

**Impacto:** Médio - Útil para assinaturas com múltiplos produtos/add-ons.

---

#### 8. **Invoice Items (Itens de Fatura)**
**Por quê?** Permite adicionar itens customizados a faturas (ex: ajustes manuais, créditos).

**O que implementar:**
- `createInvoiceItem()` - Criar item de fatura
- `updateInvoiceItem()` - Atualizar item de fatura
- `deleteInvoiceItem()` - Deletar item de fatura
- `listInvoiceItems()` - Listar itens de fatura de um customer
- `getInvoiceItem()` - Obter item específico
- Endpoint: `POST /v1/invoice-items`
- Endpoint: `GET /v1/invoice-items`
- Endpoint: `GET /v1/invoice-items/:id`
- Endpoint: `PUT /v1/invoice-items/:id`
- Endpoint: `DELETE /v1/invoice-items/:id`

**Impacto:** Médio - Útil para ajustes manuais, créditos e cobranças extras.

---

### 🟢 Baixa Prioridade (Mas Úteis)

#### 9. **Charges (Cobranças)**
**Por quê?** Permite listar e obter detalhes de cobranças individuais.

**O que implementar:**
- `listCharges()` - Listar cobranças
- `getCharge()` - Obter cobrança específica
- `updateCharge()` - Atualizar cobrança (apenas metadata)
- Endpoint: `GET /v1/charges`
- Endpoint: `GET /v1/charges/:id`
- Endpoint: `PUT /v1/charges/:id` (apenas metadata)

**Impacto:** Baixo - Útil para auditoria e histórico detalhado.

---

#### 10. **Disputes (Disputas/Chargebacks)**
**Por quê?** Permite gerenciar disputas de pagamento.

**O que implementar:**
- `listDisputes()` - Listar disputas
- `getDispute()` - Obter disputa específica
- `updateDispute()` - Atualizar disputa (adicionar evidências)
- Endpoint: `GET /v1/disputes`
- Endpoint: `GET /v1/disputes/:id`
- Endpoint: `PUT /v1/disputes/:id`

**Impacto:** Baixo - Importante apenas se houver muitas disputas.

---

#### 11. **Balance Transactions (Transações de Saldo)**
**Por quê?** Permite ver histórico de transações financeiras.

**O que implementar:**
- `listBalanceTransactions()` - Listar transações de saldo
- `getBalanceTransaction()` - Obter transação específica
- Endpoint: `GET /v1/balance-transactions`
- Endpoint: `GET /v1/balance-transactions/:id`

**Impacto:** Baixo - Útil para reconciliação financeira.

---

#### 12. **Payouts (Saques)**
**Por quê?** Permite gerenciar saques para a conta bancária.

**O que implementar:**
- `listPayouts()` - Listar saques
- `getPayout()` - Obter saque específico
- `createPayout()` - Criar saque manual
- `cancelPayout()` - Cancelar saque pendente
- Endpoint: `GET /v1/payouts`
- Endpoint: `GET /v1/payouts/:id`
- Endpoint: `POST /v1/payouts`
- Endpoint: `POST /v1/payouts/:id/cancel`

**Impacto:** Baixo - Geralmente gerenciado pelo Stripe Dashboard.

---

#### 13. **Webhooks - Mais Eventos**
**Por quê?** Tratar mais eventos do Stripe para melhor integração.

**O que implementar handlers para:**
- `payment_intent.succeeded` - Pagamento confirmado
- `payment_intent.payment_failed` - Falha no pagamento
- `invoice.payment_failed` - Falha no pagamento de fatura
- `invoice.upcoming` - Fatura próxima (para notificações)
- `customer.subscription.trial_will_end` - Trial terminando
- `charge.dispute.created` - Disputa criada
- `charge.refunded` - Reembolso processado

**Impacto:** Médio - Melhora a integração e permite ações automáticas.

---

## 📊 Resumo de Prioridades

| Prioridade | Funcionalidade | Impacto | Esforço | Urgência |
|------------|---------------|---------|---------|----------|
| 🔴 Alta | Payment Methods (delete, update, detach) | Alto | Médio | Importante |
| 🔴 Alta | Products (create, update, delete) | Médio-Alto | Médio | Importante |
| 🔴 Alta | Prices (create, update) | Médio-Alto | Baixo | Importante |
| 🟡 Média | Tax Rates | Médio | Médio | Útil |
| 🟡 Média | Promotion Codes | Médio | Médio | Útil |
| 🟡 Média | Setup Intents | Médio | Médio | Útil |
| 🟡 Média | Subscription Items | Médio | Médio | Útil |
| 🟡 Média | Invoice Items | Médio | Médio | Útil |
| 🟢 Baixa | Charges | Baixo | Baixo | Opcional |
| 🟢 Baixa | Disputes | Baixo | Médio | Opcional |
| 🟢 Baixa | Balance Transactions | Baixo | Baixo | Opcional |
| 🟢 Baixa | Payouts | Baixo | Médio | Opcional |
| 🟡 Média | Mais Webhooks | Médio | Médio | Útil |

---

## 💡 Recomendação de Ordem de Implementação

### Fase 1 - Essencial (Alta Prioridade)
1. **Payment Methods** - Gerenciamento completo (delete, update, detach)
2. **Products** - Criar e gerenciar produtos
3. **Prices** - Criar e gerenciar preços

### Fase 2 - Importante (Média Prioridade)
4. **Tax Rates** - Para compliance fiscal
5. **Promotion Codes** - Para campanhas de marketing
6. **Setup Intents** - Para trial periods sem pagamento inicial
7. **Subscription Items** - Para assinaturas com múltiplos produtos
8. **Invoice Items** - Para ajustes manuais e créditos
9. **Mais Webhooks** - Melhor integração automática

### Fase 3 - Opcional (Baixa Prioridade)
10. **Charges** - Auditoria detalhada
11. **Disputes** - Gerenciamento de chargebacks
12. **Balance Transactions** - Reconciliação financeira
13. **Payouts** - Gerenciamento de saques

---

## 🎯 Conclusão

**O que já temos:** Sistema robusto com as funcionalidades core do Stripe implementadas e testadas.

**O que falta:** Principalmente funcionalidades de gerenciamento (criar/atualizar/deletar) e algumas funcionalidades avançadas (tax rates, promotion codes, etc.).

**Recomendação:** Começar pela **Fase 1** (Payment Methods, Products, Prices) pois são essenciais para um sistema completo de pagamentos SaaS.


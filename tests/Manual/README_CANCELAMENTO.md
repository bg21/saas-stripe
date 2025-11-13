# Teste de Cancelamento de Assinatura

## 📋 Descrição

Este teste valida o funcionamento do cancelamento de assinaturas através do endpoint `DELETE /v1/subscriptions/:id`.

## ✅ Funcionalidades Testadas

1. **Cancelamento no Final do Período** (`immediately=false`)
   - Define `cancel_at_period_end = true`
   - Assinatura continua ativa até o fim do período
   - Status permanece como `active` ou `trialing`

2. **Cancelamento Imediato** (`immediately=true`)
   - Cancela a assinatura imediatamente
   - Status muda para `canceled`
   - Assinatura é encerrada na hora

3. **Validação de Erros**
   - Testa cancelamento de assinatura inexistente
   - Deve retornar 404

## 🚀 Como Executar

### Pré-requisitos

1. Servidor PHP rodando na porta 8080:
   ```bash
   php -S localhost:8080 -t public
   ```

2. Banco de dados configurado e populado com tenant válido

3. Variáveis de ambiente configuradas (`.env`):
   - `STRIPE_SECRET` - Chave secreta do Stripe
   - API key válida de um tenant

### Executar o Teste

```bash
php tests/Manual/test_cancelar_assinatura.php
```

## 📊 O que o Teste Faz

1. **Cria Produto e Preço no Stripe**
   - Produto: "Plano Teste Cancelamento"
   - Preço: R$ 19,99/mês

2. **Cria ou Obtém Customer**
   - Busca customer existente ou cria novo

3. **Teste 1: Cancelamento no Final do Período**
   - Cria assinatura com trial de 14 dias
   - Cancela com `immediately=false`
   - Verifica `cancel_at_period_end = true`
   - Verifica status no Stripe e no banco

4. **Teste 2: Cancelamento Imediato**
   - Cria nova assinatura com trial de 14 dias
   - Cancela com `immediately=true`
   - Verifica status `canceled` no Stripe
   - Verifica atualização no banco

5. **Teste 3: Validação de Erro**
   - Tenta cancelar assinatura inexistente (ID: 99999)
   - Verifica retorno 404

## ✅ Resultados Esperados

### Teste 1 - Cancelamento no Final do Período
- ✅ Status HTTP: 200
- ✅ `cancel_at_period_end = true` no Stripe
- ✅ Status pode ser `active`, `trialing` ou `active`
- ✅ Banco de dados atualizado

### Teste 2 - Cancelamento Imediato
- ✅ Status HTTP: 200
- ✅ Status `canceled` no Stripe (ou `trialing` se ainda em trial)
- ✅ Banco de dados atualizado

### Teste 3 - Validação de Erro
- ✅ Status HTTP: 404
- ✅ Mensagem de erro apropriada

## 📝 Observações Importantes

1. **Assinaturas em Trial:**
   - Assinaturas em período de trial podem não ser canceladas imediatamente
   - O Stripe pode manter o status como `trialing` até o fim do trial
   - Isso é comportamento esperado do Stripe

2. **Atualização no Banco:**
   - O sistema atualiza o banco após cancelamento
   - Campos `status` e `cancel_at_period_end` são atualizados

3. **Resposta da API:**
   - A resposta inclui informações detalhadas:
     - Status da assinatura
     - `cancel_at_period_end`
     - `canceled_at` (se cancelada imediatamente)
     - `current_period_end`

## 🔍 Verificação Manual

Após executar o teste, você pode verificar:

1. **No Stripe Dashboard:**
   - Acesse: https://dashboard.stripe.com/test/subscriptions
   - Verifique as assinaturas criadas
   - Confirme os status e `cancel_at_period_end`

2. **No Banco de Dados:**
   ```sql
   SELECT id, stripe_subscription_id, status, cancel_at_period_end, updated_at
   FROM subscriptions
   ORDER BY id DESC
   LIMIT 5;
   ```

3. **Nos Logs:**
   - Verifique `app.log` para logs detalhados
   - Procure por "Assinatura cancelada"

## 🐛 Troubleshooting

### Erro: "Erro ao criar customer"
- Verifique se o servidor está rodando
- Verifique se a API key está correta
- Verifique se o tenant está ativo no banco

### Erro: "Assinatura não encontrada"
- Verifique se a assinatura foi criada corretamente
- Verifique se o ID está correto
- Verifique se o tenant_id corresponde

### Status não muda para "canceled"
- Se a assinatura está em trial, isso é normal
- O Stripe mantém `trialing` até o fim do trial
- Verifique `cancel_at_period_end` em vez do status

## 📚 Documentação Relacionada

- [Stripe API - Cancel Subscription](https://stripe.com/docs/api/subscriptions/cancel)
- [Stripe API - Update Subscription](https://stripe.com/docs/api/subscriptions/update)
- [Análise Completa do Sistema](../docs/ANALISE_COMPLETA_SISTEMA.md)


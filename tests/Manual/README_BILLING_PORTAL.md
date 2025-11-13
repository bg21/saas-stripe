# Teste de Billing Portal

## 📋 Descrição

Este teste valida o funcionamento do Billing Portal através do endpoint `POST /v1/billing-portal`.

## ✅ Funcionalidades Testadas

1. **Criação de Sessão do Billing Portal**
   - Cria sessão para customer existente
   - Retorna URL do portal de cobrança
   - Valida URL retornada

2. **Validações**
   - `customer_id` obrigatório
   - `return_url` obrigatório
   - Customer não encontrado (404)

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

4. **IMPORTANTE: Billing Portal Configurado no Stripe**
   - Acesse: https://dashboard.stripe.com/test/settings/billing/portal
   - Configure pelo menos uma funcionalidade:
     - Atualizar método de pagamento
     - Ver histórico de faturas
     - Cancelar assinatura
   - Salve as configurações

### Executar o Teste

```bash
php tests/Manual/test_billing_portal.php
```

## 📊 O que o Teste Faz

1. **Cria ou Obtém Customer**
   - Busca customer existente ou cria novo

2. **Teste 1: Criação de Sessão**
   - Cria sessão do billing portal
   - Verifica URL retornada
   - Valida formato da URL

3. **Teste 2: Validação customer_id**
   - Tenta criar sessão sem `customer_id`
   - Verifica retorno 400

4. **Teste 3: Validação return_url**
   - Tenta criar sessão sem `return_url`
   - Verifica retorno 400

5. **Teste 4: Validação customer não encontrado**
   - Tenta criar sessão com customer inexistente
   - Verifica retorno 404

6. **Teste 5: Verificação no Stripe**
   - Verifica se customer existe no Stripe
   - Confirma que sessão foi criada

## ✅ Resultados Esperados

### Teste 1 - Criação de Sessão
- ✅ Status HTTP: 200 ou 201
- ✅ URL do portal retornada
- ✅ URL válida e do Stripe
- ⚠️ Se Billing Portal não configurado: mensagem informativa

### Teste 2 - Validação customer_id
- ✅ Status HTTP: 400
- ✅ Mensagem: "customer_id é obrigatório"

### Teste 3 - Validação return_url
- ✅ Status HTTP: 400
- ✅ Mensagem: "return_url é obrigatório"

### Teste 4 - Validação customer não encontrado
- ✅ Status HTTP: 404
- ✅ Mensagem: "Cliente não encontrado"

## 📝 Observações Importantes

1. **Billing Portal no Stripe:**
   - O Billing Portal precisa ser configurado no Stripe Dashboard antes de usar
   - Acesse: https://dashboard.stripe.com/test/settings/billing/portal
   - Configure pelo menos uma funcionalidade e salve

2. **URL do Portal:**
   - A URL retornada é válida por um período limitado
   - O customer pode usar essa URL para acessar o portal
   - No portal, o customer pode:
     - Atualizar método de pagamento
     - Ver histórico de faturas
     - Cancelar assinatura
     - Atualizar informações de cobrança

3. **Resposta da API:**
   - A resposta inclui:
     - `session_id` - ID da sessão
     - `url` - URL do portal
     - `customer` - ID do customer no Stripe
     - `return_url` - URL de retorno
     - `created` - Data de criação

## 🔍 Verificação Manual

Após executar o teste, você pode verificar:

1. **No Stripe Dashboard:**
   - Acesse: https://dashboard.stripe.com/test/customers
   - Verifique o customer usado no teste
   - Confirme que o Billing Portal está configurado

2. **Testar a URL:**
   - Copie a URL retornada pelo teste
   - Abra no navegador
   - Verifique se o portal de cobrança é exibido

3. **Nos Logs:**
   - Verifique `app.log` para logs detalhados
   - Procure por "Sessão de portal criada"

## 🐛 Troubleshooting

### Erro: "Billing Portal não configurado"
- **Solução:** Configure o Billing Portal no Stripe Dashboard
- Acesse: https://dashboard.stripe.com/test/settings/billing/portal
- Configure pelo menos uma funcionalidade e salve

### Erro: "Cliente não encontrado"
- Verifique se o customer existe no banco de dados
- Verifique se o `customer_id` está correto
- Verifique se o tenant_id corresponde

### URL não é retornada
- Verifique se o Billing Portal está configurado
- Verifique os logs para erros do Stripe
- Verifique se o customer existe no Stripe

## 📚 Documentação Relacionada

- [Stripe API - Billing Portal Sessions](https://stripe.com/docs/api/customer_portal/sessions)
- [Stripe - Customer Portal Setup](https://stripe.com/docs/billing/subscriptions/integrating-customer-portal)
- [Análise Completa do Sistema](../docs/ANALISE_COMPLETA_SISTEMA.md)


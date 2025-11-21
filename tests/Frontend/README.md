# Testes Frontend - Validações

Este diretório contém testes para as funções de validação frontend do sistema.

## 📋 Testes Disponíveis

### `validations.test.html`

Testes automatizados para as funções de validação de formatos Stripe implementadas em `public/app/validations.js`.

**Como executar:**

1. Abra o arquivo `validations.test.html` em um navegador
2. Certifique-se de que o servidor está rodando e o arquivo `/app/validations.js` está acessível
3. Os testes serão executados automaticamente ao carregar a página

**O que é testado:**

- ✅ Validação de `price_id`
- ✅ Validação de `product_id`
- ✅ Validação de `customer_id`
- ✅ Validação de `subscription_id`
- ✅ Validação de `payment_method_id`
- ✅ Validação de `invoice_id`
- ✅ Validação de `invoice_item_id`
- ✅ Validação de `balance_transaction_id`
- ✅ Validação de campos obrigatórios vs opcionais
- ✅ Validação de múltiplos campos simultaneamente
- ✅ Rejeição de formatos inválidos (caracteres especiais, espaços, prefixos errados)

**Resultados:**

Os testes exibem:
- Total de testes executados
- Número de testes que passaram
- Número de testes que falharam
- Taxa de sucesso (%)

Cada teste mostra:
- ✅ Verde: Teste passou
- ❌ Vermelho: Teste falhou (com mensagem de erro)

## 🎯 Cobertura de Testes

### Funções Testadas

1. **`validateStripeId(value, type, required)`**
   - Valida um único ID do Stripe
   - Testa todos os tipos de IDs suportados
   - Testa campos obrigatórios e opcionais
   - Testa formatos válidos e inválidos

2. **`validateStripeIds(fields)`**
   - Valida múltiplos IDs simultaneamente
   - Testa validação parcial (alguns válidos, outros inválidos)

### Padrões Testados

Todos os padrões de IDs Stripe são testados:
- `price_xxxxx`
- `prod_xxxxx`
- `cus_xxxxx`
- `sub_xxxxx`
- `pm_xxxxx`
- `in_xxxxx`
- `ii_xxxxx`
- `txn_xxxxx`
- E mais...

## 📝 Adicionando Novos Testes

Para adicionar novos testes, edite o arquivo `validations.test.html` e adicione novos casos na função apropriada:

```javascript
// Exemplo: Adicionar teste para novo tipo de ID
displayTest(container, 'Novo tipo de ID válido', 
    runTest('Novo tipo válido', () => {
        return validateStripeId('novo_123', 'novo_id', true) === null;
    })
);
```

## 🔍 Troubleshooting

### Erro: "Funções de validação não foram carregadas"

- Verifique se o servidor está rodando
- Certifique-se de que o arquivo `/app/validations.js` existe e está acessível
- Verifique o console do navegador para erros de JavaScript

### Testes não executam

- Verifique se o JavaScript está habilitado no navegador
- Abra o console do navegador (F12) para ver erros
- Certifique-se de que está acessando via servidor (não file://)

## 📊 Estatísticas Esperadas

Com a implementação completa, espera-se:
- **Total de Testes:** ~30-40 testes
- **Taxa de Sucesso:** 100%
- **Cobertura:** Todas as funções principais de validação

---

**Última Atualização:** 2025-01-18


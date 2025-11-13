# Testes Manuais

Esta pasta contém scripts úteis para testes manuais e verificação do sistema.

## 📋 Arquivos Disponíveis

### `test_api.php`
Script PHP completo para testar todos os endpoints da API.
```bash
php test_api.php
```

### `test_api.ps1`
Script PowerShell equivalente ao test_api.php.
```powershell
.\test_api.ps1
```

### `test_completo.php`
Teste completo de criação e listagem de clientes.
```bash
php test_completo.php
```

### `test_db.php`
Verifica conexão com banco de dados e lista tenants.
```bash
php test_db.php
```

### `verificar_setup.php`
Verifica toda a configuração do sistema (banco, variáveis de ambiente, etc.).
```bash
php verificar_setup.php
```

### `verificar_api_key.php`
Verifica e exibe a API key do tenant no banco de dados.
```bash
php verificar_api_key.php
```

### `test_criar_assinatura.php`
Testa a criação de assinatura no Stripe. **IMPORTANTE**: Requer um `price_id` válido do Stripe.
```bash
php test_criar_assinatura.php
```

**Nota**: Para criar uma assinatura, você precisa:
1. Ter um cliente criado (use `test_completo.php` primeiro)
2. Ter um produto/preço criado no Stripe Dashboard (https://dashboard.stripe.com/test/products)
3. Copiar o `price_id` (começa com `price_`) e usar no teste

### `test_completo_assinatura.php` ⭐ **RECOMENDADO**
Teste completo e automatizado que cria tudo automaticamente:
- Cria produto no Stripe
- Cria preço para o produto
- Cria ou obtém customer
- Adiciona método de pagamento de teste
- Cria assinatura via API
- Verifica tudo funcionou

```bash
php test_completo_assinatura.php
```

Este é o teste mais completo e recomendado para validar toda a funcionalidade de assinaturas.

### `test_checkout_payment_method.php` ⭐ **NOVO**
Teste completo de checkout e salvamento de payment method:
- Cria checkout session com customer
- Verifica se `payment_method_collection: 'always'` está configurado
- Simula webhook `checkout.session.completed`
- Verifica se payment method foi salvo e definido como padrão

```bash
php test_checkout_payment_method.php
```

**Importante**: Para testar completamente, você precisa:
1. Executar o script para criar a sessão de checkout
2. Acessar a URL do checkout retornada
3. Completar o pagamento com cartão de teste
4. O webhook será disparado automaticamente
5. Executar o script novamente para verificar se o payment method foi salvo

**Para testar webhooks localmente:**
```bash
stripe listen --forward-to http://localhost:8080/v1/webhook
```

## 🔑 API Key

Para usar os scripts de teste, você precisa da API key do tenant. Execute:

```bash
php verificar_api_key.php
```

Ou consulte o arquivo `API_KEY_CORRETA.md` nesta mesma pasta (contém a API key atual do ambiente de desenvolvimento).

## 📝 Nota

Estes scripts são para uso manual durante desenvolvimento. Para testes automatizados, use os testes PHPUnit em `tests/Unit/`.


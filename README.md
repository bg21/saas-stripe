# Sistema Base de Pagamentos SaaS

Sistema base reutilizável para gerenciar pagamentos, assinaturas e clientes via Stripe em PHP 8.2 usando FlightPHP.

## 🚀 Quer Integrar no Seu SaaS?

**👉 Consulte o [Guia Completo de Integração](docs/GUIA_INTEGRACAO_SAAS.md)** para saber como usar este sistema no seu SaaS.

**Resumo rápido:**
1. Execute `php scripts/setup_tenant.php` para criar seu tenant
2. Use a API Key gerada no seu SaaS
3. Use o [SDK PHP](sdk/PaymentsClient.php) ou faça requisições HTTP diretamente
4. Pronto! 🎉

## 🚀 Características

- ✅ Arquitetura MVC com PSR-4
- ✅ ActiveRecord simples sobre PDO
- ✅ Integração completa com Stripe API
- ✅ Webhooks seguros com idempotência (10+ eventos tratados)
- ✅ Autenticação via Bearer Token (API Key) + Session ID (usuários)
- ✅ Sistema de permissões (RBAC) - Admin, Editor, Viewer
- ✅ Cache com Redis (com fallback)
- ✅ Logging estruturado com Monolog
- ✅ Rate Limiting (Redis + MySQL fallback)
- ✅ Logs de Auditoria completos
- ✅ Health Check Avançado (DB, Redis, Stripe)
- ✅ Backup Automático do banco de dados
- ✅ Histórico de mudanças de assinaturas
- ✅ Testes com PHPUnit e scripts manuais
- ✅ Multi-tenant (SaaS)
- ✅ Migrations com Phinx

## 📋 Requisitos

- PHP 8.2+
- MySQL 8+
- Redis (opcional, mas recomendado)
- Composer
- Conta Stripe (teste ou produção)

## 🔧 Instalação

1. **Clone o repositório e instale dependências:**

```bash
composer install
```

2. **Configure o ambiente:**

Copie `.env.example` para `.env` e configure:

```env
APP_ENV=development
DB_HOST=127.0.0.1
DB_NAME=saas_payments
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
REDIS_URL=redis://127.0.0.1:6379
API_MASTER_KEY=minha_chave_de_api
LOG_PATH=app.log
BACKUP_DIR=backups
BACKUP_RETENTION_DAYS=30
BACKUP_COMPRESS=true
```

3. **Execute as migrations:**

```bash
composer run migrate
composer run seed
```

4. **Execute o servidor:**

```bash
php -S localhost:8080 -t public
```

## 📚 Estrutura do Projeto

```
├─ App/
│  ├─ Controllers/     # Controllers REST (24 controllers)
│  ├─ Models/          # Models ActiveRecord
│  ├─ Services/        # Lógica de negócio
│  ├─ Middleware/      # Middlewares (Auth, Rate Limit, Audit)
│  └─ Utils/           # Utilitários (Database, PermissionHelper)
├─ config/             # Configurações
├─ public/             # Ponto de entrada
├─ tests/              # Testes PHPUnit e scripts manuais
├─ scripts/            # Scripts utilitários (backup, testes)
├─ db/                 # Migrations e seeds (Phinx)
├─ docs/               # Documentação completa
└─ composer.json       # Dependências
```

## 🔌 Endpoints da API

### Autenticação

O sistema suporta **duas formas de autenticação**:

1. **API Key (Tenant)** - Para integração de sistemas externos
2. **Session ID (Usuários)** - Para autenticação de usuários individuais

Todas as rotas (exceto `/v1/webhook`, `/health`, `/health/detailed` e `/v1/auth/login`) requerem header:

```
Authorization: Bearer <api_key_ou_session_id>
```

### Rotas Disponíveis

#### Health Check
- `GET /health` - Status básico da API
- `GET /health/detailed` - Status detalhado (DB, Redis, Stripe)

#### Autenticação de Usuários
- `POST /v1/auth/login` - Login de usuário (email/senha)
- `POST /v1/auth/logout` - Logout de usuário
- `GET /v1/auth/me` - Informações do usuário logado

#### Clientes
- `POST /v1/customers` - Cria cliente
- `GET /v1/customers` - Lista clientes do tenant
- `GET /v1/customers/:id` - Obtém cliente específico
- `PUT /v1/customers/:id` - Atualiza cliente
- `GET /v1/customers/:id/invoices` - Lista faturas do cliente
- `GET /v1/customers/:id/payment-methods` - Lista métodos de pagamento
- `PUT /v1/customers/:id/payment-methods/:pm_id` - Atualiza método de pagamento
- `DELETE /v1/customers/:id/payment-methods/:pm_id` - Deleta método de pagamento
- `POST /v1/customers/:id/payment-methods/:pm_id/set-default` - Define método padrão

#### Checkout
- `POST /v1/checkout` - Cria sessão de checkout
- `GET /v1/checkout/:id` - Obtém sessão de checkout

#### Assinaturas
- `POST /v1/subscriptions` - Cria assinatura
- `GET /v1/subscriptions` - Lista assinaturas
- `GET /v1/subscriptions/:id` - Obtém assinatura específica
- `PUT /v1/subscriptions/:id` - Atualiza assinatura
- `DELETE /v1/subscriptions/:id` - Cancela assinatura
- `POST /v1/subscriptions/:id/reactivate` - Reativa assinatura cancelada
- `GET /v1/subscriptions/:id/history` - Histórico de mudanças
- `GET /v1/subscriptions/:id/history/stats` - Estatísticas do histórico

#### Subscription Items (Add-ons)
- `POST /v1/subscriptions/:subscription_id/items` - Adiciona item à assinatura
- `GET /v1/subscriptions/:subscription_id/items` - Lista itens da assinatura
- `GET /v1/subscription-items/:id` - Obtém item específico
- `PUT /v1/subscription-items/:id` - Atualiza item
- `DELETE /v1/subscription-items/:id` - Remove item

#### Webhooks
- `POST /v1/webhook` - Recebe webhooks do Stripe (validação automática)

**Eventos tratados:**
- `checkout.session.completed`
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `invoice.paid`
- `invoice.payment_failed`
- `invoice.upcoming`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.subscription.trial_will_end`
- `charge.dispute.created`
- `charge.refunded`

#### Portal de Cobrança
- `POST /v1/billing-portal` - Cria sessão do portal de cobrança

#### Faturas
- `GET /v1/invoices/:id` - Obtém fatura específica

#### Invoice Items (Ajustes Manuais)
- `POST /v1/invoice-items` - Cria item de fatura
- `GET /v1/invoice-items` - Lista itens de fatura
- `GET /v1/invoice-items/:id` - Obtém item específico
- `PUT /v1/invoice-items/:id` - Atualiza item
- `DELETE /v1/invoice-items/:id` - Deleta item

#### Produtos
- `POST /v1/products` - Cria produto
- `GET /v1/products/:id` - Obtém produto específico
- `PUT /v1/products/:id` - Atualiza produto
- `DELETE /v1/products/:id` - Deleta produto (soft delete)

#### Preços
- `GET /v1/prices` - Lista preços/products disponíveis
- `POST /v1/prices` - Cria preço
- `GET /v1/prices/:id` - Obtém preço específico
- `PUT /v1/prices/:id` - Atualiza preço

#### Payment Intents
- `POST /v1/payment-intents` - Cria payment intent (pagamentos únicos)

#### Reembolsos
- `POST /v1/refunds` - Reembolsa pagamento

#### Setup Intents
- `POST /v1/setup-intents` - Cria setup intent (salvar payment method sem cobrar)
- `GET /v1/setup-intents/:id` - Obtém setup intent
- `POST /v1/setup-intents/:id/confirm` - Confirma setup intent

#### Cupons de Desconto
- `POST /v1/coupons` - Cria cupom
- `GET /v1/coupons` - Lista cupons
- `GET /v1/coupons/:id` - Obtém cupom específico
- `DELETE /v1/coupons/:id` - Deleta cupom

#### Códigos Promocionais
- `POST /v1/promotion-codes` - Cria código promocional
- `GET /v1/promotion-codes` - Lista códigos promocionais
- `GET /v1/promotion-codes/:id` - Obtém código específico
- `PUT /v1/promotion-codes/:id` - Atualiza código promocional

#### Tax Rates (Impostos)
- `POST /v1/tax-rates` - Cria taxa de imposto
- `GET /v1/tax-rates` - Lista taxas de imposto
- `GET /v1/tax-rates/:id` - Obtém taxa específica
- `PUT /v1/tax-rates/:id` - Atualiza taxa de imposto

#### Estatísticas
- `GET /v1/stats` - Estatísticas e métricas do sistema

#### Disputes (Chargebacks)
- `GET /v1/disputes` - Lista disputas
- `GET /v1/disputes/:id` - Obtém disputa específica
- `PUT /v1/disputes/:id` - Atualiza disputa (adiciona evidências)

#### Balance Transactions
- `GET /v1/balance-transactions` - Lista transações de saldo
- `GET /v1/balance-transactions/:id` - Obtém transação específica

#### Charges (Cobranças)
- `GET /v1/charges` - Lista charges
- `GET /v1/charges/:id` - Obtém charge específica
- `PUT /v1/charges/:id` - Atualiza charge (metadata)

#### Audit Logs
- `GET /v1/audit-logs` - Lista logs de auditoria (com filtros)
- `GET /v1/audit-logs/:id` - Obtém log específico

#### Usuários (Apenas Admin)
- `GET /v1/users` - Lista usuários
- `GET /v1/users/:id` - Obtém usuário específico
- `POST /v1/users` - Cria usuário
- `PUT /v1/users/:id` - Atualiza usuário
- `DELETE /v1/users/:id` - Deleta usuário
- `PUT /v1/users/:id/role` - Atualiza role do usuário

#### Permissões (Apenas Admin)
- `GET /v1/permissions` - Lista permissões disponíveis
- `GET /v1/users/:id/permissions` - Lista permissões do usuário
- `POST /v1/users/:id/permissions` - Concede permissão
- `DELETE /v1/users/:id/permissions/:permission` - Revoga permissão

## 🔐 Segurança

### Autenticação
- **API Keys** - Armazenadas com hash único
- **Session IDs** - Tokens seguros para usuários
- **Master Key** - Para operações administrativas

### Validação
- Webhooks validam signature do Stripe
- Idempotência em eventos de webhook
- Senhas usando bcrypt
- Prepared statements (PDO) para prevenir SQL injection

### Rate Limiting
- Limite por API key ou IP
- Suporte a Redis (com fallback MySQL)
- Headers informativos (X-RateLimit-*)

### Auditoria
- Logs completos de todas as requisições
- Rastreamento de ações por usuário/tenant
- Retenção configurável

### Permissões (RBAC)
- **Roles:** Admin, Editor, Viewer
- **Permissões granulares:** Por funcionalidade
- **Controle individual:** Permissões customizadas por usuário

## 🧪 Testes

### Testes Automatizados (PHPUnit)

Execute os testes:

```bash
composer test
# ou
vendor/bin/phpunit
```

### Testes Manuais

Para testes manuais e scripts úteis, consulte a pasta `tests/Manual/` e o arquivo `tests/Manual/README.md`.

**Scripts de teste disponíveis:**
- `test_charges.php` - Testa charges
- `test_disputes.php` - Testa disputes
- `test_balance_transactions.php` - Testa balance transactions
- `test_backup.php` - Testa sistema de backup
- E muitos outros...

## 📝 Exemplos de Uso

### Criar um Tenant

```bash
php scripts/setup_tenant.php "Nome do Tenant"
```

Ou via SQL:

```sql
INSERT INTO tenants (name, api_key, status) 
VALUES ('Meu SaaS', 'sua_api_key_aqui', 'active');
```

### Criar Cliente

```bash
curl -X POST http://localhost:8080/v1/customers \
  -H "Authorization: Bearer sua_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "cliente@example.com",
    "name": "João Silva"
  }'
```

### Criar Sessão de Checkout

```bash
curl -X POST http://localhost:8080/v1/checkout \
  -H "Authorization: Bearer sua_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "line_items": [{
      "price": "price_xxx",
      "quantity": 1
    }],
    "mode": "subscription",
    "success_url": "https://seusite.com/success",
    "cancel_url": "https://seusite.com/cancel"
  }'
```

### Login de Usuário

```bash
curl -X POST http://localhost:8080/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "senha123"
  }'
```

### Criar Backup

```bash
composer run backup
# ou
php scripts/backup.php create
```

### Verificar Health

```bash
curl http://localhost:8080/health/detailed
```

## 🛠️ Desenvolvimento

### Migrations

```bash
# Criar nova migration
composer run migrate:create NomeDaMigration

# Executar migrations
composer run migrate

# Reverter última migration
composer run migrate:rollback

# Executar seeds
composer run seed
```

### Backup

```bash
# Criar backup
composer run backup

# Listar backups
composer run backup:list

# Estatísticas
composer run backup:stats

# Limpar backups antigos
composer run backup:clean
```

### Adicionar Nova Rota

1. Crie o Controller em `App/Controllers/`
2. Adicione a rota em `public/index.php`
3. Configure autenticação se necessário
4. Adicione permissões se for autenticação de usuário

### Adicionar Novo Model

1. Estenda `BaseModel` em `App/Models/`
2. Defina `$table` e métodos específicos
3. Crie migration se necessário

## 📚 Documentação Adicional

- **[Checklist Completo](docs/checklist.md)** - Lista completa de funcionalidades
- **[Análise de Implementações Pendentes](docs/ANALISE_IMPLEMENTACOES_PENDENTES.md)** - O que ainda falta implementar
- **[Guia de Integração](docs/GUIA_INTEGRACAO_SAAS.md)** - Como integrar no seu SaaS
- **[Sistema de Migrations](docs/MIGRATIONS.md)** - Como usar migrations
- **[Backup Automático](docs/BACKUP_AUTOMATICO.md)** - Documentação do sistema de backup

## 📄 Licença

Este projeto é uma base reutilizável para projetos SaaS.

## 🤝 Contribuindo

Este é um sistema base. Adapte conforme suas necessidades específicas.

---

**Versão:** 1.0.3  
**Última Atualização:** 2025-01-16

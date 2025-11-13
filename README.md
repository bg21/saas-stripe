# Sistema Base de Pagamentos SaaS

Sistema base reutilizável para gerenciar pagamentos, assinaturas e clientes via Stripe em PHP 8.2 usando FlightPHP.

## 🚀 Características

- ✅ Arquitetura MVC com PSR-4
- ✅ ActiveRecord simples sobre PDO
- ✅ Integração completa com Stripe API
- ✅ Webhooks seguros com idempotência
- ✅ Autenticação via Bearer Token (API Key)
- ✅ Cache com Redis
- ✅ Logging estruturado com Monolog
- ✅ Testes com PHPUnit
- ✅ Multi-tenant (SaaS)

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
```

3. **Crie o banco de dados:**

```bash
mysql -u root -p < schema.sql
```

4. **Execute o servidor:**

```bash
php -S localhost:8080 -t public
```

## 📚 Estrutura do Projeto

```
├─ App/
│  ├─ Controllers/     # Controllers REST
│  ├─ Models/          # Models ActiveRecord
│  ├─ Services/        # Lógica de negócio
│  ├─ Middleware/      # Middlewares (Auth)
│  └─ Utils/           # Utilitários (Database)
├─ config/             # Configurações
├─ public/             # Ponto de entrada
├─ tests/              # Testes PHPUnit
├─ schema.sql          # Schema do banco
└─ composer.json       # Dependências
```

## 🔌 Endpoints da API

### Autenticação

Todas as rotas (exceto `/v1/webhook` e `/health`) requerem header:

```
Authorization: Bearer <api_key>
```

### Rotas Disponíveis

#### Health Check
- `GET /health` - Status da API

#### Clientes
- `POST /v1/customers` - Cria cliente
- `GET /v1/customers` - Lista clientes do tenant

#### Checkout
- `POST /v1/checkout` - Cria sessão de checkout

#### Assinaturas
- `POST /v1/subscriptions` - Cria assinatura
- `GET /v1/subscriptions` - Lista assinaturas
- `DELETE /v1/subscriptions/:id` - Cancela assinatura

#### Webhooks
- `POST /v1/webhook` - Recebe webhooks do Stripe

#### Portal de Cobrança
- `POST /v1/billing-portal` - Cria sessão do portal

#### Faturas
- `GET /v1/invoices/:id` - Obtém fatura

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

## 📝 Exemplos de Uso

### Criar um Tenant

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

## 🔐 Segurança

- API Keys são armazenadas com hash único
- Webhooks validam signature do Stripe
- Idempotência em eventos de webhook
- Senhas usando bcrypt
- Prepared statements (PDO) para prevenir SQL injection

## 🛠️ Desenvolvimento

### Adicionar Nova Rota

1. Crie o Controller em `App/Controllers/`
2. Adicione a rota em `public/index.php`
3. Configure autenticação se necessário

### Adicionar Novo Model

1. Estenda `BaseModel` em `App/Models/`
2. Defina `$table` e métodos específicos

## 📄 Licença

Este projeto é uma base reutilizável para projetos SaaS.

## 🤝 Contribuindo

Este é um sistema base. Adapte conforme suas necessidades específicas.


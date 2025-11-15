# Guia Rápido de Setup

## 🚀 Início Rápido

### 1. Instalar Dependências

```bash
composer install
```

### 2. Configurar Ambiente

O arquivo `.env` já foi criado automaticamente a partir do template. Se não existir, copie `env.template` para `.env` e configure:

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

### 3. Criar Banco de Dados

**Opção A: Usando Migrations (Recomendado)** ✅

```bash
# Executa todas as migrations pendentes
composer run migrate

# (Opcional) Executa seeds para criar dados de teste
composer run seed
```

**Opção B: Usando Schema SQL (Método Antigo)**

```bash
mysql -u root -p < schema.sql
```

### 4. Criar Tenant de Teste (Opcional)

**Se usou migrations (Opção A):**
O seed já foi executado e criou um tenant de teste. A API key será exibida no terminal.

**Se usou schema.sql (Opção B):**
```bash
mysql -u root -p saas_payments < seed_example.sql
```

### 5. Iniciar Servidor

```bash
php -S localhost:8080 -t public
```

### 6. Testar API

```bash
# Health check
curl http://localhost:8080/health
```

Para mais informações sobre como testar a API, consulte:
- `tests/Manual/README.md` - Scripts de teste disponíveis
- `tests/Manual/TESTE_API.md` - Guia completo de testes

## 📝 Notas Importantes

- **Migrations**: O sistema agora suporta migrations via Phinx. Consulte `docs/MIGRATIONS.md` para mais detalhes
- **Redis**: O sistema funciona sem Redis, mas o cache não estará disponível
- **Stripe**: Use chaves de teste (`sk_test_`) para desenvolvimento
- **Webhooks**: Configure o endpoint `http://seu-dominio/v1/webhook` no painel do Stripe
- **API Keys**: Gere API keys únicas para cada tenant usando o método `generateApiKey()` do modelo `Tenant`

## 📦 Migrations

O projeto utiliza **Phinx** para gerenciar migrations e seeds do banco de dados.

**Comandos úteis:**
- `composer run migrate` - Executa migrations pendentes
- `composer run migrate:status` - Verifica status das migrations
- `composer run migrate:rollback` - Reverte última migration
- `composer run seed` - Executa seeds

Para mais informações, consulte: `docs/MIGRATIONS.md`

## 🔧 Troubleshooting

### Erro de conexão com banco
- Verifique se o MySQL está rodando
- Confirme as credenciais no `.env`

### Erro de autenticação
- Verifique se o header `Authorization: Bearer <api_key>` está correto
- Confirme que o tenant está ativo no banco

### Redis não disponível
- O sistema continua funcionando, apenas sem cache
- Verifique os logs para avisos do Redis


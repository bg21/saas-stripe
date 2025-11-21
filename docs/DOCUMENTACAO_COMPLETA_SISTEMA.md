# 📚 Documentação Completa do Sistema - SaaS Payments Core

**Versão do Sistema:** 1.0.5  
**Data da Documentação:** 2025-01-21  
**Linguagem:** PHP 8.2  
**Framework:** FlightPHP 1.3  
**Banco de Dados:** MySQL 8.0+  
**Cache:** Redis (Predis) com fallback

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Tecnologias e Dependências](#tecnologias-e-dependências)
3. [Arquitetura do Sistema](#arquitetura-do-sistema)
4. [Design Patterns Implementados](#design-patterns-implementados)
5. [Estrutura de Diretórios](#estrutura-de-diretórios)
6. [Componentes Principais](#componentes-principais)
7. [Fluxos de Dados](#fluxos-de-dados)
8. [Segurança](#segurança)
9. [Autenticação e Autorização](#autenticação-e-autorização)
10. [Integração com Stripe](#integração-com-stripe)
11. [Cache e Performance](#cache-e-performance)
12. [Logging e Auditoria](#logging-e-auditoria)
13. [Testes](#testes)
14. [Migrations e Versionamento](#migrations-e-versionamento)
15. [APIs e Endpoints](#apis-e-endpoints)
16. [Frontend e Views](#frontend-e-views)
17. [Deploy e Produção](#deploy-e-produção)

---

## 🎯 Visão Geral

### Propósito

Este sistema é uma **base reutilizável** para gerenciar pagamentos, assinaturas e clientes via Stripe em aplicações SaaS. Foi projetado para ser facilmente integrado em múltiplos sistemas SaaS, fornecendo um núcleo robusto de funcionalidades de pagamento.

### Características Principais

- ✅ **Multi-tenant (SaaS)**: Cada tenant possui sua própria API key e isolamento completo de dados
- ✅ **Integração Completa com Stripe**: 60+ endpoints implementados
- ✅ **Sistema de Usuários e Permissões (RBAC)**: Admin, Editor, Viewer com permissões granulares
- ✅ **Webhooks Seguros**: Idempotência e validação de assinatura
- ✅ **Rate Limiting**: Proteção contra abuso com Redis + MySQL fallback
- ✅ **Logs de Auditoria**: Rastreamento completo de todas as ações
- ✅ **Backup Automático**: Sistema de backup do banco de dados
- ✅ **Health Check**: Verificação de dependências (DB, Redis, Stripe)
- ✅ **Cache Inteligente**: Redis com fallback automático
- ✅ **Validação Robusta**: Validação de inputs e IDs Stripe
- ✅ **Tratamento de Erros**: Mensagens amigáveis e códigos HTTP apropriados

### Arquitetura Geral

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (Views/SPA)                      │
│              (HTML, JavaScript, Bootstrap)                    │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           │ HTTP/REST API
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                    FlightPHP Router                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              Middleware Stack                          │  │
│  │  • CORS & Security Headers                            │  │
│  │  • Payload Size Validation                            │  │
│  │  • Authentication (API Key / Session ID)             │  │
│  │  • Rate Limiting                                      │  │
│  │  • Audit Logging                                      │  │
│  │  • Permission Check                                   │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                  Controllers                          │  │
│  │  (26 controllers - Customer, Subscription, etc.)      │  │
│  └──────────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼──────┐  ┌────────▼────────┐  ┌─────▼──────┐
│   Services   │  │     Models      │  │   Utils    │
│              │  │  (ActiveRecord)  │  │            │
│ • Stripe     │  │ • Customer      │  │ • Database │
│ • Payment    │  │ • Subscription  │  │ • Validator│
│ • Cache      │  │ • Tenant        │  │ • Logger   │
│ • Logger     │  │ • User          │  │ • Security │
│ • RateLimit  │  │ • AuditLog       │  │ • Response │
│ • Backup     │  │ • ...           │  │ • ...      │
│ • Report     │  │                 │  │            │
└───────┬──────┘  └────────┬────────┘  └─────┬──────┘
        │                  │                  │
        └──────────────────┼──────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼──────┐  ┌────────▼────────┐  ┌─────▼──────┐
│   MySQL 8    │  │     Redis        │  │   Stripe   │
│   (PDO)      │  │   (Cache/Lock)  │  │    API     │
└──────────────┘  └──────────────────┘  └────────────┘
```

---

## 🛠️ Tecnologias e Dependências

### Linguagem e Versão

- **PHP 8.2+**: Tipagem forte, propriedades readonly, enums, match expressions
- **PSR-12**: Padrão de codificação seguido rigorosamente
- **PSR-4**: Autoloading de classes

### Dependências Principais (composer.json)

#### Produção

```json
{
  "mikecao/flight": "^1.3",           // Microframework HTTP
  "stripe/stripe-php": "^10.0",        // SDK oficial do Stripe
  "monolog/monolog": "^3.0",           // Logging estruturado
  "predis/predis": "^2.0",             // Cliente Redis
  "vlucas/phpdotenv": "^5.5",          // Gerenciamento de .env
  "ifsnop/mysqldump-php": "^2.12",     // Backup do banco
  "zircote/swagger-php": "^5.7"         // Documentação OpenAPI
}
```

#### Desenvolvimento

```json
{
  "phpunit/phpunit": "^10.0",          // Framework de testes
  "robmorgan/phinx": "^0.16.10"         // Migrations do banco
}
```

### Banco de Dados

- **MySQL 8.0+**: Recursos utilizados:
  - Window Functions (`COUNT(*) OVER()`)
  - JSON columns
  - Full-text search
  - Índices compostos
  - Foreign keys com ON DELETE CASCADE

### Cache

- **Redis**: Cache distribuído (opcional, com fallback)
- **Predis**: Cliente PHP para Redis
- **Fallback**: Sistema continua funcionando sem Redis

### Servidor Web

- **PHP Built-in Server**: Para desenvolvimento
- **Apache/Nginx**: Para produção (configuração não incluída)
- **Sem Docker**: Sistema projetado para PHP puro

---

## 🏗️ Arquitetura do Sistema

### Padrão Arquitetural: MVC (Model-View-Controller)

O sistema segue rigorosamente o padrão MVC:

#### **Models (ActiveRecord Pattern)**
- Herdam de `BaseModel`
- Abstração de acesso ao banco de dados
- Métodos CRUD automáticos
- Validação de relacionamentos
- Soft deletes (quando ativado)

#### **Views**
- Templates PHP para frontend
- Separação de layout e conteúdo
- Helpers para renderização

#### **Controllers**
- Lógica de negócio
- Validação de inputs
- Orquestração de Services e Models
- Respostas padronizadas via `ResponseHelper`

### Camadas da Aplicação

```
┌─────────────────────────────────────────┐
│         Presentation Layer             │
│  (Views, Controllers, Middleware)       │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│         Business Logic Layer            │
│  (Services: Payment, Stripe, etc.)      │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│         Data Access Layer               │
│  (Models: Customer, Subscription, etc.)  │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│         Infrastructure Layer            │
│  (Database, Cache, Logger, etc.)       │
└─────────────────────────────────────────┘
```

---

## 🎨 Design Patterns Implementados

### 1. Singleton Pattern

**Onde:** `Database`, `CacheService`, `Logger`

**Exemplo:**
```php
class Database
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO(...);
        }
        return self::$instance;
    }
}
```

**Benefícios:**
- Uma única conexão por requisição
- Reduz overhead de conexões
- Gerenciamento centralizado

### 2. ActiveRecord Pattern

**Onde:** Todos os Models (`Customer`, `Subscription`, `Tenant`, etc.)

**Exemplo:**
```php
class Customer extends BaseModel
{
    protected string $table = 'customers';
    protected string $primaryKey = 'id';
    
    // Métodos herdados de BaseModel:
    // - findById($id)
    // - findAll($conditions, $orderBy, $limit, $offset)
    // - insert($data)
    // - update($id, $data)
    // - delete($id)
    // - count($conditions)
}
```

**Benefícios:**
- Abstração de SQL
- Código mais limpo e legível
- Reutilização de lógica comum

### 3. Repository Pattern (Parcial)

**Onde:** Services encapsulam acesso a múltiplos Models

**Exemplo:**
```php
class PaymentService
{
    public function createCustomer(int $tenantId, array $data): array
    {
        // Orquestra: StripeService + Customer Model
        $stripeCustomer = $this->stripeService->createCustomer(...);
        $customer = $this->customerModel->createOrUpdate(...);
        return $customer;
    }
}
```

### 4. Dependency Injection

**Onde:** Controllers recebem Services via construtor

**Exemplo:**
```php
class CustomerController
{
    private PaymentService $paymentService;
    private StripeService $stripeService;
    
    public function __construct(
        PaymentService $paymentService,
        StripeService $stripeService
    ) {
        $this->paymentService = $paymentService;
        $this->stripeService = $stripeService;
    }
}
```

**Benefícios:**
- Testabilidade
- Baixo acoplamento
- Facilita mocks em testes

### 5. Middleware Pattern

**Onde:** `AuthMiddleware`, `RateLimitMiddleware`, `AuditMiddleware`, etc.

**Exemplo:**
```php
$app->before('start', function() use ($rateLimitMiddleware) {
    $allowed = $rateLimitMiddleware->check($requestUri);
    if (!$allowed) {
        $app->stop();
    }
});
```

**Benefícios:**
- Separação de responsabilidades
- Reutilização de lógica
- Pipeline de processamento

### 6. Strategy Pattern

**Onde:** `CacheService` (Redis com fallback), `ErrorHandler` (diferentes estratégias de erro)

**Exemplo:**
```php
class CacheService
{
    public static function get(string $key): ?string
    {
        $client = self::getClient();
        if ($client === null) {
            return null; // Fallback: retorna null se Redis não disponível
        }
        return $client->get($key);
    }
}
```

### 7. Factory Pattern (Implícito)

**Onde:** `ResponseHelper` cria diferentes tipos de resposta

**Exemplo:**
```php
class ResponseHelper
{
    public static function sendCreated($data, string $message): void
    {
        Flight::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 201);
    }
    
    public static function sendValidationError(...): void
    {
        // Resposta de validação
    }
}
```

### 8. Observer Pattern (Parcial)

**Onde:** `AuditMiddleware` observa todas as requisições

**Exemplo:**
```php
$app->before('start', function() use ($auditMiddleware) {
    $auditMiddleware->captureRequest();
});
```

### 9. Template Method Pattern

**Onde:** `BaseModel` define estrutura, models específicos implementam detalhes

**Exemplo:**
```php
abstract class BaseModel
{
    abstract protected function getTable(): string;
    
    public function findAll(...): array
    {
        // Template method que usa getTable()
        $sql = "SELECT * FROM {$this->table}";
        // ...
    }
}
```

### 10. Facade Pattern

**Onde:** `Logger`, `CacheService`, `ResponseHelper` (métodos estáticos)

**Exemplo:**
```php
Logger::info('Mensagem', ['context' => 'data']);
CacheService::set('key', 'value');
ResponseHelper::sendSuccess($data);
```

---

## 📁 Estrutura de Diretórios

```
saas-stripe/
├── App/                          # Código da aplicação
│   ├── Controllers/              # 26 controllers
│   │   ├── CustomerController.php
│   │   ├── SubscriptionController.php
│   │   ├── CheckoutController.php
│   │   ├── WebhookController.php
│   │   ├── AuthController.php
│   │   └── ... (21 outros)
│   │
│   ├── Models/                   # 11 models (ActiveRecord)
│   │   ├── BaseModel.php         # Classe base com CRUD
│   │   ├── Customer.php
│   │   ├── Subscription.php
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   ├── UserSession.php
│   │   ├── UserPermission.php
│   │   ├── AuditLog.php
│   │   ├── SubscriptionHistory.php
│   │   ├── StripeEvent.php
│   │   └── BackupLog.php
│   │
│   ├── Services/                 # 8 services
│   │   ├── StripeService.php    # Wrapper da API Stripe
│   │   ├── PaymentService.php   # Lógica de negócio de pagamentos
│   │   ├── CacheService.php     # Cache com Redis
│   │   ├── Logger.php             # Logging estruturado
│   │   ├── RateLimiterService.php # Rate limiting
│   │   ├── BackupService.php     # Backup automático
│   │   ├── ReportService.php     # Relatórios e analytics
│   │   └── AnomalyDetectionService.php
│   │
│   ├── Middleware/               # 7 middlewares
│   │   ├── AuthMiddleware.php    # Autenticação (API Key)
│   │   ├── UserAuthMiddleware.php # Autenticação (Session ID)
│   │   ├── PermissionMiddleware.php # Verificação de permissões
│   │   ├── RateLimitMiddleware.php # Rate limiting
│   │   ├── LoginRateLimitMiddleware.php # Rate limit de login
│   │   ├── AuditMiddleware.php   # Logs de auditoria
│   │   └── PayloadSizeMiddleware.php # Validação de tamanho
│   │
│   ├── Utils/                    # 8 utilitários
│   │   ├── Database.php          # Singleton PDO
│   │   ├── Validator.php         # Validação de inputs
│   │   ├── ErrorHandler.php      # Tratamento de erros
│   │   ├── ResponseHelper.php     # Respostas padronizadas
│   │   ├── SecurityHelper.php    # Helpers de segurança
│   │   ├── PermissionHelper.php  # Helpers de permissões
│   │   ├── View.php              # Renderização de views
│   │   └── RequestCache.php      # Cache de request body
│   │
│   └── Views/                    # 35 views (templates PHP)
│       ├── layouts/
│       │   └── base.php          # Layout base
│       ├── dashboard.php
│       ├── customers.php
│       ├── subscriptions.php
│       └── ... (32 outras)
│
├── config/
│   └── config.php                # Carregamento de .env
│
├── db/
│   ├── migrations/               # 11 migrations (Phinx)
│   └── seeds/                    # 2 seeds
│
├── public/
│   ├── index.php                 # Entry point da aplicação
│   ├── app/                      # Assets frontend (JS)
│   └── css/                      # Estilos
│
├── tests/
│   ├── Unit/                     # 20 testes unitários
│   ├── Manual/                   # Scripts de teste manual
│   └── Frontend/                 # Testes de frontend
│
├── scripts/                      # Scripts utilitários
│   ├── setup_tenant.php          # Criar novo tenant
│   ├── backup.php                # Backup do banco
│   └── ... (outros)
│
├── sdk/                          # SDK PHP para integração
│   └── PaymentsClient.php
│
├── docs/                         # Documentação
│   ├── GUIA_INTEGRACAO_SAAS.md
│   ├── INTEGRACAO_FRONTEND.md
│   ├── SISTEMA_PERMISSOES.md
│   └── ... (outros)
│
├── vendor/                       # Dependências Composer
├── composer.json
├── composer.lock
├── phpunit.xml                   # Configuração PHPUnit
├── phinx.php                     # Configuração Phinx
├── schema.sql                    # Schema completo do banco
├── .env                          # Variáveis de ambiente
└── README.md
```

---

## 🔧 Componentes Principais

### Models (ActiveRecord)

Todos os models herdam de `BaseModel` e implementam:

#### BaseModel

**Métodos principais:**
- `findById(int $id): ?array` - Busca por ID
- `findByIdSelect(int $id, array $fields): ?array` - Busca com campos específicos
- `findAll(array $conditions, array $orderBy, int $limit, int $offset): array` - Lista com filtros
- `findAllWithCount(...): array` - Lista com contagem total (window function)
- `select(array $fields, ...): array` - SELECT com campos específicos
- `count(array $conditions): int` - Contagem
- `insert(array $data): int|string` - Inserção
- `update(int $id, array $data): bool` - Atualização
- `delete(int $id): bool` - Exclusão (soft delete se ativo)
- `restore(int $id): bool` - Restauração (soft delete)
- `withTrashed(...): array` - Busca incluindo deletados
- `onlyTrashed(...): array` - Busca apenas deletados
- `findBy(string $field, $value): ?array` - Busca por campo único

**Recursos:**
- ✅ Soft deletes (ativável por model)
- ✅ Validação de campos permitidos (whitelist)
- ✅ Sanitização de inputs (prevenção SQL injection)
- ✅ Suporte a condições OR e LIKE
- ✅ Window functions (MySQL 8+)

#### Models Específicos

**Customer:**
- `createOrUpdate(int $tenantId, array $stripeData): array`
- `findByTenantAndId(int $tenantId, int $id): ?array`
- `findByStripeCustomerId(string $stripeId): ?array`
- Soft delete ativado

**Subscription:**
- `createOrUpdate(int $tenantId, array $stripeData): array`
- `findByTenantAndId(int $tenantId, int $id): ?array`
- `findByStripeSubscriptionId(string $stripeId): ?array`
- Validação de relacionamentos (tenant_id, customer_id)
- Soft delete ativado

**Tenant:**
- `findByApiKey(string $apiKey): ?array`
- `generateApiKey(): string`
- Soft delete ativado

**User:**
- `findByEmailAndTenant(string $email, int $tenantId): ?array`
- `updateRole(int $userId, string $role): bool`
- `isAdmin(int $userId): bool`
- Hashing de senha com bcrypt

**UserSession:**
- `create(int $userId, string $ipAddress): string` - Cria sessão e retorna session_id
- `validate(string $sessionId): ?array` - Valida sessão
- `invalidate(int $userId): void` - Invalida todas as sessões do usuário
- Expiração automática

**UserPermission:**
- `grant(int $userId, string $permission): bool`
- `revoke(int $userId, string $permission): bool`
- `hasPermission(int $userId, string $permission): bool`
- `listUserPermissions(int $userId): array`

**AuditLog:**
- `log(string $action, array $data, ?int $userId, ?int $tenantId): void`
- `findByTenant(int $tenantId, array $filters): array`

**SubscriptionHistory:**
- `logChange(int $subscriptionId, string $event, array $oldData, array $newData): void`
- `getHistory(int $subscriptionId): array`

**StripeEvent:**
- `markProcessed(string $eventId): void`
- `isProcessed(string $eventId): bool` - Idempotência de webhooks

### Services

#### StripeService

Wrapper da API Stripe com tratamento de erros.

**Métodos principais:**
- `createCustomer(array $data): \Stripe\Customer`
- `updateCustomer(string $customerId, array $data): \Stripe\Customer`
- `createSubscription(array $data): \Stripe\Subscription`
- `updateSubscription(string $subscriptionId, array $data): \Stripe\Subscription`
- `cancelSubscription(string $subscriptionId): \Stripe\Subscription`
- `createCheckoutSession(array $data): \Stripe\Checkout\Session`
- `createPaymentIntent(array $data): \Stripe\PaymentIntent`
- `createRefund(string $chargeId, array $data): \Stripe\Refund`
- `createProduct(array $data): \Stripe\Product`
- `createPrice(array $data): \Stripe\Price`
- `createCoupon(array $data): \Stripe\Coupon`
- `retrieveEvent(string $eventId): \Stripe\Event`
- E muitos outros...

**Recursos:**
- ✅ Tratamento de exceções Stripe
- ✅ Logging de erros
- ✅ Validação de dados antes de enviar

#### PaymentService

Lógica de negócio de pagamentos, orquestra StripeService e Models.

**Métodos principais:**
- `createCustomer(int $tenantId, array $data): array`
- `updateCustomer(int $tenantId, int $customerId, array $data): array`
- `createSubscription(int $tenantId, array $data): array`
- `updateSubscription(int $tenantId, int $subscriptionId, array $data): array`
- `cancelSubscription(int $tenantId, int $subscriptionId): array`
- `handleWebhook(string $eventType, array $eventData): void`

**Recursos:**
- ✅ Sincronização Stripe ↔ Banco de dados
- ✅ Validação de tenant_id
- ✅ Logging de operações

#### CacheService

Cache distribuído com Redis (fallback automático).

**Métodos principais:**
- `get(string $key): ?string`
- `set(string $key, string $value, int $ttl = 3600): bool`
- `delete(string $key): bool`
- `getJson(string $key): ?array`
- `setJson(string $key, array $value, int $ttl = 3600): bool`
- `lock(string $key, int $ttl = 60): bool` - Lock distribuído
- `unlock(string $key): bool`
- `invalidateCustomerCache(int $tenantId, ?int $customerId = null): void`
- `invalidateSubscriptionCache(int $tenantId, ?int $subscriptionId = null): void`

**Recursos:**
- ✅ Fallback automático (sistema continua sem Redis)
- ✅ Timeout de conexão (1 segundo)
- ✅ Cache de autenticação (5 minutos)
- ✅ Invalidação inteligente

#### Logger

Logging estruturado com Monolog.

**Métodos:**
- `info(string $message, array $context = []): void`
- `warning(string $message, array $context = []): void`
- `error(string $message, array $context = []): void`
- `debug(string $message, array $context = []): void`

**Recursos:**
- ✅ Logs em arquivo (`app-YYYY-MM-DD.log`)
- ✅ Formato JSON estruturado
- ✅ Contexto automático (tenant_id, user_id, action)
- ✅ Rotação diária de logs

#### RateLimiterService

Rate limiting com Redis (fallback MySQL).

**Métodos:**
- `check(string $key, array $options = []): bool`
- `getRemaining(string $key): int`
- `getResetTime(string $key): int`

**Recursos:**
- ✅ Limites configuráveis por endpoint
- ✅ Fallback para MySQL se Redis indisponível
- ✅ Headers de rate limit na resposta

#### BackupService

Backup automático do banco de dados.

**Métodos:**
- `createBackup(): string` - Retorna caminho do backup
- `listBackups(): array`
- `deleteBackup(string $filename): bool`
- `cleanOldBackups(int $days = 30): int`

**Recursos:**
- ✅ Compressão gzip
- ✅ Nomenclatura com timestamp
- ✅ Limpeza automática de backups antigos
- ✅ Logs de backup

#### ReportService

Relatórios e analytics.

**Métodos:**
- `getRevenueReport(int $tenantId, array $filters): array`
- `getSubscriptionReport(int $tenantId, array $filters): array`
- `getChurnReport(int $tenantId, array $filters): array`
- `getCustomerReport(int $tenantId, array $filters): array`
- `getMRR(int $tenantId, array $filters): array` - Monthly Recurring Revenue
- `getARR(int $tenantId, array $filters): array` - Annual Recurring Revenue

### Middleware

#### AuthMiddleware

Autenticação via API Key (Bearer Token).

**Fluxo:**
1. Extrai token do header `Authorization: Bearer <token>`
2. Verifica master key (se configurada)
3. Busca tenant pela API key
4. Valida status do tenant (ativo/inativo)
5. Injeta `tenant_id` no Flight

#### UserAuthMiddleware

Autenticação via Session ID (usuários logados).

**Fluxo:**
1. Extrai session_id do header `Authorization: Bearer <session_id>`
2. Valida sessão no banco
3. Verifica expiração
4. Injeta dados do usuário no Flight

#### PermissionMiddleware

Verificação de permissões (RBAC).

**Uso:**
```php
PermissionHelper::require('create_customers');
```

**Permissões:**
- `view_*`, `create_*`, `update_*`, `delete_*` para cada recurso
- Roles: `admin` (todas), `editor` (criar/editar), `viewer` (apenas visualizar)

#### RateLimitMiddleware

Rate limiting por endpoint.

**Limites padrão:**
- Rotas públicas: 10/min
- Webhooks: 200/min
- Endpoints de criação: 60/min
- Endpoints de atualização: 120/min
- Endpoints de exclusão: 30/min
- Outros: 100/min

#### AuditMiddleware

Logs de auditoria automáticos.

**Captura:**
- Método HTTP
- URL
- Query params
- Payload (sanitizado)
- Headers (sanitizados)
- IP do cliente
- User ID (se autenticado)
- Tenant ID
- Timestamp
- Status code da resposta
- Tempo de resposta

#### PayloadSizeMiddleware

Validação de tamanho de payload.

**Limites:**
- Endpoints críticos: 512KB
- Outros: 2MB

### Utils

#### Database

Singleton PDO com configurações otimizadas.

**Recursos:**
- ✅ Uma conexão por requisição
- ✅ PDO::ERRMODE_EXCEPTION
- ✅ Prepared statements (sem emulação)
- ✅ Timeout de conexão (2 segundos)

#### Validator

Validação de inputs.

**Métodos principais:**
- `validateSubscriptionCreate(array $data): array`
- `validateSubscriptionUpdate(array $data): array`
- `validateCustomerCreate(array $data): array`
- `validateCustomerUpdate(array $data): array`
- `validatePaymentIntentCreate(array $data): array`
- `validateCheckoutCreate(array $data): array`
- `validateAddress(array $data): array`
- `validateStripeId(string $value, string $type): array` - Valida 18 tipos de IDs Stripe
- `validateEmail(string $email): bool`
- `validatePhone(string $phone): bool`

**Recursos:**
- ✅ Validação de tipos
- ✅ Validação de formatos (email, telefone, URLs)
- ✅ Validação de ranges (valores mínimos/máximos)
- ✅ Validação de metadados (máx 50 chaves, 500 chars por valor)

#### ErrorHandler

Tratamento centralizado de erros.

**Métodos:**
- `logException(\Throwable $ex): void`
- `prepareErrorResponse(\Throwable $ex, string $message, string $code): array`
- `sendStripeError(\Stripe\Exception\ApiErrorException $e, string $message, array $context): void`

**Recursos:**
- ✅ Mapeamento de códigos de erro Stripe (30+ códigos)
- ✅ Mensagens amigáveis
- ✅ Códigos HTTP apropriados
- ✅ Contexto nos logs

#### ResponseHelper

Respostas padronizadas.

**Métodos:**
- `sendSuccess($data, string $message = 'Sucesso'): void`
- `sendCreated($data, string $message): void`
- `sendValidationError(string $message, array $errors, array $context = []): void`
- `sendUnauthorizedError(string $message, array $context = []): void`
- `sendForbiddenError(string $message, array $context = []): void`
- `sendNotFoundError(string $message, array $context = []): void`
- `sendStripeError(\Stripe\Exception\ApiErrorException $e, string $message, array $context = []): void`
- `sendGenericError(\Throwable $ex, string $message, string $code, array $context = []): void`
- `sendInvalidJsonError(array $context = []): void`

**Formato padrão:**
```json
{
  "success": true,
  "message": "Mensagem",
  "data": {...}
}
```

#### SecurityHelper

Helpers de segurança.

**Métodos:**
- `sanitizeInput(string $input): string`
- `generateApiKey(): string` - 64 caracteres hexadecimais
- `hashApiKey(string $apiKey): string` - SHA-256
- `verifyApiKey(string $apiKey, string $hash): bool` - hash_equals (timing-safe)

#### PermissionHelper

Helpers de permissões.

**Métodos:**
- `require(string $permission): void` - Lança exceção se sem permissão
- `hasPermission(string $permission): bool`
- `getUserRole(): ?string`
- `isAdmin(): bool`

---

## 🔄 Fluxos de Dados

### Fluxo de Autenticação (API Key)

```
1. Cliente envia requisição com header:
   Authorization: Bearer <api_key>

2. AuthMiddleware intercepta:
   ├─ Extrai token
   ├─ Verifica master key (se configurada)
   ├─ Busca tenant no banco (findByApiKey)
   ├─ Valida status (ativo/inativo)
   └─ Injeta tenant_id no Flight

3. Controller recebe requisição:
   ├─ Obtém tenant_id do Flight
   ├─ Valida permissões (se necessário)
   └─ Processa requisição

4. Resposta:
   └─ JSON padronizado via ResponseHelper
```

### Fluxo de Autenticação (Session ID)

```
1. Usuário faz login:
   POST /v1/auth/login
   {
     "email": "user@example.com",
     "password": "password"
   }

2. AuthController:
   ├─ Valida credenciais (User::findByEmailAndTenant)
   ├─ Verifica senha (password_verify)
   ├─ Cria sessão (UserSession::create)
   └─ Retorna session_id

3. Cliente armazena session_id e envia em requisições:
   Authorization: Bearer <session_id>

4. Middleware valida sessão:
   ├─ UserSession::validate(session_id)
   ├─ Verifica expiração
   └─ Injeta user_id, user_role, tenant_id no Flight

5. Controller processa com contexto do usuário
```

### Fluxo de Criação de Cliente

```
1. POST /v1/customers
   {
     "email": "customer@example.com",
     "name": "John Doe"
   }

2. CustomerController::create():
   ├─ Valida permissão (PermissionHelper::require)
   ├─ Obtém tenant_id do Flight
   ├─ Valida inputs (Validator::validateCustomerCreate)
   └─ Chama PaymentService::createCustomer

3. PaymentService::createCustomer:
   ├─ Chama StripeService::createCustomer
   │  └─ Cria customer no Stripe
   ├─ Customer::createOrUpdate
   │  ├─ Valida tenant_id existe
   │  └─ Insere no banco
   └─ Retorna dados do customer

4. CacheService::invalidateCustomerCache
   └─ Remove cache de listagem

5. ResponseHelper::sendCreated
   └─ Retorna JSON com status 201
```

### Fluxo de Webhook Stripe

```
1. Stripe envia evento:
   POST /v1/webhook
   {
     "type": "customer.subscription.created",
     "data": {...}
   }

2. WebhookController::handle:
   ├─ Valida assinatura (Stripe::constructEvent)
   ├─ Verifica idempotência (StripeEvent::isProcessed)
   ├─ Marca como processado (StripeEvent::markProcessed)
   └─ Chama PaymentService::handleWebhook

3. PaymentService::handleWebhook:
   ├─ Switch por tipo de evento
   ├─ Sincroniza dados Stripe ↔ Banco
   └─ Logging de operações

4. Eventos tratados:
   ├─ customer.subscription.created
   ├─ customer.subscription.updated
   ├─ customer.subscription.deleted
   ├─ invoice.payment_succeeded
   ├─ invoice.payment_failed
   ├─ checkout.session.completed
   └─ ... (10+ eventos)
```

### Fluxo de Rate Limiting

```
1. Requisição chega

2. RateLimitMiddleware intercepta:
   ├─ Identifica endpoint
   ├─ Obtém limite configurado
   ├─ Chama RateLimiterService::check
   │  ├─ Tenta Redis primeiro
   │  ├─ Se falhar, usa MySQL
   │  └─ Retorna true/false
   └─ Se excedido, retorna 429

3. Headers na resposta:
   X-RateLimit-Limit: 100
   X-RateLimit-Remaining: 95
   X-RateLimit-Reset: 1640000000
```

---

## 🔒 Segurança

### Autenticação

#### API Key (Tenant)
- **Formato:** 64 caracteres hexadecimais
- **Armazenamento:** Hash SHA-256 no banco
- **Validação:** `hash_equals()` (timing-safe)
- **Uso:** Requisições programáticas do SaaS

#### Session ID (Usuário)
- **Formato:** 64 caracteres hexadecimais
- **Armazenamento:** Hash SHA-256 no banco
- **Expiração:** 24 horas (configurável)
- **Validação:** Verificação de hash e expiração
- **Uso:** Usuários logados no dashboard

#### Master Key
- **Configuração:** Variável de ambiente `API_MASTER_KEY`
- **Uso:** Acesso administrativo total
- **Validação:** `hash_equals()` (timing-safe)

### Autorização (RBAC)

**Roles:**
- **admin:** Todas as permissões (implícitas)
- **editor:** Criar, editar, visualizar
- **viewer:** Apenas visualizar

**Permissões granulares:**
- `view_customers`, `create_customers`, `update_customers`, `delete_customers`
- `view_subscriptions`, `create_subscriptions`, `update_subscriptions`, `delete_subscriptions`
- `view_products`, `create_products`, `update_products`, `delete_products`
- E assim por diante...

### Proteções Implementadas

#### SQL Injection
- ✅ Prepared statements em todos os queries
- ✅ Sanitização de nomes de campos
- ✅ Whitelist de campos permitidos
- ✅ Validação de tipos

#### XSS (Cross-Site Scripting)
- ✅ Escape de HTML nas views
- ✅ Content Security Policy (CSP)
- ✅ Headers de segurança

#### CSRF (Cross-Site Request Forgery)
- ⚠️ Não implementado (API stateless)
- ✅ Validação de origem via CORS
- ✅ Autenticação obrigatória

#### IDOR (Insecure Direct Object Reference)
- ✅ Validação de tenant_id em todos os métodos
- ✅ Métodos `findByTenantAndId()` nos models
- ✅ Isolamento completo entre tenants

#### Rate Limiting
- ✅ Limites por endpoint
- ✅ Redis + MySQL fallback
- ✅ Headers informativos

#### Timing Attacks
- ✅ `hash_equals()` para comparação de hashes
- ✅ Validação de API keys e senhas

#### Headers de Segurança
- ✅ `X-Content-Type-Options: nosniff`
- ✅ `X-Frame-Options: DENY`
- ✅ `X-XSS-Protection: 1; mode=block`
- ✅ `Referrer-Policy: strict-origin-when-cross-origin`
- ✅ `Content-Security-Policy`
- ✅ `Strict-Transport-Security` (HTTPS)

#### Validação de Inputs
- ✅ Validação de tipos
- ✅ Validação de formatos (email, telefone, URLs)
- ✅ Validação de ranges
- ✅ Validação de metadados (máx 50 chaves, 500 chars)
- ✅ Validação de IDs Stripe (18 tipos)

#### Payload Size
- ✅ Limite de 512KB para endpoints críticos
- ✅ Limite de 2MB para outros endpoints

#### Logs de Auditoria
- ✅ Todas as requisições logadas
- ✅ Payload sanitizado (sem senhas)
- ✅ Headers sanitizados
- ✅ IP do cliente
- ✅ User ID e Tenant ID

---

## 🔐 Autenticação e Autorização

### Tipos de Autenticação

#### 1. API Key (Tenant)
**Uso:** Requisições programáticas do SaaS

**Exemplo:**
```bash
curl -X GET https://api.example.com/v1/customers \
  -H "Authorization: Bearer sk_live_abc123..."
```

**Fluxo:**
1. Tenant registra seu SaaS no sistema
2. Recebe uma API key única
3. Usa a API key em todas as requisições
4. Sistema identifica o tenant e isola dados

#### 2. Session ID (Usuário)
**Uso:** Usuários logados no dashboard

**Exemplo:**
```bash
# Login
curl -X POST https://api.example.com/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Usar session_id
curl -X GET https://api.example.com/v1/customers \
  -H "Authorization: Bearer sess_abc123..."
```

**Fluxo:**
1. Usuário faz login com email/senha
2. Sistema cria sessão e retorna session_id
3. Cliente armazena session_id (localStorage/cookie)
4. Envia session_id em requisições subsequentes
5. Sistema valida sessão e identifica usuário

#### 3. Master Key
**Uso:** Acesso administrativo total

**Exemplo:**
```bash
curl -X GET https://api.example.com/v1/tenants \
  -H "Authorization: Bearer master_key_abc123..."
```

### Sistema de Permissões (RBAC)

#### Roles

**admin:**
- Todas as permissões (implícitas)
- Gerenciar usuários
- Gerenciar permissões
- Acessar logs de auditoria

**editor:**
- Criar, editar, visualizar recursos
- Não pode deletar
- Não pode gerenciar usuários

**viewer:**
- Apenas visualizar recursos
- Não pode criar, editar ou deletar

#### Permissões Granulares

Cada recurso tem 4 permissões:
- `view_<resource>` - Visualizar
- `create_<resource>` - Criar
- `update_<resource>` - Editar
- `delete_<resource>` - Deletar

**Recursos:**
- `customers`, `subscriptions`, `products`, `prices`, `invoices`, `coupons`, `promotion_codes`, `tax_rates`, `disputes`, `charges`, `payouts`, `reports`, `audit_logs`, `users`, `permissions`

#### Uso no Código

```php
// Verifica permissão (lança exceção se não tiver)
PermissionHelper::require('create_customers');

// Verifica permissão (retorna bool)
if (PermissionHelper::hasPermission('delete_customers')) {
    // Deletar
}

// Verifica role
if (PermissionHelper::isAdmin()) {
    // Acesso admin
}
```

---

## 💳 Integração com Stripe

### Configuração

**Variáveis de ambiente:**
```env
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Endpoints Implementados

#### Customers (Clientes)
- `POST /v1/customers` - Criar
- `GET /v1/customers` - Listar
- `GET /v1/customers/:id` - Obter
- `PUT /v1/customers/:id` - Atualizar
- `GET /v1/customers/:id/invoices` - Faturas
- `GET /v1/customers/:id/payment-methods` - Métodos de pagamento

#### Subscriptions (Assinaturas)
- `POST /v1/subscriptions` - Criar
- `GET /v1/subscriptions` - Listar
- `GET /v1/subscriptions/:id` - Obter
- `PUT /v1/subscriptions/:id` - Atualizar
- `DELETE /v1/subscriptions/:id` - Cancelar
- `POST /v1/subscriptions/:id/reactivate` - Reativar
- `GET /v1/subscriptions/:id/history` - Histórico

#### Checkout
- `POST /v1/checkout` - Criar sessão
- `GET /v1/checkout/:id` - Obter sessão

#### Products (Produtos)
- `GET /v1/products` - Listar
- `POST /v1/products` - Criar
- `GET /v1/products/:id` - Obter
- `PUT /v1/products/:id` - Atualizar
- `DELETE /v1/products/:id` - Deletar

#### Prices (Preços)
- `GET /v1/prices` - Listar
- `POST /v1/prices` - Criar
- `GET /v1/prices/:id` - Obter
- `PUT /v1/prices/:id` - Atualizar

#### Invoices (Faturas)
- `GET /v1/invoices/:id` - Obter

#### Coupons (Cupons)
- `POST /v1/coupons` - Criar
- `GET /v1/coupons` - Listar
- `GET /v1/coupons/:id` - Obter
- `PUT /v1/coupons/:id` - Atualizar
- `DELETE /v1/coupons/:id` - Deletar

#### Promotion Codes
- `POST /v1/promotion-codes` - Criar
- `GET /v1/promotion-codes` - Listar
- `GET /v1/promotion-codes/:id` - Obter
- `PUT /v1/promotion-codes/:id` - Atualizar

#### Payment Intents
- `POST /v1/payment-intents` - Criar

#### Refunds (Reembolsos)
- `POST /v1/refunds` - Criar

#### Setup Intents
- `POST /v1/setup-intents` - Criar
- `GET /v1/setup-intents/:id` - Obter
- `POST /v1/setup-intents/:id/confirm` - Confirmar

#### Subscription Items
- `POST /v1/subscriptions/:subscription_id/items` - Criar
- `GET /v1/subscriptions/:subscription_id/items` - Listar
- `GET /v1/subscription-items/:id` - Obter
- `PUT /v1/subscription-items/:id` - Atualizar
- `DELETE /v1/subscription-items/:id` - Deletar

#### Tax Rates
- `POST /v1/tax-rates` - Criar
- `GET /v1/tax-rates` - Listar
- `GET /v1/tax-rates/:id` - Obter
- `PUT /v1/tax-rates/:id` - Atualizar

#### Invoice Items
- `POST /v1/invoice-items` - Criar
- `GET /v1/invoice-items` - Listar
- `GET /v1/invoice-items/:id` - Obter
- `PUT /v1/invoice-items/:id` - Atualizar
- `DELETE /v1/invoice-items/:id` - Deletar

#### Balance Transactions
- `GET /v1/balance-transactions` - Listar
- `GET /v1/balance-transactions/:id` - Obter

#### Disputes
- `GET /v1/disputes` - Listar
- `GET /v1/disputes/:id` - Obter
- `PUT /v1/disputes/:id` - Atualizar

#### Charges
- `GET /v1/charges` - Listar
- `GET /v1/charges/:id` - Obter
- `PUT /v1/charges/:id` - Atualizar

#### Payouts
- `GET /v1/payouts` - Listar
- `GET /v1/payouts/:id` - Obter
- `POST /v1/payouts` - Criar
- `POST /v1/payouts/:id/cancel` - Cancelar

#### Billing Portal
- `POST /v1/billing-portal` - Criar sessão

#### Webhooks
- `POST /v1/webhook` - Receber eventos

#### Reports
- `GET /v1/reports/revenue` - Receita
- `GET /v1/reports/subscriptions` - Assinaturas
- `GET /v1/reports/churn` - Churn
- `GET /v1/reports/customers` - Clientes
- `GET /v1/reports/payments` - Pagamentos
- `GET /v1/reports/mrr` - MRR
- `GET /v1/reports/arr` - ARR

### Webhooks

#### Eventos Tratados

1. **customer.subscription.created** - Nova assinatura criada
2. **customer.subscription.updated** - Assinatura atualizada
3. **customer.subscription.deleted** - Assinatura cancelada
4. **invoice.payment_succeeded** - Pagamento bem-sucedido
5. **invoice.payment_failed** - Pagamento falhou
6. **checkout.session.completed** - Checkout concluído
7. **customer.created** - Cliente criado
8. **customer.updated** - Cliente atualizado
9. **charge.dispute.created** - Disputa criada
10. **charge.refunded** - Reembolso processado

#### Idempotência

- ✅ Eventos processados são marcados no banco (`stripe_events`)
- ✅ Eventos duplicados são ignorados
- ✅ Validação de assinatura do webhook

#### Segurança

- ✅ Validação de assinatura usando `STRIPE_WEBHOOK_SECRET`
- ✅ Verificação de idempotência
- ✅ Logging de todos os eventos

---

## ⚡ Cache e Performance

### Estratégia de Cache

#### Cache de Autenticação
- **TTL:** 5 minutos
- **Chave:** `auth:token:<hash_sha256(token)>`
- **Conteúdo:** Dados do tenant/usuário autenticado

#### Cache de Listagens
- **TTL:** 5 minutos
- **Chave:** `customers:list:<tenant_id>:<page>:<limit>`
- **Invalidação:** Automática ao criar/atualizar/deletar

#### Cache de Recursos Individuais
- **TTL:** 10 minutos
- **Chave:** `customers:get:<tenant_id>:<customer_id>`
- **Invalidação:** Automática ao atualizar/deletar

### Otimizações

#### Database
- ✅ Índices em campos frequentemente consultados
- ✅ Window functions para contagem (1 query em vez de 2)
- ✅ SELECT com campos específicos (reduz transferência)
- ✅ Prepared statements (cache de planos)

#### Cache
- ✅ Redis com fallback automático
- ✅ Timeout de conexão (1 segundo)
- ✅ Invalidação inteligente por padrão

#### Requests
- ✅ Cache de request body (`RequestCache`)
- ✅ Compressão gzip/deflate
- ✅ Headers de cache para assets estáticos

#### Queries
- ✅ Paginação eficiente (LIMIT/OFFSET)
- ✅ Contagem otimizada (window functions)
- ✅ Soft deletes (não remove dados, apenas marca)

---

## 📝 Logging e Auditoria

### Logging (Monolog)

**Arquivos:**
- `app-YYYY-MM-DD.log` - Rotação diária

**Níveis:**
- `DEBUG` - Informações detalhadas
- `INFO` - Informações gerais
- `WARNING` - Avisos
- `ERROR` - Erros

**Formato:**
```json
{
  "timestamp": "2025-01-21T10:30:00+00:00",
  "level": "INFO",
  "message": "Cliente criado",
  "context": {
    "tenant_id": 1,
    "customer_id": 123,
    "action": "create_customer"
  }
}
```

### Auditoria (AuditLog)

**Tabela:** `audit_logs`

**Campos capturados:**
- `method` - Método HTTP
- `url` - URL da requisição
- `query_params` - Parâmetros da query (JSON)
- `payload` - Corpo da requisição (sanitizado, JSON)
- `headers` - Headers (sanitizados, JSON)
- `ip_address` - IP do cliente
- `user_id` - ID do usuário (se autenticado)
- `tenant_id` - ID do tenant
- `status_code` - Status HTTP da resposta
- `response_time_ms` - Tempo de resposta em ms
- `created_at` - Timestamp

**Sanitização:**
- Senhas removidas
- API keys mascaradas
- Headers sensíveis removidos

**Endpoints:**
- `GET /v1/audit-logs` - Listar logs
- `GET /v1/audit-logs/:id` - Obter log específico

---

## 🧪 Testes

### Estrutura

```
tests/
├── Unit/                    # Testes unitários
│   ├── Controllers/         # 8 testes de controllers
│   ├── Models/              # 5 testes de models
│   ├── Services/            # 2 testes de services
│   └── Middleware/          # 2 testes de middleware
├── Manual/                  # Scripts de teste manual
└── Frontend/                # Testes de frontend
```

### Cobertura Atual

- **Controllers:** 8/26 (31%)
- **Models:** 5/11 (45%)
- **Services:** 2/8 (25%)
- **Middleware:** 2/7 (29%)

### Executar Testes

```bash
# Todos os testes
vendor/bin/phpunit

# Teste específico
vendor/bin/phpunit --filter testCreateCustomer

# Com cobertura
vendor/bin/phpunit --coverage-html coverage/
```

### Padrão de Teste (AAA)

```php
public function testCreateCustomer(): void
{
    // Arrange - Preparar dados
    $data = ['email' => 'test@example.com'];
    
    // Act - Executar ação
    $result = $service->createCustomer(1, $data);
    
    // Assert - Verificar resultado
    $this->assertNotNull($result);
    $this->assertEquals('test@example.com', $result['email']);
}
```

---

## 🔄 Migrations e Versionamento

### Phinx

**Configuração:** `phinx.php`

**Comandos:**
```bash
# Criar migration
vendor/bin/phinx create AddUsersTable

# Executar migrations
vendor/bin/phinx migrate

# Rollback
vendor/bin/phinx rollback

# Status
vendor/bin/phinx status
```

### Estrutura de Migration

```php
<?php
use Phinx\Migration\AbstractMigration;

class AddUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('password_hash', 'string', ['limit' => 255])
              ->addTimestamps()
              ->create();
    }
}
```

### Migrations Existentes

1. `create_tenants_table`
2. `create_users_table`
3. `create_customers_table`
4. `create_subscriptions_table`
5. `create_stripe_events_table`
6. `create_audit_logs_table`
7. `create_user_sessions_table`
8. `create_user_permissions_table`
9. `create_subscription_history_table`
10. `create_backup_logs_table`
11. `add_soft_deletes_to_models`

---

## 🌐 APIs e Endpoints

### Base URL

```
https://api.example.com
```

### Autenticação

Todas as requisições (exceto rotas públicas) requerem:

```
Authorization: Bearer <api_key ou session_id>
```

### Formato de Resposta

**Sucesso:**
```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": {...}
}
```

**Erro:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos",
    "details": {
      "email": "Email inválido"
    }
  }
}
```

### Códigos HTTP

- `200` - Sucesso
- `201` - Criado
- `400` - Bad Request (validação)
- `401` - Unauthorized (não autenticado)
- `403` - Forbidden (sem permissão)
- `404` - Not Found
- `429` - Too Many Requests (rate limit)
- `500` - Internal Server Error

### Paginação

**Query params:**
- `page` - Número da página (padrão: 1)
- `limit` - Itens por página (padrão: 20, máx: 100)

**Resposta:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "total_pages": 8
  }
}
```

### Filtros e Ordenação

**Query params:**
- `search` - Busca textual
- `status` - Filtrar por status
- `sort` - Campo para ordenação
- `order` - Direção (asc/desc)

**Exemplo:**
```
GET /v1/customers?search=john&status=active&sort=created_at&order=desc
```

---

## 🎨 Frontend e Views

### Tecnologias

- **HTML5** - Estrutura
- **Bootstrap 5** - Estilos
- **JavaScript (Vanilla)** - Interatividade
- **Fetch API** - Requisições HTTP

### Estrutura de Views

```
Views/
├── layouts/
│   └── base.php          # Layout base com navbar
├── dashboard.php          # Dashboard principal
├── customers.php         # Lista de clientes
├── subscriptions.php     # Lista de assinaturas
├── products.php          # Lista de produtos
└── ... (32 outras views)
```

### Renderização

**Helper:** `App\Utils\View`

**Uso:**
```php
View::render('dashboard', [
    'apiUrl' => 'https://api.example.com',
    'user' => $user,
    'tenant' => $tenant,
    'title' => 'Dashboard'
], true); // true = usar layout base
```

### Autenticação Frontend

**Fluxo:**
1. Usuário acessa `/login`
2. Preenche email/senha
3. JavaScript faz POST para `/v1/auth/login`
4. Recebe `session_id`
5. Armazena em `localStorage`
6. Redireciona para `/dashboard`
7. Todas as requisições incluem `Authorization: Bearer <session_id>`

### Validação Frontend

- ✅ Validação de formatos Stripe (IDs)
- ✅ Validação de email
- ✅ Validação de telefone
- ✅ Validação de URLs
- ✅ Validação de valores monetários
- ✅ Feedback visual de erros

---

## 🚀 Deploy e Produção

### Requisitos

- PHP 8.2+
- MySQL 8.0+
- Redis (opcional, mas recomendado)
- Composer
- Conta Stripe

### Configuração

1. **Clone o repositório:**
```bash
git clone https://github.com/your-repo/saas-stripe.git
cd saas-stripe
```

2. **Instale dependências:**
```bash
composer install --no-dev --optimize-autoloader
```

3. **Configure `.env`:**
```env
APP_ENV=production
DB_HOST=localhost
DB_NAME=saas_payments
DB_USER=root
DB_PASS=password
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
REDIS_URL=redis://127.0.0.1:6379
API_MASTER_KEY=your_master_key_here
```

4. **Execute migrations:**
```bash
vendor/bin/phinx migrate
```

5. **Configure servidor web:**
- Apache: Configure VirtualHost apontando para `public/`
- Nginx: Configure server block apontando para `public/`

6. **Permissões:**
```bash
chmod -R 755 storage/
chmod -R 755 backups/
```

### Variáveis de Ambiente

**Obrigatórias:**
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`
- `STRIPE_WEBHOOK_SECRET`

**Opcionais:**
- `REDIS_URL` - URL do Redis (padrão: `redis://127.0.0.1:6379`)
- `API_MASTER_KEY` - Master key para acesso administrativo
- `CORS_ALLOWED_ORIGINS` - Origens permitidas (separadas por vírgula)
- `LOG_LEVEL` - Nível de log (padrão: `INFO`)

### Backup

**Automático:**
- Configurar cron job:
```bash
0 2 * * * cd /path/to/saas-stripe && php scripts/backup.php create
```

**Manual:**
```bash
php scripts/backup.php create
php scripts/backup.php list
php scripts/backup.php stats
php scripts/backup.php clean
```

### Monitoramento

**Health Check:**
```
GET /health
GET /health/detailed
```

**Logs:**
- Aplicação: `app-YYYY-MM-DD.log`
- Erros: Verificar logs do servidor web

**Métricas:**
- Rate limiting: Headers `X-RateLimit-*`
- Performance: Campo `response_time_ms` em audit logs

### Segurança em Produção

1. ✅ Use HTTPS
2. ✅ Configure CORS adequadamente
3. ✅ Use `API_MASTER_KEY` forte
4. ✅ Configure firewall
5. ✅ Monitore logs de auditoria
6. ✅ Configure backup automático
7. ✅ Use Redis para cache
8. ✅ Configure rate limiting adequado

---

## 📊 Estatísticas do Sistema

### Código

- **Controllers:** 26
- **Models:** 11
- **Services:** 8
- **Middleware:** 7
- **Utils:** 8
- **Views:** 35

### Endpoints

- **Total:** 60+
- **Públicos:** 5
- **Autenticados:** 55+

### Banco de Dados

- **Tabelas:** 11
- **Migrations:** 11
- **Seeds:** 2

### Testes

- **Unitários:** 20
- **Cobertura:** ~30% (em progresso)

---

## 📚 Referências e Documentação Adicional

### Documentação Interna

- [Guia de Integração SaaS](GUIA_INTEGRACAO_SAAS.md)
- [Guia de Integração Frontend](INTEGRACAO_FRONTEND.md)
- [Sistema de Permissões](SISTEMA_PERMISSOES.md)
- [Arquitetura de Autenticação](ARQUITETURA_AUTENTICACAO.md)
- [Rotas da API](ROTAS_API.md)
- [Swagger/OpenAPI](SWAGGER_OPENAPI.md)

### Documentação Externa

- [FlightPHP](https://flightphp.com/)
- [Stripe API](https://stripe.com/docs/api)
- [PHP 8.2](https://www.php.net/releases/8.2/en.php)
- [MySQL 8.0](https://dev.mysql.com/doc/refman/8.0/en/)
- [Redis](https://redis.io/docs/)
- [PHPUnit](https://phpunit.de/)
- [Phinx](https://book.cakephp.org/phinx/)

---

## 🎯 Conclusão

Este sistema fornece uma **base sólida e reutilizável** para gerenciar pagamentos em aplicações SaaS. Com arquitetura MVC bem organizada, padrões de design modernos, segurança robusta e integração completa com Stripe, está pronto para ser integrado em múltiplos projetos.

**Principais pontos fortes:**
- ✅ Arquitetura limpa e extensível
- ✅ Segurança robusta (RBAC, rate limiting, auditoria)
- ✅ Integração completa com Stripe
- ✅ Multi-tenant com isolamento completo
- ✅ Cache inteligente com fallback
- ✅ Logging e auditoria completos
- ✅ Testes unitários (em progresso)
- ✅ Documentação detalhada

**Áreas de melhoria futura:**
- ⚠️ Aumentar cobertura de testes
- ⚠️ Implementar sistema de notificações por email
- ⚠️ Adicionar IP whitelist por tenant
- ⚠️ Completar documentação Swagger/OpenAPI

---

**Última Atualização:** 2025-01-21  
**Versão do Documento:** 1.0.0


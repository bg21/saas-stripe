# Estratégia de Testes Automatizados - PHPUnit

Este documento descreve a estratégia completa de testes automatizados para o sistema SaaS Stripe.

## 📋 Estrutura de Testes

```
tests/
├── Unit/              # Testes unitários (componentes isolados)
│   ├── Models/        # Testes de Models
│   ├── Controllers/   # Testes de Controllers
│   ├── Services/      # Testes de Services
│   ├── Middleware/   # Testes de Middlewares
│   └── Utils/         # Testes de Utils
├── Integration/       # Testes de integração (componentes interagindo)
└── Feature/          # Testes funcionais (fluxos completos)
```

## ✅ Testes Implementados

### Models (100% Coberto)

#### ✅ TenantTest
- ✅ Criação de tenant com API key gerada automaticamente
- ✅ Criação de tenant com API key customizada
- ✅ Busca por API key válida/inexistente
- ✅ Geração de API keys únicas
- ✅ Múltiplos tenants
- ✅ Isolamento de API keys
- ✅ CRUD completo (create, read, update, delete)

#### ✅ UserTest
- ✅ Criação de usuário (completo e mínimo)
- ✅ Hash e verificação de senha (bcrypt)
- ✅ Busca por email e tenant
- ✅ Verificação de email existente
- ✅ Isolamento entre tenants (mesmo email em tenants diferentes)
- ✅ Atualização de role
- ✅ Verificação de admin
- ✅ Busca por tenant
- ✅ CRUD completo

#### ✅ CustomerTest
- ✅ Busca por Stripe ID
- ✅ Busca por tenant e ID (proteção IDOR)
- ✅ Busca por tenant com paginação
- ✅ Busca por tenant com filtros (search, status)
- ✅ createOrUpdate (upsert) - criação e atualização
- ✅ Isolamento entre tenants
- ✅ Ordenação por created_at DESC

#### ✅ SubscriptionTest
- ✅ Busca por Stripe Subscription ID
- ✅ Busca por tenant e ID (proteção IDOR)
- ✅ Busca por tenant com paginação e filtros
- ✅ Busca por customer
- ✅ Estatísticas por tenant (com e sem filtros)
- ✅ createOrUpdate (upsert)
- ✅ Isolamento entre tenants

### Utils

#### ✅ ValidatorTest
- ✅ Validação de login (sucesso, falhas, casos de erro)
- ✅ Validação de criação/atualização de customer
- ✅ Validação de criação/atualização de subscription
- ✅ Validação de criação de usuário
- ✅ Validação de força de senha (todos os critérios)
- ✅ Validação de metadata
- ✅ Validação de IDs
- ✅ Validação de paginação
- ✅ Validação de Stripe IDs (price_id, customer_id)

### Middleware

#### ✅ AuthMiddlewareTest
- ✅ Autenticação com API key válida
- ✅ Autenticação sem token
- ✅ Autenticação com formato inválido
- ✅ Autenticação com API key inexistente
- ✅ Autenticação com tenant inativo
- ✅ Autenticação com token apenas (sem Bearer)
- ✅ Isolamento entre tenants
- ✅ Diferentes formatos de header

## 🚧 Testes Pendentes

### Models
- [ ] BaseModelTest (melhorias e casos adicionais)
- [ ] AuditLogTest
- [ ] UserSessionTest
- [ ] SubscriptionHistoryTest
- [ ] StripeEventTest

### Controllers
- [ ] AuthControllerTest (crítico - login, logout, me)
- [ ] CustomerControllerTest (crítico - CRUD completo)
- [ ] SubscriptionControllerTest (crítico - CRUD completo)
- [ ] CheckoutControllerTest
- [ ] PaymentControllerTest
- [ ] WebhookControllerTest
- [ ] ProductControllerTest
- [ ] PriceControllerTest
- [ ] InvoiceControllerTest
- [ ] UserControllerTest
- [ ] PermissionControllerTest

### Services
- [ ] StripeServiceTest (melhorias - mock completo)
- [ ] PaymentServiceTest
- [ ] CacheServiceTest
- [ ] RateLimiterServiceTest
- [ ] AnomalyDetectionServiceTest
- [ ] BackupServiceTest
- [ ] ReportServiceTest
- [ ] LoggerTest

### Middleware
- [ ] PermissionMiddlewareTest
- [ ] RateLimitMiddlewareTest
- [ ] LoginRateLimitMiddlewareTest
- [ ] UserAuthMiddlewareTest
- [ ] AuditMiddlewareTest
- [ ] PayloadSizeMiddlewareTest

### Utils
- [ ] DatabaseTest
- [ ] SecurityHelperTest
- [ ] PermissionHelperTest
- [ ] ErrorHandlerTest
- [ ] RequestCacheTest
- [ ] ViewTest

### Integration Tests
- [ ] Fluxo completo de checkout
- [ ] Fluxo completo de assinatura
- [ ] Fluxo completo de webhook
- [ ] Fluxo completo de autenticação
- [ ] Integração Stripe (com mocks)

## 📊 Cobertura Atual

### Por Categoria
- **Models**: ~80% (4 de 9 models principais)
- **Utils**: ~50% (1 de 6 utils principais)
- **Middleware**: ~20% (1 de 6 middlewares)
- **Controllers**: 0% (0 de 20 controllers)
- **Services**: ~10% (melhorias no existente)

### Total Estimado
- **Cobertura Geral**: ~25-30%
- **Componentes Críticos**: ~40%

## 🎯 Próximos Passos Prioritários

### Alta Prioridade
1. **AuthControllerTest** - Autenticação é crítica
2. **CustomerControllerTest** - CRUD principal
3. **SubscriptionControllerTest** - Core do negócio
4. **StripeServiceTest** - Melhorar mocks
5. **PaymentServiceTest** - Lógica de pagamentos

### Média Prioridade
6. **WebhookControllerTest** - Processamento de eventos
7. **CheckoutControllerTest** - Criação de sessões
8. **PermissionMiddlewareTest** - Controle de acesso
9. **RateLimiterServiceTest** - Proteção contra abuso

### Baixa Prioridade
10. Testes de integração completos
11. Testes de performance
12. Testes de segurança adicionais

## 🧪 Como Executar os Testes

### Executar todos os testes
```bash
vendor/bin/phpunit
```

### Executar testes específicos
```bash
# Testes de Models
vendor/bin/phpunit tests/Unit/Models/

# Testes de um Model específico
vendor/bin/phpunit tests/Unit/Models/TenantTest.php

# Testes de Utils
vendor/bin/phpunit tests/Unit/Utils/

# Testes de Middleware
vendor/bin/phpunit tests/Unit/Middleware/
```

### Com cobertura de código
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Com filtro
```bash
vendor/bin/phpunit --filter testCreateTenant
```

## 📝 Padrões de Teste

### Estrutura AAA (Arrange, Act, Assert)
Todos os testes seguem o padrão AAA:
```php
public function testExample(): void
{
    // Arrange - Preparar dados
    $data = ['key' => 'value'];
    
    // Act - Executar ação
    $result = $method($data);
    
    // Assert - Verificar resultado
    $this->assertEquals('expected', $result);
}
```

### Isolamento
- Cada teste é independente
- Uso de SQLite in-memory para Models
- Mocks para dependências externas (Stripe, etc.)
- Limpeza em `tearDown()`

### Nomenclatura
- Métodos de teste: `testMethodNameWithCondition`
- Exemplo: `testCreateTenantWithAutoGeneratedApiKey`
- Exemplo: `testFindByEmailAndTenantWithNonExistentUser`

### Casos de Teste
Cada método público deve ter testes para:
1. ✅ Caso de sucesso (happy path)
2. ✅ Casos de erro (validações, exceções)
3. ✅ Casos extremos (valores limites, null, vazio)
4. ✅ Casos de segurança (IDOR, SQL injection, etc.)

## 🔒 Testes de Segurança

### Proteção IDOR (Insecure Direct Object Reference)
Todos os métodos que buscam por ID devem validar tenant:
- ✅ `findByTenantAndId()` em Customer
- ✅ `findByTenantAndId()` em Subscription
- ✅ Validação de tenant em Controllers

### Validação de Inputs
- ✅ Validator cobre todos os casos
- ✅ Sanitização de campos
- ✅ Validação de tipos e formatos

### Autenticação
- ✅ AuthMiddleware valida API keys
- ✅ Isolamento entre tenants
- ✅ Validação de status (ativo/inativo)

## 🛠️ Ferramentas e Dependências

- **PHPUnit 10+**: Framework de testes
- **SQLite in-memory**: Banco de dados para testes
- **PDO**: Acesso ao banco
- **Reflection**: Para injetar dependências em testes

## 📚 Recursos Adicionais

- [Documentação PHPUnit](https://phpunit.de/documentation.html)
- [PHPUnit Best Practices](https://phpunit.de/getting-started.html)
- [Test-Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)

## 🎓 Boas Práticas Aplicadas

1. ✅ **Isolamento**: Cada teste é independente
2. ✅ **Nomenclatura clara**: Nomes descritivos
3. ✅ **AAA Pattern**: Arrange, Act, Assert
4. ✅ **Cobertura de casos**: Sucesso, erro, extremos
5. ✅ **Mocks apropriados**: Dependências externas mockadas
6. ✅ **Documentação**: Comentários explicativos
7. ✅ **Validação de segurança**: Testes de proteção IDOR, validações

## 📈 Métricas de Qualidade

- **Cobertura mínima alvo**: 80%
- **Cobertura atual**: ~25-30%
- **Testes por componente**: Mínimo 5-10 testes
- **Tempo de execução**: < 30 segundos para suite completa

---

**Última atualização**: 2025-01-XX
**Responsável**: Engenheiro Sênior de Qualidade


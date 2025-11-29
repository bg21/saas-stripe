# Testes Automatizados

Este diretório contém os testes automatizados do sistema usando PHPUnit.

## Estrutura

```
tests/
├── Unit/                    # Testes unitários
│   ├── Services/           # Testes de services
│   │   ├── EmailServiceTest.php
│   │   ├── RateLimiterServiceTest.php
│   │   ├── CacheServiceTest.php
│   │   └── PaymentServiceTest.php
│   ├── Models/             # Testes de models
│   │   ├── CustomerTest.php
│   │   ├── SubscriptionTest.php
│   │   └── ...
│   └── Utils/              # Testes de utilitários
│       └── ValidatorTest.php
└── Integration/            # Testes de integração
    ├── Controllers/        # Testes de controllers
    │   ├── AppointmentControllerTest.php
    │   ├── CustomerControllerTest.php
    │   └── ...
    └── TestHelper.php     # Helper para testes de integração
```

## Executando os Testes

### Todos os testes
```bash
composer test
# ou
vendor/bin/phpunit
```

### Apenas testes unitários
```bash
vendor/bin/phpunit --testsuite Unit
```

### Apenas testes de integração
```bash
vendor/bin/phpunit --testsuite Integration
```

### Teste específico
```bash
vendor/bin/phpunit tests/Unit/Services/EmailServiceTest.php
```

### Com cobertura de código
```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Configuração

### Ambiente de Teste

Os testes usam variáveis de ambiente específicas. Crie um arquivo `.env.testing` ou defina:

```env
APP_ENV=testing
DB_HOST=localhost
DB_NAME=test_database
REDIS_URL=redis://127.0.0.1:6379
```

### Banco de Dados de Teste

Os testes de integração podem usar um banco de dados de teste separado. Configure em `config/config.php`:

```php
if (defined('TESTING') && TESTING) {
    // Configurações específicas para testes
}
```

## Estrutura dos Testes

### Testes Unitários

Testam componentes isolados (services, models, utils) sem dependências externas.

**Exemplo:**
```php
class EmailServiceTest extends TestCase
{
    public function testSendEmail(): void
    {
        $service = new EmailService();
        $result = $service->enviar('test@test.com', 'Assunto', 'Corpo');
        $this->assertTrue($result);
    }
}
```

### Testes de Integração

Testam fluxos completos, incluindo controllers, models e banco de dados.

**Exemplo:**
```php
class AppointmentControllerTest extends TestCase
{
    public function testCreateAppointment(): void
    {
        TestHelper::mockAuth(1, 1);
        TestHelper::mockRequest('POST', [], ['professional_id' => 1]);
        
        $controller = new AppointmentController();
        $controller->create();
        
        // Verifica resultado
    }
}
```

## Helpers Disponíveis

### TestHelper (Unit)
- `setMockInput()` - Define input mockado para `php://input`
- `clearMockInput()` - Limpa mock

### TestHelper (Integration)
- `mockAuth()` - Simula autenticação
- `clearAuth()` - Limpa autenticação
- `mockRequest()` - Simula requisição HTTP
- `clearRequest()` - Limpa mock de requisição
- `parseJsonResponse()` - Decodifica resposta JSON

## Boas Práticas

1. **Isolamento**: Cada teste deve ser independente
2. **Setup/Teardown**: Use `setUp()` e `tearDown()` para preparar/limpar
3. **Nomes descritivos**: Use nomes que descrevam o que está sendo testado
4. **AAA Pattern**: Arrange, Act, Assert
5. **Mocks**: Use mocks para dependências externas (Stripe, Redis, etc.)

## Cobertura de Código

Execute com cobertura para verificar quais partes do código estão sendo testadas:

```bash
vendor/bin/phpunit --coverage-html coverage/
```

Abra `coverage/index.html` no navegador para ver o relatório.

## CI/CD

Os testes podem ser integrados em pipelines CI/CD:

```yaml
# Exemplo GitHub Actions
- name: Run tests
  run: composer test
```

## Notas

- Testes que dependem de serviços externos (Stripe, Redis) podem ser marcados como `@skip` se os serviços não estiverem disponíveis
- Testes de integração podem criar dados temporários no banco de dados
- Sempre limpe dados de teste no `tearDown()`

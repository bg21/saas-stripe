# 🏢 Multi-Tenancy e Tenant - Explicação Completa

**Data:** 2025-11-29  
**Autor:** Especialista Sênior Backend PHP

---

## 🎯 O QUE É UM TENANT?

### Conceito Simples

Um **Tenant** (inquilino) é um **cliente SaaS** que usa o sistema. Cada tenant representa uma **empresa ou organização** que tem seus próprios dados isolados.

### Analogia do Mundo Real

Imagine um **prédio de apartamentos**:
- Cada **apartamento** é um **tenant**
- Cada apartamento tem sua própria **chave** (API Key)
- Os moradores de um apartamento **não veem** os dados dos outros apartamentos
- Todos compartilham a mesma **infraestrutura** (elevador, portaria, etc.), mas têm **isolamento completo**

**No sistema:**
- Cada **tenant** é uma empresa/clínica
- Cada tenant tem sua própria **API Key** para autenticação
- Cada tenant tem seus próprios **dados isolados** (clientes, agendamentos, pets, etc.)
- Todos compartilham o mesmo **banco de dados e servidor**, mas com **isolamento de dados**

---

## 🏗️ O QUE É MULTI-TENANCY?

**Multi-tenancy** (multi-inquilino) é uma arquitetura onde **um único sistema** serve **múltiplos clientes** (tenants), cada um com seus dados isolados.

### Tipos de Multi-Tenancy

#### 1. **Shared Database, Shared Schema** (O que usamos)
- ✅ **Um único banco de dados**
- ✅ **Uma única estrutura de tabelas**
- ✅ **Isolamento via `tenant_id` em cada tabela**
- ✅ **Mais eficiente** (compartilha recursos)
- ✅ **Mais fácil de manter**

**Exemplo:**
```sql
-- Tabela appointments (todos os tenants)
id | tenant_id | client_id | appointment_date | ...
1  | 3         | 10        | 2025-11-30       | ...
2  | 5         | 15        | 2025-12-01       | ...
3  | 3         | 11        | 2025-12-02       | ...
```

#### 2. **Shared Database, Separate Schema**
- Um banco, mas cada tenant tem seu próprio schema
- Exemplo: `tenant_3_appointments`, `tenant_5_appointments`

#### 3. **Separate Database**
- Cada tenant tem seu próprio banco de dados
- Exemplo: `tenant_3_db`, `tenant_5_db`

---

## 🔍 COMO FUNCIONA NO SISTEMA ATUAL

### Estrutura da Tabela `tenants`

```sql
CREATE TABLE `tenants` (
  `id` INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,                    -- Nome do tenant (ex: "Clínica Veterinária ABC")
  `api_key` VARCHAR(64) NOT NULL UNIQUE,          -- Chave única para autenticação
  `status` ENUM('active','inactive','suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Exemplo de Dados

```sql
INSERT INTO tenants (id, name, api_key, status) VALUES
(3, 'Clínica Veterinária ABC', '2259e1ec9b69c26140000304940d58e7ee4ccd61c6a3771e3e5719d6e7c41035', 'active'),
(5, 'Pet Shop XYZ', 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6', 'active'),
(7, 'Hospital Animal DEF', 'f6e5d4c3b2a1z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0', 'active');
```

### Isolamento de Dados

**Todas as tabelas têm `tenant_id`:**

```sql
-- Tabela appointments
CREATE TABLE appointments (
  id INT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,  -- ✅ Identifica qual tenant
  client_id INT,
  pet_id INT,
  appointment_date DATE,
  ...
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Tabela clients
CREATE TABLE clients (
  id INT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,  -- ✅ Identifica qual tenant
  name VARCHAR(255),
  email VARCHAR(255),
  ...
);

-- Tabela pets
CREATE TABLE pets (
  id INT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,  -- ✅ Identifica qual tenant
  client_id INT,
  name VARCHAR(255),
  ...
);
```

---

## 🔐 AUTENTICAÇÃO E IDENTIFICAÇÃO DO TENANT

### Como o Sistema Identifica o Tenant?

O sistema identifica o tenant de **3 formas**:

#### 1. **API Key (Autenticação de Tenant)**

```php
// Requisição HTTP
GET /v1/customers
Authorization: Bearer 2259e1ec9b69c26140000304940d58e7ee4ccd61c6a3771e3e5719d6e7c41035

// No middleware de autenticação
$authHeader = $_SERVER['HTTP_AUTHORIZATION']; // "Bearer 2259e1ec..."
$apiKey = extractToken($authHeader);

$tenant = $tenantModel->findByApiKey($apiKey);
// $tenant = ['id' => 3, 'name' => 'Clínica Veterinária ABC', ...]

Flight::set('tenant_id', $tenant['id']); // Armazena no Flight
```

#### 2. **Session ID (Autenticação de Usuário)**

```php
// Usuário faz login
POST /v1/auth/login
{
  "email": "admin@clinica.com",
  "password": "senha123"
}

// Sistema cria sessão vinculada ao tenant do usuário
$session = $userSessionModel->create($userId, $tenantId, $ip, $userAgent);
// Session ID: "abc123def456..."

// Próximas requisições
GET /v1/appointments
Authorization: Bearer abc123def456...

// Sistema valida sessão e obtém tenant_id
$session = $userSessionModel->validate($sessionId);
// $session = ['user_id' => 7, 'tenant_id' => 3, ...]

Flight::set('tenant_id', $session['tenant_id']);
```

#### 3. **Master Key (Acesso Administrativo)**

```php
// Master key para acesso administrativo total (sem tenant específico)
Authorization: Bearer MASTER_KEY_FROM_ENV

Flight::set('tenant_id', null);
Flight::set('is_master', true);
```

---

## 🛡️ ISOLAMENTO DE DADOS - Como Funciona

### Exemplo Prático

**Tenant 3 (Clínica Veterinária ABC):**
```sql
-- Clientes do Tenant 3
SELECT * FROM clients WHERE tenant_id = 3;
-- Retorna apenas clientes da Clínica ABC

-- Agendamentos do Tenant 3
SELECT * FROM appointments WHERE tenant_id = 3;
-- Retorna apenas agendamentos da Clínica ABC
```

**Tenant 5 (Pet Shop XYZ):**
```sql
-- Clientes do Tenant 5
SELECT * FROM clients WHERE tenant_id = 5;
-- Retorna apenas clientes do Pet Shop XYZ

-- Agendamentos do Tenant 5
SELECT * FROM appointments WHERE tenant_id = 5;
-- Retorna apenas agendamentos do Pet Shop XYZ
```

### Proteção Automática nos Models

Todos os models têm métodos que **sempre filtram por tenant_id**:

```php
// App/Models/Appointment.php
class Appointment extends BaseModel
{
    /**
     * Busca agendamento por tenant e ID
     * ✅ PROTEÇÃO: Só retorna se pertencer ao tenant
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $appointment = $this->findById($id);
        
        // ✅ Verifica se pertence ao tenant
        if ($appointment && $appointment['tenant_id'] == $tenantId) {
            return $appointment;
        }
        
        return null; // Não encontrado ou não pertence ao tenant
    }
    
    /**
     * Lista agendamentos do tenant
     * ✅ PROTEÇÃO: Sempre filtra por tenant_id
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }
}
```

### Proteção nos Controllers

```php
// App/Controllers/AppointmentController.php
class AppointmentController
{
    public function get(int $id): void
    {
        $tenantId = Flight::get('tenant_id'); // ✅ Obtém do middleware
        
        if ($tenantId === null) {
            ResponseHelper::sendUnauthorizedError('Não autenticado');
            return;
        }
        
        // ✅ Sempre passa tenant_id
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $id);
        
        if (!$appointment) {
            ResponseHelper::sendNotFoundError('Agendamento não encontrado');
            return;
        }
        
        ResponseHelper::sendSuccess($appointment);
    }
}
```

---

## 📊 EXEMPLO COMPLETO: Fluxo de uma Requisição

### Cenário: Clínica ABC busca seus agendamentos

#### 1. **Requisição HTTP**

```http
GET /v1/appointments HTTP/1.1
Host: api.exemplo.com
Authorization: Bearer 2259e1ec9b69c26140000304940d58e7ee4ccd61c6a3771e3e5719d6e7c41035
```

#### 2. **Middleware de Autenticação** (`public/index.php`)

```php
// Extrai API Key do header
$apiKey = extractTokenFromHeader('Authorization');

// Busca tenant pela API Key
$tenant = $tenantModel->findByApiKey($apiKey);
// $tenant = ['id' => 3, 'name' => 'Clínica Veterinária ABC', ...]

// Armazena no Flight (disponível em toda a aplicação)
Flight::set('tenant_id', 3);
Flight::set('tenant_name', 'Clínica Veterinária ABC');
```

#### 3. **Controller** (`AppointmentController::list()`)

```php
public function list(): void
{
    // ✅ Obtém tenant_id do Flight (definido pelo middleware)
    $tenantId = Flight::get('tenant_id'); // 3
    
    // ✅ Busca apenas agendamentos do tenant 3
    $appointments = $this->appointmentModel->findByTenant($tenantId);
    
    // Retorna apenas agendamentos da Clínica ABC
    ResponseHelper::sendSuccess(['appointments' => $appointments]);
}
```

#### 4. **Model** (`Appointment::findByTenant()`)

```php
public function findByTenant(int $tenantId, array $filters = []): array
{
    // ✅ Query SQL sempre inclui tenant_id
    $conditions = array_merge(['tenant_id' => $tenantId], $filters);
    
    // SQL gerado:
    // SELECT * FROM appointments WHERE tenant_id = 3 AND ...
    
    return $this->findAll($conditions);
}
```

#### 5. **Resposta**

```json
{
  "success": true,
  "data": {
    "appointments": [
      {
        "id": 1,
        "tenant_id": 3,
        "client_id": 10,
        "appointment_date": "2025-11-30",
        ...
      },
      {
        "id": 3,
        "tenant_id": 3,
        "client_id": 11,
        "appointment_date": "2025-12-02",
        ...
      }
    ]
  }
}
```

**✅ Resultado:** Apenas agendamentos do Tenant 3 (Clínica ABC) são retornados.

---

## 🔒 SEGURANÇA E PROTEÇÃO CONTRA IDOR

### O que é IDOR?

**IDOR** (Insecure Direct Object Reference) é uma vulnerabilidade onde um usuário pode acessar dados de outros usuários/tenants apenas mudando o ID na URL.

### Exemplo de Ataque (SEM proteção):

```http
# Tenant 3 (Clínica ABC) tenta acessar agendamento do Tenant 5
GET /v1/appointments/100
Authorization: Bearer API_KEY_TENANT_3

# Se não houver verificação de tenant_id:
# ❌ Retorna agendamento 100 (que pertence ao Tenant 5)
```

### Proteção no Sistema (COM verificação):

```php
// App/Controllers/AppointmentController.php
public function get(int $id): void
{
    $tenantId = Flight::get('tenant_id'); // 3
    
    // ✅ Sempre verifica tenant_id
    $appointment = $this->appointmentModel->findByTenantAndId($tenantId, $id);
    
    if (!$appointment) {
        // ❌ Agendamento não encontrado ou não pertence ao tenant
        ResponseHelper::sendNotFoundError('Agendamento não encontrado');
        return;
    }
    
    // ✅ Só retorna se pertencer ao tenant
    ResponseHelper::sendSuccess($appointment);
}
```

**Resultado:**
- ✅ Tenant 3 tenta acessar agendamento 100 (do Tenant 5)
- ✅ Sistema verifica: `appointment['tenant_id'] == 3?` → **NÃO**
- ✅ Retorna 404 (não encontrado)
- ✅ **Proteção contra IDOR**

---

## 📋 HIERARQUIA DO SISTEMA

```
SISTEMA (Multi-Tenant)
│
├── Tenant 3 (Clínica Veterinária ABC)
│   ├── API Key: 2259e1ec9b69c26140000304940d58e7ee4ccd61c6a3771e3e5719d6e7c41035
│   ├── Usuários:
│   │   ├── admin@clinica.com (admin)
│   │   ├── vet@clinica.com (editor)
│   │   └── recep@clinica.com (viewer)
│   ├── Dados:
│   │   ├── Clientes (10 clientes)
│   │   ├── Pets (25 pets)
│   │   ├── Agendamentos (50 agendamentos)
│   │   └── Profissionais (5 profissionais)
│   └── Configurações:
│       └── Horários, duração de consulta, etc.
│
├── Tenant 5 (Pet Shop XYZ)
│   ├── API Key: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6
│   ├── Usuários:
│   │   ├── admin@petshop.com (admin)
│   │   └── atend@petshop.com (viewer)
│   ├── Dados:
│   │   ├── Clientes (20 clientes)
│   │   ├── Pets (40 pets)
│   │   ├── Agendamentos (80 agendamentos)
│   │   └── Profissionais (3 profissionais)
│   └── Configurações:
│       └── Horários, duração de consulta, etc.
│
└── Tenant 7 (Hospital Animal DEF)
    ├── API Key: f6e5d4c3b2a1z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0
    ├── Usuários:
    │   └── admin@hospital.com (admin)
    ├── Dados:
    │   ├── Clientes (100 clientes)
    │   ├── Pets (200 pets)
    │   ├── Agendamentos (500 agendamentos)
    │   └── Profissionais (15 profissionais)
    └── Configurações:
        └── Horários, duração de consulta, etc.
```

---

## 🔧 COMO CRIAR UM NOVO TENANT

### Via Script CLI

```bash
php scripts/setup_tenant.php "Nova Clínica" admin@novaclinica.com senha123
```

### Via Código

```php
use App\Models\Tenant;
use App\Models\User;

// 1. Criar tenant
$tenantModel = new Tenant();
$tenantId = $tenantModel->create('Nova Clínica');

// 2. Criar usuário admin
$userModel = new User();
$userId = $userModel->create(
    $tenantId,
    'admin@novaclinica.com',
    'senha123',
    'Admin',
    'admin'
);

echo "Tenant criado com ID: {$tenantId}\n";
echo "Usuário admin criado com ID: {$userId}\n";
```

### Via API (se implementado)

```http
POST /v1/tenants
Authorization: Bearer MASTER_KEY
Content-Type: application/json

{
  "name": "Nova Clínica",
  "admin_email": "admin@novaclinica.com",
  "admin_password": "senha123"
}
```

---

## 📊 QUERIES E FILTROS POR TENANT

### Exemplos de Queries

#### 1. **Buscar todos os clientes de um tenant**

```php
// Controller
$tenantId = Flight::get('tenant_id'); // 3
$clients = $this->clientModel->findByTenant($tenantId);

// SQL gerado:
// SELECT * FROM clients WHERE tenant_id = 3 AND deleted_at IS NULL
```

#### 2. **Buscar agendamentos de um tenant com filtros**

```php
// Controller
$tenantId = Flight::get('tenant_id'); // 3
$filters = ['status' => 'confirmed', 'professional_id' => 5];
$appointments = $this->appointmentModel->findByTenant($tenantId, $filters);

// SQL gerado:
// SELECT * FROM appointments 
// WHERE tenant_id = 3 
//   AND status = 'confirmed' 
//   AND professional_id = 5
//   AND deleted_at IS NULL
```

#### 3. **Buscar pet específico de um tenant**

```php
// Controller
$tenantId = Flight::get('tenant_id'); // 3
$petId = 10;
$pet = $this->petModel->findByTenantAndId($tenantId, $petId);

// SQL gerado:
// SELECT * FROM pets WHERE id = 10 AND tenant_id = 3 AND deleted_at IS NULL

// ✅ Proteção: Se pet 10 pertencer ao tenant 5, retorna null
```

---

## 🎯 VANTAGENS DO MULTI-TENANCY

### 1. **Isolamento de Dados**
- ✅ Cada tenant vê apenas seus próprios dados
- ✅ Impossível acessar dados de outros tenants (com proteção adequada)

### 2. **Economia de Recursos**
- ✅ Um único servidor e banco de dados para todos os tenants
- ✅ Compartilhamento de infraestrutura

### 3. **Facilidade de Manutenção**
- ✅ Uma única versão do código para todos os tenants
- ✅ Atualizações aplicadas a todos simultaneamente

### 4. **Escalabilidade**
- ✅ Fácil adicionar novos tenants (apenas criar registro na tabela)
- ✅ Não precisa criar novo servidor/banco para cada cliente

### 5. **Customização por Tenant**
- ✅ Cada tenant pode ter suas próprias configurações
- ✅ Exemplo: horários de funcionamento, duração de consulta, etc.

---

## ⚠️ DESAFIOS E CUIDADOS

### 1. **Sempre Filtrar por tenant_id**

❌ **ERRADO:**
```php
// Perigoso! Pode retornar dados de outros tenants
$appointments = $this->appointmentModel->findById($id);
```

✅ **CORRETO:**
```php
// Sempre passa tenant_id
$appointments = $this->appointmentModel->findByTenantAndId($tenantId, $id);
```

### 2. **Validar tenant_id em Operações de Escrita**

❌ **ERRADO:**
```php
// Perigoso! Pode criar dados para outro tenant
$this->appointmentModel->insert([
    'client_id' => $clientId,
    'appointment_date' => $date
    // ❌ Falta tenant_id
]);
```

✅ **CORRETO:**
```php
// Sempre inclui tenant_id
$this->appointmentModel->insert([
    'tenant_id' => $tenantId, // ✅ Sempre incluir
    'client_id' => $clientId,
    'appointment_date' => $date
]);
```

### 3. **Verificar Relacionamentos**

✅ **CORRETO:**
```php
// Verifica se cliente pertence ao tenant antes de criar agendamento
$client = $this->clientModel->findByTenantAndId($tenantId, $clientId);
if (!$client) {
    throw new Exception('Cliente não encontrado ou não pertence ao tenant');
}
```

### 4. **Índices no Banco de Dados**

✅ **IMPORTANTE:**
```sql
-- Sempre criar índices em tenant_id para performance
CREATE INDEX idx_tenant_id ON appointments(tenant_id);
CREATE INDEX idx_tenant_client ON appointments(tenant_id, client_id);
```

---

## 📝 RESUMO

### O que é Tenant?
- **Cliente SaaS** que usa o sistema
- Representa uma **empresa/organização**
- Tem seus próprios **dados isolados**

### O que é Multi-Tenancy?
- Arquitetura onde **um sistema serve múltiplos clientes**
- Cada cliente tem **isolamento completo de dados**
- Compartilham **infraestrutura**, mas não **dados**

### Como Funciona no Sistema?
1. **Autenticação** identifica o tenant (API Key ou Session ID)
2. **Middleware** armazena `tenant_id` no Flight
3. **Controllers** sempre passam `tenant_id` para models
4. **Models** sempre filtram por `tenant_id`
5. **Resultado:** Cada tenant vê apenas seus dados

### Proteções Implementadas:
- ✅ Todos os models têm métodos `findByTenant()` e `findByTenantAndId()`
- ✅ Controllers sempre verificam `tenant_id`
- ✅ Queries SQL sempre incluem `WHERE tenant_id = ?`
- ✅ Proteção contra IDOR (Insecure Direct Object Reference)

---

**Última Atualização:** 2025-11-29


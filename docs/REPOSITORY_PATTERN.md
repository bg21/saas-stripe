# 📦 Repository Pattern - Explicação Completa

**Data:** 2025-11-29  
**Autor:** Especialista Sênior Backend PHP

---

## 🎯 O QUE É O REPOSITORY PATTERN?

O **Repository Pattern** é um padrão de design que cria uma **camada de abstração** entre a lógica de negócio (Services/Controllers) e o acesso a dados (Models/Database).

### Conceito Simples

Imagine que você tem uma **biblioteca** (Repository) que guarda livros (dados). Em vez de ir diretamente até a estante buscar o livro, você pede para o **bibliotecário** (Repository) buscar para você. O bibliotecário sabe onde está cada livro e como encontrá-lo.

**No código:**
- **Sem Repository:** Controller → Model → Database (acesso direto)
- **Com Repository:** Controller → Service → Repository → Model → Database (camada de abstração)

---

## 🔍 SITUAÇÃO ATUAL NO SISTEMA

### Como está agora (SEM Repository Pattern):

```php
// App/Controllers/AppointmentController.php
class AppointmentController
{
    private Appointment $appointmentModel;
    private Professional $professionalModel;
    private Client $clientModel;
    // ... vários models

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->professionalModel = new Professional();
        $this->clientModel = new Client();
        // ... instancia vários models
    }

    public function list(): void
    {
        $tenantId = Flight::get('tenant_id');
        
        // ❌ Acesso direto ao model
        $appointments = $this->appointmentModel->findByTenant($tenantId, $filters);
        
        // ❌ Lógica de filtragem no controller
        if (isset($filters['start_date'])) {
            $appointments = array_filter($appointments, function($apt) use ($filters) {
                // ... lógica complexa
            });
        }
        
        // ❌ Carrega dados relacionados manualmente
        foreach ($appointments as $apt) {
            $professional = $this->professionalModel->findByTenantAndId($tenantId, $apt['professional_id']);
            $client = $this->clientModel->findByTenantAndId($tenantId, $apt['client_id']);
            // ...
        }
        
        ResponseHelper::sendSuccess($appointments);
    }
}
```

### Problemas dessa abordagem:

1. **Controller faz muitas coisas:**
   - Acessa banco de dados
   - Faz lógica de filtragem
   - Carrega dados relacionados
   - Formata dados

2. **Difícil de testar:**
   - Precisa de banco de dados real para testar
   - Não pode mockar facilmente

3. **Código duplicado:**
   - Mesma lógica de busca em vários controllers
   - Mesma lógica de carregar dados relacionados

4. **Difícil de trocar banco de dados:**
   - Se quiser trocar MySQL por PostgreSQL, precisa mudar todos os controllers

---

## ✅ COMO FICARIA COM REPOSITORY PATTERN

### Estrutura proposta:

```
App/
├── Repositories/
│   ├── Interfaces/
│   │   ├── AppointmentRepositoryInterface.php
│   │   ├── ProfessionalRepositoryInterface.php
│   │   └── ClientRepositoryInterface.php
│   ├── AppointmentRepository.php
│   ├── ProfessionalRepository.php
│   └── ClientRepository.php
```

### 1. Interface (Contrato):

```php
// App/Repositories/Interfaces/AppointmentRepositoryInterface.php
<?php

namespace App\Repositories\Interfaces;

interface AppointmentRepositoryInterface
{
    /**
     * Busca agendamento por ID e tenant
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array;
    
    /**
     * Lista agendamentos do tenant com filtros
     */
    public function findByTenant(int $tenantId, array $filters = []): array;
    
    /**
     * Cria novo agendamento
     */
    public function create(int $tenantId, array $data): int;
    
    /**
     * Atualiza agendamento
     */
    public function update(int $tenantId, int $id, array $data): bool;
    
    /**
     * Deleta agendamento (soft delete)
     */
    public function delete(int $tenantId, int $id): bool;
    
    /**
     * Busca agendamentos com dados relacionados (professional, client, pet)
     */
    public function findByTenantWithRelations(int $tenantId, array $filters = []): array;
    
    /**
     * Verifica conflitos de horário
     */
    public function hasConflict(int $tenantId, int $professionalId, string $date, string $time, ?int $excludeId = null): bool;
}
```

### 2. Implementação (Repository):

```php
// App/Repositories/AppointmentRepository.php
<?php

namespace App\Repositories;

use App\Repositories\Interfaces\AppointmentRepositoryInterface;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Client;
use App\Models\Pet;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    private Appointment $appointmentModel;
    private Professional $professionalModel;
    private Client $clientModel;
    private Pet $petModel;
    
    public function __construct(
        Appointment $appointmentModel,
        Professional $professionalModel,
        Client $clientModel,
        Pet $petModel
    ) {
        $this->appointmentModel = $appointmentModel;
        $this->professionalModel = $professionalModel;
        $this->clientModel = $clientModel;
        $this->petModel = $petModel;
    }
    
    /**
     * Busca agendamento por ID e tenant
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        return $this->appointmentModel->findByTenantAndId($tenantId, $id);
    }
    
    /**
     * Lista agendamentos do tenant com filtros
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $appointments = $this->appointmentModel->findByTenant($tenantId, $filters);
        
        // Aplica filtros de data se fornecidos
        if (isset($filters['start_date']) || isset($filters['end_date'])) {
            $appointments = array_filter($appointments, function($apt) use ($filters) {
                $aptDate = $apt['appointment_date'] ?? '';
                if (isset($filters['start_date']) && $aptDate < $filters['start_date']) {
                    return false;
                }
                if (isset($filters['end_date']) && $aptDate > $filters['end_date']) {
                    return false;
                }
                return true;
            });
            $appointments = array_values($appointments);
        }
        
        return $appointments;
    }
    
    /**
     * Busca agendamentos com dados relacionados
     * ✅ OTIMIZAÇÃO: Carrega todos os dados relacionados de uma vez (elimina N+1)
     */
    public function findByTenantWithRelations(int $tenantId, array $filters = []): array
    {
        $appointments = $this->findByTenant($tenantId, $filters);
        
        if (empty($appointments)) {
            return [];
        }
        
        // Coleta IDs únicos
        $professionalIds = array_unique(array_filter(array_column($appointments, 'professional_id')));
        $clientIds = array_unique(array_filter(array_column($appointments, 'client_id')));
        $petIds = array_unique(array_filter(array_column($appointments, 'pet_id')));
        
        // Carrega todos os profissionais de uma vez
        $professionalsById = [];
        if (!empty($professionalIds)) {
            foreach ($professionalIds as $profId) {
                $professional = $this->professionalModel->findByTenantAndId($tenantId, $profId);
                if ($professional) {
                    $professionalsById[$profId] = $professional;
                }
            }
        }
        
        // Carrega todos os clientes de uma vez
        $clientsById = [];
        if (!empty($clientIds)) {
            foreach ($clientIds as $clientId) {
                $client = $this->clientModel->findByTenantAndId($tenantId, $clientId);
                if ($client) {
                    $clientsById[$clientId] = $client;
                }
            }
        }
        
        // Carrega todos os pets de uma vez
        $petsById = [];
        if (!empty($petIds)) {
            foreach ($petIds as $petId) {
                $pet = $this->petModel->findByTenantAndId($tenantId, $petId);
                if ($pet) {
                    $petsById[$petId] = $pet;
                }
            }
        }
        
        // Enriquece agendamentos com dados relacionados
        $enriched = [];
        foreach ($appointments as $apt) {
            $enrichedApt = $apt;
            
            if (!empty($apt['professional_id']) && isset($professionalsById[$apt['professional_id']])) {
                $enrichedApt['professional'] = $professionalsById[$apt['professional_id']];
            }
            
            if (!empty($apt['client_id']) && isset($clientsById[$apt['client_id']])) {
                $enrichedApt['client'] = $clientsById[$apt['client_id']];
            }
            
            if (!empty($apt['pet_id']) && isset($petsById[$apt['pet_id']])) {
                $enrichedApt['pet'] = $petsById[$apt['pet_id']];
            }
            
            $enriched[] = $enrichedApt;
        }
        
        return $enriched;
    }
    
    /**
     * Verifica conflitos de horário
     */
    public function hasConflict(int $tenantId, int $professionalId, string $date, string $time, ?int $excludeId = null): bool
    {
        $existing = $this->appointmentModel->findByTenant($tenantId, [
            'professional_id' => $professionalId,
            'appointment_date' => $date,
            'status' => ['scheduled', 'confirmed'] // Apenas agendamentos ativos
        ]);
        
        foreach ($existing as $apt) {
            if ($excludeId && $apt['id'] == $excludeId) {
                continue; // Ignora o próprio agendamento sendo atualizado
            }
            
            if ($apt['appointment_time'] === $time) {
                return true; // Conflito encontrado
            }
        }
        
        return false;
    }
    
    /**
     * Cria novo agendamento
     */
    public function create(int $tenantId, array $data): int
    {
        $data['tenant_id'] = $tenantId;
        return $this->appointmentModel->insert($data);
    }
    
    /**
     * Atualiza agendamento
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $appointment = $this->findByTenantAndId($tenantId, $id);
        if (!$appointment) {
            return false;
        }
        
        return $this->appointmentModel->update($id, $data);
    }
    
    /**
     * Deleta agendamento (soft delete)
     */
    public function delete(int $tenantId, int $id): bool
    {
        $appointment = $this->findByTenantAndId($tenantId, $id);
        if (!$appointment) {
            return false;
        }
        
        return $this->appointmentModel->softDelete($id);
    }
}
```

### 3. Controller simplificado (COM Repository):

```php
// App/Controllers/AppointmentController.php
<?php

namespace App\Controllers;

use App\Repositories\Interfaces\AppointmentRepositoryInterface;
use App\Services\EmailService;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\Validator;
use Flight;

class AppointmentController
{
    private AppointmentRepositoryInterface $appointmentRepository;
    private EmailService $emailService;
    
    public function __construct(
        AppointmentRepositoryInterface $appointmentRepository,
        EmailService $emailService
    ) {
        $this->appointmentRepository = $appointmentRepository;
        $this->emailService = $emailService;
    }
    
    /**
     * Lista agendamentos do tenant
     * GET /v1/appointments
     */
    public function list(): void
    {
        try {
            PermissionHelper::require('view_appointments');
            
            $tenantId = Flight::get('tenant_id');
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'list_appointments']);
                return;
            }
            
            $queryParams = Flight::request()->query;
            $filters = [];
            
            // Monta filtros
            if (isset($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            if (isset($queryParams['professional_id'])) {
                $filters['professional_id'] = (int)$queryParams['professional_id'];
            }
            if (isset($queryParams['start_date'])) {
                $filters['start_date'] = $queryParams['start_date'];
            }
            if (isset($queryParams['end_date'])) {
                $filters['end_date'] = $queryParams['end_date'];
            }
            
            // ✅ Usa repository - toda a lógica complexa está lá
            $appointments = $this->appointmentRepository->findByTenantWithRelations($tenantId, $filters);
            
            ResponseHelper::sendSuccess(['appointments' => $appointments]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao listar agendamentos', 'APPOINTMENT_LIST_ERROR');
        }
    }
    
    /**
     * Cria novo agendamento
     * POST /v1/appointments
     */
    public function create(): void
    {
        try {
            PermissionHelper::require('create_appointments');
            
            $tenantId = Flight::get('tenant_id');
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'create_appointment']);
                return;
            }
            
            $data = json_decode(Flight::request()->getBody(), true);
            
            // Validação
            $errors = Validator::validateAppointment($data);
            if (!empty($errors)) {
                ResponseHelper::sendValidationError('Dados inválidos', $errors, ['action' => 'create_appointment']);
                return;
            }
            
            // ✅ Verifica conflito usando repository
            if ($this->appointmentRepository->hasConflict(
                $tenantId,
                $data['professional_id'],
                $data['appointment_date'],
                $data['appointment_time']
            )) {
                ResponseHelper::sendValidationError(
                    'Já existe um agendamento neste horário',
                    ['appointment_time' => 'Horário já ocupado'],
                    ['action' => 'create_appointment']
                );
                return;
            }
            
            // ✅ Cria usando repository
            $appointmentId = $this->appointmentRepository->create($tenantId, $data);
            
            // Busca agendamento criado
            $appointment = $this->appointmentRepository->findByTenantAndId($tenantId, $appointmentId);
            
            // Envia email (não crítico, pode falhar)
            try {
                $this->emailService->sendAppointmentCreated($appointment);
            } catch (\Exception $e) {
                Logger::error('Erro ao enviar email de criação de agendamento', ['error' => $e->getMessage()]);
            }
            
            ResponseHelper::sendSuccess($appointment, 201, 'Agendamento criado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao criar agendamento', 'APPOINTMENT_CREATE_ERROR');
        }
    }
}
```

---

## 🎯 BENEFÍCIOS DO REPOSITORY PATTERN

### 1. **Separação de Responsabilidades**

**Antes:**
- Controller fazia: validação, acesso a dados, lógica de negócio, formatação

**Depois:**
- Controller: apenas recebe requisição e retorna resposta
- Repository: acesso a dados e queries complexas
- Service: lógica de negócio (opcional, mas recomendado)

### 2. **Facilita Testes Unitários**

**Antes (difícil de testar):**
```php
// Precisa de banco de dados real
$controller = new AppointmentController();
$controller->list(); // ❌ Precisa de MySQL rodando
```

**Depois (fácil de testar):**
```php
// Pode mockar o repository
$mockRepository = $this->createMock(AppointmentRepositoryInterface::class);
$mockRepository->method('findByTenantWithRelations')
    ->willReturn([/* dados fake */]);

$controller = new AppointmentController($mockRepository, $emailService);
$controller->list(); // ✅ Funciona sem banco de dados
```

### 3. **Reutilização de Código**

**Antes:**
- Mesma lógica de buscar agendamentos com relacionamentos em vários lugares
- Código duplicado

**Depois:**
- Lógica centralizada no repository
- Qualquer controller ou service pode usar

### 4. **Facilita Troca de Banco de Dados**

**Antes:**
- Se quiser trocar MySQL por PostgreSQL, precisa mudar todos os controllers

**Depois:**
- Cria nova implementação do repository (ex: `PostgreSQLAppointmentRepository`)
- Controllers não precisam mudar (usam a interface)

### 5. **Melhor Organização**

**Antes:**
- Lógica espalhada em controllers, models, services

**Depois:**
- Estrutura clara: Controller → Service → Repository → Model → Database

---

## 📊 COMPARAÇÃO: ANTES vs DEPOIS

### Exemplo: Listar Agendamentos

#### ❌ ANTES (sem Repository):

```php
public function list(): void
{
    $tenantId = Flight::get('tenant_id');
    
    // 1. Busca agendamentos
    $appointments = $this->appointmentModel->findByTenant($tenantId, $filters);
    
    // 2. Aplica filtros de data (lógica no controller)
    if (isset($filters['start_date'])) {
        $appointments = array_filter($appointments, function($apt) use ($filters) {
            // ... lógica complexa
        });
    }
    
    // 3. Carrega dados relacionados (N+1 problem)
    foreach ($appointments as $apt) {
        $apt['professional'] = $this->professionalModel->findByTenantAndId($tenantId, $apt['professional_id']);
        $apt['client'] = $this->clientModel->findByTenantAndId($tenantId, $apt['client_id']);
        $apt['pet'] = $this->petModel->findByTenantAndId($tenantId, $apt['pet_id']);
    }
    
    ResponseHelper::sendSuccess($appointments);
}
```

**Problemas:**
- ❌ Controller faz muitas coisas
- ❌ Lógica de filtragem no controller
- ❌ N+1 queries (1 query para agendamentos + N queries para cada relacionamento)
- ❌ Difícil de testar
- ❌ Código duplicado se usado em outro lugar

#### ✅ DEPOIS (com Repository):

```php
public function list(): void
{
    $tenantId = Flight::get('tenant_id');
    $filters = $this->buildFiltersFromQuery();
    
    // ✅ Uma linha - toda a lógica está no repository
    $appointments = $this->appointmentRepository->findByTenantWithRelations($tenantId, $filters);
    
    ResponseHelper::sendSuccess($appointments);
}
```

**Benefícios:**
- ✅ Controller simples e focado
- ✅ Lógica de acesso a dados no repository
- ✅ Otimizado (carrega todos os relacionamentos de uma vez)
- ✅ Fácil de testar (pode mockar repository)
- ✅ Reutilizável em qualquer lugar

---

## 🔧 IMPLEMENTAÇÃO NO SISTEMA ATUAL

### Passo a passo para implementar:

1. **Criar estrutura de diretórios:**
```
App/Repositories/
├── Interfaces/
│   ├── AppointmentRepositoryInterface.php
│   ├── ProfessionalRepositoryInterface.php
│   └── ClientRepositoryInterface.php
├── AppointmentRepository.php
├── ProfessionalRepository.php
└── ClientRepository.php
```

2. **Criar interfaces** (contratos)

3. **Implementar repositories** (usando models existentes)

4. **Atualizar controllers** para usar repositories

5. **Criar Service Layer** (opcional, mas recomendado):
```
App/Services/
├── AppointmentService.php  // Lógica de negócio
├── ProfessionalService.php
└── ClientService.php
```

6. **Injeção de Dependências:**
```php
// public/index.php
$appointmentRepository = new AppointmentRepository(
    new Appointment(),
    new Professional(),
    new Client(),
    new Pet()
);

$appointmentService = new AppointmentService(
    $appointmentRepository,
    new EmailService()
);

$appointmentController = new AppointmentController(
    $appointmentRepository,
    $appointmentService
);
```

---

## ⚠️ QUANDO USAR REPOSITORY PATTERN?

### ✅ Use quando:
- Sistema grande com muitas queries complexas
- Precisa testar código sem banco de dados
- Pode trocar de banco de dados no futuro
- Múltiplos desenvolvedores trabalhando no projeto
- Quer melhor organização e separação de responsabilidades

### ❌ Não use quando:
- Sistema muito simples (pode ser over-engineering)
- Projeto pequeno com poucas queries
- Time muito pequeno (pode adicionar complexidade desnecessária)

---

## 📝 RESUMO

**Repository Pattern** é uma camada de abstração que:
- ✅ Separa acesso a dados da lógica de negócio
- ✅ Facilita testes unitários
- ✅ Melhora organização do código
- ✅ Permite reutilização
- ✅ Facilita manutenção

**No sistema atual:**
- Models fazem acesso direto ao banco (ActiveRecord)
- Controllers acessam models diretamente
- **Com Repository:** Controllers → Repositories → Models → Database

**Recomendação:**
- Implementar gradualmente, começando pelos controllers mais complexos
- Começar com `AppointmentController` (tem muita lógica)
- Depois `ProfessionalController`, `ClientController`, etc.

---

**Última Atualização:** 2025-11-29


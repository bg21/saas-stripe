# 🏥 Plano de Implementação: Sistema de Clínica Veterinária

**Data:** 2025-01-21  
**Versão do Sistema Base:** 1.0.5  
**Status:** 📋 Planejamento

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [O que Já Existe no Sistema](#o-que-já-existe-no-sistema)
3. [O que Precisa Ser Criado](#o-que-precisa-ser-criado)
4. [Estrutura de Banco de Dados](#estrutura-de-banco-de-dados)
5. [Models a Criar](#models-a-criar)
6. [Controllers a Criar](#controllers-a-criar)
7. [Services a Criar](#services-a-criar)
8. [Views a Criar](#views-a-criar)
9. [Endpoints da API](#endpoints-da-api)
10. [Permissões e Roles](#permissões-e-roles)
11. [Integração com Stripe](#integração-com-stripe)
12. [Ordem de Implementação](#ordem-de-implementação)
13. [Checklist de Implementação](#checklist-de-implementação)

---

## 🎯 Visão Geral

### Conceito

A **clínica veterinária** será um **tenant** no sistema SaaS. Cada clínica terá:

- ✅ Seu próprio ambiente isolado
- ✅ Seus próprios profissionais (veterinários, atendentes, administradores)
- ✅ Seus próprios clientes e pets
- ✅ Suas próprias agendas e agendamentos
- ✅ Suas próprias configurações
- ✅ Seu próprio plano de assinatura (via Stripe)

### Arquitetura

```
TENANT (Clínica Veterinária)
  │
  ├─ Configurações da Clínica
  │  ├─ Horários de funcionamento
  │  ├─ Especialidades oferecidas
  │  ├─ Tempo padrão de consulta
  │  └─ Regras internas
  │
  ├─ Profissionais
  │  ├─ Veterinários (com CRMV)
  │  ├─ Clínicos gerais
  │  ├─ Especialistas
  │  ├─ Atendentes/Recepcionistas
  │  └─ Administradores
  │
  ├─ Clientes
  │  └─ Pets (múltiplos por cliente)
  │
  ├─ Agendas
  │  ├─ Horários por profissional
  │  ├─ Bloqueios e exceções
  │  └─ Configurações de disponibilidade
  │
  ├─ Agendamentos (Consultas)
  │  ├─ Médico responsável
  │  ├─ Cliente e Pet
  │  ├─ Data e hora
  │  ├─ Status (marcado, confirmado, concluído, cancelado, falta)
  │  └─ Observações
  │
  └─ Relatórios e Indicadores
     ├─ Consultas por dia/mês
     ├─ Taxa de cancelamento
     ├─ Pets atendidos
     └─ Consultas por médico
```

---

## ✅ O que Já Existe no Sistema

### 1. Infraestrutura Base (100% Pronto)

- ✅ **Multi-tenant**: Sistema já suporta tenants isolados
- ✅ **Autenticação**: API Key (tenant) + Session ID (usuários)
- ✅ **Sistema de Usuários**: Login, logout, sessões
- ✅ **Permissões (RBAC)**: Admin, Editor, Viewer com permissões granulares
- ✅ **Stripe Integration**: 60+ endpoints prontos
- ✅ **Planos de Assinatura**: Sistema completo de assinaturas
- ✅ **Logs de Auditoria**: Rastreamento completo
- ✅ **Rate Limiting**: Proteção contra abuso
- ✅ **Cache**: Redis com fallback
- ✅ **Validação**: Sistema robusto de validação
- ✅ **Error Handling**: Tratamento centralizado de erros

### 2. Models Existentes (Podem Ser Aproveitados)

- ✅ `Tenant` - Já existe (representa a clínica)
- ✅ `User` - Já existe (representa profissionais)
- ✅ `UserPermission` - Já existe (permissões dos profissionais)
- ✅ `UserSession` - Já existe (sessões de login)
- ✅ `Subscription` - Já existe (plano da clínica)
- ✅ `AuditLog` - Já existe (logs de auditoria)

### 3. Controllers Existentes (Podem Ser Aproveitados)

- ✅ `AuthController` - Login/logout
- ✅ `UserController` - CRUD de usuários
- ✅ `PermissionController` - Gerenciamento de permissões
- ✅ `SubscriptionController` - Gerenciamento de planos
- ✅ `ReportController` - Relatórios (pode ser estendido)

---

## 🆕 O que Precisa Ser Criado

### 1. Novas Tabelas no Banco de Dados

#### 1.1. `clinic_configurations` (Configurações da Clínica)
- Horários de funcionamento
- Especialidades oferecidas
- Tempo padrão de consulta
- Regras internas

#### 1.2. `professionals` (Profissionais)
- Dados do profissional (nome, CRMV, especialidades)
- Relacionamento com User
- Status (ativo/inativo)

#### 1.3. `clients` (Clientes - Donos de Pets)
- Nome, telefone, email
- Endereço (opcional)
- Observações

#### 1.4. `pets` (Pets)
- Nome, espécie, raça
- Data de nascimento / idade
- Observações
- Histórico médico (JSON)

#### 1.5. `professional_schedules` (Agendas dos Profissionais)
- Horários padrões por dia da semana
- Horários específicos (exceções)
- Bloqueios (férias, etc.)

#### 1.6. `appointments` (Agendamentos/Consultas)
- Profissional responsável
- Cliente e Pet
- Data e hora
- Status
- Observações
- Histórico de mudanças

#### 1.7. `specialties` (Especialidades)
- Nome da especialidade
- Descrição
- Ativa/Inativa

#### 1.8. `appointment_history` (Histórico de Agendamentos)
- Mudanças de status
- Alterações de data/hora
- Quem fez a alteração
- Timestamp

### 2. Novos Models

- `ClinicConfiguration` - Configurações da clínica
- `Professional` - Profissionais (veterinários, atendentes)
- `Client` - Clientes (donos de pets)
- `Pet` - Pets
- `ProfessionalSchedule` - Agendas dos profissionais
- `Appointment` - Agendamentos/consultas
- `Specialty` - Especialidades
- `AppointmentHistory` - Histórico de agendamentos

### 3. Novos Controllers

- `ClinicConfigurationController` - CRUD de configurações
- `ProfessionalController` - CRUD de profissionais
- `ClientController` - CRUD de clientes
- `PetController` - CRUD de pets
- `ScheduleController` - Gerenciamento de agendas
- `AppointmentController` - CRUD de agendamentos
- `SpecialtyController` - CRUD de especialidades

### 4. Novos Services

- `AppointmentService` - Lógica de negócio de agendamentos
  - Validação de conflitos de horário
  - Disponibilidade de profissionais
  - Regras de agendamento
- `ScheduleService` - Lógica de agendas
  - Cálculo de horários disponíveis
  - Bloqueios e exceções
  - Horários padrões

### 5. Novas Views

- `professionals.php` - Lista de profissionais
- `professional-details.php` - Detalhes do profissional
- `clients.php` - Lista de clientes
- `client-details.php` - Detalhes do cliente
- `pets.php` - Lista de pets
- `pet-details.php` - Detalhes do pet
- `appointments.php` - Lista de agendamentos
- `appointment-details.php` - Detalhes do agendamento
- `schedule.php` - Visualização de agenda
- `schedule-config.php` - Configuração de agenda
- `clinic-settings.php` - Configurações da clínica
- `specialties.php` - Lista de especialidades

### 6. Novas Permissões

- `view_professionals`, `create_professionals`, `update_professionals`, `delete_professionals`
- `view_clients`, `create_clients`, `update_clients`, `delete_clients`
- `view_pets`, `create_pets`, `update_pets`, `delete_pets`
- `view_appointments`, `create_appointments`, `update_appointments`, `delete_appointments`, `confirm_appointments`, `cancel_appointments`
- `view_schedules`, `manage_schedules`
- `view_specialties`, `create_specialties`, `update_specialties`, `delete_specialties`
- `manage_clinic_settings`

---

## 🗄️ Estrutura de Banco de Dados

### 1. Tabela: `clinic_configurations`

```sql
CREATE TABLE `clinic_configurations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `opening_time_monday` time DEFAULT '08:00:00',
  `closing_time_monday` time DEFAULT '18:00:00',
  `opening_time_tuesday` time DEFAULT '08:00:00',
  `closing_time_tuesday` time DEFAULT '18:00:00',
  `opening_time_wednesday` time DEFAULT '08:00:00',
  `closing_time_wednesday` time DEFAULT '18:00:00',
  `opening_time_thursday` time DEFAULT '08:00:00',
  `closing_time_thursday` time DEFAULT '18:00:00',
  `opening_time_friday` time DEFAULT '08:00:00',
  `closing_time_friday` time DEFAULT '18:00:00',
  `opening_time_saturday` time DEFAULT '08:00:00',
  `closing_time_saturday` time DEFAULT '12:00:00',
  `opening_time_sunday` time NULL DEFAULT NULL,
  `closing_time_sunday` time NULL DEFAULT NULL,
  `default_appointment_duration` int(11) NOT NULL DEFAULT 30 COMMENT 'Duração padrão em minutos',
  `time_slot_interval` int(11) NOT NULL DEFAULT 15 COMMENT 'Intervalo entre horários em minutos',
  `allow_online_booking` tinyint(1) NOT NULL DEFAULT 1,
  `require_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `cancellation_hours` int(11) NOT NULL DEFAULT 24 COMMENT 'Horas mínimas para cancelamento',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_clinic_config_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Tabela: `professionals`

```sql
CREATE TABLE `professionals` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL COMMENT 'Relacionamento com users',
  `crmv` varchar(20) DEFAULT NULL COMMENT 'CRMV do veterinário',
  `specialties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialties`)) COMMENT 'Array de IDs de especialidades',
  `default_consultation_duration` int(11) NOT NULL DEFAULT 30 COMMENT 'Duração padrão em minutos',
  `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_professional_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_professional_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Tabela: `clients`

```sql
CREATE TABLE `clients` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone_alt` varchar(20) DEFAULT NULL COMMENT 'Telefone alternativo',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  CONSTRAINT `fk_client_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. Tabela: `pets`

```sql
CREATE TABLE `pets` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `client_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `species` varchar(50) NOT NULL COMMENT 'cachorro, gato, ave, etc.',
  `breed` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','unknown') DEFAULT 'unknown',
  `birth_date` date DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Peso em kg',
  `color` varchar(50) DEFAULT NULL,
  `microchip` varchar(50) DEFAULT NULL,
  `medical_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`medical_history`)) COMMENT 'Histórico médico em JSON',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_species` (`species`),
  CONSTRAINT `fk_pet_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pet_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. Tabela: `specialties`

```sql
CREATE TABLE `specialties` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_specialty_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. Tabela: `professional_schedules`

```sql
CREATE TABLE `professional_schedules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `professional_id` int(11) UNSIGNED NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Domingo, 1=Segunda, ..., 6=Sábado',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_professional` (`tenant_id`, `professional_id`),
  KEY `idx_day_of_week` (`day_of_week`),
  CONSTRAINT `fk_schedule_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_professional` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 7. Tabela: `schedule_blocks` (Bloqueios de Agenda)

```sql
CREATE TABLE `schedule_blocks` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `professional_id` int(11) UNSIGNED NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'Motivo do bloqueio (férias, licença, etc.)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_professional` (`tenant_id`, `professional_id`),
  KEY `idx_datetime_range` (`start_datetime`, `end_datetime`),
  CONSTRAINT `fk_block_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_professional` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 8. Tabela: `appointments`

```sql
CREATE TABLE `appointments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `professional_id` int(11) UNSIGNED NOT NULL,
  `client_id` int(11) UNSIGNED NOT NULL,
  `pet_id` int(11) UNSIGNED NOT NULL,
  `specialty_id` int(11) UNSIGNED DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `status` enum('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) UNSIGNED DEFAULT NULL COMMENT 'ID do usuário que cancelou',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_professional_id` (`professional_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_pet_id` (`pet_id`),
  KEY `idx_appointment_datetime` (`appointment_date`, `appointment_time`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_appointment_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointment_professional` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointment_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointment_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointment_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 9. Tabela: `appointment_history`

```sql
CREATE TABLE `appointment_history` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `appointment_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'created, updated, status_changed, cancelled, etc.',
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_appointment_id` (`appointment_id`),
  KEY `idx_event_type` (`event_type`),
  CONSTRAINT `fk_history_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📦 Models a Criar

### 1. `App\Models\ClinicConfiguration`

```php
<?php

namespace App\Models;

class ClinicConfiguration extends BaseModel
{
    protected string $table = 'clinic_configurations';
    
    /**
     * Busca configuração por tenant
     */
    public function findByTenant(int $tenantId): ?array
    {
        return $this->findBy('tenant_id', $tenantId);
    }
    
    /**
     * Cria ou atualiza configuração
     */
    public function createOrUpdate(int $tenantId, array $data): int
    {
        $existing = $this->findByTenant($tenantId);
        
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        
        $data['tenant_id'] = $tenantId;
        return $this->insert($data);
    }
}
```

### 2. `App\Models\Professional`

```php
<?php

namespace App\Models;

class Professional extends BaseModel
{
    protected string $table = 'professionals';
    protected bool $usesSoftDeletes = true;
    
    /**
     * Busca profissional por tenant e ID
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        return $this->findBy('tenant_id', $tenantId) 
            && $this->findById($id)['tenant_id'] == $tenantId 
            ? $this->findById($id) 
            : null;
    }
    
    /**
     * Lista profissionais do tenant
     */
    public function findByTenant(int $tenantId, array $filters = []): array
    {
        $conditions = array_merge(['tenant_id' => $tenantId], $filters);
        return $this->findAll($conditions);
    }
    
    /**
     * Busca profissional por user_id
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }
}
```

### 3. `App\Models\Client`

```php
<?php

namespace App\Models;

class Client extends BaseModel
{
    protected string $table = 'clients';
    protected bool $usesSoftDeletes = true;
    
    /**
     * Busca cliente por tenant e ID
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $client = $this->findById($id);
        return $client && $client['tenant_id'] == $tenantId ? $client : null;
    }
    
    /**
     * Busca cliente por email
     */
    public function findByEmail(int $tenantId, string $email): ?array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'email' => $email
        ])[0] ?? null;
    }
}
```

### 4. `App\Models\Pet`

```php
<?php

namespace App\Models;

class Pet extends BaseModel
{
    protected string $table = 'pets';
    protected bool $usesSoftDeletes = true;
    
    /**
     * Busca pet por tenant e ID
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $pet = $this->findById($id);
        return $pet && $pet['tenant_id'] == $tenantId ? $pet : null;
    }
    
    /**
     * Lista pets de um cliente
     */
    public function findByClient(int $clientId): array
    {
        return $this->findAll(['client_id' => $clientId]);
    }
    
    /**
     * Calcula idade do pet
     */
    public function calculateAge(?string $birthDate): ?int
    {
        if (!$birthDate) return null;
        $birth = new \DateTime($birthDate);
        $now = new \DateTime();
        return $now->diff($birth)->y;
    }
}
```

### 5. `App\Models\Specialty`

```php
<?php

namespace App\Models;

class Specialty extends BaseModel
{
    protected string $table = 'specialties';
    protected bool $usesSoftDeletes = true;
    
    /**
     * Lista especialidades ativas do tenant
     */
    public function findActiveByTenant(int $tenantId): array
    {
        return $this->findAll([
            'tenant_id' => $tenantId,
            'status' => 'active'
        ]);
    }
}
```

### 6. `App\Models\ProfessionalSchedule`

```php
<?php

namespace App\Models;

class ProfessionalSchedule extends BaseModel
{
    protected string $table = 'professional_schedules';
    
    /**
     * Busca agenda de um profissional
     */
    public function findByProfessional(int $professionalId): array
    {
        return $this->findAll(['professional_id' => $professionalId]);
    }
    
    /**
     * Busca horários disponíveis para um dia específico
     */
    public function findAvailableByDay(int $professionalId, int $dayOfWeek): ?array
    {
        $schedules = $this->findAll([
            'professional_id' => $professionalId,
            'day_of_week' => $dayOfWeek,
            'is_available' => 1
        ]);
        return $schedules[0] ?? null;
    }
}
```

### 7. `App\Models\ScheduleBlock`

```php
<?php

namespace App\Models;

class ScheduleBlock extends BaseModel
{
    protected string $table = 'schedule_blocks';
    
    /**
     * Verifica se há bloqueio em um horário
     */
    public function hasBlock(int $professionalId, \DateTime $datetime): bool
    {
        $blocks = $this->findAll([
            'professional_id' => $professionalId
        ]);
        
        foreach ($blocks as $block) {
            $start = new \DateTime($block['start_datetime']);
            $end = new \DateTime($block['end_datetime']);
            
            if ($datetime >= $start && $datetime <= $end) {
                return true;
            }
        }
        
        return false;
    }
}
```

### 8. `App\Models\Appointment`

```php
<?php

namespace App\Models;

class Appointment extends BaseModel
{
    protected string $table = 'appointments';
    protected bool $usesSoftDeletes = true;
    
    /**
     * Busca agendamento por tenant e ID
     */
    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $appointment = $this->findById($id);
        return $appointment && $appointment['tenant_id'] == $tenantId ? $appointment : null;
    }
    
    /**
     * Verifica conflito de horário
     */
    public function hasConflict(int $professionalId, string $date, string $time, int $duration, ?int $excludeId = null): bool
    {
        $datetime = new \DateTime("$date $time");
        $endDatetime = (clone $datetime)->modify("+$duration minutes");
        
        $conditions = [
            'professional_id' => $professionalId,
            'appointment_date' => $date,
            'status' => ['scheduled', 'confirmed']
        ];
        
        if ($excludeId) {
            $conditions['id'] = ['!=', $excludeId];
        }
        
        $appointments = $this->findAll($conditions);
        
        foreach ($appointments as $apt) {
            $aptStart = new \DateTime("{$apt['appointment_date']} {$apt['appointment_time']}");
            $aptEnd = (clone $aptStart)->modify("+{$apt['duration_minutes']} minutes");
            
            // Verifica sobreposição
            if (($datetime >= $aptStart && $datetime < $aptEnd) || 
                ($endDatetime > $aptStart && $endDatetime <= $aptEnd) ||
                ($datetime <= $aptStart && $endDatetime >= $aptEnd)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Lista agendamentos por profissional e data
     */
    public function findByProfessionalAndDate(int $professionalId, string $date): array
    {
        return $this->findAll([
            'professional_id' => $professionalId,
            'appointment_date' => $date
        ], ['appointment_time' => 'ASC']);
    }
}
```

### 9. `App\Models\AppointmentHistory`

```php
<?php

namespace App\Models;

class AppointmentHistory extends BaseModel
{
    protected string $table = 'appointment_history';
    
    /**
     * Registra mudança no agendamento
     */
    public function logChange(int $appointmentId, string $eventType, ?array $oldData = null, ?array $newData = null, ?int $userId = null): void
    {
        $appointment = (new Appointment())->findById($appointmentId);
        
        $this->insert([
            'tenant_id' => $appointment['tenant_id'],
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'old_data' => $oldData ? json_encode($oldData) : null,
            'new_data' => $newData ? json_encode($newData) : null
        ]);
    }
    
    /**
     * Busca histórico de um agendamento
     */
    public function findByAppointment(int $appointmentId): array
    {
        return $this->findAll(
            ['appointment_id' => $appointmentId],
            ['created_at' => 'DESC']
        );
    }
}
```

---

## 🎮 Controllers a Criar

### 1. `App\Controllers\ClinicConfigurationController`

**Endpoints:**
- `GET /v1/clinic/configuration` - Obter configurações
- `PUT /v1/clinic/configuration` - Atualizar configurações

### 2. `App\Controllers\ProfessionalController`

**Endpoints:**
- `GET /v1/professionals` - Listar profissionais
- `POST /v1/professionals` - Criar profissional
- `GET /v1/professionals/:id` - Obter profissional
- `PUT /v1/professionals/:id` - Atualizar profissional
- `DELETE /v1/professionals/:id` - Deletar profissional
- `GET /v1/professionals/:id/schedule` - Obter agenda do profissional

### 3. `App\Controllers\ClientController`

**Endpoints:**
- `GET /v1/clients` - Listar clientes
- `POST /v1/clients` - Criar cliente
- `GET /v1/clients/:id` - Obter cliente
- `PUT /v1/clients/:id` - Atualizar cliente
- `DELETE /v1/clients/:id` - Deletar cliente
- `GET /v1/clients/:id/pets` - Listar pets do cliente

### 4. `App\Controllers\PetController`

**Endpoints:**
- `GET /v1/pets` - Listar pets
- `POST /v1/pets` - Criar pet
- `GET /v1/pets/:id` - Obter pet
- `PUT /v1/pets/:id` - Atualizar pet
- `DELETE /v1/pets/:id` - Deletar pet
- `GET /v1/pets/:id/appointments` - Listar agendamentos do pet

### 5. `App\Controllers\AppointmentController`

**Endpoints:**
- `GET /v1/appointments` - Listar agendamentos
- `POST /v1/appointments` - Criar agendamento
- `GET /v1/appointments/:id` - Obter agendamento
- `PUT /v1/appointments/:id` - Atualizar agendamento
- `DELETE /v1/appointments/:id` - Cancelar agendamento
- `POST /v1/appointments/:id/confirm` - Confirmar agendamento
- `POST /v1/appointments/:id/complete` - Marcar como concluído
- `GET /v1/appointments/available-slots` - Obter horários disponíveis
- `GET /v1/appointments/:id/history` - Histórico do agendamento

### 6. `App\Controllers\ScheduleController`

**Endpoints:**
- `GET /v1/professionals/:id/schedule` - Obter agenda
- `PUT /v1/professionals/:id/schedule` - Atualizar agenda
- `POST /v1/professionals/:id/schedule/blocks` - Criar bloqueio
- `DELETE /v1/professionals/:id/schedule/blocks/:block_id` - Remover bloqueio
- `GET /v1/professionals/:id/available-slots` - Obter horários disponíveis

### 7. `App\Controllers\SpecialtyController`

**Endpoints:**
- `GET /v1/specialties` - Listar especialidades
- `POST /v1/specialties` - Criar especialidade
- `GET /v1/specialties/:id` - Obter especialidade
- `PUT /v1/specialties/:id` - Atualizar especialidade
- `DELETE /v1/specialties/:id` - Deletar especialidade

---

## 🔧 Services a Criar

### 1. `App\Services\AppointmentService`

**Responsabilidades:**
- Validação de conflitos de horário
- Verificação de disponibilidade
- Regras de negócio de agendamento
- Cálculo de horários disponíveis

**Métodos principais:**
```php
class AppointmentService
{
    /**
     * Cria um novo agendamento
     */
    public function create(int $tenantId, array $data): array;
    
    /**
     * Verifica disponibilidade de horário
     */
    public function isTimeSlotAvailable(int $professionalId, \DateTime $datetime, int $duration, ?int $excludeAppointmentId = null): bool;
    
    /**
     * Obtém horários disponíveis para um profissional em uma data
     */
    public function getAvailableSlots(int $professionalId, string $date): array;
    
    /**
     * Confirma um agendamento
     */
    public function confirm(int $appointmentId, ?int $userId = null): array;
    
    /**
     * Cancela um agendamento
     */
    public function cancel(int $appointmentId, string $reason, ?int $userId = null): array;
}
```

### 2. `App\Services\ScheduleService`

**Responsabilidades:**
- Cálculo de horários disponíveis
- Gerenciamento de bloqueios
- Validação de horários de funcionamento

**Métodos principais:**
```php
class ScheduleService
{
    /**
     * Calcula horários disponíveis
     */
    public function calculateAvailableSlots(int $professionalId, string $date): array;
    
    /**
     * Verifica se horário está dentro do funcionamento da clínica
     */
    public function isWithinClinicHours(\DateTime $datetime, int $tenantId): bool;
    
    /**
     * Cria bloqueio de agenda
     */
    public function createBlock(int $professionalId, \DateTime $start, \DateTime $end, string $reason): array;
}
```

---

## 🎨 Views a Criar

### 1. Views de Profissionais
- `professionals.php` - Lista de profissionais
- `professional-details.php` - Detalhes do profissional
- `professional-form.php` - Formulário de criação/edição

### 2. Views de Clientes
- `clients.php` - Lista de clientes
- `client-details.php` - Detalhes do cliente
- `client-form.php` - Formulário de criação/edição

### 3. Views de Pets
- `pets.php` - Lista de pets
- `pet-details.php` - Detalhes do pet
- `pet-form.php` - Formulário de criação/edição

### 4. Views de Agendamentos
- `appointments.php` - Lista de agendamentos (calendário/lista)
- `appointment-details.php` - Detalhes do agendamento
- `appointment-form.php` - Formulário de criação/edição
- `appointment-calendar.php` - Visualização em calendário

### 5. Views de Agenda
- `schedule.php` - Visualização de agenda do profissional
- `schedule-config.php` - Configuração de agenda

### 6. Views de Configurações
- `clinic-settings.php` - Configurações da clínica
- `specialties.php` - Lista de especialidades

---

## 🔌 Endpoints da API

### Resumo de Endpoints

**Total estimado:** ~40 endpoints

#### Configurações da Clínica (2)
- `GET /v1/clinic/configuration`
- `PUT /v1/clinic/configuration`

#### Profissionais (6)
- `GET /v1/professionals`
- `POST /v1/professionals`
- `GET /v1/professionals/:id`
- `PUT /v1/professionals/:id`
- `DELETE /v1/professionals/:id`
- `GET /v1/professionals/:id/schedule`

#### Clientes (6)
- `GET /v1/clients`
- `POST /v1/clients`
- `GET /v1/clients/:id`
- `PUT /v1/clients/:id`
- `DELETE /v1/clients/:id`
- `GET /v1/clients/:id/pets`

#### Pets (6)
- `GET /v1/pets`
- `POST /v1/pets`
- `GET /v1/pets/:id`
- `PUT /v1/pets/:id`
- `DELETE /v1/pets/:id`
- `GET /v1/pets/:id/appointments`

#### Agendamentos (9)
- `GET /v1/appointments`
- `POST /v1/appointments`
- `GET /v1/appointments/:id`
- `PUT /v1/appointments/:id`
- `DELETE /v1/appointments/:id`
- `POST /v1/appointments/:id/confirm`
- `POST /v1/appointments/:id/complete`
- `GET /v1/appointments/available-slots`
- `GET /v1/appointments/:id/history`

#### Agenda (5)
- `GET /v1/professionals/:id/schedule`
- `PUT /v1/professionals/:id/schedule`
- `POST /v1/professionals/:id/schedule/blocks`
- `DELETE /v1/professionals/:id/schedule/blocks/:block_id`
- `GET /v1/professionals/:id/available-slots`

#### Especialidades (5)
- `GET /v1/specialties`
- `POST /v1/specialties`
- `GET /v1/specialties/:id`
- `PUT /v1/specialties/:id`
- `DELETE /v1/specialties/:id`

---

## 🔐 Permissões e Roles

### Novas Permissões a Criar

#### Profissionais
- `view_professionals` - Visualizar profissionais
- `create_professionals` - Criar profissionais
- `update_professionals` - Editar profissionais
- `delete_professionals` - Deletar profissionais

#### Clientes
- `view_clients` - Visualizar clientes
- `create_clients` - Criar clientes
- `update_clients` - Editar clientes
- `delete_clients` - Deletar clientes

#### Pets
- `view_pets` - Visualizar pets
- `create_pets` - Criar pets
- `update_pets` - Editar pets
- `delete_pets` - Deletar pets

#### Agendamentos
- `view_appointments` - Visualizar agendamentos
- `create_appointments` - Criar agendamentos
- `update_appointments` - Editar agendamentos
- `delete_appointments` - Deletar agendamentos
- `confirm_appointments` - Confirmar agendamentos
- `cancel_appointments` - Cancelar agendamentos

#### Agenda
- `view_schedules` - Visualizar agendas
- `manage_schedules` - Gerenciar agendas (criar/editar bloqueios)

#### Especialidades
- `view_specialties` - Visualizar especialidades
- `create_specialties` - Criar especialidades
- `update_specialties` - Editar especialidades
- `delete_specialties` - Deletar especialidades

#### Configurações
- `manage_clinic_settings` - Gerenciar configurações da clínica

### Distribuição por Role

**Admin:**
- Todas as permissões (implícitas)

**Editor (Veterinário/Atendente):**
- `view_professionals`, `view_clients`, `view_pets`
- `create_clients`, `update_clients`
- `create_pets`, `update_pets`
- `view_appointments`, `create_appointments`, `update_appointments`, `confirm_appointments`, `cancel_appointments`
- `view_schedules`, `manage_schedules` (própria agenda)
- `view_specialties`

**Viewer (Recepcionista):**
- `view_professionals`, `view_clients`, `view_pets`
- `view_appointments`, `create_appointments`, `confirm_appointments`
- `view_schedules`

---

## 💳 Integração com Stripe

### Planos de Assinatura para Clínicas

O sistema já possui integração completa com Stripe. Cada clínica (tenant) pode ter um plano:

#### Planos Sugeridos

**Básico:**
- Até 3 profissionais
- Até 100 agendamentos/mês
- 1 atendente
- Recursos básicos

**Profissional:**
- Até 10 profissionais
- Agendamentos ilimitados
- Até 5 atendentes
- Relatórios avançados
- Histórico completo

**Premium:**
- Profissionais ilimitados
- Agendamentos ilimitados
- Atendentes ilimitados
- Todos os recursos
- Suporte prioritário

### Limites por Plano

Os limites podem ser verificados no `Subscription` model e aplicados nos controllers:

```php
// Exemplo: verificar limite de profissionais
$subscription = (new Subscription())->findByTenantAndId($tenantId, $subscriptionId);
$planLimits = $this->getPlanLimits($subscription['plan_id']);

$currentProfessionals = (new Professional())->count(['tenant_id' => $tenantId]);
if ($currentProfessionals >= $planLimits['max_professionals']) {
    throw new \Exception('Limite de profissionais atingido');
}
```

---

## 📅 Ordem de Implementação

### Fase 1: Base de Dados e Models (1-2 semanas)

1. ✅ Criar migrations para todas as tabelas
2. ✅ Criar todos os Models
3. ✅ Implementar métodos básicos (CRUD)
4. ✅ Testes unitários dos Models

### Fase 2: Services e Lógica de Negócio (1 semana)

1. ✅ Criar `AppointmentService`
2. ✅ Criar `ScheduleService`
3. ✅ Implementar validações de conflito
4. ✅ Implementar cálculo de horários disponíveis
5. ✅ Testes unitários dos Services

### Fase 3: Controllers e APIs (1-2 semanas)

1. ✅ Criar todos os Controllers
2. ✅ Implementar endpoints
3. ✅ Validação de inputs
4. ✅ Tratamento de erros
5. ✅ Testes de integração

### Fase 4: Permissões e Segurança (3-5 dias)

1. ✅ Adicionar novas permissões
2. ✅ Configurar permissões por role
3. ✅ Aplicar validações de permissão nos controllers
4. ✅ Testes de autorização

### Fase 5: Frontend e Views (1-2 semanas)

1. ✅ Criar todas as views
2. ✅ Implementar formulários
3. ✅ Implementar listagens
4. ✅ Implementar calendário de agendamentos
5. ✅ Validação frontend

### Fase 6: Relatórios e Analytics (1 semana)

1. ✅ Estender `ReportController`
2. ✅ Criar relatórios específicos
3. ✅ Dashboard de métricas
4. ✅ Gráficos e visualizações

### Fase 7: Testes e Refinamento (1 semana)

1. ✅ Testes end-to-end
2. ✅ Correção de bugs
3. ✅ Otimizações de performance
4. ✅ Documentação

---

## ✅ Checklist de Implementação

### Banco de Dados
- [ ] Migration: `clinic_configurations`
- [ ] Migration: `professionals`
- [ ] Migration: `clients`
- [ ] Migration: `pets`
- [ ] Migration: `specialties`
- [ ] Migration: `professional_schedules`
- [ ] Migration: `schedule_blocks`
- [ ] Migration: `appointments`
- [ ] Migration: `appointment_history`
- [ ] Índices criados
- [ ] Foreign keys configuradas

### Models
- [ ] `ClinicConfiguration`
- [ ] `Professional`
- [ ] `Client`
- [ ] `Pet`
- [ ] `Specialty`
- [ ] `ProfessionalSchedule`
- [ ] `ScheduleBlock`
- [ ] `Appointment`
- [ ] `AppointmentHistory`
- [ ] Soft deletes ativados onde necessário
- [ ] Validação de relacionamentos

### Services
- [ ] `AppointmentService`
- [ ] `ScheduleService`
- [ ] Validação de conflitos
- [ ] Cálculo de horários disponíveis
- [ ] Regras de negócio

### Controllers
- [ ] `ClinicConfigurationController`
- [ ] `ProfessionalController`
- [ ] `ClientController`
- [ ] `PetController`
- [ ] `AppointmentController`
- [ ] `ScheduleController`
- [ ] `SpecialtyController`
- [ ] Validação de inputs
- [ ] Tratamento de erros
- [ ] Respostas padronizadas

### Permissões
- [ ] Permissões de profissionais criadas
- [ ] Permissões de clientes criadas
- [ ] Permissões de pets criadas
- [ ] Permissões de agendamentos criadas
- [ ] Permissões de agenda criadas
- [ ] Permissões de especialidades criadas
- [ ] Permissões de configurações criadas
- [ ] Permissões atribuídas por role

### Views
- [ ] Lista de profissionais
- [ ] Detalhes do profissional
- [ ] Lista de clientes
- [ ] Detalhes do cliente
- [ ] Lista de pets
- [ ] Detalhes do pet
- [ ] Lista de agendamentos
- [ ] Calendário de agendamentos
- [ ] Detalhes do agendamento
- [ ] Agenda do profissional
- [ ] Configurações da clínica
- [ ] Lista de especialidades

### Integração Stripe
- [ ] Planos configurados
- [ ] Limites por plano implementados
- [ ] Verificação de limites nos controllers

### Testes
- [ ] Testes unitários dos Models
- [ ] Testes unitários dos Services
- [ ] Testes de integração dos Controllers
- [ ] Testes de permissões
- [ ] Testes end-to-end

### Documentação
- [ ] Documentação da API
- [ ] Exemplos de uso
- [ ] Guia de integração

---

## 📊 Estimativa de Esforço

### Total Estimado: 6-8 semanas

- **Fase 1 (BD + Models):** 1-2 semanas
- **Fase 2 (Services):** 1 semana
- **Fase 3 (Controllers):** 1-2 semanas
- **Fase 4 (Permissões):** 3-5 dias
- **Fase 5 (Frontend):** 1-2 semanas
- **Fase 6 (Relatórios):** 1 semana
- **Fase 7 (Testes):** 1 semana

### Recursos Necessários

- 1 desenvolvedor backend (PHP)
- 1 desenvolvedor frontend (opcional, pode ser o mesmo)
- Acesso ao banco de dados
- Ambiente de testes

---

## 🎯 Próximos Passos

1. **Revisar este documento** e validar requisitos
2. **Criar branch de desenvolvimento**: `feature/veterinary-clinic`
3. **Começar pela Fase 1**: Criar migrations e models
4. **Implementar incrementalmente**: Uma funcionalidade por vez
5. **Testar continuamente**: Após cada implementação
6. **Documentar**: Conforme avança

---

**Última Atualização:** 2025-01-21  
**Versão do Documento:** 1.0.0


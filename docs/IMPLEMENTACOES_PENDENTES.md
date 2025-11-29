# 🚀 Implementações Pendentes - Sistema SaaS Clínica Veterinária

**Data da Análise:** 2025-01-22  
**Versão do Sistema:** 1.0.4  
**Status Geral:** 🟢 **92% Implementado**

---

## 📋 SUMÁRIO EXECUTIVO

Este documento consolida todas as implementações que ainda precisam ser realizadas no sistema. O sistema está funcionalmente completo em 92%, com as pendências focadas principalmente em:

1. Endpoints de agendamento faltantes
2. Sistema de agenda de profissionais
3. Integração completa de notificações por email
4. Melhorias de segurança e operação

---


## 🔴 PRIORIDADE ALTA - Crítico para Produção

### 1. ❌ Endpoints de Agendamento Faltantes

**Status:** ❌ Não implementado  
**Prioridade:** 🔴 ALTA  
**Impacto:** Alto - Frontend já chama, backend não responde  
**Esforço:** Baixo  
**Tempo Estimado:** 1 dia

#### Problema
O frontend (`appointments.php`, `appointment-calendar.php`, `appointment-details.php`) já chama os seguintes endpoints, mas eles não existem no backend:

- `POST /v1/appointments/:id/confirm` - Confirmar agendamento
- `POST /v1/appointments/:id/complete` - Marcar como concluído
- `GET /v1/appointments/available-slots` - Horários disponíveis

#### Implementação Necessária

**Arquivo:** `App/Controllers/AppointmentController.php`

```php
/**
 * Confirma um agendamento
 * POST /v1/appointments/:id/confirm
 */
public function confirm(string $id): void
{
    try {
        PermissionHelper::require('confirm_appointments');
        
        $tenantId = Flight::get('tenant_id');
        
        if ($tenantId === null) {
            ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'confirm_appointment', 'appointment_id' => $id]);
            return;
        }
        
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
        
        if (!$appointment) {
            ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'confirm_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
            return;
        }
        
        if ($appointment['status'] !== 'scheduled') {
            ResponseHelper::sendError('Apenas agendamentos marcados podem ser confirmados', 400, 'INVALID_STATUS', ['action' => 'confirm_appointment', 'appointment_id' => $id]);
            return;
        }
        
        $userId = Flight::get('user_id');
        
        // Atualiza status
        $this->appointmentModel->update((int)$id, [
            'status' => 'confirmed',
            'confirmed_at' => date('Y-m-d H:i:s'),
            'confirmed_by' => $userId
        ]);
        
        // Registra no histórico
        $this->appointmentHistoryModel->insert([
            'tenant_id' => $tenantId,
            'appointment_id' => (int)$id,
            'action' => 'confirmed',
            'changed_by' => $userId,
            'old_value' => 'scheduled',
            'new_value' => 'confirmed',
            'notes' => 'Agendamento confirmado'
        ]);
        
        // Busca agendamento atualizado
        $updated = $this->appointmentModel->findById((int)$id);
        
        ResponseHelper::sendSuccess($updated, 200, 'Agendamento confirmado com sucesso');
    } catch (\Exception $e) {
        ResponseHelper::sendGenericError(
            $e,
            'Erro ao confirmar agendamento',
            'APPOINTMENT_CONFIRM_ERROR',
            ['action' => 'confirm_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
        );
    }
}

/**
 * Marca agendamento como concluído
 * POST /v1/appointments/:id/complete
 */
public function complete(string $id): void
{
    try {
        PermissionHelper::require('update_appointments');
        
        $tenantId = Flight::get('tenant_id');
        
        if ($tenantId === null) {
            ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'complete_appointment', 'appointment_id' => $id]);
            return;
        }
        
        $appointment = $this->appointmentModel->findByTenantAndId($tenantId, (int)$id);
        
        if (!$appointment) {
            ResponseHelper::sendNotFoundError('Agendamento', ['action' => 'complete_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId]);
            return;
        }
        
        if (!in_array($appointment['status'], ['scheduled', 'confirmed'])) {
            ResponseHelper::sendError('Apenas agendamentos marcados ou confirmados podem ser concluídos', 400, 'INVALID_STATUS', ['action' => 'complete_appointment', 'appointment_id' => $id]);
            return;
        }
        
        $userId = Flight::get('user_id');
        
        // Atualiza status
        $this->appointmentModel->update((int)$id, [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => $userId
        ]);
        
        // Registra no histórico
        $this->appointmentHistoryModel->insert([
            'tenant_id' => $tenantId,
            'appointment_id' => (int)$id,
            'action' => 'completed',
            'changed_by' => $userId,
            'old_value' => $appointment['status'],
            'new_value' => 'completed',
            'notes' => 'Agendamento concluído'
        ]);
        
        // Busca agendamento atualizado
        $updated = $this->appointmentModel->findById((int)$id);
        
        ResponseHelper::sendSuccess($updated, 200, 'Agendamento marcado como concluído');
    } catch (\Exception $e) {
        ResponseHelper::sendGenericError(
            $e,
            'Erro ao concluir agendamento',
            'APPOINTMENT_COMPLETE_ERROR',
            ['action' => 'complete_appointment', 'appointment_id' => $id, 'tenant_id' => $tenantId ?? null]
        );
    }
}

/**
 * Obtém horários disponíveis
 * GET /v1/appointments/available-slots?professional_id=1&date=2025-01-22
 */
public function availableSlots(): void
{
    try {
        PermissionHelper::require('view_appointments');
        
        $tenantId = Flight::get('tenant_id');
        
        if ($tenantId === null) {
            ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_available_slots']);
            return;
        }
        
        $queryParams = Flight::request()->query;
        
        if (empty($queryParams['professional_id'])) {
            ResponseHelper::sendError('professional_id é obrigatório', 400, 'MISSING_PARAMETER', ['action' => 'get_available_slots']);
            return;
        }
        
        if (empty($queryParams['date'])) {
            ResponseHelper::sendError('date é obrigatório', 400, 'MISSING_PARAMETER', ['action' => 'get_available_slots']);
            return;
        }
        
        $professionalId = (int)$queryParams['professional_id'];
        $date = $queryParams['date'];
        
        // Valida formato da data
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            ResponseHelper::sendError('Data inválida. Use o formato YYYY-MM-DD', 400, 'INVALID_DATE', ['action' => 'get_available_slots']);
            return;
        }
        
        // Verifica se profissional existe
        $professional = $this->professionalModel->findByTenantAndId($tenantId, $professionalId);
        if (!$professional) {
            ResponseHelper::sendNotFoundError('Profissional', ['action' => 'get_available_slots', 'professional_id' => $professionalId]);
            return;
        }
        
        // TODO: Implementar lógica de cálculo de horários disponíveis
        // Por enquanto, retorna horários padrão (8h às 18h, intervalos de 30min)
        $slots = [];
        $startHour = 8;
        $endHour = 18;
        $intervalMinutes = 30;
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $intervalMinutes) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                
                // Verifica se há conflito com agendamentos existentes
                $hasConflict = $this->appointmentModel->hasConflict(
                    $tenantId,
                    $professionalId,
                    $date,
                    $time,
                    30, // duração padrão
                    null // não exclui nenhum agendamento
                );
                
                if (!$hasConflict) {
                    $slots[] = [
                        'time' => $time,
                        'available' => true
                    ];
                }
            }
        }
        
        ResponseHelper::sendSuccess($slots);
    } catch (\Exception $e) {
        ResponseHelper::sendGenericError(
            $e,
            'Erro ao obter horários disponíveis',
            'AVAILABLE_SLOTS_ERROR',
            ['action' => 'get_available_slots', 'tenant_id' => $tenantId ?? null]
        );
    }
}
```

**Arquivo:** `public/index.php`

Adicionar após as rotas de agendamento existentes (linha ~1529):

```php
// Rotas de Agendamentos (adicionar estas linhas)
$app->route('POST /v1/appointments/@id/confirm', [$appointmentController, 'confirm']);
$app->route('POST /v1/appointments/@id/complete', [$appointmentController, 'complete']);
$app->route('GET /v1/appointments/available-slots', [$appointmentController, 'availableSlots']);
```

**Arquivo:** `App/Models/Appointment.php`

Verificar se o método `hasConflict()` existe e está funcionando corretamente.

---

### 2. ❌ Sistema de Agenda de Profissionais

**Status:** ❌ Não implementado  
**Prioridade:** 🔴 ALTA  
**Impacto:** Alto - Essencial para agendamentos funcionarem corretamente  
**Esforço:** Médio  
**Tempo Estimado:** 2-3 dias

#### Problema
Não existe sistema para gerenciar horários de trabalho dos profissionais e bloqueios de agenda. Isso é essencial para calcular horários disponíveis corretamente.

#### Implementação Necessária

**1. Criar Migration para `professional_schedules`:**

```sql
CREATE TABLE professional_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=domingo, 1=segunda, ..., 6=sábado',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_professional_day (professional_id, day_of_week),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_professional_id (professional_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**2. Criar Migration para `schedule_blocks`:**

```sql
CREATE TABLE schedule_blocks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_professional_id (professional_id),
    INDEX idx_datetime (start_datetime, end_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**3. Criar Model `App/Models/ProfessionalSchedule.php`:**

```php
<?php

namespace App\Models;

class ProfessionalSchedule extends BaseModel
{
    protected string $table = 'professional_schedules';
    
    /**
     * Busca agenda de um profissional
     */
    public function findByProfessional(int $tenantId, int $professionalId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND is_active = 1
                ORDER BY day_of_week ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Salva ou atualiza horário de um dia
     */
    public function saveSchedule(int $tenantId, int $professionalId, int $dayOfWeek, string $startTime, string $endTime, bool $isActive = true): int
    {
        // Verifica se já existe
        $existing = $this->db->prepare(
            "SELECT id FROM {$this->table} 
             WHERE tenant_id = :tenant_id 
             AND professional_id = :professional_id 
             AND day_of_week = :day_of_week"
        );
        $existing->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'day_of_week' => $dayOfWeek
        ]);
        $row = $existing->fetch(\PDO::FETCH_ASSOC);
        
        if ($row) {
            // Atualiza
            $this->update($row['id'], [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_active' => $isActive ? 1 : 0
            ]);
            return $row['id'];
        } else {
            // Insere
            return $this->insert([
                'tenant_id' => $tenantId,
                'professional_id' => $professionalId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_active' => $isActive ? 1 : 0
            ]);
        }
    }
}
```

**4. Criar Model `App/Models/ScheduleBlock.php`:**

```php
<?php

namespace App\Models;

class ScheduleBlock extends BaseModel
{
    protected string $table = 'schedule_blocks';
    
    /**
     * Busca bloqueios de um profissional em um período
     */
    public function findByProfessionalAndPeriod(int $tenantId, int $professionalId, string $startDate, string $endDate): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND start_datetime >= :start_date 
                AND end_datetime <= :end_date
                ORDER BY start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Verifica se há bloqueio em um horário específico
     */
    public function hasBlock(int $tenantId, int $professionalId, string $datetime): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND professional_id = :professional_id 
                AND start_datetime <= :datetime 
                AND end_datetime > :datetime";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'professional_id' => $professionalId,
            'datetime' => $datetime
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['count'] > 0;
    }
}
```

**5. Atualizar `App/Controllers/ProfessionalController.php`:**

Adicionar métodos para gerenciar agenda:

```php
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;

// No construtor, adicionar:
private ProfessionalSchedule $scheduleModel;
private ScheduleBlock $blockModel;

// Adicionar métodos:

/**
 * Atualiza agenda do profissional
 * PUT /v1/professionals/:id/schedule
 */
public function updateSchedule(string $id): void
{
    // Implementar lógica de atualização de agenda
}

/**
 * Cria bloqueio de agenda
 * POST /v1/professionals/:id/schedule/blocks
 */
public function createBlock(string $id): void
{
    // Implementar criação de bloqueio
}

/**
 * Remove bloqueio de agenda
 * DELETE /v1/professionals/:id/schedule/blocks/:block_id
 */
public function deleteBlock(string $id, string $blockId): void
{
    // Implementar remoção de bloqueio
}
```

**6. Atualizar `App/Controllers/AppointmentController.php`:**

Modificar método `availableSlots()` para usar agenda e bloqueios:

```php
// No método availableSlots(), substituir a lógica simples por:
// 1. Buscar agenda do profissional para o dia da semana
// 2. Buscar bloqueios para a data
// 3. Buscar agendamentos existentes
// 4. Calcular horários disponíveis baseado nesses dados
```

**7. Registrar rotas em `public/index.php`:**

```php
// Após as rotas de profissionais existentes:
$app->route('PUT /v1/professionals/@id/schedule', [$professionalController, 'updateSchedule']);
$app->route('POST /v1/professionals/@id/schedule/blocks', [$professionalController, 'createBlock']);
$app->route('DELETE /v1/professionals/@id/schedule/blocks/@block_id', [$professionalController, 'deleteBlock']);
```

---

### 3. ⚠️ Integração Completa de Notificações por Email

**Status:** ⚠️ Parcialmente implementado  
**Prioridade:** 🔴 ALTA  
**Impacto:** Alto - Melhora experiência do usuário  
**Esforço:** Médio  
**Tempo Estimado:** 2-3 dias

#### Situação Atual
- ✅ `EmailService` existe e está implementado
- ✅ Templates de email existem em `App/Templates/Email/`
- ❌ Integração com eventos do sistema não está completa
- ❌ Notificações automáticas não estão ativas

#### Implementação Necessária

**1. Integrar com `AppointmentController`:**

```php
use App\Services\EmailService;

// No construtor:
private EmailService $emailService;

// No método create(), após criar agendamento:
try {
    $this->emailService->sendAppointmentCreated($appointment, $client, $pet, $professional);
} catch (\Exception $e) {
    // Log erro, mas não falha a criação
    Logger::error('Erro ao enviar email de agendamento criado', ['error' => $e->getMessage()]);
}

// No método confirm():
try {
    $this->emailService->sendAppointmentConfirmed($appointment, $client, $pet, $professional);
} catch (\Exception $e) {
    Logger::error('Erro ao enviar email de agendamento confirmado', ['error' => $e->getMessage()]);
}

// No método cancel (via update ou método específico):
try {
    $this->emailService->sendAppointmentCancelled($appointment, $client, $pet, $professional, $reason);
} catch (\Exception $e) {
    Logger::error('Erro ao enviar email de agendamento cancelado', ['error' => $e->getMessage()]);
}
```

**2. Adicionar métodos no `EmailService`:**

```php
/**
 * Envia email quando agendamento é criado
 */
public function sendAppointmentCreated(array $appointment, array $client, array $pet, array $professional): bool
{
    // Implementar
}

/**
 * Envia email quando agendamento é confirmado
 */
public function sendAppointmentConfirmed(array $appointment, array $client, array $pet, array $professional): bool
{
    // Implementar
}

/**
 * Envia email quando agendamento é cancelado
 */
public function sendAppointmentCancelled(array $appointment, array $client, array $pet, array $professional, ?string $reason = null): bool
{
    // Implementar
}

/**
 * Envia lembrete de agendamento (24h antes)
 */
public function sendAppointmentReminder(array $appointment, array $client, array $pet, array $professional): bool
{
    // Implementar
}
```

**3. Integrar com `WebhookController` para eventos Stripe:**

```php
// No método handleEvent(), adicionar:
case 'invoice.payment_failed':
    // Enviar email de pagamento falhado
    break;
case 'customer.subscription.deleted':
    // Enviar email de assinatura cancelada
    break;
case 'checkout.session.completed':
    // Enviar email de nova assinatura
    break;
```

**4. Criar job/cron para lembretes de agendamento:**

Criar script `cron/send-appointment-reminders.php`:

```php
<?php
// Busca agendamentos para amanhã
// Envia email de lembrete
// Executar via cron diariamente
```

---

### 4. ❌ IP Whitelist por Tenant

**Status:** ❌ Não implementado  
**Prioridade:** 🔴 ALTA  
**Impacto:** Médio - Segurança adicional  
**Esforço:** Baixo  
**Tempo Estimado:** 1 dia

#### Implementação Necessária

**1. Criar Migration:**

```sql
CREATE TABLE tenant_ip_whitelist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL COMMENT 'Suporta IPv4 e IPv6',
    description VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tenant_ip (tenant_id, ip_address),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**2. Criar Model `App/Models/TenantIpWhitelist.php`:**

```php
<?php

namespace App\Models;

class TenantIpWhitelist extends BaseModel
{
    protected string $table = 'tenant_ip_whitelist';
    
    /**
     * Verifica se IP está na whitelist do tenant
     */
    public function isAllowed(int $tenantId, string $ipAddress): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE tenant_id = :tenant_id 
                AND ip_address = :ip_address 
                AND active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['count'] > 0;
    }
    
    /**
     * Lista IPs permitidos de um tenant
     */
    public function findByTenant(int $tenantId): array
    {
        return $this->findBy('tenant_id', $tenantId);
    }
}
```

**3. Criar Middleware `App/Middleware/IpWhitelistMiddleware.php`:**

```php
<?php

namespace App\Middleware;

use App\Models\TenantIpWhitelist;
use App\Utils\ResponseHelper;
use Flight;

class IpWhitelistMiddleware
{
    private TenantIpWhitelist $ipWhitelistModel;
    
    public function __construct()
    {
        $this->ipWhitelistModel = new TenantIpWhitelist();
    }
    
    public function check(): void
    {
        $tenantId = Flight::get('tenant_id');
        
        // Se não tem tenant_id, pula verificação (pode ser rota pública)
        if (!$tenantId) {
            return;
        }
        
        // Obtém IP do cliente
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        
        if (!$ipAddress) {
            ResponseHelper::sendError('IP não identificado', 403, 'IP_NOT_IDENTIFIED');
            Flight::stop();
            return;
        }
        
        // Se IP está em múltiplos (X-Forwarded-For), pega o primeiro
        if (strpos($ipAddress, ',') !== false) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }
        
        // Verifica se IP está na whitelist
        if (!$this->ipWhitelistModel->isAllowed($tenantId, $ipAddress)) {
            ResponseHelper::sendError('IP não autorizado', 403, 'IP_NOT_WHITELISTED', [
                'ip' => $ipAddress,
                'tenant_id' => $tenantId
            ]);
            Flight::stop();
            return;
        }
    }
}
```

**4. Criar Controller `App/Controllers/TenantIpWhitelistController.php`:**

```php
<?php

namespace App\Controllers;

use App\Models\TenantIpWhitelist;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Utils\RequestCache;
use Flight;

class TenantIpWhitelistController
{
    private TenantIpWhitelist $ipWhitelistModel;
    
    public function __construct()
    {
        $this->ipWhitelistModel = new TenantIpWhitelist();
    }
    
    /**
     * Lista IPs permitidos
     * GET /v1/tenants/:id/ip-whitelist
     */
    public function list(string $tenantId): void
    {
        // Implementar
    }
    
    /**
     * Adiciona IP à whitelist
     * POST /v1/tenants/:id/ip-whitelist
     */
    public function create(string $tenantId): void
    {
        // Implementar
    }
    
    /**
     * Remove IP da whitelist
     * DELETE /v1/tenants/:id/ip-whitelist/:ip_id
     */
    public function delete(string $tenantId, string $ipId): void
    {
        // Implementar
    }
}
```

**5. Registrar rotas e middleware em `public/index.php`:**

```php
// Registrar middleware (após AuthMiddleware)
$ipWhitelistMiddleware = new \App\Middleware\IpWhitelistMiddleware();
$app->before('start', function() use ($ipWhitelistMiddleware) {
    $ipWhitelistMiddleware->check();
});

// Registrar rotas
$tenantIpWhitelistController = new \App\Controllers\TenantIpWhitelistController();
$app->route('GET /v1/tenants/@id/ip-whitelist', [$tenantIpWhitelistController, 'list']);
$app->route('POST /v1/tenants/@id/ip-whitelist', [$tenantIpWhitelistController, 'create']);
$app->route('DELETE /v1/tenants/@id/ip-whitelist/@ip_id', [$tenantIpWhitelistController, 'delete']);
```

---

### 5. ❌ Rotação Automática de API Keys

**Status:** ❌ Não implementado  
**Prioridade:** 🔴 ALTA  
**Impacto:** Médio - Segurança em produção  
**Esforço:** Médio  
**Tempo Estimado:** 2 dias

#### Implementação Necessária

**1. Criar Migration:**

```sql
CREATE TABLE api_key_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    old_key VARCHAR(64) NOT NULL,
    new_key VARCHAR(64) NOT NULL,
    rotated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rotated_by INT UNSIGNED NULL COMMENT 'user_id se foi rotacionado por usuário',
    grace_period_ends_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_old_key (old_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**2. Criar Model `App/Models/ApiKeyHistory.php`:**

```php
<?php

namespace App\Models;

class ApiKeyHistory extends BaseModel
{
    protected string $table = 'api_key_history';
    
    /**
     * Verifica se old_key ainda está no período de graça
     */
    public function isInGracePeriod(string $oldKey): bool
    {
        $sql = "SELECT grace_period_ends_at FROM {$this->table} 
                WHERE old_key = :old_key 
                AND grace_period_ends_at > NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['old_key' => $oldKey]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }
}
```

**3. Adicionar método `rotateApiKey()` em `App/Models/Tenant.php`:**

```php
/**
 * Rotaciona API key do tenant
 */
public function rotateApiKey(int $tenantId, ?int $rotatedBy = null, int $gracePeriodDays = 7): array
{
    $tenant = $this->findById($tenantId);
    
    if (!$tenant) {
        throw new \Exception('Tenant não encontrado');
    }
    
    $oldKey = $tenant['api_key'];
    $newKey = bin2hex(random_bytes(32)); // Gera nova chave
    
    // Atualiza tenant com nova chave
    $this->update($tenantId, ['api_key' => $newKey]);
    
    // Registra no histórico
    $apiKeyHistory = new \App\Models\ApiKeyHistory();
    $gracePeriodEnds = date('Y-m-d H:i:s', strtotime("+{$gracePeriodDays} days"));
    
    $apiKeyHistory->insert([
        'tenant_id' => $tenantId,
        'old_key' => $oldKey,
        'new_key' => $newKey,
        'rotated_by' => $rotatedBy,
        'grace_period_ends_at' => $gracePeriodEnds
    ]);
    
    return [
        'old_key' => $oldKey,
        'new_key' => $newKey,
        'grace_period_ends_at' => $gracePeriodEnds
    ];
}
```

**4. Atualizar `App/Middleware/AuthMiddleware.php`:**

Adicionar verificação de período de graça:

```php
// Após validar API key, verificar se está no período de graça
$apiKeyHistory = new \App\Models\ApiKeyHistory();
if ($apiKeyHistory->isInGracePeriod($token)) {
    // Permite acesso, mas loga aviso
    Logger::warning('API key em período de graça', ['token' => substr($token, 0, 10) . '...']);
}
```

**5. Criar Controller `App/Controllers/TenantController.php` (ou adicionar método):**

```php
/**
 * Rotaciona API key do tenant
 * POST /v1/tenants/:id/rotate-key
 */
public function rotateKey(string $id): void
{
    // Apenas master key ou admin do próprio tenant
    // Implementar
}
```

---

## 🟡 PRIORIDADE MÉDIA - Importante para Operação

### 6. ❌ Métricas de Performance

**Status:** ❌ Não implementado  
**Prioridade:** 🟡 MÉDIA  
**Impacto:** Médio - Otimização e monitoramento  
**Esforço:** Médio  
**Tempo Estimado:** 2-3 dias

#### Implementação Necessária

**1. Criar Migration:**

```sql
CREATE TABLE performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    duration_ms INT NOT NULL,
    memory_mb DECIMAL(10,2) NOT NULL,
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint (endpoint),
    INDEX idx_created_at (created_at),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**2. Criar Middleware `App/Middleware/PerformanceMiddleware.php`:**

```php
<?php

namespace App\Middleware;

use App\Models\PerformanceMetric;
use Flight;

class PerformanceMiddleware
{
    private PerformanceMetric $metricModel;
    private float $startTime;
    private int $startMemory;
    
    public function __construct()
    {
        $this->metricModel = new PerformanceMetric();
    }
    
    public function before(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }
    
    public function after(): void
    {
        $duration = (microtime(true) - $this->startTime) * 1000; // ms
        $memory = (memory_get_usage() - $this->startMemory) / 1024 / 1024; // MB
        
        $endpoint = Flight::request()->url;
        $method = Flight::request()->method;
        $tenantId = Flight::get('tenant_id');
        $userId = Flight::get('user_id');
        
        $this->metricModel->insert([
            'endpoint' => $endpoint,
            'method' => $method,
            'duration_ms' => (int)$duration,
            'memory_mb' => round($memory, 2),
            'tenant_id' => $tenantId,
            'user_id' => $userId
        ]);
    }
}
```

**3. Criar Controller para consultar métricas:**

```php
// App/Controllers/PerformanceController.php
// Endpoint: GET /v1/metrics/performance
```

---

### 7. ❌ Tracing de Requisições

**Status:** ❌ Não implementado  
**Prioridade:** 🟡 MÉDIA  
**Impacto:** Médio - Facilita debugging  
**Esforço:** Médio  
**Tempo Estimado:** 1-2 dias

#### Implementação Necessária

**1. Criar Middleware `App/Middleware/TracingMiddleware.php`:**

```php
<?php

namespace App\Middleware;

use App\Services\Logger;
use Flight;

class TracingMiddleware
{
    public function before(): void
    {
        $requestId = bin2hex(random_bytes(16));
        Flight::set('request_id', $requestId);
        
        // Adiciona header na resposta
        header('X-Request-ID: ' . $requestId);
    }
}
```

**2. Atualizar `App/Services/Logger.php`:**

Adicionar `request_id` automaticamente em todos os logs.

**3. Criar Controller para buscar logs por request_id:**

```php
// App/Controllers/TraceController.php
// Endpoint: GET /v1/traces/:request_id
```

---

### 8. ❌ Configurações da Clínica

**Status:** ❌ Não implementado  
**Prioridade:** 🟡 MÉDIA  
**Impacto:** Médio - Personalização  
**Esforço:** Baixo  
**Tempo Estimado:** 1 dia

#### Implementação Necessária

**Opção 1: Usar JSON em `tenants` (mais simples)**

Adicionar coluna `clinic_configuration` JSON na tabela `tenants`.

**Opção 2: Criar tabela dedicada**

```sql
CREATE TABLE clinic_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL UNIQUE,
    working_hours JSON,
    default_appointment_duration INT DEFAULT 30,
    cancellation_rules JSON,
    notification_settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Endpoints:**

```php
GET /v1/clinic/configuration
PUT /v1/clinic/configuration
```

---

## 🟢 PRIORIDADE BAIXA - Opcional/Melhorias Futuras

### 9. ❌ 2FA para Usuários Administrativos

**Status:** ❌ Não implementado  
**Prioridade:** 🟢 BAIXA  
**Impacto:** Alto - Segurança avançada  
**Esforço:** Alto  
**Tempo Estimado:** 3-4 dias

#### Implementação Necessária

- Integração com TOTP (Google Authenticator, Authy)
- Tabela `user_2fa`
- Endpoints para habilitar/desabilitar 2FA
- Integração no login

---

### 10. ❌ Criptografia de Dados Sensíveis

**Status:** ❌ Não implementado  
**Prioridade:** 🟢 BAIXA  
**Impacto:** Alto - Compliance (LGPD, GDPR)  
**Esforço:** Alto  
**Tempo Estimado:** 4-5 dias

#### Implementação Necessária

- Service `EncryptionService`
- Criptografia de API keys, tokens, etc.
- Rotação de chaves de criptografia

---

## 📊 RESUMO DE PRIORIDADES

| # | Implementação | Prioridade | Tempo | Status |
|---|---------------|------------|-------|--------|
| 1 | Endpoints de Agendamento | 🔴 Alta | 1 dia | ❌ |
| 2 | Sistema de Agenda | 🔴 Alta | 2-3 dias | ❌ |
| 3 | Notificações Email | 🔴 Alta | 2-3 dias | ⚠️ |
| 4 | IP Whitelist | 🔴 Alta | 1 dia | ❌ |
| 5 | Rotação API Keys | 🔴 Alta | 2 dias | ❌ |
| 6 | Métricas Performance | 🟡 Média | 2-3 dias | ❌ |
| 7 | Tracing | 🟡 Média | 1-2 dias | ❌ |
| 8 | Config Clínica | 🟡 Média | 1 dia | ❌ |
| 9 | 2FA | 🟢 Baixa | 3-4 dias | ❌ |
| 10 | Criptografia | 🟢 Baixa | 4-5 dias | ❌ |

**Total Estimado (Prioridade Alta):** 8-10 dias  
**Total Estimado (Todas):** 20-30 dias

---

## 🎯 PLANO DE AÇÃO RECOMENDADO

### Semana 1: Crítico (8-10 dias)
1. Endpoints de Agendamento (1 dia)
2. Sistema de Agenda (2-3 dias)
3. Notificações Email (2-3 dias)
4. IP Whitelist (1 dia)
5. Rotação API Keys (2 dias)

### Semana 2: Importante (4-6 dias)
6. Métricas Performance (2-3 dias)
7. Tracing (1-2 dias)
8. Config Clínica (1 dia)

### Futuro: Opcional (7-9 dias)
9. 2FA (3-4 dias)
10. Criptografia (4-5 dias)

---

**Última Atualização:** 2025-01-22


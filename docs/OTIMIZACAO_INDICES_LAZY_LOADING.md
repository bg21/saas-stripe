# Otimização de Índices e Lazy Loading

**Data de Implementação:** 2025-11-29  
**Status:** ✅ Implementado

---

## 📋 Resumo

Esta implementação adiciona índices de performance no banco de dados e otimiza controllers que carregavam dados desnecessários (problema N+1).

---

## 🗄️ Índices Adicionados

### Tabela: `appointments`

1. **`idx_appointments_tenant_prof_date_status`**
   - Campos: `tenant_id`, `professional_id`, `appointment_date`, `status`
   - Uso: Verificação de conflitos (`hasConflict()`)
   - Impacto: Melhora significativamente a performance de verificação de conflitos

2. **`idx_appointments_tenant_client`**
   - Campos: `tenant_id`, `client_id`
   - Uso: Listagem de agendamentos por cliente

3. **`idx_appointments_tenant_pet`**
   - Campos: `tenant_id`, `pet_id`
   - Uso: Listagem de agendamentos por pet

4. **`idx_appointments_date`**
   - Campos: `appointment_date`
   - Uso: Filtros de data

5. **`idx_appointments_tenant_created`**
   - Campos: `tenant_id`, `created_at`
   - Uso: Ordenação por data de criação

### Tabela: `professionals`

1. **`idx_professionals_tenant_user`**
   - Campos: `tenant_id`, `user_id`
   - Uso: Busca de profissional por usuário

2. **`idx_professionals_tenant_status`**
   - Campos: `tenant_id`, `status`
   - Uso: Filtros por status

### Tabela: `pets`

1. **`idx_pets_tenant_client`**
   - Campos: `tenant_id`, `client_id`
   - Uso: Listagem de pets por cliente

### Tabela: `exams`

1. **`idx_exams_tenant_pet`**
   - Campos: `tenant_id`, `pet_id`
   - Uso: Listagem de exames por pet

2. **`idx_exams_tenant_professional`**
   - Campos: `tenant_id`, `professional_id`
   - Uso: Listagem de exames por profissional

3. **`idx_exams_tenant_status`**
   - Campos: `tenant_id`, `status`
   - Uso: Filtros por status

### Tabela: `professional_schedules`

1. **`idx_prof_schedules_tenant_prof_day`**
   - Campos: `tenant_id`, `professional_id`, `day_of_week`
   - Uso: Busca de horário por dia da semana

2. **`idx_prof_schedules_tenant_prof_available`**
   - Campos: `tenant_id`, `professional_id`, `is_available`
   - Uso: Filtros de disponibilidade

### Tabela: `schedule_blocks`

1. **`idx_schedule_blocks_tenant_prof_datetime`**
   - Campos: `tenant_id`, `professional_id`, `start_datetime`, `end_datetime`
   - Uso: Verificação de bloqueios em período

### Outras Tabelas

- **`clients`**: `idx_clients_tenant_created`
- **`users`**: `idx_users_tenant`
- **`specialties`**: `idx_specialties_tenant`

---

## 🚀 Otimizações de Lazy Loading

### Problema N+1 Identificado

Alguns controllers carregavam dados relacionados em loops, causando múltiplas queries ao banco:

**Antes:**
```php
foreach ($appointments as $appointment) {
    $professional = $this->professionalModel->findByTenantAndId($tenantId, $appointment['professional_id']);
    $client = $this->clientModel->findByTenantAndId($tenantId, $appointment['client_id']);
    // ... mais queries
}
```

**Depois:**
```php
// Carrega todos os profissionais de uma vez
$professionalIds = array_unique(array_filter(array_column($appointments, 'professional_id')));
$professionalsById = $this->loadProfessionals($tenantId, $professionalIds);

// Usa dados já carregados
foreach ($appointments as $appointment) {
    $enriched['professional'] = $professionalsById[$appointment['professional_id']] ?? null;
}
```

### Controllers Otimizados

#### 1. `AppointmentController::list()`

**Antes:**
- N queries para profissionais
- N queries para clientes
- N queries para pets
- N queries para especialidades
- **Total:** 1 + 4N queries

**Depois:**
- 1 query para profissionais (todos de uma vez)
- 1 query para clientes (todos de uma vez)
- 1 query para pets (todos de uma vez)
- 1 query para especialidades (todas de uma vez)
- **Total:** 1 + 4 queries

**Ganho:** De O(N) para O(1) em queries relacionadas

#### 2. `ExamController::list()`

**Antes:**
- N queries para pets
- N queries para clientes
- N queries para profissionais
- N queries para tipos de exame
- **Total:** 1 + 4N queries

**Depois:**
- 1 query para pets (todos de uma vez)
- 1 query para clientes (todos de uma vez)
- 1 query para profissionais (todos de uma vez)
- 1 query para tipos de exame (todos de uma vez)
- **Total:** 1 + 4 queries

**Ganho:** De O(N) para O(1) em queries relacionadas

---

## 📊 Impacto Esperado

### Performance de Queries

- **Verificação de conflitos:** ~80% mais rápido (com índice composto)
- **Listagem de agendamentos:** ~70% mais rápido (eliminação de N+1)
- **Listagem de exames:** ~70% mais rápido (eliminação de N+1)
- **Filtros por data:** ~60% mais rápido (com índice em `appointment_date`)

### Redução de Carga no Banco

- **Queries reduzidas:** De 1 + 4N para 1 + 4 (para listagens com N itens)
- **Exemplo:** Para 100 agendamentos:
  - **Antes:** 401 queries (1 + 4×100)
  - **Depois:** 5 queries (1 + 4)
  - **Redução:** 98.75%

---

## 🔧 Migration

A migration `20251129055914_add_performance_indexes.php` foi criada e executada.

**Para executar manualmente:**
```bash
php vendor/bin/phinx migrate
```

**Para reverter (se necessário):**
```bash
php vendor/bin/phinx rollback
```

---

## ✅ Checklist de Implementação

- [x] Criar migration com índices
- [x] Executar migration
- [x] Otimizar `AppointmentController::list()`
- [x] Otimizar `ExamController::list()`
- [x] Atualizar documentação
- [x] Verificar linter

---

## 📝 Notas Técnicas

### Índices Compostos

Os índices compostos são criados na ordem de seletividade:
1. `tenant_id` (mais seletivo - filtra por tenant)
2. Campos de filtro (ex: `professional_id`, `status`)
3. Campos de ordenação (ex: `created_at`, `appointment_date`)

### Uso de `CREATE INDEX IF NOT EXISTS`

A migration usa `CREATE INDEX IF NOT EXISTS` para ser idempotente, permitindo execução múltipla sem erros.

### Otimização de Queries

As otimizações de lazy loading usam:
- `array_column()` para extrair IDs
- `array_unique()` e `array_filter()` para remover duplicatas e valores nulos
- `IN (...)` queries para carregar múltiplos registros de uma vez
- Arrays associativos (`$itemsById`) para acesso O(1) aos dados carregados

---

**Última Atualização:** 2025-11-29


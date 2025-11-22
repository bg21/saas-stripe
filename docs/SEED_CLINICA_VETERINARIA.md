# 🌱 Seed de Dados de Teste - Clínica Veterinária

**Data:** 2025-01-22  
**Arquivo:** `db/seeds/VeterinaryClinicSeed.php`

---

## 📋 O QUE ESTE SEED CRIA

Este seed popula o banco de dados com dados de exemplo para testar o sistema de clínica veterinária:

### ✅ Dados Criados

1. **5 Especialidades**
   - Clínica Geral
   - Cirurgia
   - Dermatologia
   - Ortopedia
   - Cardiologia

2. **4 Usuários/Profissionais**
   - Dr. João Silva (Veterinário - Clínica Geral, Cirurgia)
   - Dra. Maria Santos (Veterinária - Clínica Geral, Dermatologia)
   - Dr. Carlos Oliveira (Veterinário - Ortopedia, Cardiologia)
   - Ana Paula (Atendente)

3. **3 Profissionais** (vinculados aos veterinários)
   - Cada um com CRMV e especialidades configuradas

4. **5 Clientes**
   - Pedro Almeida
   - Juliana Costa
   - Roberto Ferreira
   - Fernanda Lima
   - Marcos Souza
   - Todos com CPF, telefone, endereço completo

5. **7 Pets**
   - Rex (Golden Retriever) - Cliente: Pedro Almeida
   - Luna (Labrador) - Cliente: Pedro Almeida
   - Mimi (Gato Persa) - Cliente: Juliana Costa
   - Thor (Pastor Alemão) - Cliente: Roberto Ferreira
   - Bella (Bulldog Francês) - Cliente: Roberto Ferreira
   - Nina (Gato Siamês) - Cliente: Fernanda Lima
   - Max (Beagle) - Cliente: Marcos Souza

6. **Configuração da Clínica**
   - Horários de funcionamento (Segunda a Sábado)
   - Duração padrão de consultas: 30 minutos
   - Intervalo entre horários: 15 minutos

7. **Agendas dos Profissionais**
   - Segunda a Sexta: 8h às 18h
   - Sábado: 8h às 12h

8. **5 Agendamentos de Exemplo**
   - Agendamentos para os próximos dias
   - Diferentes status (scheduled, confirmed)
   - Vinculados a diferentes profissionais, clientes e pets

---

## 🚀 COMO USAR

### Executar o Seed

```bash
php vendor/bin/phinx seed:run -s VeterinaryClinicSeed
```

### Executar Novamente

O seed é **idempotente** - pode ser executado múltiplas vezes sem criar duplicatas. Ele verifica se os dados já existem antes de criar.

---

## 🔑 CREDENCIAIS DE ACESSO

### Veterinários

| Nome | Email | Senha | Especialidades |
|------|-------|-------|----------------|
| Dr. João Silva | `dr.silva@clinica.com` | `senha123` | Clínica Geral, Cirurgia |
| Dra. Maria Santos | `dra.santos@clinica.com` | `senha123` | Clínica Geral, Dermatologia |
| Dr. Carlos Oliveira | `dr.oliveira@clinica.com` | `senha123` | Ortopedia, Cardiologia |

### Atendente

| Nome | Email | Senha | Função |
|------|-------|-------|--------|
| Ana Paula | `atendente@clinica.com` | `senha123` | Atendente/Recepcionista |

---

## 📊 DADOS CRIADOS

### Especialidades

- **Clínica Geral** - Atendimento clínico geral
- **Cirurgia** - Procedimentos cirúrgicos e castrações
- **Dermatologia** - Tratamento de doenças de pele
- **Ortopedia** - Tratamento de fraturas e problemas ósseos
- **Cardiologia** - Exames cardíacos

### Clientes e Pets

#### Pedro Almeida
- **CPF:** 123.456.789-00
- **Telefone:** (11) 98765-4321
- **Pets:**
  - Rex (Golden Retriever, 5 anos, 28.5 kg)
  - Luna (Labrador, 4 anos, 22.0 kg)

#### Juliana Costa
- **CPF:** 234.567.890-11
- **Telefone:** (11) 91234-5678
- **Pets:**
  - Mimi (Gato Persa, 6 anos, 4.2 kg)

#### Roberto Ferreira
- **CPF:** 345.678.901-22
- **Telefone:** (11) 99876-5432
- **Pets:**
  - Thor (Pastor Alemão, 7 anos, 35.0 kg)
  - Bella (Bulldog Francês, 3 anos, 8.5 kg)

#### Fernanda Lima
- **CPF:** 456.789.012-33
- **Telefone:** (11) 97654-3210
- **Pets:**
  - Nina (Gato Siamês, 5 anos, 3.8 kg)

#### Marcos Souza
- **CPF:** 567.890.123-44
- **Telefone:** (11) 95555-1234
- **Pets:**
  - Max (Beagle, 4 anos, 12.0 kg)

---

## 🧪 TESTANDO O SISTEMA

### 1. Fazer Login

Use uma das credenciais acima para fazer login no sistema:
- Acesse: `http://localhost:8080/login`
- Use: `dr.silva@clinica.com` / `senha123`

### 2. Navegar pelas Funcionalidades

Após o login, você pode:

- **Ver Profissionais:** `/professionals`
- **Ver Clientes:** `/clinic-clients`
- **Ver Pets:** `/pets`
- **Ver Agendamentos:** `/appointments`
- **Ver Calendário:** `/appointment-calendar`
- **Ver Relatórios:** `/clinic-reports`
- **Configurar Clínica:** `/clinic-settings`

### 3. Testar Funcionalidades

- ✅ Criar novos agendamentos
- ✅ Editar clientes e pets
- ✅ Visualizar agendas dos profissionais
- ✅ Ver relatórios e dashboards
- ✅ Gerenciar especialidades

---

## 🔄 REINICIAR OS DADOS

Se quiser limpar e recriar os dados:

### Opção 1: Deletar manualmente (recomendado)

```sql
-- Cuidado: Isso deleta TODOS os dados da clínica!
DELETE FROM appointments;
DELETE FROM appointment_history;
DELETE FROM schedule_blocks;
DELETE FROM professional_schedules;
DELETE FROM pets;
DELETE FROM clients;
DELETE FROM professionals;
DELETE FROM specialties;
DELETE FROM clinic_configurations;
DELETE FROM users WHERE email LIKE '%@clinica.com';
```

Depois execute o seed novamente:
```bash
php vendor/bin/phinx seed:run -s VeterinaryClinicSeed
```

### Opção 2: Rollback e reapply migrations

```bash
# Cuidado: Isso remove TODAS as tabelas da clínica!
php vendor/bin/phinx rollback -t 20251122003111
php vendor/bin/phinx migrate
php vendor/bin/phinx seed:run -s VeterinaryClinicSeed
```

---

## 📝 NOTAS IMPORTANTES

1. **Tenant:** O seed usa o **primeiro tenant** encontrado no banco. Certifique-se de ter pelo menos um tenant criado.

2. **Idempotência:** O seed verifica se os dados já existem antes de criar, então pode ser executado múltiplas vezes sem problemas.

3. **CPF:** Os CPFs são fictícios e apenas para teste. Não use em produção.

4. **Senhas:** Todas as senhas são `senha123` - **NUNCA use em produção!**

5. **Agendamentos:** Os agendamentos são criados para datas futuras (amanhã, depois de amanhã, próxima semana).

---

## 🎯 PRÓXIMOS PASSOS

Após executar o seed:

1. ✅ Faça login com uma das credenciais
2. ✅ Explore as funcionalidades da clínica
3. ✅ Crie novos agendamentos
4. ✅ Teste os relatórios
5. ✅ Verifique as agendas dos profissionais

---

**Bom teste! 🐾**


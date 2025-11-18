# 💾 Sistema de Backup Automático

**Status:** ✅ Implementado  
**Biblioteca:** `ifsnop/mysqldump-php`

---

## 📋 Visão Geral

O sistema de backup automático permite criar, gerenciar e restaurar backups do banco de dados MySQL de forma simples e automatizada.

**Vantagens da biblioteca:**
- ✅ Não requer `mysqldump` instalado no sistema
- ✅ Portável - funciona apenas com PHP e PDO
- ✅ Mais seguro - não expõe senhas via linha de comando
- ✅ Melhor tratamento de erros

---

## 🚀 Funcionalidades

- ✅ Criação de backups com `mysqldump`
- ✅ Compressão automática (gzip)
- ✅ Retenção configurável de backups
- ✅ Histórico completo de backups (tabela `backup_logs`)
- ✅ Restauração facilitada
- ✅ Limpeza automática de backups antigos
- ✅ Estatísticas de backups
- ✅ Script CLI completo

---

## ⚙️ Configuração

Adicione as seguintes variáveis no arquivo `.env`:

```env
BACKUP_DIR=backups
BACKUP_RETENTION_DAYS=30
BACKUP_COMPRESS=true
```

### Parâmetros

| Parâmetro | Descrição | Padrão |
|-----------|-----------|--------|
| `BACKUP_DIR` | Diretório onde os backups serão salvos (relativo à raiz do projeto) | `backups` |
| `BACKUP_RETENTION_DAYS` | Número de dias para manter backups | `30` |
| `BACKUP_COMPRESS` | Se os backups devem ser comprimidos com gzip | `true` |

---

## 📦 Instalação

1. **Execute a migration** para criar a tabela `backup_logs`:
   ```bash
   composer run migrate
   ```

2. **Configure o `.env`** com as variáveis de backup (veja acima)

3. **Pronto!** O sistema está configurado.

---

## 🎯 Uso

### Via Composer (Recomendado)

```bash
# Criar um novo backup
composer run backup

# Listar backups disponíveis
composer run backup:list

# Ver estatísticas
composer run backup:stats

# Limpar backups antigos
composer run backup:clean
```

### Via Script CLI

```bash
# Criar backup
php scripts/backup.php create

# Listar backups (limite padrão: 50)
php scripts/backup.php list
php scripts/backup.php list 10  # Limite de 10

# Ver estatísticas
php scripts/backup.php stats

# Limpar backups antigos
php scripts/backup.php clean

# Restaurar backup específico
php scripts/backup.php restore 1

# Ver informações de um backup
php scripts/backup.php get 1

# Ajuda
php scripts/backup.php help
```

---

## 📊 Exemplos de Uso

### Criar um Backup

```bash
composer run backup
```

**Saída:**
```
🔄 Criando backup...
✅ Backup criado com sucesso!

ID: 1
Arquivo: backup_saas_payments_2025-01-16_14-30-45.sql.gz
Tamanho: 2.5 MB
Duração: 3.2s
Comprimido: Sim
Criado em: 2025-01-16 14:30:45
```

### Listar Backups

```bash
composer run backup:list
```

**Saída:**
```
📋 Listando backups (limite: 50)...

ID    Arquivo                          Tamanho     Status     Criado em            Existe
------------------------------------------------------------------------------------------
1     backup_saas_payments_2025-01...  2.5 MB      ✓        16/01/2025 14:30:45  Sim
```

### Ver Estatísticas

```bash
composer run backup:stats
```

**Saída:**
```
📊 Estatísticas de Backups
======================================================================

Total de backups: 5
Bem-sucedidos: 5
Falhados: 0
Tamanho total: 12.5 MB
Retenção: 30 dias
Próxima limpeza: 15/02/2025 14:30:45

Último backup:
  ID: 5
  Arquivo: backup_saas_payments_2025-01-16_14-30-45.sql.gz
  Criado em: 16/01/2025 14:30:45
```

### Restaurar um Backup

```bash
php scripts/backup.php restore 1
```

O sistema pedirá confirmação antes de restaurar.

---

## 🔄 Automação (Cron)

### Linux/Mac

Para criar backups automáticos, adicione ao crontab:

```bash
# Backup diário às 2h da manhã
0 2 * * * cd /caminho/para/projeto && composer run backup

# Limpeza semanal (domingos às 3h)
0 3 * * 0 cd /caminho/para/projeto && composer run backup:clean
```

### Windows (Task Scheduler)

Crie uma tarefa agendada que execute:
```
php C:\caminho\para\projeto\scripts\backup.php create
```

---

## 📁 Estrutura de Arquivos

```
projeto/
├── backups/                          # Diretório de backups
│   ├── backup_saas_payments_2025-01-16_14-30-45.sql.gz
│   └── backup_saas_payments_2025-01-15_10-20-30.sql.gz
├── App/
│   ├── Services/
│   │   └── BackupService.php         # Serviço principal
│   └── Models/
│       └── BackupLog.php             # Model de logs
├── scripts/
│   ├── backup.php                    # Script CLI
│   └── test_backup.php               # Script de teste
└── db/
    └── migrations/
        └── 20250116000001_create_backup_logs_table.php
```

---

## 🧪 Testes

Execute o script de teste para validar o sistema:

```bash
php scripts/test_backup.php
```

---

## ⚠️ Requisitos

- **PHP**: Extensão `zlib` para compressão (geralmente já incluída)
- **MySQL**: Acesso ao banco de dados MySQL (via PDO)
- **Permissões**: O diretório de backups deve ser gravável
- **Biblioteca**: `ifsnop/mysqldump-php` (instalada via Composer)

### Vantagens da Biblioteca

- ✅ **Não requer `mysqldump` instalado** - Funciona apenas com PHP e PDO
- ✅ **Portável** - Funciona em qualquer ambiente com PHP
- ✅ **Mais seguro** - Não expõe senhas via linha de comando
- ✅ **Mais confiável** - Melhor tratamento de erros

---

## 🔒 Segurança

- ⚠️ **Backups contêm dados sensíveis**: Proteja o diretório de backups
- ⚠️ **Permissões**: Configure permissões adequadas (ex: 700) no diretório de backups
- ⚠️ **Backup remoto**: Considere copiar backups para servidor remoto ou S3
- ⚠️ **Senha do banco**: A senha é passada via linha de comando (visível em `ps`)

---

## 📝 Notas

- Os backups são salvos no formato: `backup_{DB_NAME}_{TIMESTAMP}.sql.gz`
- Backups comprimidos economizam ~70-90% de espaço
- A limpeza automática remove backups mais antigos que `BACKUP_RETENTION_DAYS`
- O sistema registra todos os backups (sucesso e falha) na tabela `backup_logs`

---

## 🐛 Troubleshooting

### Erro: "Biblioteca ifsnop/mysqldump-php não encontrada"

**Solução**: Instale a biblioteca via Composer:
```bash
composer require ifsnop/mysqldump-php
```

### Erro: "Não foi possível criar diretório de backups"

**Solução**: Verifique permissões do diretório pai ou crie manualmente:
```bash
mkdir backups
chmod 755 backups
```

### Erro: "Erro ao executar mysqldump"

**Solução**: Verifique:
1. Credenciais do banco no `.env`
2. Se o MySQL está rodando
3. Se o usuário tem permissão de backup

---

## 📚 API (Futuro)

O sistema pode ser expandido para incluir endpoints REST:

- `POST /v1/backups` - Criar backup
- `GET /v1/backups` - Listar backups
- `GET /v1/backups/:id` - Obter backup
- `POST /v1/backups/:id/restore` - Restaurar backup
- `GET /v1/backups/stats` - Estatísticas

---

**Última Atualização:** 2025-01-XX

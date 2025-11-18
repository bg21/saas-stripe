# 📦 Sistema de Migrations

**Ferramenta:** Phinx  
**Status:** ✅ Implementado

---

## 🎯 Por que Migrations?

- ✅ **Versionamento**: Controle de versão do schema do banco
- ✅ **Reprodutibilidade**: Mesmo schema em todos os ambientes
- ✅ **Rollback**: Possibilidade de reverter mudanças
- ✅ **Colaboração**: Facilita trabalho em equipe
- ✅ **Produção**: Deploy seguro de mudanças no banco

---

## 📋 Pré-requisitos

1. **Instalar dependências:**
   ```bash
   composer install
   ```

2. **Configurar o arquivo `.env`** com as credenciais do banco de dados.

---

## 🚀 Comandos Básicos

### Verificar Status das Migrations

```bash
composer run migrate:status
# ou
vendor/bin/phinx status
```

### Executar Migrations

Executa todas as migrations pendentes:

```bash
composer run migrate
# ou
vendor/bin/phinx migrate
```

### Reverter Última Migration

```bash
composer run migrate:rollback
# ou
vendor/bin/phinx rollback
```

### Executar Seeds

```bash
composer run seed
# ou
vendor/bin/phinx seed:run
```

### Executar Seed Específico

```bash
vendor/bin/phinx seed:run -s InitialSeed
```

---

## 📝 Criando uma Nova Migration

### Via Composer (Recomendado)

```bash
vendor/bin/phinx create NomeDaMigration
```

Isso criará um arquivo em `db/migrations/YYYYMMDDHHMMSS_nome_da_migration.php`

### Estrutura de uma Migration

```php
<?php

use Phinx\Migration\AbstractMigration;

class NomeDaMigration extends AbstractMigration
{
    public function up()
    {
        // Código para aplicar a migration
        $table = $this->table('nova_tabela');
        $table->addColumn('nome', 'string', ['limit' => 255])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('created_at', 'datetime')
              ->create();
    }

    public function down()
    {
        // Código para reverter a migration
        $this->table('nova_tabela')->drop()->save();
    }
}
```

---

## 🌱 Criando um Novo Seed

### Via Composer

```bash
vendor/bin/phinx seed:create NomeDoSeed
```

Isso criará um arquivo em `db/seeds/NomeDoSeed.php`

### Estrutura de um Seed

```php
<?php

use Phinx\Seed\AbstractSeed;

class NomeDoSeed extends AbstractSeed
{
    public function run()
    {
        $data = [
            [
                'campo1' => 'valor1',
                'campo2' => 'valor2',
            ],
        ];

        $this->table('nome_tabela')->insert($data)->saveData();
    }
}
```

---

## 🔄 Fluxo de Trabalho

### Desenvolvimento Local

1. **Criar nova migration:**
   ```bash
   vendor/bin/phinx create AdicionarNovaColuna
   ```

2. **Editar a migration** em `db/migrations/`

3. **Testar a migration:**
   ```bash
   composer run migrate
   ```

4. **Se necessário, reverter:**
   ```bash
   composer run migrate:rollback
   ```

5. **Criar seed (se necessário):**
   ```bash
   vendor/bin/phinx seed:create DadosDeTeste
   ```

### Produção

1. **Fazer backup do banco de dados** (sempre!)

2. **Verificar status:**
   ```bash
   composer run migrate:status
   ```

3. **Executar migrations:**
   ```bash
   composer run migrate
   ```

4. **Verificar se tudo está funcionando**

5. **Se houver problemas, reverter:**
   ```bash
   composer run migrate:rollback
   ```

---

## 📂 Estrutura de Arquivos

```
db/
├── migrations/
│   ├── 20250115000001_initial_schema.php
│   └── YYYYMMDDHHMMSS_nome_migration.php
└── seeds/
    ├── InitialSeed.php
    └── NomeDoSeed.php
```

---

## ⚠️ Importante

### Migration Inicial

A migration `20250115000001_initial_schema.php` reflete o schema atual do sistema.

**Se você já tem um banco de dados em uso:**

1. **NÃO execute a migration inicial** - ela criará tabelas que já existem
2. **Marque a migration inicial como executada:**
   ```sql
   -- Conecte ao banco e insira manualmente:
   INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint)
   VALUES ('20250115000001', 'initial_schema', NOW(), NOW(), 0);
   ```
3. **Ou crie uma migration vazia** que apenas marca o estado atual

### Boas Práticas

1. ✅ **Sempre teste migrations localmente antes de produção**
2. ✅ **Faça backup antes de executar migrations em produção**
3. ✅ **Uma migration = uma mudança lógica**
4. ✅ **Migrations devem ser reversíveis (método `down()`)**
5. ✅ **Não modifique migrations já executadas em produção**
6. ✅ **Use seeds apenas para dados de desenvolvimento/teste**

---

## 🔍 Troubleshooting

### Erro: "Migration already exists"

A migration já foi executada. Verifique o status:
```bash
composer run migrate:status
```

### Erro: "Table already exists"

Você está tentando criar uma tabela que já existe. Verifique se:
- A migration já foi executada
- Você está em um banco que já tem o schema

### Erro de Conexão

Verifique o arquivo `.env` e certifique-se de que:
- `DB_HOST` está correto
- `DB_NAME` existe
- `DB_USER` e `DB_PASS` estão corretos

---

## 📚 Referências

| Recurso | Link |
|---------|------|
| **Documentação do Phinx** | https://book.cakephp.org/phinx/0/en/index.html |
| **Phinx no GitHub** | https://github.com/cakephp/phinx |

---

## 🎯 Próximos Passos

Após implementar o sistema de migrations, considere:

1. **Logs de Auditoria** - Sistema de rastreabilidade
2. **Health Check Avançado** - Verificação de dependências
3. **Backup Automático** - Sistema de backup do banco

---

**Última Atualização:** 2025-01-XX

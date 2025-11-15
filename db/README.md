# 📦 Estrutura de Migrations e Seeds

Esta pasta contém as migrations e seeds do banco de dados gerenciados pelo Phinx.

## 📂 Estrutura

```
db/
├── migrations/     # Migrations do banco de dados
└── seeds/          # Seeds (dados iniciais)
```

## 📝 Migrations

As migrations são arquivos PHP que definem mudanças no schema do banco de dados.

**Localização:** `db/migrations/`

**Formato:** `YYYYMMDDHHMMSS_nome_da_migration.php`

## 🌱 Seeds

Os seeds são arquivos PHP que populam o banco com dados iniciais.

**Localização:** `db/seeds/`

**Formato:** `NomeDoSeed.php`

## 🚀 Uso

Consulte `docs/MIGRATIONS.md` para instruções completas sobre como usar migrations e seeds.


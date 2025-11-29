# 📧 Scripts de Teste de Email

Este diretório contém scripts para testar o sistema de notificações por email.

## 📋 Scripts Disponíveis

### 1. `test_emails_templates_only.php`
**Testa apenas a renderização dos templates (sem enviar emails)**

```bash
php scripts/test_emails_templates_only.php
```

- ✅ Verifica se todos os arquivos de template existem
- ✅ Testa a renderização de cada template com dados mock
- ✅ Não requer configuração SMTP
- ✅ Ideal para validar templates antes de configurar SMTP

### 2. `test_emails.php`
**Testa envio real de emails (requer SMTP configurado)**

```bash
php scripts/test_emails.php
```

- ✅ Testa todos os métodos de envio de email
- ✅ Envia emails reais para `juhcosta23@gmail.com` (ou `TEST_EMAIL` no .env)
- ✅ Requer configuração SMTP válida no `.env`
- ⚠️ Em modo desenvolvimento com `MAIL_DRIVER=log`, apenas loga em arquivo

**Modo de uso:**
```bash
# Teste completo (tenta enviar)
php scripts/test_emails.php

# Teste apenas templates (sem envio)
php scripts/test_emails.php templates
```

### 3. `preview_emails.php`
**Gera arquivos HTML de preview dos emails**

```bash
php scripts/preview_emails.php
```

- ✅ Gera arquivos HTML renderizados na pasta `previews/emails/`
- ✅ Cria um `index.html` com links para todos os previews
- ✅ Permite visualizar os emails no navegador
- ✅ Não requer SMTP

**Após executar, abra no navegador:**
```
previews/emails/index.html
```

## 🔧 Configuração

### Email de Teste

Por padrão, os scripts usam `juhcosta23@gmail.com` como email de destino.

Você pode sobrescrever definindo no `.env`:
```env
TEST_EMAIL=seu-email@example.com
```

### Configuração SMTP

Para testar envio real, configure no `.env`:
```env
MAIL_HOST=smtp.titan.email
MAIL_PORT=587
MAIL_USERNAME=suporte@orcamentum.com
MAIL_PASSWORD=sua_senha_aqui
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=suporte@orcamentum.com
MAIL_FROM_NAME="Sistema de Pagamentos"
SUPORTE_EMAIL=suporte@orcamentum.com
```

### Modo Desenvolvimento (Log)

Para apenas logar emails sem enviar, configure:
```env
APP_ENV=development
MAIL_DRIVER=log
```

Os emails serão salvos em `logs/emails-YYYY-MM-DD.log`

## 📊 Tipos de Email Testados

1. ✅ **Pagamento Falhado** (`payment_failed`)
2. ✅ **Assinatura Cancelada** (`subscription_canceled`)
3. ✅ **Nova Assinatura Criada** (`subscription_created`)
4. ✅ **Trial Terminando** (`trial_ending`)
5. ✅ **Fatura Próxima** (`invoice_upcoming`)
6. ✅ **Disputa Criada** (`dispute_created`)
7. ✅ **Assinatura Reativada** (`subscription_reactivated`)

## 🚀 Fluxo Recomendado de Teste

1. **Primeiro, teste os templates:**
   ```bash
   php scripts/test_emails_templates_only.php
   ```

2. **Gere previews para visualizar:**
   ```bash
   php scripts/preview_emails.php
   # Abra previews/emails/index.html no navegador
   ```

3. **Configure SMTP no .env e teste envio real:**
   ```bash
   php scripts/test_emails.php
   ```

4. **Verifique a caixa de entrada de `juhcosta23@gmail.com`**

## 📝 Notas

- Os emails são enviados para `juhcosta23@gmail.com` por padrão
- Em modo desenvolvimento, emails são logados em arquivo
- Todos os templates são testados com dados mock realistas
- Os scripts fornecem feedback colorido no terminal


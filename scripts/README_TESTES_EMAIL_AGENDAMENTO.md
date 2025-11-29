# Testes de Emails - Agendamentos e Eventos Stripe

Este documento descreve como testar os emails relacionados a agendamentos e eventos do Stripe.

## 📧 Script de Teste

O script `test_appointment_emails.php` testa todos os emails implementados:

### Emails de Agendamento

1. **Agendamento Criado** - Enviado quando um novo agendamento é criado
2. **Agendamento Confirmado** - Enviado quando um agendamento é confirmado
3. **Agendamento Cancelado** - Enviado quando um agendamento é cancelado
4. **Lembrete de Agendamento** - Enviado 24h antes do agendamento

### Emails de Eventos Stripe

5. **Pagamento Falhado** - Enviado quando uma tentativa de pagamento falha
6. **Assinatura Cancelada** - Enviado quando uma assinatura é cancelada
7. **Assinatura Criada** - Enviado quando uma nova assinatura é criada

## 🚀 Como Usar

### Executar Testes

```bash
php scripts/test_appointment_emails.php
```

O script enviará todos os emails para: **juhcosta23@gmail.com**

### Configuração

O script usa as configurações de email do arquivo `.env`:

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu_email@gmail.com
MAIL_PASSWORD=sua_senha_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=seu_email@gmail.com
MAIL_FROM_NAME=Sistema de Clínica
```

### Modo de Desenvolvimento

Se `APP_ENV=development` e `MAIL_DRIVER=log`, os emails serão apenas logados em:
```
logs/emails-YYYY-MM-DD.log
```

## ✅ Resultado Esperado

Ao executar o script, você verá:

```
═══════════════════════════════════════════════════════════
  TESTE DE EMAILS - Agendamentos e Eventos Stripe
═══════════════════════════════════════════════════════════
📧 Email de destino: juhcosta23@gmail.com

✓ Modo de envio: SMTP (emails reais serão enviados)

✓ EmailService inicializado

═══════════════════════════════════════════════════════════
  EMAILS DE AGENDAMENTO
═══════════════════════════════════════════════════════════

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Testando: Agendamento Criado
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Email enviado com sucesso para: juhcosta23@gmail.com

... (outros testes)
```

## 📝 Verificação

Após executar o script:

1. Verifique a caixa de entrada de **juhcosta23@gmail.com**
2. Verifique também a pasta de spam/lixo eletrônico
3. Todos os 7 emails devem ter sido recebidos

## 🔧 Troubleshooting

### Erro: "Class PHPMailer not found"

Instale o PHPMailer:
```bash
composer require phpmailer/phpmailer
```

### Emails não estão sendo enviados

1. Verifique as configurações SMTP no `.env`
2. Para Gmail, use uma "Senha de App" (não a senha normal)
3. Verifique se o firewall não está bloqueando a porta SMTP

### Emails vão para spam

- Configure SPF, DKIM e DMARC no seu domínio
- Use um email profissional (não Gmail pessoal) para produção
- Verifique se o remetente está configurado corretamente

## 📚 Templates de Email

Os templates estão localizados em:
```
App/Templates/Email/
├── appointment_created.html
├── appointment_confirmed.html
├── appointment_cancelled.html
├── appointment_reminder.html
├── payment_failed.html
├── subscription_canceled.html
└── subscription_created.html
```

## 🔗 Integração no Código

Os emails são enviados automaticamente em:

- **AppointmentController::create()** - Envia email de agendamento criado
- **AppointmentController::confirm()** - Envia email de agendamento confirmado
- **AppointmentController::update()** - Envia email quando status muda para 'cancelled'
- **PaymentService::handleInvoicePaymentFailed()** - Envia email de pagamento falhado
- **PaymentService::handleSubscriptionUpdate()** - Envia email de assinatura cancelada
- **PaymentService::handleCheckoutCompleted()** - Envia email de assinatura criada

## 📌 Notas

- Os erros de envio de email são logados mas não interrompem a operação principal
- Em modo desenvolvimento com `MAIL_DRIVER=log`, os emails são apenas logados
- O script adiciona um delay de 1 segundo entre cada envio para evitar rate limiting


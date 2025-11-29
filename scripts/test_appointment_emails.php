<?php

/**
 * Script de teste para emails de agendamento e eventos Stripe
 * 
 * Uso: php scripts/test_appointment_emails.php
 * 
 * Este script testa todos os emails relacionados a agendamentos e eventos do Stripe
 * Envia emails para: juhcosta23@gmail.com
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega configurações
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Carrega classe Config
require_once __DIR__ . '/../config/config.php';
Config::load();

use App\Services\EmailService;
use App\Services\Logger;

// Cores para terminal
class Colors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  TESTE DE EMAILS - Agendamentos e Eventos Stripe\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

// Email de destino para testes
$testEmail = 'juhcosta23@gmail.com';

echo Colors::CYAN . "📧 Email de destino: " . Colors::BOLD . $testEmail . Colors::RESET . "\n\n";

// Verifica se está em modo desenvolvimento
$isDevelopment = ($_ENV['APP_ENV'] ?? 'development') === 'development';
$mailDriver = $_ENV['MAIL_DRIVER'] ?? 'smtp';

if ($isDevelopment && $mailDriver === 'log') {
    echo Colors::YELLOW . "⚠️  MODO DESENVOLVIMENTO: Emails serão logados em logs/emails-*.log\n";
    echo "   Para enviar emails reais, configure MAIL_DRIVER=smtp no .env\n\n" . Colors::RESET;
} else {
    echo Colors::GREEN . "✓ Modo de envio: SMTP (emails reais serão enviados)\n\n" . Colors::RESET;
}

// Inicializa EmailService
try {
    $emailService = new EmailService(false); // Sem debug para não poluir output
    echo Colors::GREEN . "✓ EmailService inicializado\n\n" . Colors::RESET;
} catch (\Exception $e) {
    echo Colors::RED . "✗ Erro ao inicializar EmailService: " . $e->getMessage() . "\n" . Colors::RESET;
    exit(1);
}

// Dados mock para testes
$mockClient = [
    'name' => 'João Silva',
    'email' => $testEmail
];

$mockPet = [
    'name' => 'Rex',
    'species' => 'Cachorro'
];

$mockProfessional = [
    'name' => 'Dr. Maria Santos',
    'email' => 'maria@clinica.com'
];

$mockAppointment = [
    'id' => 1,
    'appointment_date' => date('Y-m-d', strtotime('+2 days')),
    'appointment_time' => '14:30',
    'duration_minutes' => 30,
    'notes' => 'Consulta de rotina - Primeira vez do pet na clínica'
];

$mockInvoice = [
    'id' => 'in_test_1234567890',
    'amount_due' => 9900, // R$ 99,00 em centavos
    'currency' => 'brl',
    'attempt_count' => 1,
    'next_payment_attempt' => time() + (7 * 24 * 60 * 60) // 7 dias
];

$mockSubscription = [
    'id' => 'sub_test_1234567890',
    'status' => 'active',
    'current_period_end' => time() + (30 * 24 * 60 * 60), // 30 dias
    'cancel_at_period_end' => false,
    'plan' => [
        'id' => 'plan_test',
        'nickname' => 'Plano Premium',
        'amount' => 9900,
        'currency' => 'brl',
        'interval' => 'month'
    ]
];

$mockCustomer = [
    'id' => 1,
    'name' => 'João Silva',
    'email' => $testEmail,
    'tenant_id' => 1
];

// Função auxiliar para testar envio
function testEmail($name, $callback) {
    global $testEmail;
    
    echo Colors::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Testando: " . Colors::BOLD . $name . Colors::RESET . "\n";
    echo Colors::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::RESET;
    
    try {
        $result = $callback();
        if ($result) {
            echo Colors::GREEN . "✅ Email enviado com sucesso para: " . $testEmail . "\n" . Colors::RESET;
        } else {
            echo Colors::RED . "❌ Falha ao enviar email\n" . Colors::RESET;
        }
    } catch (\Exception $e) {
        echo Colors::RED . "❌ Erro: " . $e->getMessage() . "\n" . Colors::RESET;
        Logger::error("Erro ao testar email: {$name}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    echo "\n";
    sleep(1); // Pequeno delay entre envios
}

// ============================================================================
// TESTES DE EMAILS DE AGENDAMENTO
// ============================================================================

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  EMAILS DE AGENDAMENTO\n";
echo "═══════════════════════════════════════════════════════════\n\n" . Colors::RESET;

// 1. Email de Agendamento Criado
testEmail('Agendamento Criado', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    return $emailService->sendAppointmentCreated(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional
    );
});

// 2. Email de Agendamento Confirmado
testEmail('Agendamento Confirmado', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    return $emailService->sendAppointmentConfirmed(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional
    );
});

// 3. Email de Agendamento Cancelado
testEmail('Agendamento Cancelado', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    return $emailService->sendAppointmentCancelled(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional,
        'Cliente solicitou cancelamento'
    );
});

// 4. Email de Lembrete de Agendamento
testEmail('Lembrete de Agendamento', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    // Ajusta data para amanhã
    $mockAppointment['appointment_date'] = date('Y-m-d', strtotime('+1 day'));
    return $emailService->sendAppointmentReminder(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional
    );
});

// ============================================================================
// TESTES DE EMAILS DE EVENTOS STRIPE
// ============================================================================

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  EMAILS DE EVENTOS STRIPE\n";
echo "═══════════════════════════════════════════════════════════\n\n" . Colors::RESET;

// 5. Email de Pagamento Falhado
testEmail('Pagamento Falhado', function() use ($emailService, $mockInvoice, $mockCustomer) {
    return $emailService->enviarNotificacaoPagamentoFalhado(
        $mockInvoice,
        $mockCustomer
    );
});

// 6. Email de Assinatura Cancelada
testEmail('Assinatura Cancelada', function() use ($emailService, $mockSubscription, $mockCustomer) {
    $mockSubscription['status'] = 'canceled';
    return $emailService->enviarNotificacaoAssinaturaCancelada(
        $mockSubscription,
        $mockCustomer
    );
});

// 7. Email de Assinatura Criada
testEmail('Assinatura Criada', function() use ($emailService, $mockSubscription, $mockCustomer) {
    $mockSubscription['status'] = 'active';
    return $emailService->enviarNotificacaoAssinaturaCriada(
        $mockSubscription,
        $mockCustomer
    );
});

// ============================================================================
// RESUMO
// ============================================================================

echo Colors::BOLD . Colors::GREEN . "═══════════════════════════════════════════════════════════\n";
echo "  TESTES CONCLUÍDOS\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

echo Colors::CYAN . "📧 Verifique sua caixa de entrada: " . Colors::BOLD . $testEmail . Colors::RESET . "\n";
echo Colors::CYAN . "📧 Verifique também a pasta de spam/lixo eletrônico\n\n" . Colors::RESET;

if ($isDevelopment && $mailDriver === 'log') {
    $logFile = dirname(__DIR__) . '/logs/emails-' . date('Y-m-d') . '.log';
    echo Colors::YELLOW . "📝 Emails também foram logados em: " . $logFile . "\n" . Colors::RESET;
}

echo "\n";


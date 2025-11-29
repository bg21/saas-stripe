<?php

/**
 * Script de teste COMPLETO para TODOS os emails do sistema
 * Testa tanto os emails antigos quanto os novos de agendamento
 * 
 * Uso: php scripts/test_all_emails.php
 * 
 * Este script testa TODOS os templates e métodos de envio de email
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
echo "  TESTE COMPLETO DE TODOS OS EMAILS DO SISTEMA\n";
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
    $emailService = new EmailService(false);
    echo Colors::GREEN . "✓ EmailService inicializado\n\n" . Colors::RESET;
} catch (\Exception $e) {
    echo Colors::RED . "✗ Erro ao inicializar EmailService: " . $e->getMessage() . "\n" . Colors::RESET;
    exit(1);
}

// Dados mock para testes
$mockCustomer = [
    'id' => 1,
    'name' => 'João Silva',
    'email' => $testEmail,
    'tenant_id' => 1
];

$mockSubscription = [
    'id' => 1,
    'stripe_subscription_id' => 'sub_test_1234567890',
    'plan_id' => 'plan_premium',
    'amount' => 99.90,
    'currency' => 'BRL',
    'current_period_end' => date('Y-m-d H:i:s', strtotime('+30 days')),
    'trial_end' => date('Y-m-d H:i:s', strtotime('+7 days')),
    'status' => 'active',
    'canceled_at' => null
];

$mockInvoice = [
    'id' => 'in_test_1234567890',
    'amount_due' => 9990, // em centavos
    'currency' => 'brl',
    'attempt_count' => 1,
    'next_payment_attempt' => time() + 86400, // +1 dia
    'due_date' => time() + 86400,
    'period_end' => time() + 86400
];

$mockDispute = [
    'id' => 'dp_test_1234567890',
    'amount' => 5000, // em centavos
    'currency' => 'brl',
    'reason' => 'fraudulent',
    'status' => 'warning_needs_response',
    'evidence_due_by' => time() + (7 * 24 * 60 * 60) // 7 dias
];

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

// Contadores
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Função auxiliar para testar envio
function testEmail($name, $callback, &$totalTests, &$passedTests, &$failedTests) {
    global $testEmail;
    
    $totalTests++;
    
    echo Colors::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Testando: " . Colors::BOLD . $name . Colors::RESET . "\n";
    echo Colors::CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::RESET;
    
    try {
        $result = $callback();
        if ($result) {
            echo Colors::GREEN . "✅ Email enviado com sucesso para: " . $testEmail . "\n" . Colors::RESET;
            $passedTests++;
        } else {
            echo Colors::RED . "❌ Falha ao enviar email\n" . Colors::RESET;
            $failedTests++;
        }
    } catch (\Exception $e) {
        echo Colors::RED . "❌ Erro: " . $e->getMessage() . "\n" . Colors::RESET;
        $failedTests++;
        Logger::error("Erro ao testar email: {$name}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    echo "\n";
    sleep(1); // Pequeno delay entre envios
}

// ============================================================================
// EMAILS DE AGENDAMENTO (NOVOS)
// ============================================================================

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  EMAILS DE AGENDAMENTO (NOVOS)\n";
echo "═══════════════════════════════════════════════════════════\n\n" . Colors::RESET;

testEmail('Agendamento Criado', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    return $emailService->sendAppointmentCreated(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Agendamento Confirmado', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    return $emailService->sendAppointmentConfirmed(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Agendamento Cancelado', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    return $emailService->sendAppointmentCancelled(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional,
        'Cliente solicitou cancelamento'
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Lembrete de Agendamento', function() use ($emailService, $mockAppointment, $mockClient, $mockPet, $mockProfessional) {
    $mockAppointment['appointment_date'] = date('Y-m-d', strtotime('+1 day'));
    return $emailService->sendAppointmentReminder(
        $mockAppointment,
        $mockClient,
        $mockPet,
        $mockProfessional
    );
}, $totalTests, $passedTests, $failedTests);

// ============================================================================
// EMAILS DE EVENTOS STRIPE (ANTIGOS)
// ============================================================================

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  EMAILS DE EVENTOS STRIPE (ANTIGOS)\n";
echo "═══════════════════════════════════════════════════════════\n\n" . Colors::RESET;

testEmail('Pagamento Falhado', function() use ($emailService, $mockInvoice, $mockCustomer) {
    return $emailService->enviarNotificacaoPagamentoFalhado(
        $mockInvoice,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Assinatura Cancelada', function() use ($emailService, $mockSubscription, $mockCustomer) {
    $mockSubscription['status'] = 'canceled';
    $mockSubscription['canceled_at'] = date('Y-m-d H:i:s');
    return $emailService->enviarNotificacaoAssinaturaCancelada(
        $mockSubscription,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Assinatura Criada', function() use ($emailService, $mockSubscription, $mockCustomer) {
    $mockSubscription['status'] = 'active';
    return $emailService->enviarNotificacaoAssinaturaCriada(
        $mockSubscription,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Trial Terminando', function() use ($emailService, $mockSubscription, $mockCustomer) {
    return $emailService->enviarNotificacaoTrialTerminando(
        $mockSubscription,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Fatura Próxima', function() use ($emailService, $mockInvoice, $mockCustomer) {
    return $emailService->enviarNotificacaoFaturaProxima(
        $mockInvoice,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Disputa Criada', function() use ($emailService, $mockDispute, $mockCustomer) {
    return $emailService->enviarNotificacaoDisputaCriada(
        $mockDispute,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Assinatura Reativada', function() use ($emailService, $mockSubscription, $mockCustomer) {
    $mockSubscription['status'] = 'active';
    return $emailService->enviarNotificacaoAssinaturaReativada(
        $mockSubscription,
        $mockCustomer
    );
}, $totalTests, $passedTests, $failedTests);

// ============================================================================
// EMAILS DE AUTENTICAÇÃO E SISTEMA (ANTIGOS)
// ============================================================================

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  EMAILS DE AUTENTICAÇÃO E SISTEMA (ANTIGOS)\n";
echo "═══════════════════════════════════════════════════════════\n\n" . Colors::RESET;

testEmail('Redefinição de Senha', function() use ($emailService, $mockCustomer) {
    $linkResetSenha = ($_ENV['APP_URL'] ?? 'http://localhost') . '/reset-senha/token_abc123xyz';
    return $emailService->enviarEmailResetSenha(
        $mockCustomer['email'],
        $mockCustomer['name'],
        $linkResetSenha
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Confirmação de Email', function() use ($emailService, $mockCustomer) {
    $linkConfirmacao = ($_ENV['APP_URL'] ?? 'http://localhost') . '/confirmar-email/token_abc123xyz';
    return $emailService->enviarEmailConfirmacao(
        $mockCustomer['email'],
        $mockCustomer['name'],
        $linkConfirmacao
    );
}, $totalTests, $passedTests, $failedTests);

testEmail('Notificação de Login', function() use ($emailService, $mockCustomer) {
    return $emailService->enviarNotificacaoLogin(
        $mockCustomer['email'],
        $mockCustomer['name'],
        date('d/m/Y H:i:s'),
        '192.168.1.100',
        'São Paulo, SP, Brasil',
        'Chrome no Windows'
    );
}, $totalTests, $passedTests, $failedTests);

// ============================================================================
// RESUMO
// ============================================================================

echo Colors::BOLD . Colors::GREEN . "═══════════════════════════════════════════════════════════\n";
echo "  TESTES CONCLUÍDOS\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

echo Colors::CYAN . "📊 Estatísticas:\n" . Colors::RESET;
echo "   Total de testes: " . Colors::BOLD . $totalTests . Colors::RESET . "\n";
echo "   " . Colors::GREEN . "✅ Sucessos: " . $passedTests . Colors::RESET . "\n";
echo "   " . Colors::RED . "❌ Falhas: " . $failedTests . Colors::RESET . "\n\n";

if ($failedTests === 0) {
    echo Colors::BOLD . Colors::GREEN . "🎉 Todos os emails foram enviados com sucesso!\n\n" . Colors::RESET;
} else {
    echo Colors::BOLD . Colors::YELLOW . "⚠️  Alguns emails falharam. Verifique os logs acima.\n\n" . Colors::RESET;
}

echo Colors::CYAN . "📧 Verifique sua caixa de entrada: " . Colors::BOLD . $testEmail . Colors::RESET . "\n";
echo Colors::CYAN . "📧 Verifique também a pasta de spam/lixo eletrônico\n\n" . Colors::RESET;

if ($isDevelopment && $mailDriver === 'log') {
    $logFile = dirname(__DIR__) . '/logs/emails-' . date('Y-m-d') . '.log';
    echo Colors::YELLOW . "📝 Emails também foram logados em: " . $logFile . "\n" . Colors::RESET;
}

echo "\n";


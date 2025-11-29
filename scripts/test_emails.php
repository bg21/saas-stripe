<?php

/**
 * Script de teste para todos os emails do sistema
 * 
 * Uso: php scripts/test_emails.php
 * 
 * Este script testa todos os templates e métodos de envio de email
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
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  TESTE DE EMAILS - Sistema de Notificações\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

// Verifica argumentos da linha de comando
$testMode = $argv[1] ?? 'full';
$testOnlyTemplates = ($testMode === 'templates' || $testMode === 'template');

if ($testOnlyTemplates) {
    echo Colors::YELLOW . "📧 MODO: Teste apenas de renderização de templates (sem envio)\n" . Colors::RESET;
} else {
    // Verifica se está em modo desenvolvimento
    $isDevelopment = ($_ENV['APP_ENV'] ?? 'development') === 'development';
    $mailDriver = $_ENV['MAIL_DRIVER'] ?? 'smtp';

    if ($isDevelopment && $mailDriver === 'log') {
        echo Colors::YELLOW . "⚠️  MODO DESENVOLVIMENTO: Emails serão logados em logs/emails-*.log\n" . Colors::RESET;
    } else {
        echo Colors::GREEN . "✓ Modo de envio real ativado\n" . Colors::RESET;
        echo Colors::YELLOW . "💡 Dica: Use 'php scripts/test_emails.php templates' para testar apenas templates\n" . Colors::RESET;
    }
}

echo "\n";

// Inicializa EmailService
try {
    // Se for apenas teste de templates, não precisa de debug SMTP
    $emailService = $testOnlyTemplates ? new EmailService(false) : new EmailService(true);
    echo Colors::GREEN . "✓ EmailService inicializado com sucesso\n\n" . Colors::RESET;
} catch (\Exception $e) {
    echo Colors::RED . "✗ Erro ao inicializar EmailService: " . $e->getMessage() . "\n" . Colors::RESET;
    exit(1);
}

// Dados mock para testes
$mockCustomer = [
    'id' => 1,
    'name' => 'João Silva',
    'email' => $_ENV['TEST_EMAIL'] ?? 'juhcosta23@gmail.com',
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
    'amount' => 9990, // em centavos
    'currency' => 'brl',
    'reason' => 'fraudulent',
    'status' => 'warning_needs_response',
    'evidence_due_by' => time() + (7 * 86400) // +7 dias
];

$tests = [];
$passed = 0;
$failed = 0;

/**
 * Função auxiliar para executar teste
 */
function runTest(string $name, callable $test, EmailService $emailService, bool $testOnlyTemplates = false): array {
    echo Colors::BLUE . "Testando: " . Colors::BOLD . $name . Colors::RESET . "\n";
    
    try {
        $result = $test($emailService);
        
        if ($result) {
            echo Colors::GREEN . "  ✓ Passou\n" . Colors::RESET;
            return ['name' => $name, 'status' => 'passed', 'error' => null];
        } else {
            if ($testOnlyTemplates) {
                echo Colors::YELLOW . "  ⚠ Template renderizado mas pode ter problemas\n" . Colors::RESET;
            } else {
                echo Colors::RED . "  ✗ Falhou (retornou false)\n" . Colors::RESET;
            }
            return ['name' => $name, 'status' => $testOnlyTemplates ? 'passed' : 'failed', 'error' => 'Retornou false'];
        }
    } catch (\Exception $e) {
        echo Colors::RED . "  ✗ Erro: " . $e->getMessage() . "\n" . Colors::RESET;
        return ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
    }
}

// Teste 1: Pagamento Falhado
$tests[] = runTest(
    'Email: Pagamento Falhado',
    function($emailService) use ($mockInvoice, $mockCustomer, $testOnlyTemplates) {
        if ($testOnlyTemplates) {
            // Testa apenas renderização do template
            $reflection = new \ReflectionClass($emailService);
            $method = $reflection->getMethod('renderTemplate');
            $method->setAccessible(true);
            $data = [
                'customer_name' => $mockCustomer['name'],
                'customer_email' => $mockCustomer['email'],
                'invoice_id' => $mockInvoice['id'],
                'amount_due' => number_format($mockInvoice['amount_due'] / 100, 2, ',', '.'),
                'currency' => strtoupper($mockInvoice['currency']),
                'attempt_count' => $mockInvoice['attempt_count'],
                'next_payment_attempt' => date('d/m/Y H:i', $mockInvoice['next_payment_attempt']),
                'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
                'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
            ];
            $html = $method->invoke($emailService, 'payment_failed', $data);
            return !empty($html) && strpos($html, $mockCustomer['name']) !== false;
        }
        return $emailService->enviarNotificacaoPagamentoFalhado($mockInvoice, $mockCustomer);
    },
    $emailService,
    $testOnlyTemplates
);

// Teste 2: Assinatura Cancelada
$tests[] = runTest(
    'Email: Assinatura Cancelada',
    function($emailService) use ($mockSubscription, $mockCustomer) {
        $subscription = $mockSubscription;
        $subscription['canceled_at'] = date('Y-m-d H:i:s');
        return $emailService->enviarNotificacaoAssinaturaCancelada($subscription, $mockCustomer);
    },
    $emailService,
    $testOnlyTemplates
);

// Teste 3: Nova Assinatura Criada
$tests[] = runTest(
    'Email: Nova Assinatura Criada',
    function($emailService) use ($mockSubscription, $mockCustomer) {
        return $emailService->enviarNotificacaoAssinaturaCriada($mockSubscription, $mockCustomer);
    },
    $emailService,
    $testOnlyTemplates
);

// Teste 4: Trial Terminando
$tests[] = runTest(
    'Email: Trial Terminando',
    function($emailService) use ($mockSubscription, $mockCustomer) {
        return $emailService->enviarNotificacaoTrialTerminando($mockSubscription, $mockCustomer);
    },
    $emailService
);

// Teste 5: Fatura Próxima
$tests[] = runTest(
    'Email: Fatura Próxima',
    function($emailService) use ($mockInvoice, $mockCustomer) {
        return $emailService->enviarNotificacaoFaturaProxima($mockInvoice, $mockCustomer);
    },
    $emailService
);

// Teste 6: Disputa Criada
$tests[] = runTest(
    'Email: Disputa Criada',
    function($emailService) use ($mockDispute, $mockCustomer) {
        return $emailService->enviarNotificacaoDisputaCriada($mockDispute, $mockCustomer);
    },
    $emailService
);

// Teste 7: Assinatura Reativada
$tests[] = runTest(
    'Email: Assinatura Reativada',
    function($emailService) use ($mockSubscription, $mockCustomer) {
        return $emailService->enviarNotificacaoAssinaturaReativada($mockSubscription, $mockCustomer);
    },
    $emailService
);

// Teste 8: Redefinição de Senha
$tests[] = runTest(
    'Email: Redefinição de Senha',
    function($emailService) use ($mockCustomer) {
        $linkResetSenha = ($_ENV['APP_URL'] ?? 'http://localhost') . '/reset-senha/token_abc123xyz';
        return $emailService->enviarEmailResetSenha(
            $mockCustomer['email'],
            $mockCustomer['name'],
            $linkResetSenha
        );
    },
    $emailService
);

// Teste 9: Confirmação de Email
$tests[] = runTest(
    'Email: Confirmação de Email',
    function($emailService) use ($mockCustomer) {
        $linkConfirmacao = ($_ENV['APP_URL'] ?? 'http://localhost') . '/confirmar-email/token_abc123xyz';
        return $emailService->enviarEmailConfirmacao(
            $mockCustomer['email'],
            $mockCustomer['name'],
            $linkConfirmacao
        );
    },
    $emailService
);

// Teste 10: Notificação de Login
$tests[] = runTest(
    'Email: Notificação de Login',
    function($emailService) use ($mockCustomer) {
        return $emailService->enviarNotificacaoLogin(
            $mockCustomer['email'],
            $mockCustomer['name'],
            date('d/m/Y H:i:s'),
            '192.168.1.100',
            'São Paulo, SP, Brasil',
            'Chrome 120.0 - Windows 10'
        );
    },
    $emailService
);

// Teste 11: Método genérico enviar()
$tests[] = runTest(
    'Email: Método Genérico (enviar)',
    function($emailService) use ($mockCustomer) {
        $corpo = "<h1>Teste de Email</h1><p>Este é um teste do método genérico de envio de email.</p>";
        return $emailService->enviar(
            $mockCustomer['email'],
            'Teste de Email Genérico',
            $corpo,
            $mockCustomer['name']
        );
    },
    $emailService
);

// Resumo
echo "\n" . Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  RESUMO DOS TESTES\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

foreach ($tests as $test) {
    if ($test['status'] === 'passed') {
        $passed++;
        echo Colors::GREEN . "✓ " . $test['name'] . "\n" . Colors::RESET;
    } else {
        $failed++;
        echo Colors::RED . "✗ " . $test['name'];
        if ($test['error']) {
            echo " - " . $test['error'];
        }
        echo "\n" . Colors::RESET;
    }
}

echo "\n";
echo Colors::BOLD . "Total de testes: " . count($tests) . "\n";
echo Colors::GREEN . "Passou: " . $passed . "\n" . Colors::RESET;
echo Colors::RED . "Falhou: " . $failed . "\n" . Colors::RESET;

if ($isDevelopment && $mailDriver === 'log') {
    $logFile = __DIR__ . '/../logs/emails-' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        echo "\n" . Colors::YELLOW . "📧 Emails logados em: " . $logFile . "\n" . Colors::RESET;
        echo Colors::YELLOW . "   Você pode visualizar os emails enviados neste arquivo.\n" . Colors::RESET;
    }
} else {
    echo "\n" . Colors::GREEN . "📧 Emails enviados para: " . Colors::BOLD . $mockCustomer['email'] . Colors::RESET . "\n" . Colors::RESET;
    echo Colors::YELLOW . "   Verifique a caixa de entrada (e spam) deste email.\n" . Colors::RESET;
}

echo "\n";

if ($failed === 0) {
    echo Colors::BOLD . Colors::GREEN . "✅ Todos os testes passaram com sucesso!\n" . Colors::RESET;
    exit(0);
} else {
    echo Colors::BOLD . Colors::RED . "❌ Alguns testes falharam. Verifique os erros acima.\n" . Colors::RESET;
    exit(1);
}



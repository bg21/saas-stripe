<?php

/**
 * Script para gerar previews HTML de todos os templates de email
 * Gera arquivos HTML na pasta previews/ para visualização no navegador
 * 
 * Uso: php scripts/preview_emails.php
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
echo "  GERADOR DE PREVIEWS DE EMAIL\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

// Cria diretório de previews
$previewsDir = __DIR__ . '/../previews/emails';
if (!is_dir($previewsDir)) {
    mkdir($previewsDir, 0755, true);
}

echo Colors::GREEN . "✓ Diretório de previews: {$previewsDir}\n\n" . Colors::RESET;

// Inicializa EmailService
try {
    $emailService = new EmailService(false);
} catch (\Exception $e) {
    echo Colors::RED . "✗ Erro ao inicializar EmailService: " . $e->getMessage() . "\n" . Colors::RESET;
    exit(1);
}

// Dados mock
$mockCustomer = [
    'name' => 'João Silva',
    'email' => $_ENV['TEST_EMAIL'] ?? 'juhcosta23@gmail.com'
];

$mockSubscription = [
    'stripe_subscription_id' => 'sub_test_1234567890',
    'plan_id' => 'Plano Premium',
    'amount' => 99.90,
    'currency' => 'BRL',
    'current_period_end' => date('Y-m-d H:i:s', strtotime('+30 days')),
    'trial_end' => date('Y-m-d H:i:s', strtotime('+7 days')),
    'canceled_at' => date('Y-m-d H:i:s')
];

$mockInvoice = [
    'id' => 'in_test_1234567890',
    'amount_due' => 9990,
    'currency' => 'brl',
    'attempt_count' => 2,
    'next_payment_attempt' => time() + 86400,
    'due_date' => time() + 86400,
    'period_end' => time() + 86400
];

$mockDispute = [
    'id' => 'dp_test_1234567890',
    'amount' => 9990,
    'currency' => 'brl',
    'reason' => 'fraudulent',
    'status' => 'warning_needs_response',
    'evidence_due_by' => time() + (7 * 86400)
];

$templates = [
    'payment_failed' => [
        'title' => 'Pagamento Falhado',
        'data' => [
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
        ]
    ],
    'subscription_canceled' => [
        'title' => 'Assinatura Cancelada',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'subscription_id' => $mockSubscription['stripe_subscription_id'],
            'canceled_at' => date('d/m/Y H:i', strtotime($mockSubscription['canceled_at'])),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'subscription_created' => [
        'title' => 'Nova Assinatura Criada',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'subscription_id' => $mockSubscription['stripe_subscription_id'],
            'plan_name' => $mockSubscription['plan_id'],
            'amount' => number_format($mockSubscription['amount'], 2, ',', '.'),
            'currency' => $mockSubscription['currency'],
            'current_period_end' => date('d/m/Y', strtotime($mockSubscription['current_period_end'])),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'trial_ending' => [
        'title' => 'Trial Terminando',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'subscription_id' => $mockSubscription['stripe_subscription_id'],
            'trial_end' => date('d/m/Y H:i', strtotime($mockSubscription['trial_end'])),
            'days_remaining' => ceil((strtotime($mockSubscription['trial_end']) - time()) / 86400),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'invoice_upcoming' => [
        'title' => 'Fatura Próxima',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'invoice_id' => $mockInvoice['id'],
            'amount_due' => number_format($mockInvoice['amount_due'] / 100, 2, ',', '.'),
            'currency' => strtoupper($mockInvoice['currency']),
            'due_date' => date('d/m/Y', $mockInvoice['due_date']),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'dispute_created' => [
        'title' => 'Disputa Criada',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'dispute_id' => $mockDispute['id'],
            'amount' => number_format($mockDispute['amount'] / 100, 2, ',', '.'),
            'currency' => strtoupper($mockDispute['currency']),
            'reason' => $mockDispute['reason'],
            'status' => $mockDispute['status'],
            'evidence_due_by' => date('d/m/Y H:i', $mockDispute['evidence_due_by']),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'subscription_reactivated' => [
        'title' => 'Assinatura Reativada',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'subscription_id' => $mockSubscription['stripe_subscription_id'],
            'plan_name' => $mockSubscription['plan_id'],
            'current_period_end' => date('d/m/Y', strtotime($mockSubscription['current_period_end'])),
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'password_reset' => [
        'title' => 'Redefinição de Senha',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'link_reset_senha' => ($_ENV['APP_URL'] ?? 'http://localhost') . '/reset-senha/token_abc123xyz',
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'email_confirmation' => [
        'title' => 'Confirmação de Email',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'link_confirmacao' => ($_ENV['APP_URL'] ?? 'http://localhost') . '/confirmar-email/token_abc123xyz',
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ],
    'login_notification' => [
        'title' => 'Notificação de Login',
        'data' => [
            'customer_name' => $mockCustomer['name'],
            'customer_email' => $mockCustomer['email'],
            'data_hora' => date('d/m/Y H:i:s'),
            'ip' => '192.168.1.100',
            'localizacao' => 'São Paulo, SP, Brasil',
            'dispositivo' => 'Chrome 120.0 - Windows 10',
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
            'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
        ]
    ]
];

$indexContent = "<!DOCTYPE html>\n<html lang='pt-BR'>\n<head>\n<meta charset='UTF-8'>\n<title>Previews de Email</title>\n<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}h1{color:#333;}ul{list-style:none;padding:0;}li{margin:10px 0;}a{display:inline-block;padding:10px 20px;background:#3498db;color:white;text-decoration:none;border-radius:5px;}a:hover{background:#2980b9;}</style>\n</head>\n<body>\n<h1>Previews de Templates de Email</h1>\n<ul>\n";

$generated = 0;
$failed = 0;

foreach ($templates as $templateName => $templateInfo) {
    echo Colors::BLUE . "Gerando preview: " . Colors::BOLD . $templateInfo['title'] . Colors::RESET . "\n";
    
    try {
        $reflection = new \ReflectionClass($emailService);
        $method = $reflection->getMethod('renderTemplate');
        $method->setAccessible(true);
        $html = $method->invoke($emailService, $templateName, $templateInfo['data']);
        
        $filename = $previewsDir . '/' . $templateName . '.html';
        file_put_contents($filename, $html);
        
        echo Colors::GREEN . "  ✓ Gerado: {$filename}\n" . Colors::RESET;
        $indexContent .= "<li><a href='{$templateName}.html' target='_blank'>{$templateInfo['title']}</a></li>\n";
        $generated++;
    } catch (\Exception $e) {
        echo Colors::RED . "  ✗ Erro: " . $e->getMessage() . "\n" . Colors::RESET;
        $failed++;
    }
}

$indexContent .= "</ul>\n</body>\n</html>";

// Salva índice
file_put_contents($previewsDir . '/index.html', $indexContent);
echo Colors::GREEN . "\n✓ Índice gerado: {$previewsDir}/index.html\n" . Colors::RESET;

echo "\n" . Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  RESUMO\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

echo Colors::GREEN . "Gerados: {$generated}\n" . Colors::RESET;
if ($failed > 0) {
    echo Colors::RED . "Falhou: {$failed}\n" . Colors::RESET;
}

echo "\n" . Colors::YELLOW . "💡 Abra o arquivo previews/emails/index.html no navegador para visualizar os emails.\n" . Colors::RESET;
echo "\n";


<?php

/**
 * Script de teste APENAS para renderização de templates de email
 * Não tenta enviar emails, apenas verifica se os templates são renderizados corretamente
 * 
 * Uso: php scripts/test_emails_templates_only.php
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
echo "  TESTE DE TEMPLATES DE EMAIL (Apenas Renderização)\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

echo Colors::YELLOW . "📧 Modo: Apenas renderização de templates (sem envio de email)\n\n" . Colors::RESET;

// Inicializa EmailService
try {
    $emailService = new EmailService(false); // Sem debug para não poluir output
    echo Colors::GREEN . "✓ EmailService inicializado\n\n" . Colors::RESET;
} catch (\Exception $e) {
    echo Colors::RED . "✗ Erro ao inicializar EmailService: " . $e->getMessage() . "\n" . Colors::RESET;
    exit(1);
}

// Dados mock para testes
$mockCustomer = [
    'name' => 'João Silva',
    'email' => $_ENV['TEST_EMAIL'] ?? 'juhcosta23@gmail.com'
];

$mockSubscription = [
    'stripe_subscription_id' => 'sub_test_1234567890',
    'plan_id' => 'plan_premium',
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
    'attempt_count' => 1,
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

$templatesPath = __DIR__ . '/../App/Templates/Email';
$templates = [
    'payment_failed' => 'Pagamento Falhado',
    'subscription_canceled' => 'Assinatura Cancelada',
    'subscription_created' => 'Nova Assinatura Criada',
    'trial_ending' => 'Trial Terminando',
    'invoice_upcoming' => 'Fatura Próxima',
    'dispute_created' => 'Disputa Criada',
    'subscription_reactivated' => 'Assinatura Reativada'
];

$passed = 0;
$failed = 0;

echo Colors::BLUE . "Verificando existência dos arquivos de template...\n\n" . Colors::RESET;

// Testa existência e renderização de cada template
foreach ($templates as $templateName => $templateLabel) {
    $templateFile = $templatesPath . '/' . $templateName . '.html';
    
    echo Colors::BLUE . "Testando: " . Colors::BOLD . $templateLabel . Colors::RESET . " ({$templateName}.html)\n";
    
    // Verifica se arquivo existe
    if (!file_exists($templateFile)) {
        echo Colors::RED . "  ✗ Arquivo não encontrado: {$templateFile}\n" . Colors::RESET;
        $failed++;
        continue;
    }
    
    echo Colors::GREEN . "  ✓ Arquivo existe\n" . Colors::RESET;
    
    // Prepara dados para o template
    $data = [
        'customer_name' => $mockCustomer['name'],
        'customer_email' => $mockCustomer['email'],
        'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
        'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com',
        'app_name' => $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'
    ];
    
    // Adiciona dados específicos por template
    switch ($templateName) {
        case 'payment_failed':
            $data['invoice_id'] = $mockInvoice['id'];
            $data['amount_due'] = number_format($mockInvoice['amount_due'] / 100, 2, ',', '.');
            $data['currency'] = strtoupper($mockInvoice['currency']);
            $data['attempt_count'] = $mockInvoice['attempt_count'];
            $data['next_payment_attempt'] = date('d/m/Y H:i', $mockInvoice['next_payment_attempt']);
            break;
            
        case 'subscription_canceled':
            $data['subscription_id'] = $mockSubscription['stripe_subscription_id'];
            $data['canceled_at'] = date('d/m/Y H:i', strtotime($mockSubscription['canceled_at']));
            break;
            
        case 'subscription_created':
            $data['subscription_id'] = $mockSubscription['stripe_subscription_id'];
            $data['plan_name'] = $mockSubscription['plan_id'];
            $data['amount'] = number_format($mockSubscription['amount'], 2, ',', '.');
            $data['currency'] = $mockSubscription['currency'];
            $data['current_period_end'] = date('d/m/Y', strtotime($mockSubscription['current_period_end']));
            break;
            
        case 'trial_ending':
            $data['subscription_id'] = $mockSubscription['stripe_subscription_id'];
            $data['trial_end'] = date('d/m/Y H:i', strtotime($mockSubscription['trial_end']));
            $data['days_remaining'] = ceil((strtotime($mockSubscription['trial_end']) - time()) / 86400);
            break;
            
        case 'invoice_upcoming':
            $data['invoice_id'] = $mockInvoice['id'];
            $data['amount_due'] = number_format($mockInvoice['amount_due'] / 100, 2, ',', '.');
            $data['currency'] = strtoupper($mockInvoice['currency']);
            $data['due_date'] = date('d/m/Y', $mockInvoice['due_date']);
            break;
            
        case 'dispute_created':
            $data['dispute_id'] = $mockDispute['id'];
            $data['amount'] = number_format($mockDispute['amount'] / 100, 2, ',', '.');
            $data['currency'] = strtoupper($mockDispute['currency']);
            $data['reason'] = $mockDispute['reason'];
            $data['status'] = $mockDispute['status'];
            $data['evidence_due_by'] = date('d/m/Y H:i', $mockDispute['evidence_due_by']);
            break;
            
        case 'subscription_reactivated':
            $data['subscription_id'] = $mockSubscription['stripe_subscription_id'];
            $data['plan_name'] = $mockSubscription['plan_id'];
            $data['current_period_end'] = date('d/m/Y', strtotime($mockSubscription['current_period_end']));
            break;
    }
    
    // Tenta renderizar o template
    try {
        $reflection = new \ReflectionClass($emailService);
        $method = $reflection->getMethod('renderTemplate');
        $method->setAccessible(true);
        $html = $method->invoke($emailService, $templateName, $data);
        
        if (empty($html)) {
            echo Colors::RED . "  ✗ Template renderizado vazio\n" . Colors::RESET;
            $failed++;
            continue;
        }
        
        // Verifica se contém dados esperados
        $checks = [
            $mockCustomer['name'] => 'Nome do cliente',
            $mockCustomer['email'] => 'Email do cliente (pode não estar visível)',
            $_ENV['APP_NAME'] ?? 'Sistema de Pagamentos' => 'Nome da aplicação'
        ];
        
        $allChecksPassed = true;
        foreach ($checks as $needle => $label) {
            if (stripos($html, $needle) === false) {
                echo Colors::YELLOW . "  ⚠ Não contém: {$label}\n" . Colors::RESET;
                // Não falha o teste, apenas avisa
            }
        }
        
        // Verifica se é HTML válido
        if (strpos($html, '<html') === false && strpos($html, '<!DOCTYPE') === false) {
            echo Colors::YELLOW . "  ⚠ Não parece ser HTML válido\n" . Colors::RESET;
        }
        
        echo Colors::GREEN . "  ✓ Template renderizado com sucesso (" . strlen($html) . " caracteres)\n" . Colors::RESET;
        $passed++;
        
    } catch (\Exception $e) {
        echo Colors::RED . "  ✗ Erro ao renderizar: " . $e->getMessage() . "\n" . Colors::RESET;
        $failed++;
    }
    
    echo "\n";
}

// Resumo
echo Colors::BOLD . Colors::BLUE . "═══════════════════════════════════════════════════════════\n";
echo "  RESUMO DOS TESTES\n";
echo "═══════════════════════════════════════════════════════════\n" . Colors::RESET;

echo Colors::BOLD . "Total de templates: " . count($templates) . "\n";
echo Colors::GREEN . "Passou: " . $passed . "\n" . Colors::RESET;
echo Colors::RED . "Falhou: " . $failed . "\n" . Colors::RESET;

echo "\n";

if ($failed === 0) {
    echo Colors::BOLD . Colors::GREEN . "✅ Todos os templates foram renderizados com sucesso!\n" . Colors::RESET;
    echo Colors::YELLOW . "💡 Para testar envio real, configure as credenciais SMTP no .env e execute:\n" . Colors::RESET;
    echo Colors::YELLOW . "   php scripts/test_emails.php\n" . Colors::RESET;
    exit(0);
} else {
    echo Colors::BOLD . Colors::RED . "❌ Alguns templates falharam. Verifique os erros acima.\n" . Colors::RESET;
    exit(1);
}


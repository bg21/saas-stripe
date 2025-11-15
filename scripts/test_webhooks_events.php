<?php

/**
 * Script de teste para Webhooks - Mais Eventos
 * 
 * Testa os novos handlers de webhook implementados:
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - invoice.payment_failed
 * - invoice.upcoming
 * - customer.subscription.trial_will_end
 * - charge.dispute.created
 * - charge.refunded
 * 
 * Nota: Este script valida a estrutura dos handlers, mas não envia eventos reais do Stripe.
 * Para testar eventos reais, use o Stripe CLI: stripe listen --forward-to localhost:8080/v1/webhook
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

echo "🧪 Teste de Webhooks - Mais Eventos\n";
echo str_repeat("=", 70) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

// Contadores de testes
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Função para fazer requisições HTTP
function makeRequest(string $url, string $method = 'GET', array $data = [], ?string $token = null): array
{
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $method !== 'GET' ? json_encode($data) : null
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'code' => 0,
            'data' => ['error' => $error],
            'raw_response' => $response
        ];
    }
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true) ?? [],
        'raw_response' => $response
    ];
}

// Função para testar e registrar resultado
function testResult(string $description, int $expectedCode, int $actualCode, bool $checkSuccess = false, array $responseData = []): bool
{
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    
    $totalTests++;
    $passed = false;
    
    if ($expectedCode === $actualCode) {
        if ($checkSuccess) {
            $passed = isset($responseData['success']) && $responseData['success'] === true;
        } else {
            $passed = true;
        }
    }
    
    if ($passed) {
        $passedTests++;
        echo "{$green}✅{$reset} {$description}\n";
        echo "   HTTP Code: {$actualCode} (esperado: {$expectedCode})\n";
    } else {
        $failedTests++;
        echo "{$red}❌{$reset} {$description}\n";
        echo "   HTTP Code: {$actualCode} (esperado: {$expectedCode})\n";
        if (!empty($responseData)) {
            echo "   Resposta: " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "\n";
    
    return $passed;
}

// Verifica se o servidor está rodando
echo "🔍 Verificando se o servidor está rodando...\n";
$healthCheck = makeRequest("{$apiUrl}/health", 'GET');
if ($healthCheck['code'] === 0) {
    echo "{$red}❌ Servidor não está rodando!{$reset}\n";
    echo "   Execute: php -S localhost:8080 -t public\n\n";
    exit(1);
}
echo "{$green}✅ Servidor está rodando!{$reset}\n\n";

// ============================================================================
// TESTE 1: Verificar que endpoint de webhook existe
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: Verificar endpoint de webhook\n";
echo str_repeat("=", 70) . "\n\n";

// Tenta enviar um webhook sem signature (deve falhar, mas endpoint existe)
$response = makeRequest("{$apiUrl}/v1/webhook", 'POST', []);
$hasError = isset($response['data']['error']) && 
            (strpos(strtolower($response['data']['error']), 'signature') !== false || 
             strpos(strtolower($response['data']['error']), 'não fornecida') !== false);
testResult(
    "Endpoint de webhook existe e valida signature (sem signature retorna erro)",
    true,
    $hasError,
    false,
    $response['data']
);

// ============================================================================
// TESTE 2: Verificar eventos suportados (via código)
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 2: Verificar eventos implementados no código\n";
echo str_repeat("=", 70) . "\n\n";

$paymentServiceFile = __DIR__ . '/../App/Services/PaymentService.php';
$paymentServiceContent = file_get_contents($paymentServiceFile);

$eventsToCheck = [
    'payment_intent.succeeded' => 'handlePaymentIntentSucceeded',
    'payment_intent.payment_failed' => 'handlePaymentIntentFailed',
    'invoice.payment_failed' => 'handleInvoicePaymentFailed',
    'invoice.upcoming' => 'handleInvoiceUpcoming',
    'customer.subscription.trial_will_end' => 'handleSubscriptionTrialWillEnd',
    'charge.dispute.created' => 'handleChargeDisputeCreated',
    'charge.refunded' => 'handleChargeRefunded'
];

foreach ($eventsToCheck as $eventType => $handlerMethod) {
    $hasEvent = strpos($paymentServiceContent, "'{$eventType}'") !== false || 
                strpos($paymentServiceContent, "\"{$eventType}\"") !== false;
    $hasHandler = strpos($paymentServiceContent, "function {$handlerMethod}") !== false;
    
    if ($hasEvent && $hasHandler) {
        echo "{$green}✅{$reset} Evento '{$eventType}' implementado\n";
        echo "   Handler: {$handlerMethod}\n\n";
        $passedTests++;
    } else {
        echo "{$red}❌{$reset} Evento '{$eventType}' não encontrado\n";
        if (!$hasEvent) {
            echo "   Evento não encontrado no switch case\n";
        }
        if (!$hasHandler) {
            echo "   Handler '{$handlerMethod}' não encontrado\n";
        }
        echo "\n";
        $failedTests++;
    }
    $totalTests++;
}

// ============================================================================
// TESTE 3: Verificar integração com SubscriptionHistory
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 3: Verificar integração com SubscriptionHistory\n";
echo str_repeat("=", 70) . "\n\n";

$hasInvoicePaymentFailedHistory = strpos($paymentServiceContent, 'handleInvoicePaymentFailed') !== false &&
                                   strpos($paymentServiceContent, 'SubscriptionHistory') !== false &&
                                   strpos($paymentServiceContent, 'invoice.payment_failed') !== false;

$hasTrialWillEndHistory = strpos($paymentServiceContent, 'handleSubscriptionTrialWillEnd') !== false &&
                          strpos($paymentServiceContent, 'SubscriptionHistory') !== false &&
                          strpos($paymentServiceContent, 'customer.subscription.trial_will_end') !== false;

testResult(
    "invoice.payment_failed integrado com SubscriptionHistory",
    true,
    $hasInvoicePaymentFailedHistory,
    false,
    []
);

testResult(
    "customer.subscription.trial_will_end integrado com SubscriptionHistory",
    true,
    $hasTrialWillEndHistory,
    false,
    []
);

// ============================================================================
// TESTE 4: Verificar logs estruturados
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 4: Verificar logs estruturados nos handlers\n";
echo str_repeat("=", 70) . "\n\n";

$hasPaymentIntentSucceededLog = strpos($paymentServiceContent, 'Logger::info("Payment Intent bem-sucedido"') !== false;
$hasPaymentIntentFailedLog = strpos($paymentServiceContent, 'Logger::warning("Payment Intent falhou"') !== false;
$hasDisputeCreatedLog = strpos($paymentServiceContent, 'Logger::warning("Disputa/chargeback criada"') !== false;
$hasChargeRefundedLog = strpos($paymentServiceContent, 'Logger::info("Reembolso processado"') !== false;

testResult(
    "Log estruturado em payment_intent.succeeded",
    true,
    $hasPaymentIntentSucceededLog,
    false,
    []
);

testResult(
    "Log estruturado em payment_intent.payment_failed",
    true,
    $hasPaymentIntentFailedLog,
    false,
    []
);

testResult(
    "Log estruturado em charge.dispute.created",
    true,
    $hasDisputeCreatedLog,
    false,
    []
);

testResult(
    "Log estruturado em charge.refunded",
    true,
    $hasChargeRefundedLog,
    false,
    []
);

// ============================================================================
// TESTE 5: Verificar método getCharge no StripeService
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 5: Verificar método getCharge no StripeService\n";
echo str_repeat("=", 70) . "\n\n";

$stripeServiceFile = __DIR__ . '/../App/Services/StripeService.php';
$stripeServiceContent = file_get_contents($stripeServiceFile);

$hasGetCharge = strpos($stripeServiceContent, 'function getCharge') !== false ||
                strpos($stripeServiceContent, 'public function getCharge') !== false;

testResult(
    "Método getCharge implementado no StripeService",
    true,
    $hasGetCharge,
    false,
    []
);

// ============================================================================
// TESTE 6: Verificar estrutura completa do switch case
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 6: Verificar estrutura completa do switch case\n";
echo str_repeat("=", 70) . "\n\n";

$allEvents = [
    'checkout.session.completed',
    'payment_intent.succeeded',
    'payment_intent.payment_failed',
    'invoice.paid',
    'invoice.payment_failed',
    'invoice.upcoming',
    'customer.subscription.updated',
    'customer.subscription.deleted',
    'customer.subscription.trial_will_end',
    'charge.dispute.created',
    'charge.refunded'
];

$eventsFound = 0;
foreach ($allEvents as $event) {
    if (strpos($paymentServiceContent, "'{$event}'") !== false || 
        strpos($paymentServiceContent, "\"{$event}\"") !== false) {
        $eventsFound++;
    }
}

$expectedEvents = count($allEvents);
testResult(
    "Todos os eventos estão no switch case ({$eventsFound}/{$expectedEvents})",
    $expectedEvents,
    $eventsFound,
    false,
    []
);

// ============================================================================
// RESUMO DOS TESTES
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 70) . "\n\n";

echo "Total de testes: {$totalTests}\n";
echo "{$green}Testes passados: {$passedTests}{$reset}\n";
echo "{$red}Testes falhados: {$failedTests}{$reset}\n\n";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "Taxa de sucesso: {$successRate}%\n\n";

if ($failedTests > 0) {
    echo "{$red}❌ Alguns testes falharam! Verifique os logs acima.{$reset}\n\n";
    echo "{$yellow}💡 Dica: Para testar eventos reais do Stripe, use:{$reset}\n";
    echo "   stripe listen --forward-to localhost:8080/v1/webhook\n";
    echo "   stripe trigger payment_intent.succeeded\n";
    echo "   stripe trigger payment_intent.payment_failed\n";
    echo "   stripe trigger invoice.payment_failed\n";
    echo "   stripe trigger invoice.upcoming\n";
    echo "   stripe trigger customer.subscription.trial_will_end\n";
    echo "   stripe trigger charge.dispute.created\n";
    echo "   stripe trigger charge.refunded\n\n";
    exit(1);
} else {
    echo "{$green}✅ Todos os testes passaram!{$reset}\n\n";
    echo "{$blue}ℹ️  Para testar eventos reais do Stripe:{$reset}\n";
    echo "   1. Instale o Stripe CLI: https://stripe.com/docs/stripe-cli\n";
    echo "   2. Execute: stripe listen --forward-to localhost:8080/v1/webhook\n";
    echo "   3. Em outro terminal, dispare eventos:\n";
    echo "      stripe trigger payment_intent.succeeded\n";
    echo "      stripe trigger payment_intent.payment_failed\n";
    echo "      stripe trigger invoice.payment_failed\n";
    echo "      stripe trigger invoice.upcoming\n";
    echo "      stripe trigger customer.subscription.trial_will_end\n";
    echo "      stripe trigger charge.dispute.created\n";
    echo "      stripe trigger charge.refunded\n\n";
    exit(0);
}


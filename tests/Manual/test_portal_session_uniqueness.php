<?php

/**
 * Teste para demonstrar que cada sessão do Billing Portal é única
 * e não pode ser reutilizada
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE: UNICIDADE DAS SESSÕES DO BILLING PORTAL             ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;

try {
    $stripeSecret = Config::get('STRIPE_SECRET');
    if (empty($stripeSecret)) {
        throw new Exception("STRIPE_SECRET não configurado no .env");
    }
    
    $stripe = new \Stripe\StripeClient($stripeSecret);
    echo "✅ Stripe Client inicializado" . PHP_EOL . PHP_EOL;

    // Customer de teste
    $customerId = 'cus_TPgbrcwldjwD6k';
    $returnUrl = 'https://example.com/return';

    echo "📋 Informações do Teste:" . PHP_EOL;
    echo "   Customer ID: {$customerId}" . PHP_EOL;
    echo "   Return URL: {$returnUrl}" . PHP_EOL . PHP_EOL;

    // ============================================
    // TESTE 1: Criar primeira sessão
    // ============================================
    echo "🔐 TESTE 1: Criando primeira sessão..." . PHP_EOL;
    
    $session1 = $stripe->billingPortal->sessions->create([
        'customer' => $customerId,
        'return_url' => $returnUrl
    ]);
    
    echo "   ✅ Sessão 1 criada!" . PHP_EOL;
    echo "   Session ID: {$session1->id}" . PHP_EOL;
    echo "   URL: {$session1->url}" . PHP_EOL;
    echo "   Created: " . date('Y-m-d H:i:s', $session1->created) . PHP_EOL . PHP_EOL;

    // Aguarda 2 segundos
    sleep(2);

    // ============================================
    // TESTE 2: Criar segunda sessão (mesmo customer)
    // ============================================
    echo "🔐 TESTE 2: Criando segunda sessão (mesmo customer)..." . PHP_EOL;
    
    $session2 = $stripe->billingPortal->sessions->create([
        'customer' => $customerId,
        'return_url' => $returnUrl
    ]);
    
    echo "   ✅ Sessão 2 criada!" . PHP_EOL;
    echo "   Session ID: {$session2->id}" . PHP_EOL;
    echo "   URL: {$session2->url}" . PHP_EOL;
    echo "   Created: " . date('Y-m-d H:i:s', $session2->created) . PHP_EOL . PHP_EOL;

    // ============================================
    // ANÁLISE
    // ============================================
    echo "📊 ANÁLISE:" . PHP_EOL;
    echo "   Session IDs são diferentes? " . ($session1->id !== $session2->id ? '✅ SIM' : '❌ NÃO') . PHP_EOL;
    echo "   URLs são diferentes? " . ($session1->url !== $session2->url ? '✅ SIM' : '❌ NÃO') . PHP_EOL;
    echo "   Timestamps são diferentes? " . ($session1->created !== $session2->created ? '✅ SIM' : '❌ NÃO') . PHP_EOL . PHP_EOL;

    // ============================================
    // CONCLUSÃO
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║                    ✅ CONCLUSÃO                                ║" . PHP_EOL;
    echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;

    if ($session1->id !== $session2->id && $session1->url !== $session2->url) {
        echo "✅ CONFIRMADO: Cada sessão é ÚNICA e GERADA NOVA a cada vez!" . PHP_EOL . PHP_EOL;
        
        echo "📝 IMPLICAÇÕES:" . PHP_EOL;
        echo "   ❌ NÃO é possível salvar a URL no banco de dados" . PHP_EOL;
        echo "   ❌ NÃO é possível reutilizar a mesma sessão" . PHP_EOL;
        echo "   ✅ É necessário gerar uma NOVA sessão sempre que o cliente precisar acessar" . PHP_EOL;
        echo "   ✅ A sessão expira após período de inatividade (segurança)" . PHP_EOL . PHP_EOL;
        
        echo "💡 RECOMENDAÇÃO:" . PHP_EOL;
        echo "   • Criar endpoint que gera sessão sob demanda" . PHP_EOL;
        echo "   • Redirecionar cliente imediatamente após criar sessão" . PHP_EOL;
        echo "   • NÃO armazenar URLs de sessão no banco de dados" . PHP_EOL;
        echo "   • Cada acesso ao portal requer nova chamada à API" . PHP_EOL . PHP_EOL;
    } else {
        echo "❌ ERRO: As sessões deveriam ser diferentes!" . PHP_EOL;
    }

} catch (\Stripe\Exception\ApiErrorException $e) {
    echo PHP_EOL . "❌ ERRO DO STRIPE:" . PHP_EOL;
    echo "   Tipo: " . get_class($e) . PHP_EOL;
    echo "   Mensagem: " . $e->getMessage() . PHP_EOL;
    if ($e->getStripeCode()) {
        echo "   Código: " . $e->getStripeCode() . PHP_EOL;
    }
    exit(1);
} catch (Exception $e) {
    echo PHP_EOL . "❌ ERRO:" . PHP_EOL;
    echo "   " . $e->getMessage() . PHP_EOL;
    exit(1);
}


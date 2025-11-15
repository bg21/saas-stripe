<?php

/**
 * Exemplo de uso do PaymentsClient no seu SaaS
 * 
 * Este arquivo mostra como usar o cliente PHP para integrar
 * o sistema de pagamentos no seu SaaS
 */

require_once __DIR__ . '/PaymentsClient.php';

use PaymentsSDK\PaymentsClient;

// Configuração
$apiBaseUrl = 'https://pagamentos.seudominio.com'; // URL do sistema de pagamentos
$apiKey = 'sua_api_key_aqui'; // API Key do tenant criado

// Inicializa cliente
$payments = new PaymentsClient($apiBaseUrl, $apiKey);

try {
    // ============================================
    // EXEMPLO 1: Criar Cliente
    // ============================================
    echo "1. Criando cliente...\n";
    $customer = $payments->createCustomer(
        'usuario@example.com',
        'João Silva',
        ['user_id' => 123, 'saas_id' => 'meu_saas']
    );
    
    echo "✅ Cliente criado: ID {$customer['data']['id']}\n";
    $customerId = $customer['data']['id'];
    
    // ============================================
    // EXEMPLO 2: Criar Checkout Session
    // ============================================
    echo "\n2. Criando checkout session...\n";
    $checkout = $payments->createCheckout(
        $customerId,
        'price_1234567890', // Price ID do Stripe
        'https://meu-saas.com/success',
        'https://meu-saas.com/cancel',
        ['user_id' => 123]
    );
    
    echo "✅ Checkout criado: {$checkout['data']['url']}\n";
    echo "   Redirecione o usuário para esta URL\n";
    
    // ============================================
    // EXEMPLO 3: Listar Assinaturas
    // ============================================
    echo "\n3. Listando assinaturas...\n";
    $subscriptions = $payments->listSubscriptions();
    
    echo "✅ Total de assinaturas: {$subscriptions['count']}\n";
    
    foreach ($subscriptions['data'] as $sub) {
        echo "   - ID: {$sub['id']}, Status: {$sub['status']}\n";
    }
    
    // ============================================
    // EXEMPLO 4: Verificar Assinatura Ativa
    // ============================================
    echo "\n4. Verificando assinaturas ativas...\n";
    $hasActive = false;
    
    foreach ($subscriptions['data'] as $sub) {
        if ($sub['status'] === 'active') {
            $hasActive = true;
            echo "✅ Usuário tem assinatura ativa (ID: {$sub['id']})\n";
            
            // Obter histórico
            $history = $payments->getSubscriptionHistory($sub['id']);
            echo "   Histórico: {$history['pagination']['total']} registros\n";
            break;
        }
    }
    
    if (!$hasActive) {
        echo "⚠️  Usuário não tem assinatura ativa\n";
    }
    
    // ============================================
    // EXEMPLO 5: Obter Estatísticas
    // ============================================
    echo "\n5. Obtendo estatísticas...\n";
    $stats = $payments->getStats('month');
    
    echo "✅ Estatísticas do mês:\n";
    echo "   Customers: {$stats['data']['customers']['total']}\n";
    echo "   Assinaturas ativas: {$stats['data']['subscriptions']['active']}\n";
    echo "   MRR: R$ {$stats['data']['revenue']['mrr']}\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
}


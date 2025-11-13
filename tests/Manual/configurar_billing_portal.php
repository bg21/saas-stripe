<?php

/**
 * Script para Configurar Billing Portal no Stripe
 * 
 * Este script cria/atualiza a configuração padrão do Billing Portal
 * no Stripe via API, permitindo que o portal seja usado sem
 * configuração manual no Dashboard.
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   CONFIGURAÇÃO DO BILLING PORTAL NO STRIPE                  ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;

try {
    $stripeSecret = Config::get('STRIPE_SECRET');
    if (empty($stripeSecret)) {
        throw new Exception("STRIPE_SECRET não configurado no .env");
    }
    
    $stripe = new \Stripe\StripeClient($stripeSecret);
    echo "✅ Stripe Client inicializado" . PHP_EOL . PHP_EOL;

    // ============================================
    // PASSO 1: Listar Configurações Existentes
    // ============================================
    echo "🔍 PASSO 1: Verificando configurações existentes..." . PHP_EOL;
    
    try {
        $configurations = $stripe->billingPortal->configurations->all(['limit' => 10]);
        
        if (!empty($configurations->data)) {
            echo "   ✅ Encontradas " . count($configurations->data) . " configuração(ões)" . PHP_EOL;
            foreach ($configurations->data as $config) {
                echo "   - ID: {$config->id}" . PHP_EOL;
                echo "     Ativo: " . ($config->active ? 'Sim' : 'Não') . PHP_EOL;
                echo "     É padrão: " . ($config->is_default ? 'Sim' : 'Não') . PHP_EOL;
            }
            echo PHP_EOL;
            
            // Verifica se já existe uma configuração padrão
            $defaultConfig = null;
            foreach ($configurations->data as $config) {
                if ($config->is_default) {
                    $defaultConfig = $config;
                    break;
                }
            }
            
            if ($defaultConfig) {
                echo "   ✅ Já existe uma configuração padrão!" . PHP_EOL;
                echo "   Config ID: {$defaultConfig->id}" . PHP_EOL;
                echo "   Você pode usar o Billing Portal agora!" . PHP_EOL . PHP_EOL;
                exit(0);
            }
        } else {
            echo "   ℹ️  Nenhuma configuração encontrada" . PHP_EOL . PHP_EOL;
        }
    } catch (\Exception $e) {
        echo "   ⚠️  Erro ao listar configurações: " . $e->getMessage() . PHP_EOL;
        echo "   Vamos tentar criar uma nova..." . PHP_EOL . PHP_EOL;
    }

    // ============================================
    // PASSO 2: Criar Nova Configuração
    // ============================================
    echo "🔧 PASSO 2: Criando configuração do Billing Portal..." . PHP_EOL;
    
    try {
        $configuration = $stripe->billingPortal->configurations->create([
            'business_profile' => [
                'headline' => 'Gerenciar sua assinatura e método de pagamento'
            ],
            'features' => [
                'customer_update' => [
                    'enabled' => true,
                    'allowed_updates' => ['email', 'address', 'phone', 'tax_id']
                ],
                'payment_method_update' => [
                    'enabled' => true
                ],
                'subscription_cancel' => [
                    'enabled' => true,
                    'cancellation_reason' => [
                        'enabled' => true,
                        'options' => [
                            'too_expensive',
                            'missing_features',
                            'switched_service',
                            'too_complex',
                            'low_quality',
                            'other'
                        ]
                    ],
                    'mode' => 'at_period_end',
                    'proration_behavior' => 'none'
                ],
                'invoice_history' => [
                    'enabled' => true
                ]
            ]
        ]);
        
        echo "   ✅ Configuração criada com sucesso!" . PHP_EOL;
        echo "   Config ID: {$configuration->id}" . PHP_EOL;
        echo "   Ativo: " . ($configuration->active ? 'Sim' : 'Não') . PHP_EOL;
        echo "   É padrão: " . ($configuration->is_default ? 'Sim' : 'Não') . PHP_EOL . PHP_EOL;
        
        // Se não for padrão, tenta definir como padrão
        if (!$configuration->is_default) {
            echo "   🔧 Definindo como configuração padrão..." . PHP_EOL;
            try {
                $updatedConfig = $stripe->billingPortal->configurations->update($configuration->id, [
                    'active' => true
                ]);
                echo "   ✅ Configuração atualizada!" . PHP_EOL;
            } catch (\Exception $e) {
                echo "   ⚠️  Não foi possível definir como padrão: " . $e->getMessage() . PHP_EOL;
                echo "   ℹ️  Você pode definir manualmente no Dashboard" . PHP_EOL;
            }
        }
        
        echo PHP_EOL;
        echo "✅ BILLING PORTAL CONFIGURADO COM SUCESSO!" . PHP_EOL;
        echo "   Agora você pode usar o endpoint POST /v1/billing-portal" . PHP_EOL;
        echo "   Execute o teste novamente: php tests/Manual/test_billing_portal.php" . PHP_EOL . PHP_EOL;
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo "   ❌ Erro ao criar configuração: " . $e->getMessage() . PHP_EOL;
        
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ℹ️  Uma configuração padrão já existe" . PHP_EOL;
            echo "   Você pode usar o Billing Portal agora!" . PHP_EOL;
        } else {
            throw $e;
        }
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


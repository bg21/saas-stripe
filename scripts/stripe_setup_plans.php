<?php
/**
 * Script para configurar planos no Stripe
 * 
 * Este script:
 * 1. Lista produtos e preços existentes
 * 2. Deleta/desativa produtos e preços antigos
 * 3. Cria os novos produtos e preços conforme documentação
 * 
 * USO: php scripts/stripe_setup_plans.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Services\StripeService;
use App\Services\Logger;

echo "🚀 Configuração de Planos no Stripe\n";
echo str_repeat("=", 70) . "\n\n";

// Verifica se está em modo de teste
$stripeKey = Config::get('STRIPE_SECRET');
$isTestMode = strpos($stripeKey, 'sk_test_') !== false;

if ($isTestMode) {
    echo "⚠️  MODO DE TESTE DETECTADO (sk_test_)\n";
    echo "   Os planos serão criados no ambiente de teste do Stripe\n\n";
} else {
    echo "🔴 MODO DE PRODUÇÃO DETECTADO (sk_live_)\n";
    echo "   ATENÇÃO: Os planos serão criados no ambiente de PRODUÇÃO!\n\n";
    
    echo "Deseja continuar? (digite 'SIM' para confirmar): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtoupper($line) !== 'SIM') {
        echo "❌ Operação cancelada pelo usuário.\n";
        exit(1);
    }
    echo "\n";
}

try {
    $stripeService = new StripeService();
    
    // ==========================================
    // PASSO 1: Listar produtos existentes
    // ==========================================
    echo "📋 PASSO 1: Listando produtos existentes...\n";
    echo str_repeat("-", 70) . "\n";
    
    $existingProducts = $stripeService->listProducts(['limit' => 100]);
    $productsToDelete = [];
    
    if (count($existingProducts->data) > 0) {
        echo "Encontrados " . count($existingProducts->data) . " produto(s):\n\n";
        
        foreach ($existingProducts->data as $product) {
            echo "  📦 Produto: {$product->name}\n";
            echo "     ID: {$product->id}\n";
            echo "     Ativo: " . ($product->active ? 'Sim' : 'Não') . "\n";
            
            // Lista preços do produto
            $prices = $stripeService->listPrices(['product' => $product->id, 'limit' => 100]);
            echo "     Preços: " . count($prices->data) . "\n";
            
            foreach ($prices->data as $price) {
                $amount = number_format($price->unit_amount / 100, 2, ',', '.');
                $currency = strtoupper($price->currency);
                $interval = $price->recurring->interval ?? 'one-time';
                echo "       - {$price->id}: R$ {$amount} ({$currency}) - {$interval}\n";
            }
            
            $productsToDelete[] = $product;
            echo "\n";
        }
    } else {
        echo "Nenhum produto encontrado.\n\n";
    }
    
    // ==========================================
    // PASSO 2: Deletar produtos e preços antigos
    // ==========================================
    if (count($productsToDelete) > 0) {
        echo "🗑️  PASSO 2: Deletando produtos e preços antigos...\n";
        echo str_repeat("-", 70) . "\n";
        
        foreach ($productsToDelete as $product) {
            echo "Deletando produto: {$product->name} ({$product->id})...\n";
            
            // Primeiro, desativa todos os preços do produto
            try {
                $prices = $stripeService->listPrices(['product' => $product->id, 'limit' => 100]);
                foreach ($prices->data as $price) {
                    try {
                        // Tenta desativar o preço (Stripe não permite deletar preços)
                        if ($price->active) {
                            // Usa o cliente Stripe diretamente para evitar problemas com cache
                            $stripeClient = new \Stripe\StripeClient(Config::get('STRIPE_SECRET'));
                            $stripeClient->prices->update($price->id, ['active' => false]);
                            echo "  ✅ Preço desativado: {$price->id}\n";
                        } else {
                            echo "  ⏭️  Preço já estava desativado: {$price->id}\n";
                        }
                    } catch (\Exception $e) {
                        echo "  ⚠️  Não foi possível desativar preço {$price->id}: " . $e->getMessage() . "\n";
                    }
                }
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao listar preços: " . $e->getMessage() . "\n";
            }
            
            // Depois, deleta/desativa o produto
            try {
                $stripeService->deleteProduct($product->id);
                echo "  ✅ Produto deletado/desativado: {$product->id}\n\n";
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao deletar produto: " . $e->getMessage() . "\n\n";
            }
        }
        
        echo "✅ Produtos antigos processados.\n\n";
    } else {
        echo "⏭️  PASSO 2: Nenhum produto para deletar.\n\n";
    }
    
    // ==========================================
    // PASSO 3: Criar novos produtos
    // ==========================================
    echo "✨ PASSO 3: Criando novos produtos...\n";
    echo str_repeat("-", 70) . "\n";
    
    $plans = [
        'basic' => [
            'name' => 'Plano Básico - Clínica Veterinária',
            'description' => 'Ideal para clínicas pequenas. Até 3 profissionais, 100 agendamentos/mês e 1 usuário.',
            'metadata' => [
                'plan_type' => 'basic',
                'max_professionals' => '3',
                'max_appointments_per_month' => '100',
                'max_users' => '1',
                'features' => 'basic'
            ]
        ],
        'professional' => [
            'name' => 'Plano Profissional - Clínica Veterinária',
            'description' => 'Para clínicas de médio porte. Até 10 profissionais, agendamentos ilimitados e 5 usuários. Inclui relatórios avançados e histórico completo.',
            'metadata' => [
                'plan_type' => 'professional',
                'max_professionals' => '10',
                'max_appointments_per_month' => 'unlimited',
                'max_users' => '5',
                'features' => 'basic,advanced_reports,history'
            ]
        ],
        'premium' => [
            'name' => 'Plano Premium - Clínica Veterinária',
            'description' => 'Para clínicas grandes e redes. Recursos ilimitados, todos os recursos do sistema e suporte prioritário.',
            'metadata' => [
                'plan_type' => 'premium',
                'max_professionals' => 'unlimited',
                'max_appointments_per_month' => 'unlimited',
                'max_users' => 'unlimited',
                'features' => 'all'
            ]
        ]
    ];
    
    $createdProducts = [];
    
    foreach ($plans as $planKey => $planData) {
        echo "Criando produto: {$planData['name']}...\n";
        
        try {
            $product = $stripeService->createProduct([
                'name' => $planData['name'],
                'description' => $planData['description'],
                'active' => true,
                'metadata' => $planData['metadata']
            ]);
            
            $createdProducts[$planKey] = $product;
            echo "  ✅ Produto criado: {$product->id}\n\n";
        } catch (\Exception $e) {
            echo "  ❌ Erro ao criar produto: " . $e->getMessage() . "\n\n";
            exit(1);
        }
    }
    
    // ==========================================
    // PASSO 4: Criar preços
    // ==========================================
    echo "💰 PASSO 4: Criando preços...\n";
    echo str_repeat("-", 70) . "\n";
    
    // Preços (Opção 1: Conservadora - Recomendada)
    $prices = [
        'basic' => [
            'monthly' => ['amount' => 9700, 'currency' => 'brl'],      // R$ 97,00
            'yearly' => ['amount' => 97000, 'currency' => 'brl']       // R$ 970,00
        ],
        'professional' => [
            'monthly' => ['amount' => 19700, 'currency' => 'brl'],    // R$ 197,00
            'yearly' => ['amount' => 197000, 'currency' => 'brl']     // R$ 1.970,00
        ],
        'premium' => [
            'monthly' => ['amount' => 39700, 'currency' => 'brl'],    // R$ 397,00
            'yearly' => ['amount' => 397000, 'currency' => 'brl']     // R$ 3.970,00
        ]
    ];
    
    $createdPrices = [];
    
    foreach ($createdProducts as $planKey => $product) {
        foreach ($prices[$planKey] as $interval => $priceData) {
            $amountFormatted = number_format($priceData['amount'] / 100, 2, ',', '.');
            $nickname = ucfirst($planKey) . ' - ' . ucfirst($interval);
            
            echo "Criando preço: {$nickname} (R$ {$amountFormatted})...\n";
            
            try {
                $price = $stripeService->createPrice([
                    'product' => $product->id,
                    'unit_amount' => $priceData['amount'],
                    'currency' => $priceData['currency'],
                    'recurring' => [
                        'interval' => $interval === 'monthly' ? 'month' : 'year'
                    ],
                    'nickname' => $nickname,
                    'metadata' => [
                        'plan_type' => $planKey,
                        'billing_interval' => $interval
                    ]
                ]);
                
                $createdPrices[$planKey][$interval] = $price;
                echo "  ✅ Preço criado: {$price->id}\n\n";
            } catch (\Exception $e) {
                echo "  ❌ Erro ao criar preço: " . $e->getMessage() . "\n\n";
                exit(1);
            }
        }
    }
    
    // ==========================================
    // PASSO 5: Resumo e próximos passos
    // ==========================================
    echo "📊 RESUMO DA CONFIGURAÇÃO\n";
    echo str_repeat("=", 70) . "\n\n";
    
    echo "✅ Produtos criados:\n";
    foreach ($createdProducts as $planKey => $product) {
        echo "  - {$product->name}: {$product->id}\n";
    }
    echo "\n";
    
    echo "✅ Preços criados:\n";
    foreach ($createdPrices as $planKey => $intervals) {
        foreach ($intervals as $interval => $price) {
            $amount = number_format($price->unit_amount / 100, 2, ',', '.');
            echo "  - {$planKey} ({$interval}): {$price->id} - R$ {$amount}\n";
        }
    }
    echo "\n";
    
    echo "📝 PRÓXIMOS PASSOS:\n";
    echo str_repeat("-", 70) . "\n";
    echo "1. Copie os price_id acima\n";
    echo "2. Atualize o arquivo App/Services/PlanLimitsService.php\n";
    echo "3. Substitua os placeholders (price_basico, price_profissional, price_premium)\n";
    echo "   pelos price_id reais obtidos acima\n";
    echo "4. Teste a criação de assinaturas\n\n";
    
    echo "💾 Price IDs para copiar:\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($createdPrices as $planKey => $intervals) {
        echo "\n// Plano " . ucfirst($planKey) . "\n";
        foreach ($intervals as $interval => $price) {
            $varName = 'price_' . $planKey . '_' . $interval;
            echo "'{$price->id}' => [ // {$varName}\n";
        }
    }
    echo "\n";
    
    echo "✅ Configuração concluída com sucesso!\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}


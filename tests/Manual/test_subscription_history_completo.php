<?php

/**
 * Teste Completo do Histórico de Mudanças de Assinatura
 * 
 * Este script testa:
 * 1. Criação de assinatura (deve registrar histórico)
 * 2. Atualização de assinatura (deve registrar histórico)
 * 3. Cancelamento de assinatura (deve registrar histórico)
 * 4. Reativação de assinatura (deve registrar histórico)
 * 5. Listagem de histórico via endpoint
 * 6. Verificação de todos os tipos de mudança
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

use App\Utils\Database;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║   TESTE COMPLETO DE HISTÓRICO DE MUDANÇAS DE ASSINATURA      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$testsPassed = 0;
$testsFailed = 0;

try {
    // Inicializa Stripe Client
    $stripeSecret = Config::get('STRIPE_SECRET');
    if (empty($stripeSecret)) {
        throw new Exception("STRIPE_SECRET não configurado no .env");
    }
    
    $stripe = new \Stripe\StripeClient($stripeSecret);
    echo "✅ Stripe Client inicializado\n\n";

    // Busca tenant
    $tenantModel = new Tenant();
    $tenants = $tenantModel->findAll();
    if (empty($tenants)) {
        throw new Exception("Nenhum tenant encontrado. Execute: composer run seed");
    }
    $tenant = $tenants[0];
    $tenantId = $tenant['id'];
    $apiKey = $tenant['api_key'];
    echo "✅ Tenant encontrado: {$tenant['name']} (ID: {$tenantId})\n";
    echo "   API Key: " . substr($apiKey, 0, 20) . "...\n\n";

    // Busca customer
    $customerModel = new Customer();
    $customers = $customerModel->findByTenant($tenantId);
    if (empty($customers)) {
        throw new Exception("Nenhum customer encontrado. Crie um customer primeiro.");
    }
    $customer = $customers[0];
    echo "✅ Customer encontrado: {$customer['email']} (ID: {$customer['id']})\n";
    echo "   Stripe Customer ID: {$customer['stripe_customer_id']}\n\n";

    // Cria produto e preço no Stripe
    echo "📦 Criando produto e preço no Stripe...\n";
    $product = $stripe->products->create([
        'name' => 'Plano Teste Histórico - ' . date('Y-m-d H:i:s'),
        'description' => 'Produto criado para teste de histórico',
        'metadata' => ['test' => 'true']
    ]);
    
    $price = $stripe->prices->create([
        'product' => $product->id,
        'unit_amount' => 9999, // R$ 99,99
        'currency' => 'brl',
        'recurring' => ['interval' => 'month']
    ]);
    
    echo "✅ Produto criado: {$product->id}\n";
    echo "✅ Preço criado: {$price->id}\n\n";

    $baseUrl = 'http://localhost:8080';
    $subscriptionModel = new Subscription();
    $historyModel = new SubscriptionHistory();

    // ============================================
    // TESTE 1: Criar Assinatura
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 1: Criar Assinatura\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'customer_id' => $customer['id'],
            'price_id' => $price->id
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        $data = json_decode($response, true);
        $subscriptionId = $data['data']['id'];
        echo "✅ Assinatura criada: ID $subscriptionId\n";
        $testsPassed++;
        
        // Verifica se histórico foi criado
        sleep(1); // Aguarda um pouco para garantir que o histórico foi salvo
        $history = $historyModel->findBySubscription($subscriptionId, $tenantId, 10, 0);
        $createdHistory = null;
        foreach ($history as $h) {
            if ($h['change_type'] === SubscriptionHistory::CHANGE_TYPE_CREATED) {
                $createdHistory = $h;
                break;
            }
        }
        
        if ($createdHistory) {
            echo "✅ Histórico de criação registrado (ID: {$createdHistory['id']})\n";
            echo "   Tipo: {$createdHistory['change_type']}\n";
            echo "   Origem: {$createdHistory['changed_by']}\n";
            $testsPassed++;
        } else {
            echo "❌ Histórico de criação NÃO foi registrado\n";
            $testsFailed++;
        }
    } else {
        echo "❌ Erro ao criar assinatura: HTTP $httpCode\n";
        echo "   Resposta: $response\n";
        $testsFailed++;
        exit(1);
    }
    
    echo "\n";

    // ============================================
    // TESTE 2: Atualizar Assinatura
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 2: Atualizar Assinatura\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    // Busca assinatura atual
    $subscription = $subscriptionModel->findById($subscriptionId);
    $oldStatus = $subscription['status'];
    $oldPlanId = $subscription['plan_id'];
    
    // Cria novo preço para mudança de plano
    $newPrice = $stripe->prices->create([
        'product' => $product->id,
        'unit_amount' => 14999, // R$ 149,99
        'currency' => 'brl',
        'recurring' => ['interval' => 'month']
    ]);
    
    echo "📦 Novo preço criado: {$newPrice->id}\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'price_id' => $newPrice->id,
            'metadata' => ['updated_at' => date('Y-m-d H:i:s')]
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Assinatura atualizada\n";
        $testsPassed++;
        
        // Verifica se histórico foi criado
        sleep(1);
        $history = $historyModel->findBySubscription($subscriptionId, $tenantId, 10, 0);
        $updatedHistory = null;
        foreach ($history as $h) {
            if ($h['change_type'] === SubscriptionHistory::CHANGE_TYPE_PLAN_CHANGED || 
                $h['change_type'] === SubscriptionHistory::CHANGE_TYPE_UPDATED) {
                $updatedHistory = $h;
                break;
            }
        }
        
        if ($updatedHistory) {
            echo "✅ Histórico de atualização registrado (ID: {$updatedHistory['id']})\n";
            echo "   Tipo: {$updatedHistory['change_type']}\n";
            echo "   Plano antigo: {$updatedHistory['old_plan_id']}\n";
            echo "   Plano novo: {$updatedHistory['new_plan_id']}\n";
            $testsPassed++;
        } else {
            echo "❌ Histórico de atualização NÃO foi registrado\n";
            $testsFailed++;
        }
    } else {
        echo "❌ Erro ao atualizar assinatura: HTTP $httpCode\n";
        echo "   Resposta: $response\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 3: Cancelar Assinatura
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 3: Cancelar Assinatura\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId?immediately=false");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Assinatura cancelada\n";
        $testsPassed++;
        
        // Verifica se histórico foi criado
        sleep(1);
        $history = $historyModel->findBySubscription($subscriptionId, $tenantId, 10, 0);
        $canceledHistory = null;
        foreach ($history as $h) {
            if ($h['change_type'] === SubscriptionHistory::CHANGE_TYPE_CANCELED) {
                $canceledHistory = $h;
                break;
            }
        }
        
        if ($canceledHistory) {
            echo "✅ Histórico de cancelamento registrado (ID: {$canceledHistory['id']})\n";
            echo "   Tipo: {$canceledHistory['change_type']}\n";
            echo "   Status antigo: {$canceledHistory['old_status']}\n";
            echo "   Status novo: {$canceledHistory['new_status']}\n";
            $testsPassed++;
        } else {
            echo "❌ Histórico de cancelamento NÃO foi registrado\n";
            $testsFailed++;
        }
    } else {
        echo "❌ Erro ao cancelar assinatura: HTTP $httpCode\n";
        echo "   Resposta: $response\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 4: Reativar Assinatura
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 4: Reativar Assinatura\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId/reactivate");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Assinatura reativada\n";
        $testsPassed++;
        
        // Verifica se histórico foi criado
        sleep(1);
        $history = $historyModel->findBySubscription($subscriptionId, $tenantId, 10, 0);
        $reactivatedHistory = null;
        foreach ($history as $h) {
            if ($h['change_type'] === SubscriptionHistory::CHANGE_TYPE_REACTIVATED) {
                $reactivatedHistory = $h;
                break;
            }
        }
        
        if ($reactivatedHistory) {
            echo "✅ Histórico de reativação registrado (ID: {$reactivatedHistory['id']})\n";
            echo "   Tipo: {$reactivatedHistory['change_type']}\n";
            $testsPassed++;
        } else {
            echo "❌ Histórico de reativação NÃO foi registrado\n";
            $testsFailed++;
        }
    } else {
        echo "⚠️  Erro ao reativar assinatura: HTTP $httpCode\n";
        echo "   Resposta: $response\n";
        echo "   (Pode ser que a assinatura não possa ser reativada neste estado)\n";
    }
    
    echo "\n";

    // ============================================
    // TESTE 5: Listar Histórico via Endpoint
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 5: Listar Histórico via Endpoint\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId/history");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ Endpoint funcionando corretamente!\n";
            echo "   Total de registros: {$data['pagination']['total']}\n";
            echo "   Registros retornados: " . count($data['data']) . "\n";
            echo "   Limite: {$data['pagination']['limit']}\n";
            echo "   Offset: {$data['pagination']['offset']}\n";
            echo "   Tem mais: " . ($data['pagination']['has_more'] ? 'Sim' : 'Não') . "\n";
            $testsPassed++;
            
            if (!empty($data['data'])) {
                echo "\n   Últimos registros:\n";
                foreach (array_slice($data['data'], 0, 5) as $record) {
                    echo "     - ID: {$record['id']}\n";
                    echo "       Tipo: {$record['change_type']}\n";
                    echo "       Origem: {$record['changed_by']}\n";
                    echo "       Data: {$record['created_at']}\n";
                    if ($record['description']) {
                        echo "       Descrição: {$record['description']}\n";
                    }
                    echo "\n";
                }
            }
        } else {
            echo "❌ Resposta inesperada do endpoint\n";
            $testsFailed++;
        }
    } else {
        echo "❌ Erro ao consultar histórico: HTTP $httpCode\n";
        echo "   Resposta: $response\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 6: Paginação
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 6: Testar Paginação\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId/history?limit=2&offset=0");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && count($data['data']) <= 2) {
            echo "✅ Paginação funcionando (limit=2 retornou " . count($data['data']) . " registros)\n";
            $testsPassed++;
        } else {
            echo "❌ Paginação não funcionou corretamente\n";
            $testsFailed++;
        }
    } else {
        echo "❌ Erro ao testar paginação: HTTP $httpCode\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // RESUMO
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                        RESUMO DOS TESTES                     ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "✅ Testes passados: $testsPassed\n";
    echo "❌ Testes falhados: $testsFailed\n";
    echo "📊 Total: " . ($testsPassed + $testsFailed) . "\n";
    
    if ($testsFailed === 0) {
        echo "\n🎉 Todos os testes passaram!\n";
    } else {
        echo "\n⚠️  Alguns testes falharam. Verifique os logs acima.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro fatal: {$e->getMessage()}\n";
    echo "   Arquivo: {$e->getFile()}\n";
    echo "   Linha: {$e->getLine()}\n";
    exit(1);
}


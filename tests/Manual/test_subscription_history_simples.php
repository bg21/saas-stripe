<?php

/**
 * Teste Simples do Histórico de Mudanças de Assinatura
 * 
 * Este script testa:
 * 1. Registro manual de histórico (diferentes tipos)
 * 2. Busca de histórico
 * 3. Endpoint HTTP de listagem
 * 4. Paginação
 * 5. Validações de segurança
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
echo "║   TESTE SIMPLES DE HISTÓRICO DE MUDANÇAS DE ASSINATURA        ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$testsPassed = 0;
$testsFailed = 0;

try {
    // Busca tenant
    $tenantModel = new Tenant();
    $tenants = $tenantModel->findAll();
    if (empty($tenants)) {
        throw new Exception("Nenhum tenant encontrado. Execute: composer run seed");
    }
    $tenant = $tenants[0];
    $tenantId = $tenant['id'];
    $apiKey = $tenant['api_key'];
    echo "✅ Tenant encontrado: {$tenant['name']} (ID: {$tenantId})\n\n";

    // Busca customer
    $customerModel = new Customer();
    $customers = $customerModel->findByTenant($tenantId);
    if (empty($customers)) {
        throw new Exception("Nenhum customer encontrado.");
    }
    $customer = $customers[0];
    echo "✅ Customer encontrado: {$customer['email']} (ID: {$customer['id']})\n\n";

    // Busca assinaturas
    $subscriptionModel = new Subscription();
    $subscriptions = $subscriptionModel->findByTenant($tenantId);
    
    if (empty($subscriptions)) {
        echo "⚠️  Nenhuma assinatura encontrada.\n";
        echo "💡 Vamos criar registros de histórico de teste para uma assinatura fictícia.\n\n";
        
        // Cria uma assinatura fictícia no banco para teste
        $testSubscriptionId = $subscriptionModel->insert([
            'tenant_id' => $tenantId,
            'customer_id' => $customer['id'],
            'stripe_subscription_id' => 'sub_test_' . time(),
            'stripe_customer_id' => $customer['stripe_customer_id'],
            'status' => 'active',
            'plan_id' => 'price_test',
            'plan_name' => 'Plano Teste',
            'amount' => 99.99,
            'currency' => 'BRL',
            'current_period_start' => date('Y-m-d H:i:s'),
            'current_period_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'cancel_at_period_end' => 0,
            'metadata' => json_encode(['test' => true])
        ]);
        
        echo "✅ Assinatura de teste criada (ID: $testSubscriptionId)\n\n";
        $subscriptionId = $testSubscriptionId;
    } else {
        $subscription = $subscriptions[0];
        $subscriptionId = $subscription['id'];
        echo "✅ Assinatura encontrada: ID $subscriptionId, Status: {$subscription['status']}\n\n";
    }

    $historyModel = new SubscriptionHistory();
    $baseUrl = 'http://localhost:8080';

    // ============================================
    // TESTE 1: Registrar Diferentes Tipos de Mudança
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 1: Registrar Diferentes Tipos de Mudança\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $changeTypes = [
        SubscriptionHistory::CHANGE_TYPE_CREATED => [
            'old' => [],
            'new' => ['status' => 'active', 'plan_id' => 'price_1', 'amount' => 99.99, 'currency' => 'BRL'],
            'desc' => 'Assinatura criada'
        ],
        SubscriptionHistory::CHANGE_TYPE_PLAN_CHANGED => [
            'old' => ['plan_id' => 'price_1', 'amount' => 99.99],
            'new' => ['plan_id' => 'price_2', 'amount' => 149.99],
            'desc' => 'Plano alterado de price_1 para price_2'
        ],
        SubscriptionHistory::CHANGE_TYPE_STATUS_CHANGED => [
            'old' => ['status' => 'active'],
            'new' => ['status' => 'past_due'],
            'desc' => 'Status alterado de active para past_due'
        ],
        SubscriptionHistory::CHANGE_TYPE_CANCELED => [
            'old' => ['status' => 'active', 'cancel_at_period_end' => 0],
            'new' => ['status' => 'active', 'cancel_at_period_end' => 1],
            'desc' => 'Assinatura marcada para cancelar no final do período'
        ],
        SubscriptionHistory::CHANGE_TYPE_REACTIVATED => [
            'old' => ['status' => 'active', 'cancel_at_period_end' => 1],
            'new' => ['status' => 'active', 'cancel_at_period_end' => 0],
            'desc' => 'Assinatura reativada'
        ]
    ];
    
    $createdHistoryIds = [];
    
    foreach ($changeTypes as $type => $data) {
        try {
            $historyId = $historyModel->recordChange(
                $subscriptionId,
                $tenantId,
                $type,
                $data['old'],
                $data['new'],
                SubscriptionHistory::CHANGED_BY_API,
                $data['desc']
            );
            
            $createdHistoryIds[] = $historyId;
            echo "✅ Registro criado: $type (ID: $historyId)\n";
            $testsPassed++;
        } catch (\Exception $e) {
            echo "❌ Erro ao criar registro $type: {$e->getMessage()}\n";
            $testsFailed++;
        }
    }
    
    echo "\n";

    // ============================================
    // TESTE 2: Buscar Histórico
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 2: Buscar Histórico\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $history = $historyModel->findBySubscription($subscriptionId, $tenantId, 100, 0);
    $total = $historyModel->countBySubscription($subscriptionId, $tenantId);
    
    echo "Total de registros: $total\n";
    echo "Registros retornados: " . count($history) . "\n";
    
    if ($total >= count($changeTypes)) {
        echo "✅ Busca funcionando corretamente\n";
        $testsPassed++;
    } else {
        echo "❌ Busca não retornou todos os registros esperados\n";
        $testsFailed++;
    }
    
    if (!empty($history)) {
        echo "\nÚltimos 3 registros:\n";
        foreach (array_slice($history, 0, 3) as $record) {
            echo "  - ID: {$record['id']}, Tipo: {$record['change_type']}, Data: {$record['created_at']}\n";
        }
    }
    
    echo "\n";

    // ============================================
    // TESTE 3: Paginação
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 3: Testar Paginação\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $limit = 2;
    $offset = 0;
    $paginated = $historyModel->findBySubscription($subscriptionId, $tenantId, $limit, $offset);
    
    if (count($paginated) <= $limit) {
        echo "✅ Paginação funcionando (limit=$limit retornou " . count($paginated) . " registros)\n";
        $testsPassed++;
    } else {
        echo "❌ Paginação não funcionou corretamente\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 4: Endpoint HTTP
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 4: Endpoint HTTP GET /v1/subscriptions/:id/history\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId/history");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ Endpoint funcionando corretamente!\n";
            echo "   Total: {$data['pagination']['total']}\n";
            echo "   Retornados: " . count($data['data']) . "\n";
            echo "   Limite: {$data['pagination']['limit']}\n";
            echo "   Offset: {$data['pagination']['offset']}\n";
            echo "   Tem mais: " . ($data['pagination']['has_more'] ? 'Sim' : 'Não') . "\n";
            $testsPassed++;
            
            if (!empty($data['data'])) {
                echo "\n   Primeiro registro:\n";
                $first = $data['data'][0];
                echo "     - ID: {$first['id']}\n";
                echo "     - Tipo: {$first['change_type']}\n";
                echo "     - Origem: {$first['changed_by']}\n";
                echo "     - Data: {$first['created_at']}\n";
                if ($first['description']) {
                    echo "     - Descrição: {$first['description']}\n";
                }
            }
        } else {
            echo "❌ Resposta inesperada\n";
            $testsFailed++;
        }
    } elseif ($httpCode === 0) {
        echo "⚠️  Servidor não está rodando\n";
        echo "   Inicie com: php -S localhost:8080 -t public\n";
    } else {
        echo "❌ Erro HTTP: $httpCode\n";
        echo "   Resposta: " . substr($response, 0, 200) . "\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 5: Paginação via Endpoint
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 5: Paginação via Endpoint\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    $ch = curl_init("$baseUrl/v1/subscriptions/$subscriptionId/history?limit=2&offset=0");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && count($data['data']) <= 2) {
            echo "✅ Paginação via endpoint funcionando (limit=2 retornou " . count($data['data']) . " registros)\n";
            $testsPassed++;
        } else {
            echo "❌ Paginação via endpoint não funcionou\n";
            $testsFailed++;
        }
    } elseif ($httpCode === 0) {
        echo "⚠️  Servidor não está rodando\n";
    } else {
        echo "❌ Erro HTTP: $httpCode\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 6: Validação de Segurança
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 6: Validação de Segurança (Tenant ID)\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    // Tenta buscar histórico com tenant_id diferente
    $wrongTenantId = 99999;
    $wrongHistory = $historyModel->findBySubscription($subscriptionId, $wrongTenantId, 10, 0);
    
    if (count($wrongHistory) === 0) {
        echo "✅ Validação de segurança funcionando (tenant_id incorreto não retorna dados)\n";
        $testsPassed++;
    } else {
        echo "❌ Validação de segurança falhou (retornou dados de outro tenant)\n";
        $testsFailed++;
    }
    
    echo "\n";

    // ============================================
    // TESTE 7: Verificar Dados dos Registros
    // ============================================
    echo "═══════════════════════════════════════════════════════════\n";
    echo "TESTE 7: Verificar Dados dos Registros\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    if (!empty($createdHistoryIds)) {
        $firstId = $createdHistoryIds[0];
        $record = $historyModel->findById($firstId);
        
        if ($record) {
            echo "✅ Registro encontrado no banco\n";
            echo "   ID: {$record['id']}\n";
            echo "   Subscription ID: {$record['subscription_id']}\n";
            echo "   Tenant ID: {$record['tenant_id']}\n";
            echo "   Tipo: {$record['change_type']}\n";
            echo "   Origem: {$record['changed_by']}\n";
            echo "   Status antigo: {$record['old_status']}\n";
            echo "   Status novo: {$record['new_status']}\n";
            echo "   Data: {$record['created_at']}\n";
            $testsPassed++;
            
            // Verifica se os dados estão corretos
            if ($record['subscription_id'] == $subscriptionId && 
                $record['tenant_id'] == $tenantId) {
                echo "✅ Dados do registro estão corretos\n";
                $testsPassed++;
            } else {
                echo "❌ Dados do registro estão incorretos\n";
                $testsFailed++;
            }
        } else {
            echo "❌ Registro não encontrado após criação\n";
            $testsFailed++;
        }
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
    echo "📈 Taxa de sucesso: " . round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 2) . "%\n";
    
    if ($testsFailed === 0) {
        echo "\n🎉 Todos os testes passaram!\n";
        exit(0);
    } else {
        echo "\n⚠️  Alguns testes falharam. Verifique os logs acima.\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ Erro fatal: {$e->getMessage()}\n";
    echo "   Arquivo: {$e->getFile()}\n";
    echo "   Linha: {$e->getLine()}\n";
    exit(1);
}


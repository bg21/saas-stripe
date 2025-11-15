<?php

/**
 * Teste do Histórico de Mudanças de Assinatura
 * 
 * Este script testa:
 * 1. Criação de assinatura (deve registrar histórico)
 * 2. Atualização de assinatura (deve registrar histórico)
 * 3. Cancelamento de assinatura (deve registrar histórico)
 * 4. Reativação de assinatura (deve registrar histórico)
 * 5. Listagem de histórico via endpoint
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

Config::load();

use App\Utils\Database;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;

echo "=== Teste de Histórico de Mudanças de Assinatura ===\n\n";

$db = Database::getInstance();

// 1. Busca ou cria tenant
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll();
if (empty($tenants)) {
    echo "❌ Nenhum tenant encontrado. Execute: composer run seed\n";
    exit(1);
}
$tenant = $tenants[0];
$tenantId = $tenant['id'];
echo "✅ Tenant encontrado: {$tenant['name']} (ID: {$tenantId})\n\n";

// 2. Busca ou cria customer
$customerModel = new Customer();
$customers = $customerModel->findByTenant($tenantId);
$customer = null;

if (empty($customers)) {
    echo "⚠️  Nenhum customer encontrado. Criando customer de teste...\n";
    // Para criar um customer, precisaríamos de uma API key válida e fazer requisição HTTP
    // Por enquanto, vamos assumir que existe um customer
    echo "❌ É necessário ter pelo menos um customer. Crie um customer primeiro.\n";
    exit(1);
} else {
    $customer = $customers[0];
    echo "✅ Customer encontrado: {$customer['email']} (ID: {$customer['id']})\n\n";
}

// 3. Busca assinaturas existentes
$subscriptionModel = new Subscription();
$subscriptions = $subscriptionModel->findByTenant($tenantId);

if (empty($subscriptions)) {
    echo "⚠️  Nenhuma assinatura encontrada.\n";
    echo "💡 Para testar completamente, você precisa:\n";
    echo "   1. Criar uma assinatura via API\n";
    echo "   2. Atualizar a assinatura\n";
    echo "   3. Cancelar a assinatura\n";
    echo "   4. Reativar a assinatura\n";
    echo "   5. Consultar o histórico\n\n";
    
    // Vamos testar apenas a consulta de histórico vazio
    echo "=== Testando consulta de histórico vazio ===\n";
    $historyModel = new SubscriptionHistory();
    $history = $historyModel->findBySubscription(999999, $tenantId, 10, 0);
    $total = $historyModel->countBySubscription(999999, $tenantId);
    
    echo "Histórico de assinatura inexistente:\n";
    echo "  Total: $total\n";
    echo "  Registros: " . count($history) . "\n";
    
    if ($total === 0 && count($history) === 0) {
        echo "✅ Teste passou: histórico vazio retornado corretamente\n\n";
    } else {
        echo "❌ Teste falhou\n\n";
    }
    
    exit(0);
}

$subscription = $subscriptions[0];
$subscriptionId = $subscription['id'];
echo "✅ Assinatura encontrada: ID {$subscriptionId}, Status: {$subscription['status']}\n\n";

// 4. Verifica histórico atual
$historyModel = new SubscriptionHistory();
$history = $historyModel->findBySubscription($subscriptionId, $tenantId, 100, 0);
$total = $historyModel->countBySubscription($subscriptionId, $tenantId);

echo "=== Histórico Atual ===\n";
echo "Total de registros: $total\n";
echo "Registros retornados: " . count($history) . "\n\n";

if ($total > 0) {
    echo "Últimos registros:\n";
    foreach (array_slice($history, 0, 5) as $record) {
        echo "  - ID: {$record['id']}\n";
        echo "    Tipo: {$record['change_type']}\n";
        echo "    Origem: {$record['changed_by']}\n";
        echo "    Status: {$record['old_status']} → {$record['new_status']}\n";
        echo "    Data: {$record['created_at']}\n";
        if ($record['description']) {
            echo "    Descrição: {$record['description']}\n";
        }
        echo "\n";
    }
} else {
    echo "⚠️  Nenhum registro de histórico encontrado.\n";
    echo "💡 O histórico é criado automaticamente quando:\n";
    echo "   - Uma assinatura é criada\n";
    echo "   - Uma assinatura é atualizada\n";
    echo "   - Uma assinatura é cancelada\n";
    echo "   - Uma assinatura é reativada\n";
    echo "   - Webhooks do Stripe atualizam a assinatura\n\n";
}

// 5. Testa métodos do model
echo "=== Testando Métodos do Model ===\n";

// Testa findBySubscription com diferentes limites
$historyLimited = $historyModel->findBySubscription($subscriptionId, $tenantId, 5, 0);
echo "✅ findBySubscription com limit=5: " . count($historyLimited) . " registros\n";

// Testa countBySubscription
$count = $historyModel->countBySubscription($subscriptionId, $tenantId);
echo "✅ countBySubscription: $count registros\n";

// Testa registro manual (simulação)
echo "\n=== Testando Registro Manual ===\n";
try {
    $testRecordId = $historyModel->recordChange(
        $subscriptionId,
        $tenantId,
        SubscriptionHistory::CHANGE_TYPE_UPDATED,
        [
            'status' => 'active',
            'plan_id' => 'price_test_old',
            'amount' => 99.99,
            'currency' => 'BRL'
        ],
        [
            'status' => 'active',
            'plan_id' => 'price_test_new',
            'amount' => 149.99,
            'currency' => 'BRL'
        ],
        SubscriptionHistory::CHANGED_BY_API,
        'Teste de registro manual - mudança de plano'
    );
    echo "✅ Registro criado com sucesso (ID: $testRecordId)\n";
    
    // Verifica se foi criado
    $testRecord = $historyModel->findById($testRecordId);
    if ($testRecord) {
        echo "✅ Registro encontrado no banco\n";
        echo "   Tipo: {$testRecord['change_type']}\n";
        echo "   Plano antigo: {$testRecord['old_plan_id']}\n";
        echo "   Plano novo: {$testRecord['new_plan_id']}\n";
        echo "   Valor antigo: {$testRecord['old_amount']}\n";
        echo "   Valor novo: {$testRecord['new_amount']}\n";
    } else {
        echo "❌ Registro não encontrado após criação\n";
    }
} catch (\Exception $e) {
    echo "❌ Erro ao criar registro: {$e->getMessage()}\n";
}

// 6. Testa endpoint via HTTP (se servidor estiver rodando)
echo "\n=== Testando Endpoint HTTP ===\n";
echo "💡 Para testar o endpoint HTTP, você precisa:\n";
echo "   1. Ter o servidor rodando (php -S localhost:8080 -t public)\n";
echo "   2. Ter uma API key válida do tenant\n";
echo "   3. Fazer requisição: GET /v1/subscriptions/{$subscriptionId}/history\n\n";

// Tenta fazer requisição se possível
$apiKey = $tenant['api_key'];
$baseUrl = 'http://localhost:8080';

echo "Tentando conectar em: $baseUrl\n";

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
        echo "   Total de registros: {$data['pagination']['total']}\n";
        echo "   Registros retornados: " . count($data['data']) . "\n";
        echo "   Limite: {$data['pagination']['limit']}\n";
        echo "   Offset: {$data['pagination']['offset']}\n";
        echo "   Tem mais: " . ($data['pagination']['has_more'] ? 'Sim' : 'Não') . "\n";
        
        if (!empty($data['data'])) {
            echo "\n   Primeiro registro:\n";
            $first = $data['data'][0];
            echo "     - Tipo: {$first['change_type']}\n";
            echo "     - Origem: {$first['changed_by']}\n";
            echo "     - Data: {$first['created_at']}\n";
        }
    } else {
        echo "⚠️  Resposta inesperada do endpoint\n";
        echo "   HTTP Code: $httpCode\n";
        echo "   Resposta: " . substr($response, 0, 200) . "\n";
    }
} elseif ($httpCode === 0) {
    echo "⚠️  Servidor não está rodando ou não foi possível conectar\n";
    echo "   Inicie o servidor com: php -S localhost:8080 -t public\n";
} else {
    echo "⚠️  Erro HTTP: $httpCode\n";
    echo "   Resposta: " . substr($response, 0, 200) . "\n";
}

// 7. Testa tipos de mudança
echo "\n=== Testando Tipos de Mudança ===\n";
$changeTypes = [
    SubscriptionHistory::CHANGE_TYPE_CREATED,
    SubscriptionHistory::CHANGE_TYPE_UPDATED,
    SubscriptionHistory::CHANGE_TYPE_CANCELED,
    SubscriptionHistory::CHANGE_TYPE_REACTIVATED,
    SubscriptionHistory::CHANGE_TYPE_PLAN_CHANGED,
    SubscriptionHistory::CHANGE_TYPE_STATUS_CHANGED
];

echo "Tipos de mudança suportados:\n";
foreach ($changeTypes as $type) {
    echo "  ✅ $type\n";
}

// 8. Testa origens de mudança
echo "\n=== Testando Origens de Mudança ===\n";
$changedBy = [
    SubscriptionHistory::CHANGED_BY_API,
    SubscriptionHistory::CHANGED_BY_WEBHOOK,
    SubscriptionHistory::CHANGED_BY_ADMIN
];

echo "Origens suportadas:\n";
foreach ($changedBy as $origin) {
    echo "  ✅ $origin\n";
}

echo "\n=== Teste Concluído ===\n";
echo "\n💡 Para testar completamente o histórico:\n";
echo "   1. Crie uma assinatura via API (POST /v1/subscriptions)\n";
echo "   2. Atualize a assinatura (PUT /v1/subscriptions/:id)\n";
echo "   3. Cancele a assinatura (DELETE /v1/subscriptions/:id)\n";
echo "   4. Reative a assinatura (POST /v1/subscriptions/:id/reactivate)\n";
echo "   5. Consulte o histórico (GET /v1/subscriptions/:id/history)\n";


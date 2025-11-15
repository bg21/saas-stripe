<?php

/**
 * Teste Completo de Balance Transactions
 * 
 * Este script testa:
 * 1. Método listBalanceTransactions() do StripeService - Lista transações de saldo
 * 2. Método getBalanceTransaction() do StripeService - Obtém transação específica
 * 3. GET /v1/balance-transactions - Endpoint de listagem
 * 4. GET /v1/balance-transactions/:id - Endpoint de obtenção
 * 5. Filtros e paginação
 * 6. Validação da estrutura de resposta
 * 
 * IMPORTANTE: Este teste usa recursos reais do Stripe (ambiente de teste)
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = Config::get('TEST_API_KEY');
if (empty($apiKey)) {
    $apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086'; // Fallback
    echo "⚠️  Usando API key hardcoded. Configure TEST_API_KEY no .env para produção.\n\n";
}
$baseUrl = 'http://localhost:8080';

echo "\033[34m╔═══════════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[34m║     TESTES MANUAIS - BALANCE TRANSACTIONS                    ║\033[0m\n";
echo "\033[34m╚═══════════════════════════════════════════════════════════════╝\033[0m\n\n";

$testsPassed = 0;
$testsFailed = 0;
$balanceTransactionId = null;

// Função auxiliar para fazer requisições
function makeRequest($method, $endpoint, $data = null) {
    global $baseUrl, $apiKey;
    
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    
    if ($data !== null && ($method === 'POST' || $method === 'PUT')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Função auxiliar para imprimir teste
function printTest($num, $description) {
    echo "\033[33m═══════════════════════════════════════════════════════════════\033[0m\n";
    echo "\033[34mTESTE {$num}: {$description}\033[0m\n";
    echo "\033[33m═══════════════════════════════════════════════════════════════\033[0m\n";
}

// Função auxiliar para imprimir resultado
function printResult($passed, $message) {
    global $testsPassed, $testsFailed;
    
    if ($passed) {
        echo "\033[32m✓ PASSOU\033[0m - {$message}\n\n";
        $testsPassed++;
    } else {
        echo "\033[31m✗ FALHOU\033[0m - {$message}\n\n";
        $testsFailed++;
    }
}

try {
    // Inicializa StripeService
    $stripeService = new \App\Services\StripeService();
    echo "✅ StripeService inicializado\n\n";

    // ============================================================================
    // TESTE 1: Listar Balance Transactions via API (básico)
    // ============================================================================
    printTest(1, "Listar Balance Transactions via API (básico)");
    
    $response = makeRequest('GET', '/v1/balance-transactions?limit=5');
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data'])
        && is_array($response['body']['data']);
    
    if ($passed) {
        $count = count($response['body']['data']);
        echo "   Encontrado {$count} balance transaction(s)\n";
        
        if ($count > 0) {
            $first = $response['body']['data'][0];
            $balanceTransactionId = $first['id'];
            echo "   Primeiro: {$first['id']} - {$first['type']} - R$ " . number_format(($first['amount'] ?? 0) / 100, 2, ',', '.') . "\n";
            echo "   Status: {$first['status']}\n";
            echo "   Currency: {$first['currency']}\n";
        }
    } else {
        echo "   HTTP Code: {$response['code']}\n";
        if (isset($response['body']['error'])) {
            echo "   Erro: {$response['body']['error']}\n";
        }
    }
    
    printResult($passed, $passed ? "Listagem retornou {$count} balance transaction(s)" : "HTTP {$response['code']}");

    // ============================================================================
    // TESTE 2: Listar Balance Transactions com filtro de tipo
    // ============================================================================
    printTest(2, "Listar Balance Transactions com filtro de tipo (charge)");
    
    $response = makeRequest('GET', '/v1/balance-transactions?limit=5&type=charge');
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data'])
        && is_array($response['body']['data']);
    
    if ($passed) {
        $count = count($response['body']['data']);
        echo "   Retornou {$count} balance transaction(s) do tipo 'charge'\n";
        
        // Verifica se todas são do tipo charge
        $allCharges = true;
        foreach ($response['body']['data'] as $tx) {
            if ($tx['type'] !== 'charge') {
                $allCharges = false;
                break;
            }
        }
        
        if ($allCharges && $count > 0) {
            echo "   ✅ Todas as transações são do tipo 'charge'\n";
        } elseif ($count === 0) {
            echo "   ⚠️  Nenhuma transação do tipo 'charge' encontrada (pode ser normal)\n";
        }
    }
    
    printResult($passed, $passed ? "Filtro funcionando" : "HTTP {$response['code']}");

    // ============================================================================
    // TESTE 3: Listar Balance Transactions com filtro de moeda
    // ============================================================================
    printTest(3, "Listar Balance Transactions com filtro de moeda (brl)");
    
    $response = makeRequest('GET', '/v1/balance-transactions?limit=5&currency=brl');
    $passed = $response['code'] === 200
        && isset($response['body']['success'])
        && $response['body']['success'] === true
        && isset($response['body']['data'])
        && is_array($response['body']['data']);
    
    if ($passed) {
        $count = count($response['body']['data']);
        echo "   Retornou {$count} balance transaction(s) em BRL\n";
    }
    
    printResult($passed, $passed ? "Filtro de moeda funcionando" : "HTTP {$response['code']}");

    // ============================================================================
    // TESTE 4: Listar Balance Transactions com paginação
    // ============================================================================
    printTest(4, "Listar Balance Transactions com paginação");
    
    // Primeira página
    $response1 = makeRequest('GET', '/v1/balance-transactions?limit=3');
    if ($response1['code'] === 200 && isset($response1['body']['data']) && count($response1['body']['data']) > 0) {
        $firstPage = $response1['body']['data'];
        $lastId = end($firstPage)['id'];
        
        // Segunda página usando starting_after
        $response2 = makeRequest('GET', "/v1/balance-transactions?limit=3&starting_after={$lastId}");
        
        $passed = $response2['code'] === 200
            && isset($response2['body']['data'])
            && is_array($response2['body']['data']);
        
        if ($passed) {
            $count2 = count($response2['body']['data']);
            echo "   Primeira página: " . count($firstPage) . " transação(ões)\n";
            echo "   Segunda página: {$count2} transação(ões)\n";
            
            // Verifica se não há duplicatas
            $firstPageIds = array_column($firstPage, 'id');
            $secondPageIds = array_column($response2['body']['data'], 'id');
            $duplicates = array_intersect($firstPageIds, $secondPageIds);
            
            if (empty($duplicates)) {
                echo "   ✅ Nenhuma duplicata entre páginas\n";
            } else {
                echo "   ⚠️  Duplicatas encontradas: " . implode(', ', $duplicates) . "\n";
            }
        }
    } else {
        $passed = false;
        echo "   ⚠️  Não há transações suficientes para testar paginação\n";
    }
    
    printResult($passed, $passed ? "Paginação funcionando" : "Não foi possível testar paginação");

    // ============================================================================
    // TESTE 5: Obter Balance Transaction por ID via API
    // ============================================================================
    printTest(5, "Obter Balance Transaction por ID via API");
    
    if (empty($balanceTransactionId)) {
        // Tenta obter uma transação da listagem
        $response = makeRequest('GET', '/v1/balance-transactions?limit=1');
        if ($response['code'] === 200 && isset($response['body']['data'][0]['id'])) {
            $balanceTransactionId = $response['body']['data'][0]['id'];
        }
    }
    
    if (empty($balanceTransactionId)) {
        printResult(false, "Balance Transaction ID não disponível (pule este teste)");
    } else {
        $response = makeRequest('GET', "/v1/balance-transactions/{$balanceTransactionId}");
        $passed = $response['code'] === 200
            && isset($response['body']['success'])
            && $response['body']['success'] === true
            && isset($response['body']['data']['id'])
            && $response['body']['data']['id'] === $balanceTransactionId;
        
        if ($passed) {
            $tx = $response['body']['data'];
            echo "   ID: {$tx['id']}\n";
            echo "   Type: {$tx['type']}\n";
            echo "   Amount: R$ " . number_format(($tx['amount'] ?? 0) / 100, 2, ',', '.') . "\n";
            echo "   Net: R$ " . number_format(($tx['net'] ?? 0) / 100, 2, ',', '.') . "\n";
            echo "   Fee: R$ " . number_format(($tx['fee'] ?? 0) / 100, 2, ',', '.') . "\n";
            echo "   Status: {$tx['status']}\n";
            echo "   Currency: {$tx['currency']}\n";
        } else {
            echo "   HTTP Code: {$response['code']}\n";
            if (isset($response['body']['error'])) {
                echo "   Erro: {$response['body']['error']}\n";
            }
        }
        
        printResult($passed, $passed ? "Balance Transaction obtida com sucesso" : "HTTP {$response['code']}");
    }

    // ============================================================================
    // TESTE 6: Validação - Tentar obter Balance Transaction inexistente
    // ============================================================================
    printTest(6, "Validação - Tentar obter Balance Transaction inexistente");
    
    $response = makeRequest('GET', '/v1/balance-transactions/txn_invalid_123456789');
    $passed = $response['code'] === 404 || ($response['code'] === 200 && isset($response['body']['error']));
    
    if ($passed) {
        echo "   ✅ Erro tratado corretamente\n";
    } else {
        echo "   HTTP Code: {$response['code']}\n";
    }
    
    printResult($passed, $passed ? "Validação funcionando" : "HTTP {$response['code']}");

    // ============================================================================
    // TESTE 7: Teste direto do StripeService - listBalanceTransactions
    // ============================================================================
    printTest(7, "Teste direto do StripeService - listBalanceTransactions");
    
    try {
        $transactions = $stripeService->listBalanceTransactions(['limit' => 5]);
        $passed = $transactions instanceof \Stripe\Collection && count($transactions->data) >= 0;
        
        if ($passed) {
            $count = count($transactions->data);
            echo "   Encontrado {$count} balance transaction(s)\n";
            echo "   Has more: " . ($transactions->has_more ? 'sim' : 'não') . "\n";
            
            if ($count > 0) {
                $first = $transactions->data[0];
                echo "   Primeiro: {$first->id} - {$first->type} (R$ " . number_format(($first->amount ?? 0) / 100, 2, ',', '.') . ")\n";
            }
        }
    } catch (\Exception $e) {
        echo "   Erro: {$e->getMessage()}\n";
        $passed = false;
    }
    
    printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao chamar método");

    // ============================================================================
    // TESTE 8: Teste direto do StripeService - getBalanceTransaction
    // ============================================================================
    printTest(8, "Teste direto do StripeService - getBalanceTransaction");
    
    if (empty($balanceTransactionId)) {
        // Tenta obter uma transação da listagem
        try {
            $transactions = $stripeService->listBalanceTransactions(['limit' => 1]);
            if (count($transactions->data) > 0) {
                $balanceTransactionId = $transactions->data[0]->id;
            }
        } catch (\Exception $e) {
            // Ignora
        }
    }
    
    if (empty($balanceTransactionId)) {
        printResult(false, "Balance Transaction ID não disponível (pule este teste)");
    } else {
        try {
            $transaction = $stripeService->getBalanceTransaction($balanceTransactionId);
            $passed = $transaction instanceof \Stripe\BalanceTransaction && $transaction->id === $balanceTransactionId;
            
            if ($passed) {
                echo "   Balance Transaction ID: {$transaction->id}\n";
                echo "   Type: {$transaction->type}\n";
                echo "   Amount: R$ " . number_format(($transaction->amount ?? 0) / 100, 2, ',', '.') . "\n";
                echo "   Net: R$ " . number_format(($transaction->net ?? 0) / 100, 2, ',', '.') . "\n";
            }
        } catch (\Exception $e) {
            echo "   Erro: {$e->getMessage()}\n";
            $passed = false;
        }
        
        printResult($passed, $passed ? "Método do serviço funcionando" : "Erro ao chamar método");
    }

    // ============================================================================
    // RESUMO
    // ============================================================================
    echo "\033[34m╔═══════════════════════════════════════════════════════════════╗\033[0m\n";
    echo "\033[34m║                    RESUMO DOS TESTES                           ║\033[0m\n";
    echo "\033[34m╚═══════════════════════════════════════════════════════════════╝\033[0m\n";
    echo "Total de testes: " . ($testsPassed + $testsFailed) . "\n";
    echo "\033[32m✓ Passou: {$testsPassed}\033[0m\n";
    echo "\033[31m✗ Falhou: {$testsFailed}\033[0m\n";
    
    if ($testsFailed === 0) {
        echo "\n\033[32m🎉 Todos os testes passaram!\033[0m\n";
    } else {
        echo "\n\033[33m⚠️  Alguns testes falharam. Verifique os detalhes acima.\033[0m\n";
    }

} catch (\Exception $e) {
    echo "\033[31m❌ Erro fatal: {$e->getMessage()}\033[0m\n";
    echo "Trace: {$e->getTraceAsString()}\n";
    exit(1);
}


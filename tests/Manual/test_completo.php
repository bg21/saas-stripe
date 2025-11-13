<?php

/**
 * Teste completo simulando uma requisição real
 */

$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086';

echo "=== Teste Completo ===\n\n";

// Teste 1: Criar Cliente
echo "1. Criando Cliente:\n";
$ch = curl_init('http://localhost:8080/v1/customers');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'teste@example.com',
    'name' => 'Teste Usuario'
]));

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $status\n";
echo "   Resposta: $response\n\n";

$data = json_decode($response, true);
if (isset($data['success']) && $data['success']) {
    echo "   ✅ SUCESSO! Cliente criado!\n";
    echo "   Cliente ID: " . ($data['data']['id'] ?? 'N/A') . "\n";
    echo "   Stripe Customer ID: " . ($data['data']['stripe_customer_id'] ?? 'N/A') . "\n\n";
    
    // Teste 2: Listar Clientes
    echo "2. Listando Clientes:\n";
    $ch = curl_init('http://localhost:8080/v1/customers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   Status: $status\n";
    echo "   Resposta: $response\n\n";
    
    $data = json_decode($response, true);
    if (isset($data['success']) && $data['success']) {
        echo "   ✅ SUCESSO! Clientes listados!\n";
        echo "   Total: " . ($data['count'] ?? 0) . " cliente(s)\n";
    }
} else {
    echo "   ❌ Erro ao criar cliente\n";
    if (isset($data['error'])) {
        echo "   Erro: {$data['error']}\n";
        if (isset($data['message'])) {
            echo "   Detalhes: {$data['message']}\n";
        }
        if (isset($data['debug'])) {
            echo "   Debug: " . json_encode($data['debug'], JSON_PRETTY_PRINT) . "\n";
        }
    }
}

echo "\n=== Teste Concluído ===\n";


<?php

/**
 * Script de teste da API
 * Execute: php test_api.php
 */

$baseUrl = 'http://localhost:8080';
// API Key do banco (64 caracteres)
$apiKey = 'test_api_key_123456789012345678901234567890123456789012345678901';

echo "=== Testando API de Pagamentos SaaS ===\n\n";

// Teste 1: Health Check
echo "1. Testando Health Check...\n";
$response = makeRequest('GET', "$baseUrl/health");
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . $response['body'] . "\n\n";

// Teste 2: Listar Clientes
echo "2. Testando Listar Clientes...\n";
$response = makeRequest('GET', "$baseUrl/v1/customers", $apiKey);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . $response['body'] . "\n\n";

// Teste 3: Criar Cliente
echo "3. Testando Criar Cliente...\n";
$data = [
    'email' => 'cliente@example.com',
    'name' => 'João Silva'
];
$response = makeRequest('POST', "$baseUrl/v1/customers", $apiKey, $data);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . $response['body'] . "\n\n";

// Teste 4: Listar Clientes novamente (para ver o cliente criado)
echo "4. Listando Clientes novamente...\n";
$response = makeRequest('GET', "$baseUrl/v1/customers", $apiKey);
echo "Status: " . $response['status'] . "\n";
echo "Resposta: " . $response['body'] . "\n\n";

echo "=== Testes concluídos ===\n";

function makeRequest($method, $url, $apiKey = null, $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    
    if ($apiKey) {
        $headers[] = "Authorization: Bearer $apiKey";
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['status' => 'ERROR', 'body' => $error];
    }
    
    return ['status' => $status, 'body' => $body];
}


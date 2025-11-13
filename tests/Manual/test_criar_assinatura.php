<?php

/**
 * Script para testar criação de assinatura no Stripe
 * 
 * IMPORTANTE: Para criar uma assinatura, você precisa:
 * 1. Ter um customer_id válido (criado anteriormente)
 * 2. Ter um price_id válido do Stripe (criar um produto/preço no Stripe Dashboard)
 * 
 * Como obter um price_id:
 * - Acesse: https://dashboard.stripe.com/test/products
 * - Crie um produto e um preço
 * - Copie o price_id (começa com "price_")
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = 'SUA_API_KEY_AQUI'; // Substitua pela sua API key do tenant
$baseUrl = 'http://localhost:8080';

echo "=== Teste de Criação de Assinatura no Stripe ===" . PHP_EOL . PHP_EOL;

// 1. Primeiro, vamos listar os clientes para pegar um customer_id
echo "1. Listando clientes..." . PHP_EOL;
$ch = curl_init($baseUrl . '/v1/customers');
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

if ($httpCode !== 200) {
    echo "❌ Erro ao listar clientes: " . $response . PHP_EOL;
    exit(1);
}

$customersData = json_decode($response, true);
$customers = $customersData['data'] ?? [];

if (empty($customers)) {
    echo "❌ Nenhum cliente encontrado. Crie um cliente primeiro!" . PHP_EOL;
    echo "   Use: POST /v1/customers com email e name" . PHP_EOL;
    exit(1);
}

echo "✅ Clientes encontrados: " . count($customers) . PHP_EOL;
$customer = $customers[0];
echo "   Usando cliente ID: " . $customer['id'] . PHP_EOL;
echo "   Stripe Customer ID: " . $customer['stripe_customer_id'] . PHP_EOL . PHP_EOL;

// 2. Solicitar price_id do usuário
echo "2. Para criar uma assinatura, você precisa de um price_id do Stripe." . PHP_EOL;
echo "   Acesse: https://dashboard.stripe.com/test/products" . PHP_EOL;
echo "   Crie um produto e um preço, depois copie o price_id (começa com 'price_')" . PHP_EOL . PHP_EOL;

// Se você quiser testar automaticamente, descomente e adicione um price_id válido:
// $priceId = 'price_1234567890'; // Substitua por um price_id válido do seu Stripe

// Para este teste, vamos pedir ao usuário para inserir
echo "Digite o price_id do Stripe (ou pressione Enter para pular): ";
$priceId = trim(fgets(STDIN));

if (empty($priceId)) {
    echo PHP_EOL . "⚠️  Teste pulado. Para testar:" . PHP_EOL;
    echo "   1. Crie um produto/preço no Stripe Dashboard" . PHP_EOL;
    echo "   2. Execute este script novamente com o price_id" . PHP_EOL;
    echo "   3. Ou use a API diretamente:" . PHP_EOL . PHP_EOL;
    echo "   curl -X POST http://localhost:8080/v1/subscriptions \\" . PHP_EOL;
    echo "     -H \"Authorization: Bearer $apiKey\" \\" . PHP_EOL;
    echo "     -H \"Content-Type: application/json\" \\" . PHP_EOL;
    echo "     -d '{" . PHP_EOL;
    echo "       \"customer_id\": " . $customer['id'] . "," . PHP_EOL;
    echo "       \"price_id\": \"price_1234567890\"" . PHP_EOL;
    echo "     }'" . PHP_EOL;
    exit(0);
}

// 3. Criar assinatura
echo PHP_EOL . "3. Criando assinatura..." . PHP_EOL;
echo "   Customer ID (banco): " . $customer['id'] . PHP_EOL;
echo "   Price ID (Stripe): " . $priceId . PHP_EOL . PHP_EOL;

$ch = curl_init($baseUrl . '/v1/subscriptions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'customer_id' => (int)$customer['id'],
        'price_id' => $priceId
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $httpCode" . PHP_EOL;
echo "Resposta: " . $response . PHP_EOL . PHP_EOL;

if ($httpCode === 201) {
    $data = json_decode($response, true);
    echo "✅ Assinatura criada com sucesso!" . PHP_EOL;
    echo "   Subscription ID (banco): " . ($data['data']['id'] ?? 'N/A') . PHP_EOL;
    echo "   Stripe Subscription ID: " . ($data['data']['stripe_subscription_id'] ?? 'N/A') . PHP_EOL;
    echo "   Status: " . ($data['data']['status'] ?? 'N/A') . PHP_EOL;
    echo PHP_EOL . "✅ TESTE CONCLUÍDO COM SUCESSO!" . PHP_EOL;
} else {
    echo "❌ Erro ao criar assinatura" . PHP_EOL;
    $error = json_decode($response, true);
    if (isset($error['message'])) {
        echo "   Mensagem: " . $error['message'] . PHP_EOL;
    }
    echo PHP_EOL . "Possíveis causas:" . PHP_EOL;
    echo "   - Price ID inválido ou não existe no Stripe" . PHP_EOL;
    echo "   - Customer não tem método de pagamento configurado" . PHP_EOL;
    echo "   - Problemas de conectividade com Stripe" . PHP_EOL;
    exit(1);
}


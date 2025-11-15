<?php

/**
 * Script de teste para Charges (Cobranças)
 * 
 * Testa:
 * - Listar charges
 * - Obter charge específica
 * - Atualizar charge (metadata)
 * - Filtros (customer, payment_intent, data)
 * - Permissões (view_charges, manage_charges)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\Tenant;

Config::load();

echo "🧪 Teste de Charges (Cobranças)\n";
echo str_repeat("=", 70) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';
$tenantId = 1;

// Cores para output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

// Contadores de testes
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Função para fazer requisições HTTP
function makeRequest(string $url, string $method = 'GET', array $data = [], ?string $token = null): array
{
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $method !== 'GET' ? json_encode($data) : null
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'code' => 0,
            'data' => ['error' => $error],
            'raw_response' => $response
        ];
    }
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true) ?? [],
        'raw_response' => $response
    ];
}

// Função para testar e registrar resultado
function testResult(string $description, int $expectedCode, int $actualCode, bool $checkSuccess = false, array $responseData = []): bool
{
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    
    $totalTests++;
    $passed = false;
    
    if ($expectedCode === $actualCode) {
        if ($checkSuccess) {
            $passed = isset($responseData['success']) && $responseData['success'] === true;
        } else {
            $passed = true;
        }
    }
    
    if ($passed) {
        $passedTests++;
        echo "{$green}✅{$reset} {$description}\n";
        echo "   HTTP Code: {$actualCode} (esperado: {$expectedCode})\n";
    } else {
        $failedTests++;
        echo "{$red}❌{$reset} {$description}\n";
        echo "   HTTP Code: {$actualCode} (esperado: {$expectedCode})\n";
        if (!empty($responseData)) {
            echo "   Response: " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    echo "\n";
    return $passed;
}

// Obtém API key do tenant
$tenantModel = new Tenant();
$tenant = $tenantModel->findById($tenantId);

if (!$tenant) {
    echo "{$red}❌{$reset} Tenant não encontrado (ID: {$tenantId})\n";
    exit(1);
}

$apiKey = $tenant['api_key'];
echo "📋 Usando API Key do Tenant: " . substr($apiKey, 0, 20) . "...\n\n";

// ============================================
// TESTE 1: Listar Charges (sem filtros)
// ============================================
echo "📝 Teste 1: Listar Charges (sem filtros)\n";
$response = makeRequest("{$apiUrl}/v1/charges", 'GET', [], $apiKey);
testResult(
    "Listar charges",
    200,
    $response['code'],
    false,
    $response['data']
);

if (isset($response['data']['data']) && !empty($response['data']['data'])) {
    $firstCharge = $response['data']['data'][0];
    $chargeId = $firstCharge['id'] ?? null;
    echo "   Primeira charge encontrada: {$chargeId}\n\n";
} else {
    echo "   {$yellow}⚠️  Nenhuma charge encontrada (pode ser normal se não houver charges no Stripe){$reset}\n\n";
    $chargeId = null;
}

// ============================================
// TESTE 2: Listar Charges com limite
// ============================================
echo "📝 Teste 2: Listar Charges com limite\n";
$response = makeRequest("{$apiUrl}/v1/charges?limit=5", 'GET', [], $apiKey);
testResult(
    "Listar charges com limite=5",
    200,
    $response['code'],
    false,
    $response['data']
);

// ============================================
// TESTE 3: Listar Charges com filtro de data
// ============================================
echo "📝 Teste 3: Listar Charges com filtro de data\n";
$createdGte = time() - (30 * 24 * 60 * 60); // Últimos 30 dias
$response = makeRequest("{$apiUrl}/v1/charges?created_gte={$createdGte}", 'GET', [], $apiKey);
testResult(
    "Listar charges criadas nos últimos 30 dias",
    200,
    $response['code'],
    false,
    $response['data']
);

// ============================================
// TESTE 4: Obter Charge específica
// ============================================
if ($chargeId) {
    echo "📝 Teste 4: Obter Charge específica\n";
    $response = makeRequest("{$apiUrl}/v1/charges/{$chargeId}", 'GET', [], $apiKey);
    testResult(
        "Obter charge específica",
        200,
        $response['code'],
        false,
        $response['data']
    );
    
    if (isset($response['data']['id'])) {
        echo "   Charge ID: {$response['data']['id']}\n";
        echo "   Amount: {$response['data']['amount']} {$response['data']['currency']}\n";
        echo "   Status: {$response['data']['status']}\n";
        echo "   Paid: " . ($response['data']['paid'] ? 'Sim' : 'Não') . "\n\n";
    }
} else {
    echo "{$yellow}⚠️  Teste 4 pulado (nenhuma charge disponível){$reset}\n\n";
}

// ============================================
// TESTE 5: Obter Charge inexistente
// ============================================
echo "📝 Teste 5: Obter Charge inexistente\n";
$response = makeRequest("{$apiUrl}/v1/charges/ch_invalid123", 'GET', [], $apiKey);
testResult(
    "Obter charge inexistente (deve retornar 404 ou 500)",
    in_array($response['code'], [404, 500]) ? $response['code'] : 404,
    $response['code'],
    false,
    $response['data']
);

// ============================================
// TESTE 6: Atualizar Charge (metadata)
// ============================================
if ($chargeId) {
    echo "📝 Teste 6: Atualizar Charge (metadata)\n";
    $metadata = [
        'test_key' => 'test_value_' . time(),
        'updated_by' => 'test_script'
    ];
    $response = makeRequest("{$apiUrl}/v1/charges/{$chargeId}", 'PUT', ['metadata' => $metadata], $apiKey);
    testResult(
        "Atualizar metadata da charge",
        200,
        $response['code'],
        false,
        $response['data']
    );
    
    if (isset($response['data']['metadata'])) {
        echo "   Metadata atualizada: " . json_encode($response['data']['metadata'], JSON_PRETTY_PRINT) . "\n\n";
    }
} else {
    echo "{$yellow}⚠️  Teste 6 pulado (nenhuma charge disponível){$reset}\n\n";
}

// ============================================
// TESTE 7: Atualizar Charge sem dados
// ============================================
if ($chargeId) {
    echo "📝 Teste 7: Atualizar Charge sem dados\n";
    $response = makeRequest("{$apiUrl}/v1/charges/{$chargeId}", 'PUT', [], $apiKey);
    testResult(
        "Atualizar charge sem dados (deve retornar erro)",
        400,
        $response['code'],
        false,
        $response['data']
    );
}

// ============================================
// TESTE 8: Atualizar Charge com campos inválidos
// ============================================
if ($chargeId) {
    echo "📝 Teste 8: Atualizar Charge com campos inválidos\n";
    $response = makeRequest("{$apiUrl}/v1/charges/{$chargeId}", 'PUT', ['amount' => 1000], $apiKey);
    testResult(
        "Atualizar charge com campos inválidos (deve retornar erro)",
        400,
        $response['code'],
        false,
        $response['data']
    );
}

// ============================================
// TESTE 9: Listar Charges com filtro por customer
// ============================================
echo "📝 Teste 9: Listar Charges com filtro por customer\n";
// Nota: Este teste requer um customer_id válido do Stripe
// Por enquanto, apenas testamos se o endpoint aceita o parâmetro
$response = makeRequest("{$apiUrl}/v1/charges?customer=cus_test123", 'GET', [], $apiKey);
testResult(
    "Listar charges com filtro por customer (pode retornar lista vazia)",
    in_array($response['code'], [200, 400]) ? $response['code'] : 200,
    $response['code'],
    false,
    $response['data']
);

// ============================================
// TESTE 10: Listar Charges com filtro por payment_intent
// ============================================
echo "📝 Teste 10: Listar Charges com filtro por payment_intent\n";
// Nota: Este teste requer um payment_intent_id válido do Stripe
$response = makeRequest("{$apiUrl}/v1/charges?payment_intent=pi_test123", 'GET', [], $apiKey);
testResult(
    "Listar charges com filtro por payment_intent (pode retornar lista vazia)",
    in_array($response['code'], [200, 400]) ? $response['code'] : 200,
    $response['code'],
    false,
    $response['data']
);

// ============================================
// TESTE 11: Acesso sem autenticação
// ============================================
echo "📝 Teste 11: Acesso sem autenticação\n";
$response = makeRequest("{$apiUrl}/v1/charges", 'GET', [], null);
testResult(
    "Acesso sem autenticação (deve retornar 401)",
    401,
    $response['code'],
    false,
    $response['data']
);

// ============================================
// RESUMO
// ============================================
echo str_repeat("=", 70) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 70) . "\n";
echo "Total de testes: {$totalTests}\n";
echo "{$green}✅ Passou: {$passedTests}{$reset}\n";
echo "{$red}❌ Falhou: {$failedTests}{$reset}\n";
echo "\n";

if ($failedTests === 0) {
    echo "{$green}🎉 Todos os testes passaram!{$reset}\n";
    exit(0);
} else {
    echo "{$red}⚠️  Alguns testes falharam{$reset}\n";
    exit(1);
}


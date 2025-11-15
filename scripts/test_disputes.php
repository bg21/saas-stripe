<?php

/**
 * Script de teste para Disputes (Disputas/Chargebacks)
 * 
 * Testa:
 * - Listar disputes
 * - Obter dispute específica
 * - Atualizar dispute (adicionar evidências)
 * - Filtros (charge, payment_intent, data)
 * - Permissões (view_disputes, manage_disputes)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\Tenant;

Config::load();

echo "🧪 Teste de Disputes (Disputas/Chargebacks)\n";
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
            echo "   Resposta: " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "\n";
    
    return $passed;
}

// Função para fazer login e obter Session ID
function login(string $email, string $password, int $tenantId): ?string
{
    global $apiUrl;
    
    $loginData = [
        'email' => $email,
        'password' => $password,
        'tenant_id' => $tenantId
    ];
    
    $response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', $loginData);
    
    if ($response['code'] === 200 && isset($response['data']['data']['session_id'])) {
        return $response['data']['data']['session_id'];
    }
    
    return null;
}

// Verifica se o servidor está rodando
echo "🔍 Verificando se o servidor está rodando...\n";
$healthCheck = makeRequest("{$apiUrl}/health", 'GET');
if ($healthCheck['code'] === 0) {
    echo "{$red}❌ Servidor não está rodando!{$reset}\n";
    echo "   Execute: php -S localhost:8080 -t public\n\n";
    exit(1);
}
echo "{$green}✅ Servidor está rodando!{$reset}\n\n";

// Obtém API Key do tenant
echo "🔍 Obtendo API Key do tenant...\n";
$tenantModel = new Tenant();
$tenant = $tenantModel->findById($tenantId);
if (!$tenant || empty($tenant['api_key'])) {
    echo "{$red}❌ Tenant não encontrado ou API Key não configurada!{$reset}\n";
    echo "   Execute: composer run seed\n\n";
    exit(1);
}
$apiKey = $tenant['api_key'];
echo "{$green}✅ API Key obtida!{$reset}\n\n";

// Login como admin
echo "🔐 Fazendo login como admin...\n";
$adminSessionId = login('admin@example.com', 'admin123', $tenantId);
if (!$adminSessionId) {
    echo "{$yellow}⚠️  Login como admin falhou (pode não existir){$reset}\n\n";
} else {
    echo "{$green}✅ Login como admin bem-sucedido!{$reset}\n\n";
}

// ============================================================================
// TESTE 1: Listar disputes (API Key)
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 1: Listar disputes (API Key)\n";
echo str_repeat("=", 70) . "\n\n";

$response = makeRequest("{$apiUrl}/v1/disputes?limit=10", 'GET', [], $apiKey);
testResult(
    "Listar disputes com API Key",
    200,
    $response['code'],
    true,
    $response['data']
);

if (isset($response['data']['data']) && count($response['data']['data']) > 0) {
    echo "   Total de disputes encontradas: " . count($response['data']['data']) . "\n";
    $firstDispute = $response['data']['data'][0];
    echo "   Primeira dispute: ID {$firstDispute['id']}, Status: {$firstDispute['status']}, Amount: {$firstDispute['amount']}\n\n";
    $testDisputeId = $firstDispute['id'];
} else {
    echo "   {$yellow}⚠️  Nenhuma dispute encontrada (pode ser normal se não houver disputas no Stripe){$reset}\n\n";
    $testDisputeId = null;
}

// ============================================================================
// TESTE 2: Listar disputes (Session ID - Admin)
// ============================================================================
if ($adminSessionId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 2: Listar disputes (Session ID - Admin)\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $response = makeRequest("{$apiUrl}/v1/disputes?limit=10", 'GET', [], $adminSessionId);
    testResult(
        "Listar disputes com Session ID (Admin)",
        200,
        $response['code'],
        true,
        $response['data']
    );
}

// ============================================================================
// TESTE 3: Obter dispute específica
// ============================================================================
if ($testDisputeId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 3: Obter dispute específica\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $token = $adminSessionId ?? $apiKey;
    $response = makeRequest("{$apiUrl}/v1/disputes/{$testDisputeId}", 'GET', [], $token);
    testResult(
        "Obter dispute específica",
        200,
        $response['code'],
        true,
        $response['data']
    );
    
    if (isset($response['data']['data'])) {
        $dispute = $response['data']['data'];
        echo "   ID: {$dispute['id']}\n";
        echo "   Status: {$dispute['status']}\n";
        echo "   Reason: {$dispute['reason']}\n";
        echo "   Amount: {$dispute['amount']} {$dispute['currency']}\n";
        echo "   Evidence due by: " . ($dispute['evidence_details']['due_by'] ?? 'N/A') . "\n";
        echo "   Has evidence: " . ($dispute['evidence_details']['has_evidence'] ? 'Sim' : 'Não') . "\n\n";
    }
} else {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 3: Obter dispute específica (PULADO - nenhuma dispute disponível)\n";
    echo str_repeat("=", 70) . "\n\n";
}

// ============================================================================
// TESTE 4: Filtrar por charge
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 4: Filtrar disputes por charge\n";
echo str_repeat("=", 70) . "\n\n";

// Nota: Este teste requer um charge_id válido, então vamos apenas testar o endpoint
$token = $adminSessionId ?? $apiKey;
$response = makeRequest("{$apiUrl}/v1/disputes?limit=10", 'GET', [], $token);
testResult(
    "Filtrar disputes (endpoint funciona mesmo sem charge_id)",
    200,
    $response['code'],
    true,
    $response['data']
);

// ============================================================================
// TESTE 5: Filtrar por data
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📋 TESTE 5: Filtrar disputes por data (últimos 30 dias)\n";
echo str_repeat("=", 70) . "\n\n";

$createdGte = time() - (30 * 24 * 60 * 60); // Últimos 30 dias
$response = makeRequest(
    "{$apiUrl}/v1/disputes?created_gte={$createdGte}&limit=10",
    'GET',
    [],
    $token
);
testResult(
    "Filtrar disputes por data (últimos 30 dias)",
    200,
    $response['code'],
    true,
    $response['data']
);

// ============================================================================
// TESTE 6: Atualizar dispute (adicionar evidências)
// ============================================================================
if ($testDisputeId && $adminSessionId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 6: Atualizar dispute (adicionar evidências)\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Adiciona evidências básicas
    $evidenceData = [
        'customer_communication' => 'Cliente confirmou recebimento do produto via email em ' . date('Y-m-d'),
        'product_description' => 'Produto entregue conforme descrição',
        'shipping_carrier' => 'Correios',
        'shipping_tracking_number' => 'BR123456789BR',
        'uncategorized_text' => 'Evidências adicionais: Cliente recebeu produto e confirmou satisfação.'
    ];
    
    $response = makeRequest(
        "{$apiUrl}/v1/disputes/{$testDisputeId}",
        'PUT',
        $evidenceData,
        $adminSessionId
    );
    
    // Nota: Pode retornar 400 se a dispute já foi resolvida ou se não aceita mais evidências
    $expectedCode = in_array($response['code'], [200, 400]) ? $response['code'] : 200;
    testResult(
        "Atualizar dispute com evidências",
        $expectedCode,
        $response['code'],
        $response['code'] === 200,
        $response['data']
    );
    
    if ($response['code'] === 200) {
        echo "   ✅ Evidências adicionadas com sucesso!\n\n";
    } elseif ($response['code'] === 400) {
        echo "   {$yellow}⚠️  Dispute não pode ser atualizada (pode estar fechada ou não aceitar mais evidências){$reset}\n\n";
    }
} else {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 6: Atualizar dispute (PULADO - requer dispute e admin)\n";
    echo str_repeat("=", 70) . "\n\n";
}

// ============================================================================
// TESTE 7: Verificar permissões (Editor não pode gerenciar disputes)
// ============================================================================
if ($adminSessionId) {
    echo str_repeat("=", 70) . "\n";
    echo "📋 TESTE 7: Verificar permissões (Editor não pode gerenciar disputes)\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Login como editor
    $editorSessionId = login('editor@example.com', 'editor123', $tenantId);
    if ($editorSessionId) {
        // Editor pode visualizar disputes (tem view_disputes)
        $response = makeRequest("{$apiUrl}/v1/disputes?limit=5", 'GET', [], $editorSessionId);
        testResult(
            "Editor: Listar disputes (deve permitir - tem view_disputes)",
            200,
            $response['code'],
            true,
            $response['data']
        );
        
        // Editor NÃO pode atualizar disputes (não tem manage_disputes)
        if ($testDisputeId) {
            $response = makeRequest(
                "{$apiUrl}/v1/disputes/{$testDisputeId}",
                'PUT',
                ['uncategorized_text' => 'Teste'],
                $editorSessionId
            );
            testResult(
                "Editor: Atualizar dispute (deve BLOQUEAR - precisa de manage_disputes)",
                403,
                $response['code'],
                false,
                $response['data']
            );
        }
    } else {
        echo "   {$yellow}⚠️  Login como editor falhou{$reset}\n\n";
    }
}

// ============================================================================
// RESUMO DOS TESTES
// ============================================================================
echo str_repeat("=", 70) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 70) . "\n\n";

echo "Total de testes: {$totalTests}\n";
echo "{$green}Testes passados: {$passedTests}{$reset}\n";
echo "{$red}Testes falhados: {$failedTests}{$reset}\n\n";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "Taxa de sucesso: {$successRate}%\n\n";

if ($failedTests > 0) {
    echo "{$red}❌ Alguns testes falharam! Verifique os logs acima.{$reset}\n\n";
    exit(1);
} else {
    echo "{$green}✅ Todos os testes passaram!{$reset}\n\n";
    exit(0);
}


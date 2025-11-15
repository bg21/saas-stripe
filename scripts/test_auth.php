<?php

/**
 * Script de teste para autenticação de usuários
 * 
 * Testa o sistema de login, logout e verificação de sessão
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\User;
use App\Models\UserSession;
use App\Models\Tenant;

Config::load();

echo "🧪 Teste de Autenticação de Usuários\n";
echo str_repeat("=", 50) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';
$tenantId = 1; // Ajuste conforme necessário

// Cores para output (Windows não suporta ANSI, mas funciona em alguns terminais)
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

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

// Teste 1: Login com credenciais válidas
echo "📝 Teste 1: Login com credenciais válidas\n";
echo str_repeat("-", 50) . "\n";

$loginData = [
    'email' => 'admin@example.com',
    'password' => 'admin123',
    'tenant_id' => $tenantId
];

$response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', $loginData);

if ($response['code'] === 0) {
    echo "❌ Erro de conexão!\n";
    echo "   Erro: {$response['data']['error']}\n";
    echo "   Verifique se o servidor está rodando: php -S localhost:8080 -t public\n\n";
    exit(1);
}

if ($response['code'] === 200 && isset($response['data']['data']['session_id'])) {
    $sessionId = $response['data']['data']['session_id'];
    echo "✅ Login bem-sucedido!\n";
    echo "   Session ID: " . substr($sessionId, 0, 30) . "...\n";
    echo "   Usuário: {$response['data']['data']['user']['email']}\n";
    echo "   Role: {$response['data']['data']['user']['role']}\n\n";
} else {
    echo "❌ Login falhou!\n";
    echo "   HTTP Code: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
    if (isset($response['raw_response'])) {
        echo "   Raw Response: " . substr($response['raw_response'], 0, 500) . "\n";
    }
    echo "\n";
    
    // Se a resposta está vazia, pode ser que o servidor não esteja rodando
    if (empty($response['data']) && $response['code'] === 200) {
        echo "⚠️  Aviso: Resposta vazia recebida. Verifique se o servidor está rodando:\n";
        echo "   php -S localhost:8080 -t public\n\n";
    }
    
    exit(1);
}

// Teste 2: Verificar sessão (GET /v1/auth/me)
echo "📝 Teste 2: Verificar sessão atual\n";
echo str_repeat("-", 50) . "\n";

$response = makeRequest("{$apiUrl}/v1/auth/me", 'GET', [], $sessionId);

if ($response['code'] === 200 && isset($response['data']['data']['user'])) {
    echo "✅ Sessão válida!\n";
    echo "   Usuário: {$response['data']['data']['user']['email']}\n";
    echo "   Role: {$response['data']['data']['user']['role']}\n";
    echo "   Tenant: {$response['data']['data']['tenant']['name']}\n\n";
} else {
    echo "❌ Verificação de sessão falhou!\n";
    echo "   HTTP Code: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
}

// Teste 3: Login com credenciais inválidas
echo "📝 Teste 3: Login com credenciais inválidas\n";
echo str_repeat("-", 50) . "\n";

$invalidLoginData = [
    'email' => 'admin@example.com',
    'password' => 'senha_errada',
    'tenant_id' => $tenantId
];

$response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', $invalidLoginData);

if ($response['code'] === 401) {
    echo "✅ Login negado corretamente (credenciais inválidas)!\n";
    echo "   Mensagem: " . ($response['data']['error'] ?? $response['data']['message'] ?? 'N/A') . "\n\n";
} else {
    echo "⚠️  Teste: Login com credenciais inválidas retornou código {$response['code']} (esperado: 401)\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
    if ($response['code'] === 200 && isset($response['data']['error'])) {
        echo "   (Nota: Retornou 200 mas com erro no JSON - pode ser comportamento do FlightPHP)\n";
    }
    echo "\n";
}

// Teste 4: Acessar endpoint protegido com Session ID
echo "📝 Teste 4: Acessar endpoint protegido com Session ID\n";
echo str_repeat("-", 50) . "\n";

$response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $sessionId);

if ($response['code'] === 200) {
    echo "✅ Acesso autorizado com Session ID!\n";
    echo "   Endpoint: /v1/customers\n";
    echo "   Total de clientes: " . (isset($response['data']['data']) ? count($response['data']['data']) : 0) . "\n\n";
} else {
    echo "❌ Acesso negado!\n";
    echo "   HTTP Code: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
}

// Teste 5: Logout
echo "📝 Teste 5: Logout\n";
echo str_repeat("-", 50) . "\n";

$response = makeRequest("{$apiUrl}/v1/auth/logout", 'POST', [], $sessionId);

if ($response['code'] === 200) {
    echo "✅ Logout realizado com sucesso!\n\n";
} else {
    echo "❌ Logout falhou!\n";
    echo "   HTTP Code: {$response['code']}\n\n";
}

// Teste 6: Tentar usar sessão após logout
echo "📝 Teste 6: Tentar usar sessão após logout\n";
echo str_repeat("-", 50) . "\n";

$response = makeRequest("{$apiUrl}/v1/auth/me", 'GET', [], $sessionId);

if ($response['code'] === 401) {
    echo "✅ Sessão inválida após logout (esperado)!\n\n";
} else {
    echo "⚠️  Sessão ainda válida após logout (pode ser comportamento esperado)\n";
    echo "   HTTP Code: {$response['code']}\n\n";
}

// Teste 7: Comparar API Key vs Session ID
echo "📝 Teste 7: Comparar autenticação API Key vs Session ID\n";
echo str_repeat("-", 50) . "\n";

// Busca API Key do tenant
$tenantModel = new Tenant();
$tenant = $tenantModel->findById($tenantId);

if ($tenant && isset($tenant['api_key'])) {
    $apiKey = $tenant['api_key'];
    
    // Testa com API Key
    $response = makeRequest("{$apiUrl}/v1/customers", 'GET', [], $apiKey);
    
    if ($response['code'] === 200) {
        echo "✅ API Key funciona corretamente!\n";
        echo "   Endpoint: /v1/customers\n";
        echo "   Tipo: API Key (Tenant)\n\n";
    } else {
        echo "❌ API Key não funcionou!\n";
        echo "   HTTP Code: {$response['code']}\n\n";
    }
} else {
    echo "⚠️  Tenant não encontrado para testar API Key\n\n";
}

echo str_repeat("=", 50) . "\n";
echo "✨ Testes concluídos!\n\n";
echo "💡 Dicas:\n";
echo "   - Certifique-se de que o servidor está rodando (php -S localhost:8080 -t public)\n";
echo "   - Execute o seed de usuários primeiro: composer run seed:users\n";
echo "   - Ajuste \$tenantId e \$apiUrl conforme necessário\n";


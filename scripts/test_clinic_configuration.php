<?php

/**
 * Script de teste para os endpoints de configuração da clínica
 * 
 * Testa:
 * - GET /v1/clinic/configuration
 * - PUT /v1/clinic/configuration
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Tenant;
use App\Models\User;

echo "🧪 Teste dos Endpoints de Configuração da Clínica\n";
echo str_repeat("=", 60) . "\n\n";

// Configurações
$apiUrl = 'http://localhost:8080';

// Cores para output
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
        CURLOPT_POSTFIELDS => !empty($data) ? json_encode($data) : null
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    return [
        'code' => $httpCode,
        'data' => $decoded ?: $response
    ];
}

// Função para fazer login e obter session ID
function login(string $email, string $password, int $tenantId): ?string
{
    global $apiUrl;
    
    $response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', [
        'email' => $email,
        'password' => $password,
        'tenant_id' => $tenantId
    ]);
    
    if ($response['code'] === 200 && isset($response['data']['data']['session_id'])) {
        return $response['data']['data']['session_id'];
    }
    
    return null;
}

// Teste 1: Obter configuração (sem autenticação - deve falhar)
echo "{$blue}Teste 1: GET /v1/clinic/configuration (sem autenticação){$reset}\n";
$response = makeRequest("{$apiUrl}/v1/clinic/configuration");
if ($response['code'] === 401 || $response['code'] === 403) {
    echo "{$green}✅ Passou: Retornou erro de autenticação como esperado{$reset}\n";
} else {
    echo "{$red}❌ Falhou: Esperava 401/403, recebeu {$response['code']}{$reset}\n";
}
echo "\n";

// Busca um tenant e usuário para teste
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll();
if (empty($tenants)) {
    echo "{$red}❌ Nenhum tenant encontrado. Execute o seed primeiro.{$reset}\n";
    exit(1);
}

$tenant = $tenants[0];
echo "{$blue}Usando tenant: {$tenant['name']} (ID: {$tenant['id']}){$reset}\n\n";

// Busca um usuário admin
$userModel = new User();
$users = $userModel->findByTenant($tenant['id']);
$adminUser = null;
foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $adminUser = $user;
        break;
    }
}

if (!$adminUser) {
    echo "{$yellow}⚠️  Nenhum usuário admin encontrado. Tentando fazer login com qualquer usuário...{$reset}\n";
    $adminUser = $users[0] ?? null;
}

if (!$adminUser) {
    echo "{$red}❌ Nenhum usuário encontrado para o tenant. Execute o seed primeiro.{$reset}\n";
    exit(1);
}

echo "{$blue}Usando usuário: {$adminUser['email']} (ID: {$adminUser['id']}, Role: {$adminUser['role']}){$reset}\n\n";

// Verifica se o servidor está rodando
echo "{$blue}Verificando se o servidor está rodando...{$reset}\n";
$healthCheck = makeRequest("{$apiUrl}/health", 'GET');
if ($healthCheck['code'] === 0) {
    echo "{$red}❌ Servidor não está rodando!{$reset}\n";
    echo "   Execute: php -S localhost:8080 -t public\n\n";
    exit(1);
}
echo "{$green}✅ Servidor está rodando!{$reset}\n\n";

// Faz login
echo "{$blue}Fazendo login...{$reset}\n";
$sessionId = login($adminUser['email'], 'admin123', $tenant['id']); // Senha padrão

if (!$sessionId) {
    echo "{$yellow}⚠️  Tentando com senha 'senha123'...{$reset}\n";
    $sessionId = login($adminUser['email'], 'senha123', $tenant['id']);
}

if (!$sessionId) {
    echo "{$red}❌ Não foi possível fazer login. Verifique as credenciais.{$reset}\n";
    echo "   Email: {$adminUser['email']}\n";
    echo "   Tenant ID: {$tenant['id']}\n";
    echo "   Tente: admin123 ou senha123\n\n";
    exit(1);
}

echo "{$green}✅ Login realizado com sucesso{$reset}\n";
echo "   Session ID: " . substr($sessionId, 0, 20) . "...\n\n";

// Teste 2: Obter configuração (com autenticação)
echo "{$blue}Teste 2: GET /v1/clinic/configuration (com autenticação){$reset}\n";
$response = makeRequest("{$apiUrl}/v1/clinic/configuration", 'GET', [], $sessionId);

if ($response['code'] === 200) {
    echo "{$green}✅ Passou: Configuração obtida com sucesso{$reset}\n";
    $config = $response['data']['data'] ?? [];
    echo "  - ID: " . ($config['id'] ?? 'null') . "\n";
    echo "  - Duração padrão: " . ($config['default_appointment_duration'] ?? 'N/A') . " minutos\n";
    echo "  - Intervalo: " . ($config['time_slot_interval'] ?? 'N/A') . " minutos\n";
    echo "  - Agendamento online: " . (($config['allow_online_booking'] ?? 0) ? 'Sim' : 'Não') . "\n";
} else {
    echo "{$red}❌ Falhou: Código {$response['code']}{$reset}\n";
    if (isset($response['data']['error'])) {
        echo "  Erro: {$response['data']['error']}\n";
    }
}
echo "\n";

// Teste 3: Atualizar configuração
echo "{$blue}Teste 3: PUT /v1/clinic/configuration{$reset}\n";
$updateData = [
    'default_appointment_duration' => 45,
    'time_slot_interval' => 15,
    'allow_online_booking' => 1,
    'require_confirmation' => 1,
    'cancellation_hours' => 24,
    'opening_time_monday' => '08:00',
    'closing_time_monday' => '18:00',
    'opening_time_tuesday' => '08:00',
    'closing_time_tuesday' => '18:00',
    'opening_time_wednesday' => '08:00',
    'closing_time_wednesday' => '18:00',
    'opening_time_thursday' => '08:00',
    'closing_time_thursday' => '18:00',
    'opening_time_friday' => '08:00',
    'closing_time_friday' => '18:00',
    'opening_time_saturday' => '08:00',
    'closing_time_saturday' => '12:00',
    'opening_time_sunday' => null,
    'closing_time_sunday' => null
];

$response = makeRequest("{$apiUrl}/v1/clinic/configuration", 'PUT', $updateData, $sessionId);

if ($response['code'] === 200) {
    echo "{$green}✅ Passou: Configuração atualizada com sucesso{$reset}\n";
    $config = $response['data']['data'] ?? [];
    echo "  - Duração padrão: {$config['default_appointment_duration']} minutos\n";
    echo "  - Intervalo: {$config['time_slot_interval']} minutos\n";
    echo "  - Agendamento online: " . ($config['allow_online_booking'] ? 'Sim' : 'Não') . "\n";
    echo "  - Requer confirmação: " . ($config['require_confirmation'] ? 'Sim' : 'Não') . "\n";
} else {
    echo "{$red}❌ Falhou: Código {$response['code']}{$reset}\n";
    if (isset($response['data']['error'])) {
        echo "  Erro: {$response['data']['error']}\n";
    }
    if (isset($response['data']['errors'])) {
        echo "  Erros de validação:\n";
        foreach ($response['data']['errors'] as $field => $error) {
            echo "    - {$field}: {$error}\n";
        }
    }
}
echo "\n";

// Teste 4: Obter configuração atualizada
echo "{$blue}Teste 4: GET /v1/clinic/configuration (verificar atualização){$reset}\n";
$response = makeRequest("{$apiUrl}/v1/clinic/configuration", 'GET', [], $sessionId);

if ($response['code'] === 200) {
    $config = $response['data']['data'] ?? [];
    if (($config['default_appointment_duration'] ?? 0) === 45) {
        echo "{$green}✅ Passou: Configuração foi atualizada corretamente{$reset}\n";
    } else {
        echo "{$yellow}⚠️  Configuração obtida, mas valor não corresponde ao esperado{$reset}\n";
    }
} else {
    echo "{$red}❌ Falhou ao obter configuração{$reset}\n";
}
echo "\n";

// Teste 5: Validação de dados inválidos
echo "{$blue}Teste 5: PUT /v1/clinic/configuration (validação - dados inválidos){$reset}\n";
$invalidData = [
    'default_appointment_duration' => 300, // Muito alto (máx: 240)
    'time_slot_interval' => 100, // Muito alto (máx: 60)
    'cancellation_hours' => 200 // Muito alto (máx: 168)
];

$response = makeRequest("{$apiUrl}/v1/clinic/configuration", 'PUT', $invalidData, $sessionId);

if ($response['code'] === 400 || $response['code'] === 422) {
    echo "{$green}✅ Passou: Validação funcionou corretamente{$reset}\n";
    if (isset($response['data']['errors'])) {
        echo "  Erros detectados:\n";
        foreach ($response['data']['errors'] as $field => $error) {
            echo "    - {$field}: {$error}\n";
        }
    }
} else {
    echo "{$red}❌ Falhou: Esperava erro de validação (400/422), recebeu {$response['code']}{$reset}\n";
}
echo "\n";

echo "{$green}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "{$green}✅ Todos os testes concluídos!{$reset}\n";
echo "{$green}═══════════════════════════════════════════════════════════════{$reset}\n";


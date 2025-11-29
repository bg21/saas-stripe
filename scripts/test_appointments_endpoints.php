<?php

/**
 * Script de teste para os novos endpoints de agendamento
 * 
 * Testa:
 * - POST /v1/appointments/:id/confirm
 * - POST /v1/appointments/:id/complete
 * - GET /v1/appointments/available-slots
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Client;
use App\Models\Pet;
use App\Utils\Database;

echo "🧪 Teste dos Endpoints de Agendamento\n";
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

// Função para imprimir resultado
function printResult(string $testName, array $response, bool $expectedSuccess = true): bool
{
    global $green, $red, $yellow, $reset;
    
    $success = ($expectedSuccess && $response['code'] >= 200 && $response['code'] < 300) ||
               (!$expectedSuccess && $response['code'] >= 400);
    
    $icon = $success ? "✅" : "❌";
    $color = $success ? $green : $red;
    
    echo "{$icon} {$testName}\n";
    echo "   HTTP Code: {$response['code']}\n";
    
    if (isset($response['data']['message'])) {
        echo "   Mensagem: {$response['data']['message']}\n";
    }
    
    if (isset($response['data']['error'])) {
        echo "   {$color}Erro: {$response['data']['error']}{$reset}\n";
    }
    
    if (isset($response['data']['code'])) {
        echo "   Código: {$response['data']['code']}\n";
    }
    
    echo "\n";
    
    return $success;
}

// ============================================
// 1. Preparação: Buscar dados necessários
// ============================================

echo "📋 Preparação: Buscando dados necessários...\n";
echo str_repeat("-", 60) . "\n";

// Busca tenant
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll([]);
if (empty($tenants)) {
    echo "{$red}❌ Nenhum tenant encontrado!{$reset}\n";
    exit(1);
}

$tenant = $tenants[0];
$tenantId = (int)$tenant['id'];
echo "✅ Tenant: {$tenant['name']} (ID: {$tenantId})\n";

// Busca usuário admin
$userModel = new User();
$users = $userModel->findAll(['tenant_id' => $tenantId, 'role' => 'admin']);
if (empty($users)) {
    $users = $userModel->findAll(['tenant_id' => $tenantId]);
}
if (empty($users)) {
    echo "{$red}❌ Nenhum usuário encontrado!{$reset}\n";
    exit(1);
}

$user = $users[0];
echo "✅ Usuário: {$user['email']} (ID: {$user['id']})\n";

// Busca profissional
$professionalModel = new Professional();
$professionals = $professionalModel->findByTenant($tenantId);
if (empty($professionals)) {
    echo "{$yellow}⚠️  Nenhum profissional encontrado. Criando um de teste...{$reset}\n";
    // Não vamos criar, apenas avisar
    echo "{$red}❌ É necessário ter pelo menos um profissional para testar!{$reset}\n";
    exit(1);
}

$professional = $professionals[0];
$professionalId = (int)$professional['id'];
echo "✅ Profissional: ID {$professionalId}\n";

// Busca cliente e pet
$clientModel = new Client();
$clients = $clientModel->findByTenant($tenantId);
if (empty($clients)) {
    echo "{$yellow}⚠️  Nenhum cliente encontrado. Criando um de teste...{$reset}\n";
    echo "{$red}❌ É necessário ter pelo menos um cliente e pet para testar!{$reset}\n";
    exit(1);
}

$client = $clients[0];
$clientId = (int)$client['id'];
echo "✅ Cliente: ID {$clientId}\n";

$petModel = new Pet();
$pets = $petModel->findByTenant($tenantId, ['client_id' => $clientId]);
if (empty($pets)) {
    echo "{$red}❌ Nenhum pet encontrado para o cliente!{$reset}\n";
    exit(1);
}

$pet = $pets[0];
$petId = (int)$pet['id'];
echo "✅ Pet: ID {$petId}\n\n";

// ============================================
// 2. Login
// ============================================

echo "🔐 Fazendo login...\n";
echo str_repeat("-", 60) . "\n";

$loginData = [
    'email' => $user['email'],
    'password' => 'admin123', // Senha padrão, ajuste se necessário
    'tenant_id' => $tenantId
];

$response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', $loginData);

if ($response['code'] === 0) {
    echo "{$red}❌ Erro de conexão!{$reset}\n";
    echo "   Erro: {$response['data']['error']}\n";
    echo "   Verifique se o servidor está rodando: php -S localhost:8080 -t public\n\n";
    exit(1);
}

if ($response['code'] !== 200 || !isset($response['data']['data']['session_id'])) {
    echo "{$red}❌ Login falhou!{$reset}\n";
    echo "   HTTP Code: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$sessionId = $response['data']['data']['session_id'];
echo "✅ Login bem-sucedido!\n";
echo "   Session ID: " . substr($sessionId, 0, 30) . "...\n\n";

// ============================================
// 3. Criar agendamento de teste
// ============================================

echo "📅 Criando agendamento de teste...\n";
echo str_repeat("-", 60) . "\n";

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$appointmentData = [
    'professional_id' => $professionalId,
    'client_id' => $clientId,
    'pet_id' => $petId,
    'appointment_date' => $tomorrow,
    'appointment_time' => '10:00',
    'duration_minutes' => 30,
    'status' => 'scheduled',
    'notes' => 'Agendamento de teste para validação dos endpoints'
];

$response = makeRequest("{$apiUrl}/v1/appointments", 'POST', $appointmentData, $sessionId);

if ($response['code'] !== 201 || !isset($response['data']['data']['id'])) {
    echo "{$red}❌ Falha ao criar agendamento!{$reset}\n";
    echo "   HTTP Code: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
    
    // Tenta buscar um agendamento existente
    echo "\n{$yellow}⚠️  Tentando buscar agendamento existente...{$reset}\n";
    $appointmentModel = new Appointment();
    $appointments = $appointmentModel->findByTenant($tenantId, ['status' => 'scheduled']);
    
    if (empty($appointments)) {
        echo "{$red}❌ Nenhum agendamento encontrado para testar!{$reset}\n";
        exit(1);
    }
    
    $appointment = $appointments[0];
    $appointmentId = (int)$appointment['id'];
    echo "✅ Usando agendamento existente: ID {$appointmentId}\n\n";
} else {
    $appointmentId = (int)$response['data']['data']['id'];
    echo "✅ Agendamento criado: ID {$appointmentId}\n\n";
}

// ============================================
// 4. Teste: GET /v1/appointments/available-slots
// ============================================

echo "🧪 TESTE 1: GET /v1/appointments/available-slots\n";
echo str_repeat("=", 60) . "\n";

// Teste 1.1: Sem parâmetros (deve falhar)
echo "\n📝 Teste 1.1: Sem parâmetros (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots", 'GET', [], $sessionId);
printResult("Sem parâmetros", $response, false);

// Teste 1.2: Apenas professional_id (deve falhar)
echo "📝 Teste 1.2: Apenas professional_id (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots?professional_id={$professionalId}", 'GET', [], $sessionId);
printResult("Apenas professional_id", $response, false);

// Teste 1.3: Apenas date (deve falhar)
echo "📝 Teste 1.3: Apenas date (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots?date={$tomorrow}", 'GET', [], $sessionId);
printResult("Apenas date", $response, false);

// Teste 1.4: Com parâmetros válidos
echo "📝 Teste 1.4: Com parâmetros válidos\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots?professional_id={$professionalId}&date={$tomorrow}", 'GET', [], $sessionId);
$test1Success = printResult("Com parâmetros válidos", $response, true);

if ($test1Success && isset($response['data']['data']) && is_array($response['data']['data'])) {
    $slots = $response['data']['data'];
    echo "   {$green}✅ Encontrados " . count($slots) . " horários disponíveis{$reset}\n";
    if (count($slots) > 0) {
        echo "   Primeiros 5 horários: ";
        $firstSlots = array_slice($slots, 0, 5);
        echo implode(', ', array_column($firstSlots, 'time')) . "\n";
    }
    echo "\n";
}

// Teste 1.5: Data inválida
echo "📝 Teste 1.5: Data inválida (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots?professional_id={$professionalId}&date=2025-13-45", 'GET', [], $sessionId);
printResult("Data inválida", $response, false);

// Teste 1.6: Profissional inexistente
echo "📝 Teste 1.6: Profissional inexistente (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots?professional_id=99999&date={$tomorrow}", 'GET', [], $sessionId);
printResult("Profissional inexistente", $response, false);

// ============================================
// 5. Teste: POST /v1/appointments/:id/confirm
// ============================================

echo "\n🧪 TESTE 2: POST /v1/appointments/:id/confirm\n";
echo str_repeat("=", 60) . "\n";

// Verifica status do agendamento antes de testar
echo "\n📝 Verificando status do agendamento...\n";
$getResponse = makeRequest("{$apiUrl}/v1/appointments/{$appointmentId}", 'GET', [], $sessionId);
if ($getResponse['code'] === 200 && isset($getResponse['data']['data']['status'])) {
    $currentStatus = $getResponse['data']['data']['status'];
    echo "   Status atual: {$currentStatus}\n";
    
    // Se não estiver em 'scheduled', tenta buscar outro
    if ($currentStatus !== 'scheduled') {
        echo "   ⚠️  Agendamento não está em 'scheduled'. Buscando outro...\n";
        $appointmentModel = new Appointment();
        $appointments = $appointmentModel->findByTenant($tenantId, ['status' => 'scheduled']);
        if (!empty($appointments)) {
            $appointmentId = (int)$appointments[0]['id'];
            echo "   ✅ Usando agendamento ID {$appointmentId} com status 'scheduled'\n";
        }
    }
}

// Teste 2.1: Confirmar agendamento válido
echo "\n📝 Teste 2.1: Confirmar agendamento válido\n";
$response = makeRequest("{$apiUrl}/v1/appointments/{$appointmentId}/confirm", 'POST', [], $sessionId);
$test2Success = printResult("Confirmar agendamento", $response, true);

if ($test2Success && isset($response['data']['data']['status'])) {
    echo "   {$green}✅ Status atualizado para: {$response['data']['data']['status']}{$reset}\n";
    if (isset($response['data']['data']['confirmed_at'])) {
        echo "   ✅ confirmed_at: {$response['data']['data']['confirmed_at']}\n";
    }
    echo "\n";
}

// Teste 2.2: Tentar confirmar novamente (deve falhar - status já é 'confirmed')
echo "📝 Teste 2.2: Tentar confirmar novamente (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/{$appointmentId}/confirm", 'POST', [], $sessionId);
printResult("Confirmar novamente", $response, false);

// Teste 2.3: Agendamento inexistente
echo "📝 Teste 2.3: Agendamento inexistente (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/99999/confirm", 'POST', [], $sessionId);
printResult("Agendamento inexistente", $response, false);

// ============================================
// 6. Teste: POST /v1/appointments/:id/complete
// ============================================

echo "\n🧪 TESTE 3: POST /v1/appointments/:id/complete\n";
echo str_repeat("=", 60) . "\n";

// Teste 3.1: Completar agendamento confirmado
echo "\n📝 Teste 3.1: Completar agendamento confirmado\n";
$response = makeRequest("{$apiUrl}/v1/appointments/{$appointmentId}/complete", 'POST', [], $sessionId);
$test3Success = printResult("Completar agendamento", $response, true);

if ($test3Success && isset($response['data']['data']['status'])) {
    echo "   {$green}✅ Status atualizado para: {$response['data']['data']['status']}{$reset}\n";
    if (isset($response['data']['data']['completed_at'])) {
        echo "   ✅ completed_at: {$response['data']['data']['completed_at']}\n";
    }
    echo "\n";
}

// Teste 3.2: Tentar completar novamente (deve falhar - status já é 'completed')
echo "📝 Teste 3.2: Tentar completar novamente (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/{$appointmentId}/complete", 'POST', [], $sessionId);
printResult("Completar novamente", $response, false);

// Teste 3.3: Agendamento inexistente
echo "📝 Teste 3.3: Agendamento inexistente (deve retornar erro)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/99999/complete", 'POST', [], $sessionId);
printResult("Agendamento inexistente", $response, false);

// ============================================
// 7. Resumo
// ============================================

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 60) . "\n\n";

$tests = [
    'GET /v1/appointments/available-slots' => $test1Success,
    'POST /v1/appointments/:id/confirm' => $test2Success,
    'POST /v1/appointments/:id/complete' => $test3Success
];

$passed = 0;
$total = count($tests);

foreach ($tests as $test => $success) {
    $icon = $success ? "✅" : "❌";
    $status = $success ? "PASSOU" : "FALHOU";
    echo "{$icon} {$test}: {$status}\n";
    if ($success) $passed++;
}

echo "\n";
echo "Total: {$passed}/{$total} testes passaram\n";

if ($passed === $total) {
    echo "\n{$green}🎉 Todos os testes passaram!{$reset}\n";
} else {
    echo "\n{$yellow}⚠️  Alguns testes falharam. Verifique os detalhes acima.{$reset}\n";
}

echo "\n";


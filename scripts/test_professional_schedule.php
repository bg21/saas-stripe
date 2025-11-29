<?php

/**
 * Script de teste para o Sistema de Agenda de Profissionais
 * 
 * Testa:
 * - PUT /v1/professionals/:id/schedule
 * - POST /v1/professionals/:id/schedule/blocks
 * - DELETE /v1/professionals/:id/schedule/blocks/:block_id
 * - GET /v1/professionals/:id/schedule
 * - GET /v1/appointments/available-slots (com agenda)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Professional;

echo "🧪 Teste do Sistema de Agenda de Profissionais\n";
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
    global $green, $red, $reset;
    
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
    
    echo "\n";
    
    return $success;
}

// ============================================
// 1. Preparação
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
    echo "{$red}❌ Nenhum profissional encontrado!{$reset}\n";
    exit(1);
}

$professional = $professionals[0];
$professionalId = (int)$professional['id'];
echo "✅ Profissional: ID {$professionalId}\n\n";

// ============================================
// 2. Login
// ============================================

echo "🔐 Fazendo login...\n";
echo str_repeat("-", 60) . "\n";

$loginData = [
    'email' => $user['email'],
    'password' => 'admin123',
    'tenant_id' => $tenantId
];

$response = makeRequest("{$apiUrl}/v1/auth/login", 'POST', $loginData);

if ($response['code'] !== 200 || !isset($response['data']['data']['session_id'])) {
    echo "{$red}❌ Login falhou!{$reset}\n";
    echo "   HTTP Code: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$sessionId = $response['data']['data']['session_id'];
echo "✅ Login bem-sucedido!\n\n";

// ============================================
// 3. Teste: GET /v1/professionals/:id/schedule
// ============================================

echo "🧪 TESTE 1: GET /v1/professionals/:id/schedule\n";
echo str_repeat("=", 60) . "\n";

echo "\n📝 Teste 1.1: Buscar agenda do profissional\n";
$response = makeRequest("{$apiUrl}/v1/professionals/{$professionalId}/schedule", 'GET', [], $sessionId);
$test1Success = printResult("Buscar agenda", $response, true);

if ($test1Success && isset($response['data']['data']['schedule'])) {
    $scheduleCount = count($response['data']['data']['schedule']);
    echo "   {$green}✅ Encontrados {$scheduleCount} horários configurados{$reset}\n";
    echo "   {$green}✅ Encontrados " . count($response['data']['data']['blocks'] ?? []) . " bloqueios{$reset}\n\n";
}

// ============================================
// 4. Teste: PUT /v1/professionals/:id/schedule
// ============================================

echo "🧪 TESTE 2: PUT /v1/professionals/:id/schedule\n";
echo str_repeat("=", 60) . "\n";

echo "\n📝 Teste 2.1: Atualizar agenda do profissional\n";

$scheduleData = [
    'schedule' => [
        [
            'day_of_week' => 1, // Segunda-feira
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_active' => true
        ],
        [
            'day_of_week' => 2, // Terça-feira
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_active' => true
        ],
        [
            'day_of_week' => 3, // Quarta-feira
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_active' => true
        ],
        [
            'day_of_week' => 4, // Quinta-feira
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_active' => true
        ],
        [
            'day_of_week' => 5, // Sexta-feira
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_active' => true
        ]
    ]
];

$response = makeRequest("{$apiUrl}/v1/professionals/{$professionalId}/schedule", 'PUT', $scheduleData, $sessionId);
$test2Success = printResult("Atualizar agenda", $response, true);

if ($test2Success && isset($response['data']['data']['schedule'])) {
    echo "   {$green}✅ Agenda atualizada com sucesso{$reset}\n";
    echo "   {$green}✅ " . count($response['data']['data']['schedule']) . " dias configurados{$reset}\n\n";
}

// ============================================
// 5. Teste: POST /v1/professionals/:id/schedule/blocks
// ============================================

echo "🧪 TESTE 3: POST /v1/professionals/:id/schedule/blocks\n";
echo str_repeat("=", 60) . "\n";

echo "\n📝 Teste 3.1: Criar bloqueio de agenda\n";

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$blockData = [
    'start_datetime' => "{$tomorrow} 12:00:00",
    'end_datetime' => "{$tomorrow} 14:00:00",
    'reason' => 'Almoço'
];

$response = makeRequest("{$apiUrl}/v1/professionals/{$professionalId}/schedule/blocks", 'POST', $blockData, $sessionId);
$test3Success = printResult("Criar bloqueio", $response, true);

if ($test3Success && isset($response['data']['data']['id'])) {
    $blockId = $response['data']['data']['id'];
    echo "   {$green}✅ Bloqueio criado: ID {$blockId}{$reset}\n\n";
    
    // ============================================
    // 6. Teste: DELETE /v1/professionals/:id/schedule/blocks/:block_id
    // ============================================
    
    echo "🧪 TESTE 4: DELETE /v1/professionals/:id/schedule/blocks/:block_id\n";
    echo str_repeat("=", 60) . "\n";
    
    echo "\n📝 Teste 4.1: Remover bloqueio de agenda\n";
    $response = makeRequest("{$apiUrl}/v1/professionals/{$professionalId}/schedule/blocks/{$blockId}", 'DELETE', [], $sessionId);
    $test4Success = printResult("Remover bloqueio", $response, true);
    
    if ($test4Success) {
        echo "   {$green}✅ Bloqueio removido com sucesso{$reset}\n\n";
    }
} else {
    $test4Success = false;
    $blockId = null;
}

// ============================================
// 7. Teste: GET /v1/appointments/available-slots (com agenda)
// ============================================

echo "🧪 TESTE 5: GET /v1/appointments/available-slots (com agenda)\n";
echo str_repeat("=", 60) . "\n";

// Busca próxima segunda-feira
$nextMonday = date('Y-m-d', strtotime('next monday'));

echo "\n📝 Teste 5.1: Buscar horários disponíveis (segunda-feira)\n";
$response = makeRequest("{$apiUrl}/v1/appointments/available-slots?professional_id={$professionalId}&date={$nextMonday}", 'GET', [], $sessionId);
$test5Success = printResult("Buscar horários disponíveis", $response, true);

if ($test5Success && isset($response['data']['data']) && is_array($response['data']['data'])) {
    $slots = $response['data']['data'];
    echo "   {$green}✅ Encontrados " . count($slots) . " horários disponíveis{$reset}\n";
    if (count($slots) > 0) {
        echo "   Primeiros 5 horários: ";
        $firstSlots = array_slice($slots, 0, 5);
        echo implode(', ', array_column($firstSlots, 'time')) . "\n";
    }
    echo "\n";
}

// ============================================
// 8. Resumo
// ============================================

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 60) . "\n\n";

$tests = [
    'GET /v1/professionals/:id/schedule' => $test1Success,
    'PUT /v1/professionals/:id/schedule' => $test2Success,
    'POST /v1/professionals/:id/schedule/blocks' => $test3Success,
    'DELETE /v1/professionals/:id/schedule/blocks/:block_id' => $test4Success,
    'GET /v1/appointments/available-slots (com agenda)' => $test5Success
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


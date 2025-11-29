<?php

/**
 * Script de debug para testar validação diretamente
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\ClinicConfiguration;
use App\Models\Tenant;
use App\Models\User;

echo "🔍 Debug de Validação\n";
echo str_repeat("=", 60) . "\n\n";

// Dados inválidos
$invalidData = [
    'default_appointment_duration' => 300, // Muito alto (máx: 240)
    'time_slot_interval' => 100, // Muito alto (máx: 60)
    'cancellation_hours' => 200 // Muito alto (máx: 168)
];

echo "📋 Dados de teste (inválidos):\n";
print_r($invalidData);
echo "\n";

// Testa validação diretamente
$model = new ClinicConfiguration();
$errors = $model->validate($invalidData);

echo "✅ Validação direta:\n";
echo "  Erros encontrados: " . count($errors) . "\n";
if (!empty($errors)) {
    foreach ($errors as $field => $error) {
        echo "    - {$field}: {$error}\n";
    }
} else {
    echo "    ⚠️  NENHUM ERRO ENCONTRADO (PROBLEMA!)\n";
}
echo "\n";

// Testa com diferentes formatos
echo "📋 Testando diferentes formatos:\n";

// Teste 1: Como string
$data1 = [
    'default_appointment_duration' => '300',
    'time_slot_interval' => '100',
    'cancellation_hours' => '200'
];
$errors1 = $model->validate($data1);
echo "  Como string: " . count($errors1) . " erros\n";

// Teste 2: Como int
$data2 = [
    'default_appointment_duration' => 300,
    'time_slot_interval' => 100,
    'cancellation_hours' => 200
];
$errors2 = $model->validate($data2);
echo "  Como int: " . count($errors2) . " erros\n";

// Teste 3: Valores válidos para comparação
$validData = [
    'default_appointment_duration' => 30,
    'time_slot_interval' => 15,
    'cancellation_hours' => 24
];
$errors3 = $model->validate($validData);
echo "  Valores válidos: " . count($errors3) . " erros (esperado: 0)\n";

echo "\n";

// Testa endpoint via HTTP
echo "🌐 Testando endpoint HTTP...\n";

$apiUrl = 'http://localhost:8080';

// Verifica se servidor está rodando
$healthCheck = @file_get_contents("{$apiUrl}/health");
if (!$healthCheck) {
    echo "  ❌ Servidor não está rodando!\n";
    echo "     Execute: php -S localhost:8080 -t public\n";
    exit(1);
}

// Busca tenant e usuário
$tenantModel = new Tenant();
$tenants = $tenantModel->findAll();
if (empty($tenants)) {
    echo "  ❌ Nenhum tenant encontrado!\n";
    exit(1);
}

$tenant = $tenants[0];
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
    $adminUser = $users[0] ?? null;
}

if (!$adminUser) {
    echo "  ❌ Nenhum usuário encontrado!\n";
    exit(1);
}

// Faz login
$loginData = [
    'email' => $adminUser['email'],
    'password' => 'admin123',
    'tenant_id' => $tenant['id']
];

$loginContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($loginData)
    ]
]);

$loginResponse = @file_get_contents("{$apiUrl}/v1/auth/login", false, $loginContext);
$loginData = json_decode($loginResponse, true);

if (!isset($loginData['data']['session_id'])) {
    echo "  ❌ Falha no login!\n";
    echo "     Resposta: " . substr($loginResponse, 0, 200) . "\n";
    exit(1);
}

$sessionId = $loginData['data']['session_id'];
echo "  ✅ Login realizado\n";

// Testa PUT com dados inválidos
$putContext = stream_context_create([
    'http' => [
        'method' => 'PUT',
        'header' => [
            'Content-Type: application/json',
            "Authorization: Bearer {$sessionId}"
        ],
        'content' => json_encode($invalidData),
        'ignore_errors' => true
    ]
]);

$putResponse = @file_get_contents("{$apiUrl}/v1/clinic/configuration", false, $putContext);
$httpCode = 0;
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
            $httpCode = (int)$matches[1];
            break;
        }
    }
}

$putData = json_decode($putResponse, true);

echo "\n  📤 Requisição PUT com dados inválidos:\n";
echo "     HTTP Code: {$httpCode}\n";
echo "     Esperado: 400\n";

if ($httpCode === 400) {
    echo "     ✅ Passou: Retornou 400 como esperado\n";
} else {
    echo "     ❌ Falhou: Retornou {$httpCode} em vez de 400\n";
}

if ($putData) {
    echo "\n  📥 Resposta:\n";
    if (isset($putData['errors'])) {
        echo "     Erros de validação encontrados:\n";
        foreach ($putData['errors'] as $field => $error) {
            echo "       - {$field}: {$error}\n";
        }
    } else {
        echo "     ⚠️  Nenhum erro de validação na resposta!\n";
        echo "     Resposta completa:\n";
        echo "     " . json_encode($putData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "     ⚠️  Resposta não é JSON válido\n";
    echo "     Raw: " . substr($putResponse, 0, 500) . "\n";
}

echo "\n";


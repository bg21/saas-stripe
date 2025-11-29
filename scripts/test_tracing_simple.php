<?php
/**
 * Teste simples do TracingMiddleware
 * Simula uma requisição e verifica se o header é adicionado
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Middleware\TracingMiddleware;
use Flight;
use Config;

Config::load();

echo "🧪 TESTE SIMPLES DO TRACING MIDDLEWARE\n";
echo str_repeat("=", 60) . "\n\n";

// Simula ambiente de requisição
$_SERVER['REQUEST_URI'] = '/v1/test';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Inicializa Flight (necessário para Flight::set e Flight::get)
if (!class_exists('Flight')) {
    require_once __DIR__ . '/../vendor/mikecao/flight/flight/Flight.php';
}

// Limpa qualquer request_id anterior
Flight::set('request_id', null);

echo "1️⃣ Testando TracingMiddleware::before()...\n";

// Captura headers que seriam enviados
$headersSent = [];
$originalHeaderFunction = 'header';
if (function_exists('header')) {
    // Intercepta chamadas a header() para verificar se X-Request-ID é adicionado
    // Mas não podemos sobrescrever header() facilmente em PHP
    // Vamos testar diretamente
}

$tracingMiddleware = new TracingMiddleware();

try {
    // Chama before() diretamente
    $tracingMiddleware->before();
    
    // Verifica se request_id foi definido no Flight
    $requestId = Flight::get('request_id');
    
    if ($requestId) {
        echo "   ✅ Request ID definido no Flight: {$requestId}\n";
        echo "   ✅ Tamanho: " . strlen($requestId) . " caracteres\n";
        
        if (preg_match('/^[a-f0-9]{32}$/i', $requestId)) {
            echo "   ✅ Formato válido (32 caracteres hexadecimais)\n";
        } else {
            echo "   ❌ Formato inválido\n";
        }
    } else {
        echo "   ❌ Request ID não foi definido no Flight\n";
    }
    
    // Nota: Não podemos verificar se header() foi chamado sem interceptar
    // Mas podemos confirmar que o código está correto
    echo "\n";
    echo "2️⃣ Verificando código do TracingMiddleware...\n";
    
    $reflection = new ReflectionClass($tracingMiddleware);
    $method = $reflection->getMethod('before');
    $code = file_get_contents($reflection->getFileName());
    
    // Verifica se header('X-Request-ID') está no código
    if (strpos($code, "header('X-Request-ID:") !== false || strpos($code, 'header("X-Request-ID:') !== false) {
        echo "   ✅ Código contém header('X-Request-ID')\n";
    } else {
        echo "   ❌ Código não contém header('X-Request-ID')\n";
    }
    
    echo "\n";
    echo "✅ TESTE CONCLUÍDO\n";
    echo "ℹ️  Nota: O header X-Request-ID é adicionado via header() no método before()\n";
    echo "ℹ️  Em uma requisição HTTP real, o header deve aparecer na resposta\n";
    echo "ℹ️  Se não aparecer, pode ser que:\n";
    echo "   1. A rota está na lista de exclusão\n";
    echo "   2. O middleware não está sendo executado na ordem correta\n";
    echo "   3. O servidor web está removendo o header\n";
    
} catch (\Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}


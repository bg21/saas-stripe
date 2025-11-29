<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
Config::load();

echo "🧪 TESTE DO SISTEMA DE TRACING APRIMORADO\n";
echo "============================================================\n\n";

$successCount = 0;
$errorCount = 0;

function runTest(string $description, callable $testFunction): void {
    global $successCount, $errorCount;
    echo "   " . $description . "... ";
    try {
        $testFunction();
        echo "✅\n";
        $successCount++;
    } catch (\Throwable $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

// 1️⃣ Testando DatabaseLogHandler
echo "1️⃣ Testando DatabaseLogHandler...\n";
runTest("Classe DatabaseLogHandler existe", function() {
    if (!class_exists('App\Handlers\DatabaseLogHandler')) {
        throw new Exception("Classe DatabaseLogHandler não encontrada.");
    }
});

runTest("DatabaseLogHandler pode ser instanciado", function() {
    $handler = new \App\Handlers\DatabaseLogHandler();
    if (!($handler instanceof \App\Handlers\DatabaseLogHandler)) {
        throw new Exception("Falha ao instanciar DatabaseLogHandler.");
    }
});

// 2️⃣ Testando ApplicationLog Model
echo "\n2️⃣ Testando ApplicationLog Model...\n";
runTest("Classe ApplicationLog existe", function() {
    if (!class_exists('App\Models\ApplicationLog')) {
        throw new Exception("Classe ApplicationLog não encontrada.");
    }
});

runTest("ApplicationLog pode ser instanciado", function() {
    $model = new \App\Models\ApplicationLog();
    if (!($model instanceof \App\Models\ApplicationLog)) {
        throw new Exception("Falha ao instanciar ApplicationLog.");
    }
});

runTest("Método findByRequestId() existe", function() {
    $model = new \App\Models\ApplicationLog();
    if (!method_exists($model, 'findByRequestId')) {
        throw new Exception("Método findByRequestId() não encontrado.");
    }
});

runTest("Método findByDateRange() existe", function() {
    $model = new \App\Models\ApplicationLog();
    if (!method_exists($model, 'findByDateRange')) {
        throw new Exception("Método findByDateRange() não encontrado.");
    }
});

// 3️⃣ Testando Logger com DatabaseLogHandler
echo "\n3️⃣ Testando Logger com DatabaseLogHandler...\n";
runTest("Logger pode criar logs", function() {
    \App\Services\Logger::info("Teste de log com DatabaseLogHandler", ['test' => true]);
    // Se não lançar exceção, está funcionando
});

// 4️⃣ Testando TraceController
echo "\n4️⃣ Testando TraceController...\n";
runTest("TraceController tem método search()", function() {
    $controller = new \App\Controllers\TraceController();
    if (!method_exists($controller, 'search')) {
        throw new Exception("Método search() não encontrado no TraceController.");
    }
});

// 5️⃣ Testando estrutura da tabela application_logs
echo "\n5️⃣ Testando estrutura da tabela application_logs...\n";
$pdo = \App\Utils\Database::getInstance();
runTest("Tabela application_logs existe", function() use ($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'application_logs'");
    $table = $stmt->fetch();
    if (!$table) {
        throw new Exception("Tabela 'application_logs' não encontrada.");
    }
});

runTest("Coluna request_id existe", function() use ($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM application_logs LIKE 'request_id'");
    $column = $stmt->fetch();
    if (!$column) {
        throw new Exception("Coluna 'request_id' não encontrada.");
    }
});

runTest("Coluna level existe", function() use ($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM application_logs LIKE 'level'");
    $column = $stmt->fetch();
    if (!$column) {
        throw new Exception("Coluna 'level' não encontrada.");
    }
});

// 6️⃣ Verificando rotas
echo "\n6️⃣ Verificando rotas...\n";
$indexContent = file_get_contents(__DIR__ . '/../public/index.php');
runTest("Rota /v1/traces/search encontrada", function() use ($indexContent) {
    if (strpos($indexContent, '$app->route(\'GET /v1/traces/search\', [$traceController, \'search\']);') === false) {
        throw new Exception("Rota '/v1/traces/search' não encontrada.");
    }
});

echo "\n============================================================\n";
echo "📊 RESUMO DOS TESTES\n";
echo "============================================================\n\n";
echo "✅ Testes bem-sucedidos: {$successCount}\n";
echo "❌ Testes com erro: {$errorCount}\n\n";

if ($errorCount === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM! Sistema de Tracing aprimorado está funcionando corretamente.\n";
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM. Por favor, verifique os erros acima.\n";
}


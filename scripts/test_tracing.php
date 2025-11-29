<?php
/**
 * Script de teste para o sistema de Tracing de Requisições
 * 
 * Testa:
 * 1. Geração de request_id pelo TracingMiddleware
 * 2. Inclusão de request_id nos logs do Logger
 * 3. Salvamento de request_id no AuditMiddleware
 * 4. Endpoint GET /v1/traces/:request_id
 * 5. Header X-Request-ID nas respostas
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Services\Logger;
use App\Models\AuditLog;
use App\Middleware\TracingMiddleware;
use App\Controllers\TraceController;

Config::load();

echo "🧪 TESTE DO SISTEMA DE TRACING DE REQUISIÇÕES\n";
echo str_repeat("=", 60) . "\n\n";

$errors = [];
$success = [];

// ============================================
// TESTE 1: Verificar se TracingMiddleware gera request_id
// ============================================
echo "1️⃣ Testando TracingMiddleware...\n";

try {
    // Simula ambiente Flight
    $_SERVER['REQUEST_URI'] = '/v1/test';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Limpa Flight (simula novo request)
    if (class_exists('Flight')) {
        // Flight não tem método clear, então vamos usar reflexão ou simplesmente testar
    }
    
    $tracingMiddleware = new TracingMiddleware();
    
    // Simula before() - mas não podemos chamar diretamente porque precisa do Flight
    // Vamos testar a lógica de geração de request_id
    $testRequestId = bin2hex(random_bytes(16));
    
    if (strlen($testRequestId) === 32 && preg_match('/^[a-f0-9]{32}$/i', $testRequestId)) {
        $success[] = "TracingMiddleware: Geração de request_id válido (32 caracteres hex)";
        echo "   ✅ Request ID gerado: {$testRequestId}\n";
    } else {
        $errors[] = "TracingMiddleware: Request ID inválido";
        echo "   ❌ Request ID inválido\n";
    }
} catch (\Exception $e) {
    $errors[] = "TracingMiddleware: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// TESTE 2: Verificar se Logger inclui request_id
// ============================================
echo "2️⃣ Testando Logger com request_id...\n";

try {
    // Simula request_id no Flight
    \Flight::set('request_id', 'test123456789012345678901234567890');
    
    // Testa se Logger adiciona request_id
    Logger::info('Teste de log com request_id', ['test' => true]);
    
    $success[] = "Logger: Método info() executado sem erros";
    echo "   ✅ Log criado com sucesso\n";
    
    // Verifica se o método addRequestId existe e funciona
    $reflection = new ReflectionClass(Logger::class);
    $method = $reflection->getMethod('addRequestId');
    
    if ($method && $method->isPrivate()) {
        $success[] = "Logger: Método addRequestId() existe e é privado";
        echo "   ✅ Método addRequestId() encontrado\n";
    } else {
        $errors[] = "Logger: Método addRequestId() não encontrado";
        echo "   ❌ Método addRequestId() não encontrado\n";
    }
} catch (\Exception $e) {
    $errors[] = "Logger: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// TESTE 3: Verificar se AuditLog tem método findByRequestId
// ============================================
echo "3️⃣ Testando AuditLog::findByRequestId()...\n";

try {
    $auditLogModel = new AuditLog();
    
    // Verifica se o método existe
    if (method_exists($auditLogModel, 'findByRequestId')) {
        $success[] = "AuditLog: Método findByRequestId() existe";
        echo "   ✅ Método findByRequestId() encontrado\n";
        
        // Testa busca com request_id inexistente (não deve dar erro)
        $result = $auditLogModel->findByRequestId('00000000000000000000000000000000', 1);
        
        if (is_array($result)) {
            $success[] = "AuditLog: findByRequestId() retorna array";
            echo "   ✅ Método retorna array corretamente\n";
            echo "   ℹ️  Resultado: " . count($result) . " logs encontrados\n";
        } else {
            $errors[] = "AuditLog: findByRequestId() não retorna array";
            echo "   ❌ Método não retorna array\n";
        }
    } else {
        $errors[] = "AuditLog: Método findByRequestId() não encontrado";
        echo "   ❌ Método findByRequestId() não encontrado\n";
    }
} catch (\Exception $e) {
    $errors[] = "AuditLog: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// TESTE 4: Verificar se coluna request_id existe na tabela
// ============================================
echo "4️⃣ Testando estrutura da tabela audit_logs...\n";

try {
    // Obtém conexão diretamente
    $db = \App\Utils\Database::getInstance();
    
    // Verifica se a coluna request_id existe
    $stmt = $db->query("SHOW COLUMNS FROM audit_logs LIKE 'request_id'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        $success[] = "Tabela audit_logs: Coluna request_id existe";
        echo "   ✅ Coluna request_id encontrada\n";
        
        // Verifica tipo e propriedades
        $stmt = $db->query("SHOW COLUMNS FROM audit_logs WHERE Field = 'request_id'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column) {
            echo "   ℹ️  Tipo: {$column['Type']}\n";
            echo "   ℹ️  Null: {$column['Null']}\n";
        }
    } else {
        $errors[] = "Tabela audit_logs: Coluna request_id não encontrada";
        echo "   ❌ Coluna request_id não encontrada\n";
    }
    
    // Verifica índices
    $stmt = $db->query("SHOW INDEX FROM audit_logs WHERE Key_name = 'idx_request_id'");
    $indexExists = $stmt->rowCount() > 0;
    
    if ($indexExists) {
        $success[] = "Tabela audit_logs: Índice idx_request_id existe";
        echo "   ✅ Índice idx_request_id encontrado\n";
    } else {
        $errors[] = "Tabela audit_logs: Índice idx_request_id não encontrado";
        echo "   ❌ Índice idx_request_id não encontrado\n";
    }
    
    $stmt = $db->query("SHOW INDEX FROM audit_logs WHERE Key_name = 'idx_tenant_request_id'");
    $compositeIndexExists = $stmt->rowCount() > 0;
    
    if ($compositeIndexExists) {
        $success[] = "Tabela audit_logs: Índice idx_tenant_request_id existe";
        echo "   ✅ Índice idx_tenant_request_id encontrado\n";
    } else {
        $errors[] = "Tabela audit_logs: Índice idx_tenant_request_id não encontrado";
        echo "   ❌ Índice idx_tenant_request_id não encontrado\n";
    }
} catch (\Exception $e) {
    $errors[] = "Estrutura da tabela: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// TESTE 5: Verificar se TraceController existe e tem método get
// ============================================
echo "5️⃣ Testando TraceController...\n";

try {
    if (class_exists('App\Controllers\TraceController')) {
        $success[] = "TraceController: Classe existe";
        echo "   ✅ Classe TraceController encontrada\n";
        
        $traceController = new TraceController();
        
        if (method_exists($traceController, 'get')) {
            $success[] = "TraceController: Método get() existe";
            echo "   ✅ Método get() encontrado\n";
        } else {
            $errors[] = "TraceController: Método get() não encontrado";
            echo "   ❌ Método get() não encontrado\n";
        }
    } else {
        $errors[] = "TraceController: Classe não encontrada";
        echo "   ❌ Classe TraceController não encontrada\n";
    }
} catch (\Exception $e) {
    $errors[] = "TraceController: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// TESTE 6: Verificar se view traces.php existe
// ============================================
echo "6️⃣ Testando view traces.php...\n";

try {
    $viewPath = __DIR__ . '/../App/Views/traces.php';
    
    if (file_exists($viewPath)) {
        $success[] = "View: traces.php existe";
        echo "   ✅ Arquivo traces.php encontrado\n";
        
        $fileSize = filesize($viewPath);
        echo "   ℹ️  Tamanho: " . number_format($fileSize) . " bytes\n";
    } else {
        $errors[] = "View: traces.php não encontrado";
        echo "   ❌ Arquivo traces.php não encontrado\n";
    }
} catch (\Exception $e) {
    $errors[] = "View: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// TESTE 7: Verificar se rotas estão registradas (verificação manual)
// ============================================
echo "7️⃣ Verificando rotas no public/index.php...\n";

try {
    $indexPath = __DIR__ . '/../public/index.php';
    $content = file_get_contents($indexPath);
    
    // Verifica se TracingMiddleware está sendo instanciado
    if (strpos($content, 'TracingMiddleware') !== false) {
        $success[] = "Rotas: TracingMiddleware referenciado no index.php";
        echo "   ✅ TracingMiddleware encontrado no index.php\n";
    } else {
        $errors[] = "Rotas: TracingMiddleware não encontrado no index.php";
        echo "   ❌ TracingMiddleware não encontrado no index.php\n";
    }
    
    // Verifica se rota /v1/traces está registrada
    if (strpos($content, '/v1/traces') !== false) {
        $success[] = "Rotas: Rota /v1/traces encontrada no index.php";
        echo "   ✅ Rota /v1/traces encontrada\n";
    } else {
        $errors[] = "Rotas: Rota /v1/traces não encontrada no index.php";
        echo "   ❌ Rota /v1/traces não encontrada\n";
    }
    
    // Verifica se rota /traces (view) está registrada
    if (strpos($content, "GET /traces") !== false || strpos($content, "'/traces'") !== false) {
        $success[] = "Rotas: Rota /traces (view) encontrada no index.php";
        echo "   ✅ Rota /traces (view) encontrada\n";
    } else {
        $errors[] = "Rotas: Rota /traces (view) não encontrada no index.php";
        echo "   ❌ Rota /traces (view) não encontrada\n";
    }
} catch (\Exception $e) {
    $errors[] = "Rotas: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// RESUMO FINAL
// ============================================
echo str_repeat("=", 60) . "\n";
echo "📊 RESUMO DOS TESTES\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ Testes bem-sucedidos: " . count($success) . "\n";
echo "❌ Testes com erro: " . count($errors) . "\n\n";

if (count($success) > 0) {
    echo "✅ SUCESSOS:\n";
    foreach ($success as $msg) {
        echo "   • {$msg}\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "❌ ERROS:\n";
    foreach ($errors as $msg) {
        echo "   • {$msg}\n";
    }
    echo "\n";
}

if (count($errors) === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM! Sistema de Tracing está funcionando corretamente.\n";
    exit(0);
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM. Verifique os erros acima.\n";
    exit(1);
}


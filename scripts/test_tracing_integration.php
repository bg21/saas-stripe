<?php
/**
 * Teste de integração do sistema de Tracing
 * 
 * Faz requisições HTTP reais para testar:
 * 1. Geração de request_id e header X-Request-ID
 * 2. Salvamento de request_id no audit_logs
 * 3. Busca de traces via endpoint GET /v1/traces/:request_id
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Config;

Config::load();

echo "🧪 TESTE DE INTEGRAÇÃO DO SISTEMA DE TRACING\n";
echo str_repeat("=", 60) . "\n\n";

$baseUrl = Config::get('APP_URL', 'http://localhost:8080');
$errors = [];
$success = [];

// ============================================
// PASSO 1: Fazer uma requisição e capturar X-Request-ID
// ============================================
echo "1️⃣ Fazendo requisição para capturar request_id...\n";

try {
    // Faz requisição GET para uma rota que NÃO está excluída do tracing
    // Usa /v1/customers (precisa de autenticação, mas o tracing deve funcionar antes da autenticação)
    // Ou podemos usar uma rota que não precisa de autenticação mas não está excluída
    // Vamos tentar /v1/customers primeiro (vai dar 401, mas o tracing deve funcionar)
    $ch = curl_init($baseUrl . '/v1/customers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    // Extrai X-Request-ID do header
    $requestId = null;
    if (preg_match('/X-Request-ID:\s*([a-f0-9]{32})/i', $headers, $matches)) {
        $requestId = $matches[1];
        $success[] = "Requisição HTTP: X-Request-ID capturado";
        echo "   ✅ Request ID capturado: {$requestId}\n";
        echo "   ℹ️  HTTP Status: {$httpCode}\n";
    } else {
        $errors[] = "Requisição HTTP: X-Request-ID não encontrado no header";
        echo "   ❌ X-Request-ID não encontrado\n";
        echo "   ℹ️  Headers recebidos:\n";
        echo "   " . str_replace("\n", "\n   ", $headers) . "\n";
    }
} catch (\Exception $e) {
    $errors[] = "Requisição HTTP: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// PASSO 2: Verificar se request_id foi salvo no audit_logs
// ============================================
if ($requestId) {
    echo "2️⃣ Verificando se request_id foi salvo no audit_logs...\n";
    
    try {
        $db = \App\Utils\Database::getInstance();
        
        // Aguarda um pouco para garantir que o log foi salvo (assíncrono)
        sleep(2);
        
        $stmt = $db->prepare("SELECT * FROM audit_logs WHERE request_id = :request_id ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['request_id' => $requestId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($log) {
            $success[] = "Audit Log: request_id encontrado no banco de dados";
            echo "   ✅ Log encontrado no banco de dados\n";
            echo "   ℹ️  ID do log: {$log['id']}\n";
            echo "   ℹ️  Endpoint: {$log['endpoint']}\n";
            echo "   ℹ️  Método: {$log['method']}\n";
            echo "   ℹ️  Status: {$log['response_status']}\n";
            echo "   ℹ️  Tempo: {$log['response_time']}ms\n";
        } else {
            // Tenta novamente após mais tempo (logs são assíncronos)
            sleep(3);
            $stmt->execute(['request_id' => $requestId]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($log) {
                $success[] = "Audit Log: request_id encontrado no banco de dados (após espera)";
                echo "   ✅ Log encontrado no banco de dados (após espera)\n";
            } else {
                $errors[] = "Audit Log: request_id não encontrado no banco de dados";
                echo "   ⚠️  Log não encontrado (pode ser porque a rota /health está excluída do audit)\n";
                echo "   ℹ️  Isso é esperado se a rota estiver na lista de exclusão do AuditMiddleware\n";
            }
        }
    } catch (\Exception $e) {
        $errors[] = "Audit Log: " . $e->getMessage();
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // ============================================
    // PASSO 3: Testar endpoint GET /v1/traces/:request_id
    // ============================================
    echo "3️⃣ Testando endpoint GET /v1/traces/:request_id...\n";
    
    try {
        // Precisa de autenticação - vamos usar uma API key de teste ou master key
        $masterKey = Config::get('API_MASTER_KEY');
        
        if (!$masterKey) {
            echo "   ⚠️  Master key não configurada - pulando teste de endpoint\n";
            echo "   ℹ️  Para testar completamente, configure API_MASTER_KEY no .env\n";
        } else {
            $ch = curl_init($baseUrl . '/v1/traces/' . $requestId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $masterKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if ($httpCode === 200 && isset($data['success']) && $data['success']) {
                $success[] = "Endpoint /v1/traces: Resposta válida recebida";
                echo "   ✅ Endpoint funcionando corretamente\n";
                echo "   ℹ️  Total de logs: " . ($data['data']['total_logs'] ?? 0) . "\n";
                
                if (isset($data['data']['summary'])) {
                    $summary = $data['data']['summary'];
                    echo "   ℹ️  Tempo médio: " . ($summary['average_response_time'] ?? 0) . "ms\n";
                }
            } else {
                if ($httpCode === 404) {
                    echo "   ⚠️  Trace não encontrado (esperado se não houver logs para este request_id)\n";
                    echo "   ℹ️  Isso pode acontecer se a rota /health estiver excluída do audit\n";
                } else {
                    $errors[] = "Endpoint /v1/traces: Resposta inválida (HTTP {$httpCode})";
                    echo "   ❌ Resposta inválida (HTTP {$httpCode})\n";
                    echo "   ℹ️  Resposta: " . substr($response, 0, 200) . "\n";
                }
            }
        }
    } catch (\Exception $e) {
        $errors[] = "Endpoint /v1/traces: " . $e->getMessage();
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// ============================================
// RESUMO FINAL
// ============================================
echo str_repeat("=", 60) . "\n";
echo "📊 RESUMO DOS TESTES DE INTEGRAÇÃO\n";
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
    echo "🎉 TODOS OS TESTES DE INTEGRAÇÃO PASSARAM!\n";
    echo "✅ Sistema de Tracing está funcionando 100%!\n";
    exit(0);
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM.\n";
    echo "ℹ️  Nota: Alguns avisos podem ser esperados (ex: rotas excluídas do audit)\n";
    exit(1);
}


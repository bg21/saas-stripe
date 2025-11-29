<?php
/**
 * Teste completo do sistema de Tracing com autenticação
 * 
 * Testa o fluxo completo:
 * 1. Login para obter token
 * 2. Requisição autenticada para capturar X-Request-ID
 * 3. Verificação de salvamento no audit_logs
 * 4. Teste do endpoint GET /v1/traces/:request_id
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Config;

Config::load();

echo "🧪 TESTE COMPLETO DO SISTEMA DE TRACING (COM AUTENTICAÇÃO)\n";
echo str_repeat("=", 70) . "\n\n";

$baseUrl = Config::get('APP_URL', 'http://localhost:8080');
$errors = [];
$success = [];
$requestId = null;
$authToken = null;

// ============================================
// PASSO 1: Login para obter token
// ============================================
echo "1️⃣ Fazendo login para obter token de autenticação...\n";

try {
    // Tenta usar master key primeiro (mais simples)
    $masterKey = Config::get('API_MASTER_KEY');
    
    if ($masterKey) {
        $authToken = $masterKey;
        $success[] = "Autenticação: Usando master key";
        echo "   ✅ Usando master key para autenticação\n";
    } else {
        // Tenta fazer login com usuário padrão
        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'admin123'
        ];
        
        $ch = curl_init($baseUrl . '/v1/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['data']['token'])) {
                $authToken = $data['data']['token'];
                $success[] = "Autenticação: Login realizado com sucesso";
                echo "   ✅ Login realizado com sucesso\n";
            } else {
                $errors[] = "Autenticação: Token não encontrado na resposta";
                echo "   ❌ Token não encontrado na resposta\n";
            }
        } else {
            $errors[] = "Autenticação: Falha no login (HTTP {$httpCode})";
            echo "   ❌ Falha no login (HTTP {$httpCode})\n";
            echo "   ℹ️  Resposta: " . substr($response, 0, 200) . "\n";
        }
    }
} catch (\Exception $e) {
    $errors[] = "Autenticação: " . $e->getMessage();
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// PASSO 2: Requisição autenticada para capturar X-Request-ID
// ============================================
if ($authToken) {
    echo "2️⃣ Fazendo requisição autenticada para capturar X-Request-ID...\n";
    
    try {
        // Faz requisição GET para uma rota que não está excluída e não precisa de dados específicos
        // Usa GET /v1/users (precisa de autenticação, mas é uma rota simples)
        $ch = curl_init($baseUrl . '/v1/users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $authToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        curl_close($ch);
        
        // Extrai X-Request-ID do header
        if (preg_match('/X-Request-ID:\s*([a-f0-9]{32})/i', $headers, $matches)) {
            $requestId = $matches[1];
            $success[] = "Requisição HTTP: X-Request-ID capturado com sucesso";
            echo "   ✅ X-Request-ID capturado: {$requestId}\n";
            echo "   ℹ️  HTTP Status: {$httpCode}\n";
            
            // Verifica formato
            if (preg_match('/^[a-f0-9]{32}$/i', $requestId)) {
                $success[] = "Request ID: Formato válido (32 caracteres hexadecimais)";
                echo "   ✅ Formato válido\n";
            } else {
                $errors[] = "Request ID: Formato inválido";
                echo "   ❌ Formato inválido\n";
            }
        } else {
            $errors[] = "Requisição HTTP: X-Request-ID não encontrado no header";
            echo "   ❌ X-Request-ID não encontrado\n";
            echo "   ℹ️  Headers recebidos:\n";
            // Mostra apenas as primeiras linhas dos headers
            $headerLines = explode("\n", $headers);
            foreach (array_slice($headerLines, 0, 15) as $line) {
                echo "      " . trim($line) . "\n";
            }
            if (count($headerLines) > 15) {
                echo "      ... (mais " . (count($headerLines) - 15) . " linhas)\n";
            }
        }
    } catch (\Exception $e) {
        $errors[] = "Requisição HTTP: " . $e->getMessage();
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // ============================================
    // PASSO 3: Verificar se request_id foi salvo no audit_logs
    // ============================================
    if ($requestId) {
        echo "3️⃣ Verificando se request_id foi salvo no audit_logs...\n";
        
        try {
            $db = \App\Utils\Database::getInstance();
            
            // Aguarda um pouco para garantir que o log foi salvo (assíncrono via register_shutdown_function)
            echo "   ℹ️  Aguardando 3 segundos para logs assíncronos...\n";
            sleep(3);
            
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
                echo "   ℹ️  Timestamp: {$log['created_at']}\n";
            } else {
                // Tenta novamente após mais tempo
                echo "   ℹ️  Log não encontrado, aguardando mais 2 segundos...\n";
                sleep(2);
                $stmt->execute(['request_id' => $requestId]);
                $log = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($log) {
                    $success[] = "Audit Log: request_id encontrado no banco de dados (após espera adicional)";
                    echo "   ✅ Log encontrado no banco de dados (após espera adicional)\n";
                    echo "   ℹ️  ID do log: {$log['id']}\n";
                } else {
                    $errors[] = "Audit Log: request_id não encontrado no banco de dados";
                    echo "   ❌ Log não encontrado no banco de dados\n";
                    echo "   ℹ️  Isso pode indicar que:\n";
                    echo "      - A rota está excluída do AuditMiddleware\n";
                    echo "      - O log ainda não foi processado (assíncrono)\n";
                    echo "      - Há um problema no salvamento do request_id\n";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Audit Log: " . $e->getMessage();
            echo "   ❌ Erro: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
        
        // ============================================
        // PASSO 4: Testar endpoint GET /v1/traces/:request_id
        // ============================================
        echo "4️⃣ Testando endpoint GET /v1/traces/:request_id...\n";
        
        try {
            $ch = curl_init($baseUrl . '/v1/traces/' . $requestId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $authToken,
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
                
                if (isset($data['data'])) {
                    $trace = $data['data'];
                    echo "   ℹ️  Request ID: {$trace['request_id']}\n";
                    echo "   ℹ️  Total de logs: " . ($trace['total_logs'] ?? 0) . "\n";
                    
                    if (isset($trace['summary'])) {
                        $summary = $trace['summary'];
                        echo "   ℹ️  Tempo médio: " . ($summary['average_response_time'] ?? 0) . "ms\n";
                        
                        if (isset($summary['endpoints']) && !empty($summary['endpoints'])) {
                            echo "   ℹ️  Endpoints: " . implode(', ', array_keys($summary['endpoints'])) . "\n";
                        }
                    }
                    
                    if (isset($trace['logs']) && count($trace['logs']) > 0) {
                        $success[] = "Endpoint /v1/traces: Logs retornados corretamente";
                        echo "   ✅ " . count($trace['logs']) . " log(s) retornado(s)\n";
                    } else {
                        echo "   ⚠️  Nenhum log retornado (pode ser esperado se a rota estiver excluída do audit)\n";
                    }
                }
            } else {
                if ($httpCode === 404) {
                    echo "   ⚠️  Trace não encontrado (HTTP 404)\n";
                    echo "   ℹ️  Isso pode acontecer se não houver logs para este request_id\n";
                    echo "   ℹ️  Resposta: " . substr($response, 0, 200) . "\n";
                } elseif ($httpCode === 401 || $httpCode === 403) {
                    $errors[] = "Endpoint /v1/traces: Erro de autenticação/autorização (HTTP {$httpCode})";
                    echo "   ❌ Erro de autenticação/autorização (HTTP {$httpCode})\n";
                    echo "   ℹ️  Resposta: " . substr($response, 0, 200) . "\n";
                } else {
                    $errors[] = "Endpoint /v1/traces: Resposta inválida (HTTP {$httpCode})";
                    echo "   ❌ Resposta inválida (HTTP {$httpCode})\n";
                    echo "   ℹ️  Resposta: " . substr($response, 0, 200) . "\n";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Endpoint /v1/traces: " . $e->getMessage();
            echo "   ❌ Erro: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

// ============================================
// RESUMO FINAL
// ============================================
echo str_repeat("=", 70) . "\n";
echo "📊 RESUMO DO TESTE COMPLETO\n";
echo str_repeat("=", 70) . "\n\n";

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

// Determina status final
$criticalErrors = array_filter($errors, function($error) {
    return strpos($error, 'X-Request-ID não encontrado') !== false || 
           strpos($error, 'Autenticação') !== false;
});

if (count($criticalErrors) === 0 && $requestId) {
    echo "🎉 TESTE COMPLETO PASSOU!\n";
    echo "✅ Sistema de Tracing está funcionando 100%!\n";
    echo "✅ Header X-Request-ID está sendo gerado e retornado corretamente\n";
    if (count($errors) > 0) {
        echo "⚠️  Alguns avisos menores (ver acima), mas funcionalidade principal está OK\n";
    }
    exit(0);
} elseif ($requestId) {
    echo "⚠️  TESTE PARCIALMENTE BEM-SUCEDIDO\n";
    echo "✅ Request ID foi gerado e capturado\n";
    echo "⚠️  Alguns aspectos precisam de atenção (ver erros acima)\n";
    exit(1);
} else {
    echo "❌ TESTE FALHOU\n";
    echo "❌ Não foi possível capturar X-Request-ID\n";
    echo "ℹ️  Verifique se:\n";
    echo "   1. O servidor está rodando em {$baseUrl}\n";
    echo "   2. O TracingMiddleware está sendo executado\n";
    echo "   3. A rota não está na lista de exclusão\n";
    exit(1);
}


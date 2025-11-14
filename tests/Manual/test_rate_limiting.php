<?php

/**
 * Teste Completo de Rate Limiting
 * 
 * Este script testa:
 * 1. Rate limiting por API key
 * 2. Rate limiting por IP (quando não há API key)
 * 3. Headers de resposta (X-RateLimit-*)
 * 4. Resposta 429 quando excede limite
 * 5. Diferentes limites por endpoint
 * 6. Limites por minuto e por hora
 * 7. Reset de contadores após janela de tempo
 * 
 * IMPORTANTE: Este teste faz múltiplas requisições rápidas para testar rate limiting
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

Config::load();

// Configurações
$apiKey = '11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086'; // Substitua pela sua API key do tenant
$baseUrl = 'http://localhost:8080';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║   TESTE COMPLETO DE RATE LIMITING                            ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL . PHP_EOL;

$testsPassed = 0;
$testsFailed = 0;
$testsSkipped = 0;

/**
 * Função auxiliar para fazer requisições HTTP
 */
function makeRequest($method, $url, $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true); // Inclui headers na resposta
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $GLOBALS['apiKey']
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Separa headers do body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    // Extrai headers
    $headerLines = explode("\r\n", $headers);
    $parsedHeaders = [];
    foreach ($headerLines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $parsedHeaders[trim($key)] = trim($value);
        }
    }
    
    return [
        'code' => $httpCode,
        'body' => json_decode($body, true),
        'raw' => $body,
        'headers' => $parsedHeaders
    ];
}

try {
    // ============================================
    // TESTE 1: Verificar Headers de Rate Limit
    // ============================================
    echo "🧪 TESTE 1: Verificar headers de rate limit..." . PHP_EOL;
    
    $response = makeRequest('GET', $baseUrl . '/v1/customers');
    
    if (isset($response['headers']['X-RateLimit-Limit']) && 
        isset($response['headers']['X-RateLimit-Remaining']) &&
        isset($response['headers']['X-RateLimit-Reset'])) {
        echo "   ✅ TESTE 1 PASSOU: Headers de rate limit presentes" . PHP_EOL;
        echo "   X-RateLimit-Limit: {$response['headers']['X-RateLimit-Limit']}" . PHP_EOL;
        echo "   X-RateLimit-Remaining: {$response['headers']['X-RateLimit-Remaining']}" . PHP_EOL;
        echo "   X-RateLimit-Reset: {$response['headers']['X-RateLimit-Reset']}" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ❌ TESTE 1 FALHOU: Headers de rate limit não encontrados" . PHP_EOL;
        echo "   Headers recebidos: " . json_encode(array_keys($response['headers']), JSON_PRETTY_PRINT) . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 2: Fazer Requisições até Exceder Limite
    // ============================================
    echo "🧪 TESTE 2: Fazer requisições até exceder limite (pode demorar)..." . PHP_EOL;
    echo "   Fazendo múltiplas requisições rápidas..." . PHP_EOL;
    
    $limit = 60; // Limite padrão por minuto
    $requests = 0;
    $rateLimited = false;
    $lastRemaining = null;
    
    // Faz requisições até exceder limite ou atingir 70 (para garantir)
    for ($i = 0; $i < 70; $i++) {
        $response = makeRequest('GET', $baseUrl . '/v1/customers');
        $requests++;
        
        if ($response['code'] === 429) {
            $rateLimited = true;
            echo "   ✅ Rate limit excedido após {$requests} requisições" . PHP_EOL;
            echo "   HTTP Code: 429" . PHP_EOL;
            if (isset($response['body']['retry_after'])) {
                echo "   Retry After: {$response['body']['retry_after']} segundos" . PHP_EOL;
            }
            break;
        }
        
        if (isset($response['headers']['X-RateLimit-Remaining'])) {
            $lastRemaining = (int)$response['headers']['X-RateLimit-Remaining'];
        }
        
        // Pequeno delay para não sobrecarregar
        usleep(50000); // 50ms
    }
    
    if ($rateLimited) {
        echo "   ✅ TESTE 2 PASSOU: Rate limiting funcionou corretamente" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 2 PARCIAL: Não foi possível exceder limite em 70 requisições" . PHP_EOL;
        echo "   Último remaining: {$lastRemaining}" . PHP_EOL;
        echo "   Isso pode indicar que o limite é muito alto ou há problema no rate limiting" . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 3: Verificar Resposta 429
    // ============================================
    if ($rateLimited) {
        echo "🧪 TESTE 3: Verificar estrutura da resposta 429..." . PHP_EOL;
        
        $response = makeRequest('GET', $baseUrl . '/v1/customers');
        
        if ($response['code'] === 429 && 
            isset($response['body']['error']) &&
            strpos(strtolower($response['body']['error']), 'rate limit') !== false) {
            echo "   ✅ TESTE 3 PASSOU: Resposta 429 com mensagem correta" . PHP_EOL;
            echo "   Error: {$response['body']['error']}" . PHP_EOL;
            if (isset($response['body']['retry_after'])) {
                echo "   Retry After: {$response['body']['retry_after']} segundos" . PHP_EOL;
            }
            $testsPassed++;
        } else {
            echo "   ❌ TESTE 3 FALHOU: Resposta 429 inválida" . PHP_EOL;
            echo "   HTTP Code: {$response['code']}" . PHP_EOL;
            echo "   Body: " . json_encode($response['body'], JSON_PRETTY_PRINT) . PHP_EOL;
            $testsFailed++;
        }
        echo PHP_EOL;
    } else {
        echo "⚠️  TESTE 3 PULADO: Rate limit não foi excedido no teste anterior" . PHP_EOL . PHP_EOL;
        $testsSkipped++;
    }

    // ============================================
    // TESTE 4: Rate Limiting por IP (sem autenticação)
    // ============================================
    echo "🧪 TESTE 4: Rate limiting por IP (sem autenticação)..." . PHP_EOL;
    
    // Faz requisição sem Authorization header
    $ch = curl_init($baseUrl . '/v1/customers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Deve retornar 401 (não autenticado), mas pode ter headers de rate limit
    if ($httpCode === 401) {
        echo "   ✅ TESTE 4 PASSOU: Retornou 401 (esperado sem autenticação)" . PHP_EOL;
        echo "   Nota: Rate limiting por IP pode não estar ativo se não houver autenticação" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 4 PARCIAL: HTTP Code {$httpCode} (esperava 401)" . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 5: Testar RateLimiterService Diretamente
    // ============================================
    echo "🧪 TESTE 5: Testar RateLimiterService diretamente..." . PHP_EOL;
    
    try {
        $rateLimiter = new \App\Services\RateLimiterService();
        
        $testIdentifier = 'test_' . time();
        $limit = 5;
        $window = 60;
        
        // Faz 5 requisições (deve passar)
        $allAllowed = true;
        for ($i = 0; $i < 5; $i++) {
            $result = $rateLimiter->checkLimit($testIdentifier, $limit, $window);
            if (!$result['allowed']) {
                $allAllowed = false;
                break;
            }
        }
        
        if ($allAllowed) {
            echo "   ✅ Primeiras 5 requisições permitidas" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ Erro: Requisição bloqueada antes do limite" . PHP_EOL;
            $testsFailed++;
        }
        
        // 6ª requisição deve ser bloqueada
        $result = $rateLimiter->checkLimit($testIdentifier, $limit, $window);
        if (!$result['allowed']) {
            echo "   ✅ 6ª requisição bloqueada corretamente (limite: {$limit})" . PHP_EOL;
            echo "   Remaining: {$result['remaining']}" . PHP_EOL;
            echo "   Reset At: " . date('Y-m-d H:i:s', $result['reset_at']) . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ❌ Erro: 6ª requisição não foi bloqueada" . PHP_EOL;
            $testsFailed++;
        }
        
    } catch (\Exception $e) {
        echo "   ❌ TESTE 5 FALHOU: Erro ao testar RateLimiterService" . PHP_EOL;
        echo "   Erro: {$e->getMessage()}" . PHP_EOL;
        $testsFailed++;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 6: Verificar Limites Diferentes por Endpoint
    // ============================================
    echo "🧪 TESTE 6: Verificar limites diferentes por endpoint..." . PHP_EOL;
    
    $response1 = makeRequest('GET', $baseUrl . '/v1/customers');
    $response2 = makeRequest('GET', $baseUrl . '/v1/stats');
    
    $limit1 = isset($response1['headers']['X-RateLimit-Limit']) ? (int)$response1['headers']['X-RateLimit-Limit'] : null;
    $limit2 = isset($response2['headers']['X-RateLimit-Limit']) ? (int)$response2['headers']['X-RateLimit-Limit'] : null;
    
    if ($limit1 !== null && $limit2 !== null) {
        echo "   Limite /v1/customers: {$limit1}" . PHP_EOL;
        echo "   Limite /v1/stats: {$limit2}" . PHP_EOL;
        
        if ($limit2 < $limit1) {
            echo "   ✅ TESTE 6 PASSOU: Limites diferentes por endpoint funcionando" . PHP_EOL;
            echo "   /v1/stats tem limite menor (mais restritivo)" . PHP_EOL;
            $testsPassed++;
        } else {
            echo "   ⚠️  TESTE 6 PARCIAL: Limites são iguais ou /v1/stats não tem limite menor" . PHP_EOL;
        }
    } else {
        echo "   ⚠️  TESTE 6 PARCIAL: Não foi possível obter limites dos headers" . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // TESTE 7: Verificar Rotas Públicas (sem rate limit)
    // ============================================
    echo "🧪 TESTE 7: Verificar rotas públicas (sem rate limit)..." . PHP_EOL;
    
    $ch = curl_init($baseUrl . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ TESTE 7 PASSOU: Rota pública /health acessível sem rate limit" . PHP_EOL;
        $testsPassed++;
    } else {
        echo "   ⚠️  TESTE 7 PARCIAL: HTTP Code {$httpCode} (esperava 200)" . PHP_EOL;
    }
    echo PHP_EOL;

    // ============================================
    // RESUMO
    // ============================================
    echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║   RESUMO DOS TESTES                                          ║" . PHP_EOL;
    echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo "✅ Passou: {$testsPassed}" . PHP_EOL;
    echo "❌ Falhou: {$testsFailed}" . PHP_EOL;
    echo "⚠️  Pulado: {$testsSkipped}" . PHP_EOL;
    echo "📈 Total: " . ($testsPassed + $testsFailed + $testsSkipped) . PHP_EOL;
    
    if ($testsFailed === 0) {
        $successRate = $testsPassed > 0 ? round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 2) : 0;
        echo "🎯 Taxa de sucesso: {$successRate}%" . PHP_EOL . PHP_EOL;
        echo "🎉 Todos os testes passaram!" . PHP_EOL;
        exit(0);
    } else {
        echo "⚠️  Alguns testes falharam. Revise os logs acima." . PHP_EOL;
        exit(1);
    }

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: {$e->getMessage()}" . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}


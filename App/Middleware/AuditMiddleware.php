<?php

namespace App\Middleware;

use App\Models\AuditLog;
use App\Services\Logger;
use Config;
use Flight;

/**
 * Middleware de Auditoria
 * 
 * Registra todas as requisições para rastreabilidade e compliance.
 * Não bloqueia requisições - apenas registra informações.
 */
class AuditMiddleware
{
    private AuditLog $auditLogModel;
    private bool $enabled;
    private array $excludedRoutes;
    private int $maxRequestBodySize;

    public function __construct(AuditLog $auditLogModel)
    {
        $this->auditLogModel = $auditLogModel;
        
        // Configurações
        $this->enabled = Config::get('AUDIT_ENABLED', 'true') === 'true';
        $this->excludedRoutes = [
            '/',
            '/health',
            '/v1/webhook', // Webhooks podem gerar muitos logs
            '/debug'
        ];
        $this->maxRequestBodySize = (int) Config::get('AUDIT_MAX_REQUEST_BODY_SIZE', '10240'); // 10KB padrão
    }

    /**
     * Captura início da requisição
     * Deve ser chamado no before('start')
     */
    public function captureRequest(): void
    {
        if (!$this->enabled) {
            return;
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        
        // Ignora rotas excluídas
        if (in_array($requestUri, $this->excludedRoutes)) {
            return;
        }

        // Armazena informações iniciais
        Flight::set('audit_start_time', microtime(true));
        Flight::set('audit_request_data', [
            'endpoint' => $requestUri,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_body' => $this->getRequestBody(),
            'tenant_id' => Flight::get('tenant_id'),
            'user_id' => Flight::get('user_id'), // Para uso futuro
        ]);
    }

    /**
     * Registra log após resposta
     * Deve ser chamado no after('start') ou no tratamento de erros
     */
    public function logResponse(int $statusCode = 200): void
    {
        if (!$this->enabled) {
            return;
        }

        $requestData = Flight::get('audit_request_data');
        
        // Se não capturou início, não registra
        if (!$requestData) {
            return;
        }

        $requestUri = $requestData['endpoint'];
        
        // Ignora rotas excluídas novamente (por segurança)
        if (in_array($requestUri, $this->excludedRoutes)) {
            return;
        }

        $startTime = Flight::get('audit_start_time');
        $responseTime = $startTime ? (int) ((microtime(true) - $startTime) * 1000) : 0;

        try {
            $this->auditLogModel->createLog([
                'tenant_id' => $requestData['tenant_id'],
                'user_id' => $requestData['user_id'],
                'endpoint' => $requestData['endpoint'],
                'method' => $requestData['method'],
                'ip_address' => $requestData['ip_address'],
                'user_agent' => $requestData['user_agent'],
                'request_body' => $this->sanitizeRequestBody($requestData['request_body']),
                'response_status' => $statusCode,
                'response_time' => $responseTime,
            ]);
        } catch (\Exception $e) {
            // Não deve quebrar a aplicação se o log falhar
            Logger::error("Erro ao registrar log de auditoria", [
                'error' => $e->getMessage(),
                'endpoint' => $requestData['endpoint']
            ]);
        }

        // Limpa dados temporários (FlightPHP não tem clear, então apenas sobrescreve)
        Flight::set('audit_start_time', null);
        Flight::set('audit_request_data', null);
    }

    /**
     * Obtém IP do cliente (considerando proxies)
     */
    private function getClientIp(): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx
            'HTTP_X_FORWARDED_FOR',  // Proxies
            'REMOTE_ADDR'            // IP direto
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Se for X-Forwarded-For, pega o primeiro IP
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Valida IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Obtém corpo da requisição (apenas para métodos que enviam dados)
     */
    private function getRequestBody(): ?string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Apenas para métodos que enviam dados
        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        // Lê do input stream
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return null;
        }

        // Limita tamanho
        if (strlen($input) > $this->maxRequestBodySize) {
            $input = substr($input, 0, $this->maxRequestBodySize) . '... [truncated]';
        }

        return $input;
    }

    /**
     * Sanitiza request body removendo dados sensíveis
     */
    private function sanitizeRequestBody(?string $body): ?string
    {
        if (!$body) {
            return null;
        }

        // Tenta decodificar JSON
        $decoded = json_decode($body, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Remove campos sensíveis
            $sensitiveFields = ['password', 'password_hash', 'api_key', 'token', 'secret', 'credit_card', 'cvv'];
            
            foreach ($sensitiveFields as $field) {
                if (isset($decoded[$field])) {
                    $decoded[$field] = '[REDACTED]';
                }
            }

            return json_encode($decoded);
        }

        // Se não for JSON, retorna como está (mas pode conter dados sensíveis)
        // Em produção, considere não registrar body não-JSON ou aplicar regex
        return $body;
    }
}


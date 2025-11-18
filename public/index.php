<?php

/**
 * Arquivo principal da aplicação
 * Configura FlightPHP e rotas
 */

// ✅ OTIMIZAÇÃO: Compressão de resposta (gzip/deflate)
if (extension_loaded('zlib') && !ob_get_level()) {
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if (strpos($acceptEncoding, 'gzip') !== false) {
        ob_start('ob_gzhandler');
    } elseif (strpos($acceptEncoding, 'deflate') !== false) {
        ob_start('ob_deflatehandler');
    } else {
        ob_start();
    }
}

// Servir arquivos estáticos da pasta /app (front-end)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/^\/app\//', $requestUri)) {
    $filePath = __DIR__ . $requestUri;
    
    // Verificar se arquivo existe e é seguro (dentro da pasta public/app)
    if (file_exists($filePath) && is_file($filePath)) {
        $realPath = realpath($filePath);
        $publicPath = realpath(__DIR__);
        
        // Verificar se o arquivo está dentro de public/app
        if ($realPath && strpos($realPath, $publicPath . DIRECTORY_SEPARATOR . 'app') === 0) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimeTypes = [
                'html' => 'text/html; charset=utf-8',
                'js' => 'application/javascript; charset=utf-8',
                'css' => 'text/css; charset=utf-8',
                'json' => 'application/json; charset=utf-8',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf'
            ];
            
            header('Content-Type: ' . ($mimeTypes[$ext] ?? 'text/plain'));
            // ✅ OTIMIZAÇÃO: Cache agressivo para assets estáticos (1 ano)
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            // Remove headers que expõem informações do servidor
            header_remove('X-Powered-By');
            readfile($filePath);
            exit;
        }
    }
    
    // Arquivo não encontrado
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>404 - Not Found</title></head><body><h1>404 - Arquivo não encontrado</h1></body></html>';
    exit;
}

// Servir arquivos CSS da pasta /css
if (preg_match('/^\/css\//', $requestUri)) {
    $filePath = __DIR__ . $requestUri;
    
    if (file_exists($filePath) && is_file($filePath)) {
        $realPath = realpath($filePath);
        $publicPath = realpath(__DIR__);
        
        // Verificar se o arquivo está dentro de public/css
        if ($realPath && strpos($realPath, $publicPath . DIRECTORY_SEPARATOR . 'css') === 0) {
            header('Content-Type: text/css; charset=utf-8');
            // ✅ OTIMIZAÇÃO: Cache agressivo para CSS (1 ano)
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            // Remove headers que expõem informações do servidor
            header_remove('X-Powered-By');
            readfile($filePath);
            exit;
        }
    }
    
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega configurações
require_once __DIR__ . '/../config/config.php';
Config::load();

// Inicializa FlightPHP
use flight\Engine;
$app = new Engine();

// Configura JSON como padrão
$app->set('flight.handle_errors', true);
$app->set('flight.log_errors', true);

// Suprime warnings de compatibilidade do FlightPHP com PHP 8.2
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Middleware de CORS e Headers de Segurança
$app->before('start', function() {
    // Remove headers que expõem informações do servidor
    // Ocultar versão do PHP
    header_remove('X-Powered-By');
    
    // Ocultar informações do servidor web (se configurado no servidor)
    // Nota: Para Apache, configure no .htaccess: ServerTokens Prod
    // Para Nginx, configure: server_tokens off;
    
    // ✅ OTIMIZAÇÃO: Cache de headers para respostas JSON (5 minutos)
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($requestUri, '/v1/') === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Cache-Control: private, max-age=300'); // 5 minutos para APIs
    }
    
    // Headers de Segurança (sempre aplicados)
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    // Ajuste conforme necessário para permitir recursos externos específicos
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
           "connect-src 'self'; " .
           "frame-ancestors 'none';";
    header("Content-Security-Policy: {$csp}");
    
    // HSTS (apenas em HTTPS)
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // CORS - Configuração Segura
    // Lista de origens permitidas (ajuste conforme necessário)
    $allowedOrigins = Config::get('CORS_ALLOWED_ORIGINS', '');
    $allowedOriginsArray = !empty($allowedOrigins) ? explode(',', $allowedOrigins) : [];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    
    // Em desenvolvimento, permite localhost e origens configuradas
    if (Config::isDevelopment()) {
        if ($origin && (
            strpos($origin, 'http://localhost') === 0 ||
            strpos($origin, 'http://127.0.0.1') === 0 ||
            in_array($origin, $allowedOriginsArray)
        )) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
        } elseif (!$origin) {
            // Se não há origem (requisição direta), permite
            header('Access-Control-Allow-Origin: *');
        }
    } else {
        // Em produção, apenas origens explicitamente permitidas
        if ($origin && in_array($origin, $allowedOriginsArray)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
        } else {
            // Rejeita requisições de origens não permitidas
            // (não define header, o que faz o navegador bloquear)
        }
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400'); // Cache preflight por 24h
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
});

// Middleware de autenticação (suporta API Key e Session ID)
$app->before('start', function() use ($app) {
    // Rotas públicas (sem autenticação)
    $publicRoutes = [
        '/', '/v1/webhook', '/health', '/health/detailed', '/v1/auth/login', '/login', '/register',
        '/index', '/checkout', '/success', '/cancel', '/api-docs', '/api-docs/ui',
        // Rotas autenticadas (serão verificadas individualmente)
        '/dashboard', '/customers', '/subscriptions', '/products', '/prices', '/reports',
        '/users', '/permissions', '/audit-logs',
        '/customer-details', '/customer-invoices', '/subscription-details', '/subscription-history',
        '/invoices', '/refunds', '/coupons', '/promotion-codes', '/settings',
        '/transactions', '/transaction-details', '/disputes', '/charges', '/payouts',
        '/invoice-items', '/tax-rates', '/payment-methods', '/billing-portal'
    ];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Permite CSS e arquivos estáticos
    if (strpos($requestUri, '/css/') === 0 || strpos($requestUri, '/app/') === 0) {
        return;
    }
    
    if (in_array($requestUri, $publicRoutes) || strpos($requestUri, '/api-docs') === 0) {
        return;
    }

    // Obtém o request do FlightPHP
    $request = $app->request();
    
    // Tenta obter o header Authorization de várias formas
    $authHeader = null;
    
    // Primeiro tenta getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    
    // Fallback: verifica $_SERVER (formato HTTP_AUTHORIZATION)
    if (!$authHeader) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    }
    
    // Se ainda não encontrou, verifica todas as variáveis $_SERVER que começam com HTTP_
    if (!$authHeader) {
        foreach ($_SERVER as $key => $value) {
            if (strtoupper($key) === 'HTTP_AUTHORIZATION' || strtoupper($key) === 'REDIRECT_HTTP_AUTHORIZATION') {
                $authHeader = $value;
                break;
            }
        }
    }
    
    // Se ainda não encontrou, tenta via FlightPHP request
    if (!$authHeader) {
        try {
            if (method_exists($request, 'getHeader')) {
                $authHeader = $request->getHeader('Authorization') ?? $request->getHeader('authorization');
            }
        } catch (\Exception $e) {
            // Ignora
        }
    }
    
    // Se não tem header, retorna erro
    if (!$authHeader) {
        // ✅ SEGURANÇA: Não expõe informações sensíveis mesmo em desenvolvimento
        $debug = Config::isDevelopment() ? [
            'server_keys_count' => count(array_keys($_SERVER)),
            'has_authorization' => isset($_SERVER['HTTP_AUTHORIZATION'])
        ] : null;
        $app->json(['error' => 'Token de autenticação não fornecido', 'debug' => $debug], 401);
        $app->stop();
        exit;
    }
    
    // Extrai o Bearer token
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $app->json(['error' => 'Formato de token inválido'], 401);
        $app->stop();
        exit;
    }
    
    $token = trim($matches[1]);
    
    // ✅ CACHE: Verifica cache de autenticação (TTL: 5 minutos)
    $cacheKey = "auth:token:" . hash('sha256', $token);
    $cachedAuth = \App\Services\CacheService::getJson($cacheKey);
    
    if ($cachedAuth !== null) {
        // Usa dados do cache
        if (isset($cachedAuth['user_id'])) {
            // Autenticação via Session ID (usuário)
            Flight::set('user_id', (int)$cachedAuth['user_id']);
            Flight::set('user_role', $cachedAuth['user_role'] ?? 'viewer');
            Flight::set('user_email', $cachedAuth['user_email']);
            Flight::set('user_name', $cachedAuth['user_name']);
            Flight::set('tenant_id', (int)$cachedAuth['tenant_id']);
            Flight::set('tenant_name', $cachedAuth['tenant_name']);
            Flight::set('is_user_auth', true);
            Flight::set('is_master', false);
        } else {
            // Autenticação via API Key (tenant)
            Flight::set('tenant_id', (int)$cachedAuth['tenant_id']);
            Flight::set('tenant', $cachedAuth['tenant'] ?? null);
            Flight::set('is_master', $cachedAuth['is_master'] ?? false);
            Flight::set('is_user_auth', false);
        }
        return;
    }
    
    // Se não há cache, valida normalmente
    // Tenta primeiro como Session ID (usuário autenticado)
    $userSessionModel = new \App\Models\UserSession();
    $session = $userSessionModel->validate($token);
    
    if ($session) {
        // Autenticação via Session ID (usuário)
        $authData = [
            'user_id' => (int)$session['user_id'],
            'user_role' => $session['role'] ?? 'viewer',
            'user_email' => $session['email'],
            'user_name' => $session['name'],
            'tenant_id' => (int)$session['tenant_id'],
            'tenant_name' => $session['tenant_name'],
            'is_user_auth' => true,
            'is_master' => false
        ];
        
        Flight::set('user_id', $authData['user_id']);
        Flight::set('user_role', $authData['user_role']);
        Flight::set('user_email', $authData['user_email']);
        Flight::set('user_name', $authData['user_name']);
        Flight::set('tenant_id', $authData['tenant_id']);
        Flight::set('tenant_name', $authData['tenant_name']);
        Flight::set('is_user_auth', true);
        Flight::set('is_master', false);
        
        // ✅ Salva no cache
        \App\Services\CacheService::setJson($cacheKey, $authData, 300);
        return;
    }
    
    // Se não é Session ID, tenta como API Key (tenant)
    $tenantModel = new \App\Models\Tenant();
    $tenant = $tenantModel->findByApiKey($token);
    
    if (!$tenant) {
        // Verifica master key (usando hash_equals para prevenir timing attacks)
        $masterKey = Config::get('API_MASTER_KEY');
        if ($masterKey && hash_equals($masterKey, $token)) {
            $authData = [
                'tenant_id' => null,
                'is_master' => true,
                'is_user_auth' => false
            ];
            
            Flight::set('tenant_id', null);
            Flight::set('is_master', true);
            Flight::set('is_user_auth', false);
            
            // ✅ Salva no cache
            \App\Services\CacheService::setJson($cacheKey, $authData, 300);
            return;
        }
        
        // ✅ SEGURANÇA: Não expõe informações sensíveis sobre tokens mesmo em desenvolvimento
        $debug = Config::isDevelopment() ? [
            'token_length' => strlen($token),
            'token_format_valid' => preg_match('/^[a-zA-Z0-9]+$/', $token) === 1
        ] : null;
        $app->json(['error' => 'Token inválido', 'debug' => $debug], 401);
        $app->stop();
        exit;
    }
    
    if ($tenant['status'] !== 'active') {
        $app->json(['error' => 'Tenant inativo'], 401);
        $app->stop();
        exit;
    }

    // Autenticação via API Key (tenant)
    $authData = [
        'tenant_id' => (int)$tenant['id'],
        'tenant' => $tenant,
        'is_master' => false,
        'is_user_auth' => false
    ];
    
    Flight::set('tenant_id', (int)$tenant['id']);
    Flight::set('tenant', $tenant);
    Flight::set('is_master', false);
    Flight::set('is_user_auth', false);
    
    // ✅ Salva no cache
    \App\Services\CacheService::setJson($cacheKey, $authData, 300);
});

// Inicializa serviços (injeção de dependência)
$rateLimiterService = new \App\Services\RateLimiterService();
$rateLimitMiddleware = new \App\Middleware\RateLimitMiddleware($rateLimiterService);

// Inicializa middleware de validação de tamanho de payload
$payloadSizeMiddleware = new \App\Middleware\PayloadSizeMiddleware();

// Inicializa middleware de auditoria
$auditLogModel = new \App\Models\AuditLog();
$auditMiddleware = new \App\Middleware\AuditMiddleware($auditLogModel);

// Middleware de Validação de Tamanho de Payload (antes de processar requisições)
$app->before('start', function() use ($payloadSizeMiddleware, $app) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Aplica apenas em métodos que podem ter payload
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        // Endpoints críticos têm limite mais restritivo (512KB)
        $criticalEndpoints = [
            '/v1/customers',
            '/v1/subscriptions',
            '/v1/products',
            '/v1/prices',
            '/v1/auth/login',
            '/v1/users'
        ];
        
        $isCritical = false;
        foreach ($criticalEndpoints as $endpoint) {
            if (strpos($requestUri, $endpoint) === 0) {
                $isCritical = true;
                break;
            }
        }
        
        if ($isCritical) {
            $allowed = $payloadSizeMiddleware->checkStrict();
        } else {
            $allowed = $payloadSizeMiddleware->check();
        }
        
        if (!$allowed) {
            // Resposta já foi enviada pelo middleware
            $app->stop();
            exit;
        }
    }
});

// Middleware de Rate Limiting (após autenticação)
$app->before('start', function() use ($rateLimitMiddleware, $app) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Rotas públicas têm rate limiting mais restritivo (exceto webhook que tem limite maior)
    $publicRoutes = ['/', '/health', '/health/detailed'];
    
    if (in_array($requestUri, $publicRoutes)) {
        // Rate limit mais restritivo para rotas públicas
        $allowed = $rateLimitMiddleware->check($requestUri, [
            'limit' => 10,  // 10 requisições
            'window' => 60  // por minuto
        ]);
        
        if (!$allowed) {
            $app->stop();
            exit;
        }
        return;
    }
    
    // Webhook tem limite maior (200/min) mas ainda é controlado
    if ($requestUri === '/v1/webhook' && $method === 'POST') {
        // O middleware já aplica limite de 200/min para webhooks
        $allowed = $rateLimitMiddleware->check($requestUri);
        
        if (!$allowed) {
            $app->stop();
            exit;
        }
        return;
    }
    
    // Endpoints de criação têm limite mais baixo
    $createEndpoints = [
        '/v1/customers',
        '/v1/subscriptions',
        '/v1/products',
        '/v1/prices',
        '/v1/coupons',
        '/v1/promotion-codes',
        '/v1/payment-intents',
        '/v1/refunds',
        '/v1/invoice-items',
        '/v1/tax-rates',
        '/v1/setup-intents',
        '/v1/payouts'
    ];
    
    if ($method === 'POST' && in_array($requestUri, $createEndpoints)) {
        // Limite diferenciado por endpoint (o middleware já aplica)
        $allowed = $rateLimitMiddleware->check($requestUri);
        
        if (!$allowed) {
            $app->stop();
            exit;
        }
        return;
    }
    
    // Endpoints de atualização também têm limites específicos
    $updateEndpoints = [
        '/v1/customers',
        '/v1/subscriptions',
        '/v1/products',
        '/v1/prices',
        '/v1/coupons',
        '/v1/promotion-codes',
        '/v1/invoice-items',
        '/v1/tax-rates',
        '/v1/charges',
        '/v1/disputes'
    ];
    
    if ($method === 'PUT' && in_array($requestUri, $updateEndpoints)) {
        $allowed = $rateLimitMiddleware->check($requestUri);
        
        if (!$allowed) {
            $app->stop();
            exit;
        }
        return;
    }
    
    // Endpoints de exclusão têm limite ainda mais restritivo
    $deleteEndpoints = [
        '/v1/subscriptions',
        '/v1/products',
        '/v1/coupons',
        '/v1/invoice-items',
        '/v1/subscription-items',
        '/v1/customers/payment-methods'
    ];
    
    if ($method === 'DELETE' && in_array($requestUri, $deleteEndpoints)) {
        $allowed = $rateLimitMiddleware->check($requestUri);
        
        if (!$allowed) {
            $app->stop();
            exit;
        }
        return;
    }
    
    // Rate limit padrão para outros endpoints
    $allowed = $rateLimitMiddleware->check($requestUri);
    
    if (!$allowed) {
        // Rate limit excedido - resposta já foi enviada pelo middleware
        $app->stop();
        exit;
    }
});

// Middleware de Auditoria - Captura início da requisição
$app->before('start', function() use ($auditMiddleware) {
    $auditMiddleware->captureRequest();
});

// Inicializa serviços (injeção de dependência)
$stripeService = new \App\Services\StripeService();
$paymentService = new \App\Services\PaymentService(
    $stripeService,
    new \App\Models\Customer(),
    new \App\Models\Subscription(),
    new \App\Models\StripeEvent()
);

// Inicializa controllers
$customerController = new \App\Controllers\CustomerController($paymentService, $stripeService);
$checkoutController = new \App\Controllers\CheckoutController($stripeService);
$subscriptionController = new \App\Controllers\SubscriptionController($paymentService, $stripeService);
$webhookController = new \App\Controllers\WebhookController($paymentService, $stripeService);
$billingPortalController = new \App\Controllers\BillingPortalController($stripeService);
$invoiceController = new \App\Controllers\InvoiceController($stripeService);
$priceController = new \App\Controllers\PriceController($stripeService);
$paymentController = new \App\Controllers\PaymentController($stripeService);
$statsController = new \App\Controllers\StatsController($stripeService);
$couponController = new \App\Controllers\CouponController($stripeService);
$productController = new \App\Controllers\ProductController($stripeService);
$promotionCodeController = new \App\Controllers\PromotionCodeController($stripeService);
$setupIntentController = new \App\Controllers\SetupIntentController($stripeService);
$subscriptionItemController = new \App\Controllers\SubscriptionItemController($stripeService);
$taxRateController = new \App\Controllers\TaxRateController($stripeService);
$invoiceItemController = new \App\Controllers\InvoiceItemController($stripeService);
$balanceTransactionController = new \App\Controllers\BalanceTransactionController($stripeService);
$disputeController = new \App\Controllers\DisputeController($stripeService);
$chargeController = new \App\Controllers\ChargeController($stripeService);
$payoutController = new \App\Controllers\PayoutController($stripeService);
$reportController = new \App\Controllers\ReportController($stripeService);
$auditLogController = new \App\Controllers\AuditLogController();
$healthCheckController = new \App\Controllers\HealthCheckController();
$swaggerController = new \App\Controllers\SwaggerController();

// Rota raiz - informações da API
$app->route('GET /', function() use ($app) {
    $app->json([
        'name' => 'SaaS Payments API',
        'version' => '1.0.0',
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => Config::env(),
        'endpoints' => [
            'health' => '/health',
            'health_detailed' => '/health/detailed',
            'customers' => '/v1/customers',
            'checkout' => '/v1/checkout',
            'subscriptions' => '/v1/subscriptions',
            'webhook' => '/v1/webhook',
            'billing-portal' => '/v1/billing-portal',
            'invoices' => '/v1/invoices/:id',
            'prices' => '/v1/prices',
            'payment-intents' => '/v1/payment-intents',
            'refunds' => '/v1/refunds',
            'stats' => '/v1/stats',
            'coupons' => '/v1/coupons',
            'promotion-codes' => '/v1/promotion-codes',
            'setup-intents' => '/v1/setup-intents',
            'subscription-items' => '/v1/subscription-items',
            'balance-transactions' => '/v1/balance-transactions',
            'disputes' => '/v1/disputes',
            'payouts' => '/v1/payouts',
            'audit-logs' => '/v1/audit-logs',
            'reports' => '/v1/reports',
            'auth' => '/v1/auth',
            'users' => '/v1/users',
            'permissions' => '/v1/permissions'
        ],
        'documentation' => 'Consulte o README.md para mais informações'
    ]);
});

// Rotas de documentação Swagger/OpenAPI (públicas)
$app->route('GET /api-docs', [$swaggerController, 'getSpec']);
$app->route('GET /api-docs/ui', [$swaggerController, 'getUI']);

// Rotas de Health Check
$app->route('GET /health', [$healthCheckController, 'basic']);
$app->route('GET /health/detailed', [$healthCheckController, 'detailed']);

// Rota de debug (apenas em desenvolvimento)
if (Config::isDevelopment()) {
    $app->route('GET /debug', function() use ($app) {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }
        
        $httpHeaders = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $httpHeaders[$key] = $value;
            }
        }
        
        $app->json([
            'getallheaders_exists' => function_exists('getallheaders'),
            'headers_from_getallheaders' => $headers,
            'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET',
            'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET',
            'all_http_headers' => $httpHeaders
        ]);
    });
}

// Rotas de clientes
$app->route('POST /v1/customers', [$customerController, 'create']);
$app->route('GET /v1/customers', [$customerController, 'list']);
$app->route('GET /v1/customers/@id', [$customerController, 'get']);
$app->route('PUT /v1/customers/@id', [$customerController, 'update']);
$app->route('GET /v1/customers/@id/invoices', [$customerController, 'listInvoices']);
$app->route('GET /v1/customers/@id/payment-methods', [$customerController, 'listPaymentMethods']);
$app->route('PUT /v1/customers/@id/payment-methods/@pm_id', [$customerController, 'updatePaymentMethod']);
$app->route('DELETE /v1/customers/@id/payment-methods/@pm_id', [$customerController, 'deletePaymentMethod']);
$app->route('POST /v1/customers/@id/payment-methods/@pm_id/set-default', [$customerController, 'setDefaultPaymentMethod']);

// Rotas de checkout
$app->route('POST /v1/checkout', [$checkoutController, 'create']);
$app->route('GET /v1/checkout/@id', [$checkoutController, 'get']);

// Rotas de assinaturas
$app->route('POST /v1/subscriptions', [$subscriptionController, 'create']);
$app->route('GET /v1/subscriptions', [$subscriptionController, 'list']);
$app->route('GET /v1/subscriptions/@id', [$subscriptionController, 'get']);
$app->route('PUT /v1/subscriptions/@id', [$subscriptionController, 'update']);
$app->route('DELETE /v1/subscriptions/@id', [$subscriptionController, 'cancel']);
$app->route('POST /v1/subscriptions/@id/reactivate', [$subscriptionController, 'reactivate']);
$app->route('GET /v1/subscriptions/@id/history', [$subscriptionController, 'history']);
$app->route('GET /v1/subscriptions/@id/history/stats', [$subscriptionController, 'historyStats']);

// Rota de webhook
$app->route('POST /v1/webhook', [$webhookController, 'handle']);

// Rota de portal de cobrança
$app->route('POST /v1/billing-portal', [$billingPortalController, 'create']);

// Rotas de faturas
$app->route('GET /v1/invoices/@id', [$invoiceController, 'get']);

// Rotas de preços
$app->route('GET /v1/prices', [$priceController, 'list']);
$app->route('POST /v1/prices', [$priceController, 'create']);
$app->route('GET /v1/prices/@id', [$priceController, 'get']);
$app->route('PUT /v1/prices/@id', [$priceController, 'update']);

// Rotas de produtos
$app->route('GET /v1/products', [$productController, 'list']);
$app->route('POST /v1/products', [$productController, 'create']);
$app->route('GET /v1/products/@id', [$productController, 'get']);
$app->route('PUT /v1/products/@id', [$productController, 'update']);
$app->route('DELETE /v1/products/@id', [$productController, 'delete']);

// Rotas de pagamentos
$app->route('POST /v1/payment-intents', [$paymentController, 'createPaymentIntent']);
$app->route('POST /v1/refunds', [$paymentController, 'createRefund']);

// Rotas de estatísticas
$app->route('GET /v1/stats', [$statsController, 'get']);

// Rotas de cupons
$app->route('POST /v1/coupons', [$couponController, 'create']);
$app->route('GET /v1/coupons', [$couponController, 'list']);
$app->route('GET /v1/coupons/@id', [$couponController, 'get']);
$app->route('PUT /v1/coupons/@id', [$couponController, 'update']);
$app->route('DELETE /v1/coupons/@id', [$couponController, 'delete']);

// Rotas de códigos promocionais
$app->route('POST /v1/promotion-codes', [$promotionCodeController, 'create']);
$app->route('GET /v1/promotion-codes', [$promotionCodeController, 'list']);
$app->route('GET /v1/promotion-codes/@id', [$promotionCodeController, 'get']);
$app->route('PUT /v1/promotion-codes/@id', [$promotionCodeController, 'update']);

// Rotas de Setup Intents
$app->route('POST /v1/setup-intents', [$setupIntentController, 'create']);
$app->route('GET /v1/setup-intents/@id', [$setupIntentController, 'get']);
$app->route('POST /v1/setup-intents/@id/confirm', [$setupIntentController, 'confirm']);

// Rotas de Subscription Items
$app->route('POST /v1/subscriptions/@subscription_id/items', [$subscriptionItemController, 'create']);
$app->route('GET /v1/subscriptions/@subscription_id/items', [$subscriptionItemController, 'list']);
$app->route('GET /v1/subscription-items/@id', [$subscriptionItemController, 'get']);
$app->route('PUT /v1/subscription-items/@id', [$subscriptionItemController, 'update']);
$app->route('DELETE /v1/subscription-items/@id', [$subscriptionItemController, 'delete']);

// Rotas de Tax Rates
$app->route('POST /v1/tax-rates', [$taxRateController, 'create']);
$app->route('GET /v1/tax-rates', [$taxRateController, 'list']);
$app->route('GET /v1/tax-rates/@id', [$taxRateController, 'get']);
$app->route('PUT /v1/tax-rates/@id', [$taxRateController, 'update']);

// Rotas de Invoice Items
$app->route('POST /v1/invoice-items', [$invoiceItemController, 'create']);
$app->route('GET /v1/invoice-items', [$invoiceItemController, 'list']);
$app->route('GET /v1/invoice-items/@id', [$invoiceItemController, 'get']);
$app->route('PUT /v1/invoice-items/@id', [$invoiceItemController, 'update']);
$app->route('DELETE /v1/invoice-items/@id', [$invoiceItemController, 'delete']);

// Rotas de Balance Transactions
$app->route('GET /v1/balance-transactions', [$balanceTransactionController, 'list']);
$app->route('GET /v1/balance-transactions/@id', [$balanceTransactionController, 'get']);

// Rotas de Disputes
$app->route('GET /v1/disputes', [$disputeController, 'list']);
$app->route('GET /v1/disputes/@id', [$disputeController, 'get']);
$app->route('PUT /v1/disputes/@id', [$disputeController, 'update']);

// Rotas de Charges
$app->route('GET /v1/charges', [$chargeController, 'list']);
$app->route('GET /v1/charges/@id', [$chargeController, 'get']);
$app->route('PUT /v1/charges/@id', [$chargeController, 'update']);

// Rotas de Payouts
$app->route('GET /v1/payouts', [$payoutController, 'list']);
$app->route('GET /v1/payouts/@id', [$payoutController, 'get']);
$app->route('POST /v1/payouts', [$payoutController, 'create']);
$app->route('POST /v1/payouts/@id/cancel', [$payoutController, 'cancel']);

// Rotas de Audit Logs
$app->route('GET /v1/audit-logs', [$auditLogController, 'list']);
$app->route('GET /v1/audit-logs/@id', [$auditLogController, 'get']);

// Rotas de Relatórios e Analytics
$app->route('GET /v1/reports/revenue', [$reportController, 'revenue']);
$app->route('GET /v1/reports/subscriptions', [$reportController, 'subscriptions']);
$app->route('GET /v1/reports/churn', [$reportController, 'churn']);
$app->route('GET /v1/reports/customers', [$reportController, 'customers']);
$app->route('GET /v1/reports/payments', [$reportController, 'payments']);
$app->route('GET /v1/reports/mrr', [$reportController, 'mrr']);
$app->route('GET /v1/reports/arr', [$reportController, 'arr']);

// Rota de página de login (HTML)
$app->route('GET /login', function() use ($app) {
    // Detecta URL base automaticamente
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove query string e fragmentos da URL
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $basePath = dirname($requestUri);
    
    // Constrói URL base
    if ($scriptName === '/' || $scriptName === '\\') {
        $baseUrl = $protocol . '://' . $host;
    } else {
        $baseUrl = $protocol . '://' . $host . rtrim($scriptName, '/');
    }
    
    $apiUrl = rtrim($baseUrl, '/');
    
    // Renderiza a view
    \App\Utils\View::render('login', ['apiUrl' => $apiUrl]);
});

// Helper para obter dados do usuário autenticado
function getAuthenticatedUserData() {
    $sessionId = null;
    $headers = [];
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
    }
    
    // Tenta obter session_id do header Authorization
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    
    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $sessionId = trim($matches[1]);
    }
    
    // Fallback: tenta obter de cookie (se houver)
    if (!$sessionId && isset($_COOKIE['session_id'])) {
        $sessionId = $_COOKIE['session_id'];
    }
    
    // Fallback: tenta obter de query string (para desenvolvimento)
    if (!$sessionId && isset($_GET['session_id'])) {
        $sessionId = $_GET['session_id'];
    }
    
    $user = null;
    $tenant = null;
    
    if ($sessionId) {
        $userSessionModel = new \App\Models\UserSession();
        $session = $userSessionModel->validate($sessionId);
        
        if ($session) {
            $user = [
                'id' => (int)$session['user_id'],
                'email' => $session['email'],
                'name' => $session['name'],
                'role' => $session['role'] ?? 'viewer'
            ];
            $tenant = [
                'id' => (int)$session['tenant_id'],
                'name' => $session['tenant_name']
            ];
        }
    }
    
    return [$user, $tenant, $sessionId];
}

// Helper para detectar URL base
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    
    if ($scriptName === '/' || $scriptName === '\\') {
        return $protocol . '://' . $host;
    }
    
    return $protocol . '://' . $host . rtrim($scriptName, '/');
}

// Rota de dashboard (requer autenticação)
$app->route('GET /dashboard', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    // Se não houver usuário autenticado, redireciona para login
    // O JavaScript também fará essa verificação, mas isso garante no servidor
    if (!$user) {
        header('Location: /login');
        exit;
    }
    
    \App\Utils\View::render('dashboard', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Dashboard',
        'currentPage' => 'dashboard'
    ], true);
});

// Rota de clientes
$app->route('GET /customers', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    \App\Utils\View::render('customers', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Clientes',
        'currentPage' => 'customers'
    ], true);
});

// Rota de assinaturas
$app->route('GET /subscriptions', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    \App\Utils\View::render('subscriptions', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Assinaturas',
        'currentPage' => 'subscriptions'
    ], true);
});

// Rota de produtos
$app->route('GET /products', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    \App\Utils\View::render('products', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Produtos',
        'currentPage' => 'products'
    ], true);
});

// Rota de preços
$app->route('GET /prices', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    \App\Utils\View::render('prices', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Preços',
        'currentPage' => 'prices'
    ], true);
});

// Rota de relatórios
$app->route('GET /reports', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    \App\Utils\View::render('reports', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Relatórios',
        'currentPage' => 'reports'
    ], true);
});

// Rota de usuários (apenas admin)
$app->route('GET /users', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $app->json(['error' => 'Acesso negado'], 403);
        return;
    }
    
    \App\Utils\View::render('users', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Usuários',
        'currentPage' => 'users'
    ], true);
});

// Rota de permissões (apenas admin)
$app->route('GET /permissions', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $app->json(['error' => 'Acesso negado'], 403);
        return;
    }
    
    \App\Utils\View::render('permissions', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Permissões',
        'currentPage' => 'permissions'
    ], true);
});

// Rota de logs de auditoria (apenas admin)
$app->route('GET /audit-logs', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $app->json(['error' => 'Acesso negado'], 403);
        return;
    }
    
    \App\Utils\View::render('audit-logs', [
        'apiUrl' => $apiUrl,
        'user' => $user,
        'tenant' => $tenant,
        'title' => 'Logs de Auditoria',
        'currentPage' => 'audit-logs'
    ], true);
});

// Rota de registro (pública)
$app->route('GET /register', function() use ($app) {
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('register', ['apiUrl' => $apiUrl]);
});

// Rota de index/planos (pública)
$app->route('GET /index', function() use ($app) {
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('index', ['apiUrl' => $apiUrl]);
});

// Rota de checkout (pública)
$app->route('GET /checkout', function() use ($app) {
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('checkout', ['apiUrl' => $apiUrl]);
});

// Rota de success (pública)
$app->route('GET /success', function() use ($app) {
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('success', ['apiUrl' => $apiUrl]);
});

// Rota de cancel (pública)
$app->route('GET /cancel', function() use ($app) {
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('cancel', ['apiUrl' => $apiUrl]);
});

// Rota de detalhes do cliente
$app->route('GET /customer-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('customer-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes do Cliente', 'currentPage' => 'customers'
    ], true);
});

// Rota de faturas do cliente
$app->route('GET /customer-invoices', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('customer-invoices', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Faturas do Cliente', 'currentPage' => 'customers'
    ], true);
});

// Rota de detalhes da assinatura
$app->route('GET /subscription-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('subscription-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes da Assinatura', 'currentPage' => 'subscriptions'
    ], true);
});

// Rota de detalhes do produto
$app->route('GET /product-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('product-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes do Produto', 'currentPage' => 'products'
    ], true);
});

// Rota de detalhes do preço
$app->route('GET /price-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('price-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes do Preço', 'currentPage' => 'prices'
    ], true);
});

// Rota de detalhes do usuário
$app->route('GET /user-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $app->json(['error' => 'Acesso negado'], 403);
        return;
    }
    
    \App\Utils\View::render('user-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes do Usuário', 'currentPage' => 'users'
    ], true);
});

// Rota de detalhes da fatura
$app->route('GET /invoice-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('invoice-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes da Fatura', 'currentPage' => 'invoices'
    ], true);
});

// Rota de faturas
$app->route('GET /invoices', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('invoices', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Faturas', 'currentPage' => 'invoices'
    ], true);
});

// Rota de reembolsos
$app->route('GET /refunds', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('refunds', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Reembolsos', 'currentPage' => 'refunds'
    ], true);
});

// Rota de cupons
$app->route('GET /coupons', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('coupons', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Cupons', 'currentPage' => 'coupons'
    ], true);
});

// Rota de códigos promocionais
$app->route('GET /promotion-codes', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('promotion-codes', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Códigos Promocionais', 'currentPage' => 'promotion-codes'
    ], true);
});

// Rota de configurações
$app->route('GET /settings', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('settings', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Configurações', 'currentPage' => 'settings'
    ], true);
});

// Rota de histórico de assinaturas
$app->route('GET /subscription-history', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('subscription-history', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Histórico de Assinaturas', 'currentPage' => 'subscriptions'
    ], true);
});

// Rota de transações
$app->route('GET /transactions', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('transactions', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Transações', 'currentPage' => 'transactions'
    ], true);
});

// Rota de detalhes da transação
$app->route('GET /transaction-details', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('transaction-details', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Detalhes da Transação', 'currentPage' => 'transactions'
    ], true);
});

// Rota de disputas
$app->route('GET /disputes', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('disputes', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Disputas', 'currentPage' => 'disputes'
    ], true);
});

// Rota de cobranças
$app->route('GET /charges', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('charges', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Cobranças', 'currentPage' => 'charges'
    ], true);
});

// Rota de saques
$app->route('GET /payouts', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('payouts', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Saques', 'currentPage' => 'payouts'
    ], true);
});

// Rota de itens de fatura
$app->route('GET /invoice-items', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('invoice-items', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Itens de Fatura', 'currentPage' => 'invoice-items'
    ], true);
});

// Rota de taxas de imposto
$app->route('GET /tax-rates', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('tax-rates', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Taxas de Imposto', 'currentPage' => 'tax-rates'
    ], true);
});

// Rota de métodos de pagamento
$app->route('GET /payment-methods', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('payment-methods', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Métodos de Pagamento', 'currentPage' => 'payment-methods'
    ], true);
});

// Rota de portal de cobrança
$app->route('GET /billing-portal', function() use ($app) {
    [$user, $tenant, $sessionId] = getAuthenticatedUserData();
    $apiUrl = getBaseUrl();
    \App\Utils\View::render('billing-portal', [
        'apiUrl' => $apiUrl, 'user' => $user, 'tenant' => $tenant,
        'title' => 'Portal de Cobrança', 'currentPage' => 'billing-portal'
    ], true);
});

// Rotas de Autenticação (públicas - não precisam de autenticação)
$authController = new \App\Controllers\AuthController();
$app->route('POST /v1/auth/login', [$authController, 'login']);
$app->route('POST /v1/auth/logout', [$authController, 'logout']);
$app->route('GET /v1/auth/me', [$authController, 'me']);

// Rotas de Usuários (apenas admin)
$userController = new \App\Controllers\UserController();
$app->route('GET /v1/users', [$userController, 'list']);
$app->route('GET /v1/users/@id', [$userController, 'get']);
$app->route('POST /v1/users', [$userController, 'create']);
$app->route('PUT /v1/users/@id', [$userController, 'update']);
$app->route('DELETE /v1/users/@id', [$userController, 'delete']);
$app->route('PUT /v1/users/@id/role', [$userController, 'updateRole']);

// Rotas de Permissões (apenas admin)
$permissionController = new \App\Controllers\PermissionController();
$app->route('GET /v1/permissions', [$permissionController, 'listAvailable']);
$app->route('GET /v1/users/@id/permissions', [$permissionController, 'listUserPermissions']);
$app->route('POST /v1/users/@id/permissions', [$permissionController, 'grant']);
$app->route('DELETE /v1/users/@id/permissions/@permission', [$permissionController, 'revoke']);

// Tratamento de erros
$app->map('notFound', function() use ($app, $auditMiddleware) {
    $auditMiddleware->logResponse(404);
    $app->json(['error' => 'Rota não encontrada'], 404);
});

$app->map('error', function(\Throwable $ex) use ($app, $auditMiddleware) {
    try {
        \App\Utils\ErrorHandler::logException($ex);
    } catch (\Exception $e) {
        // Ignora erros de log
    }
    
    $auditMiddleware->logResponse(500);
    
    $response = \App\Utils\ErrorHandler::prepareErrorResponse($ex, 'Erro interno do servidor', 'INTERNAL_SERVER_ERROR');
    $app->json($response, 500);
});

// ✅ OTIMIZAÇÃO: AuditMiddleware já usa register_shutdown_function internamente
// Não precisa de outro aqui (já foi otimizado para não bloquear resposta)

// Inicia aplicação
$app->start();


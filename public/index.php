<?php

/**
 * Arquivo principal da aplicação
 * Configura FlightPHP e rotas
 */

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
            header('Cache-Control: public, max-age=3600');
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

// Middleware de CORS (opcional, ajuste conforme necessário)
$app->before('start', function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
});

// Middleware de autenticação (suporta API Key e Session ID)
$app->before('start', function() use ($app) {
    // Rotas públicas (sem autenticação)
    $publicRoutes = ['/', '/v1/webhook', '/health', '/health/detailed', '/v1/auth/login', '/api-docs', '/api-docs/ui'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
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
        $app->json(['error' => 'Token de autenticação não fornecido', 'debug' => Config::isDevelopment() ? ['server_keys' => array_keys($_SERVER)] : null], 401);
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
    
    // Tenta primeiro como Session ID (usuário autenticado)
    $userSessionModel = new \App\Models\UserSession();
    $session = $userSessionModel->validate($token);
    
    if ($session) {
        // Autenticação via Session ID (usuário)
        Flight::set('user_id', (int)$session['user_id']);
        Flight::set('user_role', $session['role'] ?? 'viewer');
        Flight::set('user_email', $session['email']);
        Flight::set('user_name', $session['name']);
        Flight::set('tenant_id', (int)$session['tenant_id']);
        Flight::set('tenant_name', $session['tenant_name']);
        Flight::set('is_user_auth', true);
        Flight::set('is_master', false);
        return;
    }
    
    // Se não é Session ID, tenta como API Key (tenant)
    $tenantModel = new \App\Models\Tenant();
    $tenant = $tenantModel->findByApiKey($token);
    
    if (!$tenant) {
        // Verifica master key
        $masterKey = Config::get('API_MASTER_KEY');
        if ($masterKey && $token === $masterKey) {
            Flight::set('tenant_id', null);
            Flight::set('is_master', true);
            Flight::set('is_user_auth', false);
            return;
        }
        
        $app->json(['error' => 'Token inválido', 'debug' => Config::isDevelopment() ? ['token_received' => substr($token, 0, 20) . '...', 'length' => strlen($token)] : null], 401);
        $app->stop();
        exit;
    }
    
    if ($tenant['status'] !== 'active') {
        $app->json(['error' => 'Tenant inativo'], 401);
        $app->stop();
        exit;
    }

    // Autenticação via API Key (tenant)
    Flight::set('tenant_id', (int)$tenant['id']);
    Flight::set('tenant', $tenant);
    Flight::set('is_master', false);
    Flight::set('is_user_auth', false);
});

// Inicializa serviços (injeção de dependência)
$rateLimiterService = new \App\Services\RateLimiterService();
$rateLimitMiddleware = new \App\Middleware\RateLimitMiddleware($rateLimiterService);

// Inicializa middleware de auditoria
$auditLogModel = new \App\Models\AuditLog();
$auditMiddleware = new \App\Middleware\AuditMiddleware($auditLogModel);

// Middleware de Rate Limiting (após autenticação)
$app->before('start', function() use ($rateLimitMiddleware, $app) {
    // Rotas públicas não têm rate limiting
    $publicRoutes = ['/', '/v1/webhook', '/health', '/health/detailed'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    
    if (in_array($requestUri, $publicRoutes)) {
        return;
    }
    
    // Verifica rate limit
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
        \App\Services\Logger::error("Erro não tratado", [
            'message' => $ex->getMessage(),
            'trace' => $ex->getTraceAsString()
        ]);
    } catch (\Exception $e) {
        // Ignora erros de log
    }
    
    $auditMiddleware->logResponse(500);
    
    $app->json([
        'error' => 'Erro interno do servidor',
        'message' => Config::isDevelopment() ? $ex->getMessage() : null
    ], 500);
});

// Middleware de Auditoria - Registra resposta após processamento
// Usa register_shutdown_function para garantir que sempre execute
register_shutdown_function(function() use ($auditMiddleware) {
    // Obtém status HTTP da resposta
    $statusCode = http_response_code() ?: 200;
    $auditMiddleware->logResponse($statusCode);
});

// Inicia aplicação
$app->start();


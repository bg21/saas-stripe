<?php

/**
 * Cliente PHP para integração com a API de Pagamentos SaaS
 * 
 * Use esta classe no seu SaaS para facilitar a integração
 * 
 * Exemplo:
 *   $client = new PaymentsClient('https://pagamentos.seudominio.com', 'sua_api_key');
 *   $customer = $client->createCustomer('email@example.com', 'Nome');
 */

namespace PaymentsSDK;

class PaymentsClient
{
    private string $baseUrl;
    private string $apiKey;
    private array $defaultHeaders;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->defaultHeaders = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
    }

    /**
     * Faz requisição HTTP
     */
    private function request(string $method, string $endpoint, array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->defaultHeaders
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro de conexão: $error");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $result['error'] ?? 'Erro desconhecido';
            throw new \Exception("Erro HTTP $httpCode: $errorMsg");
        }

        return $result;
    }

    // ============================================
    // CUSTOMERS
    // ============================================

    /**
     * Cria um novo cliente
     */
    public function createCustomer(string $email, ?string $name = null, array $metadata = []): array
    {
        return $this->request('POST', '/v1/customers', [
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata
        ]);
    }

    /**
     * Lista clientes
     */
    public function listCustomers(): array
    {
        return $this->request('GET', '/v1/customers');
    }

    /**
     * Obtém cliente por ID
     */
    public function getCustomer(int $customerId): array
    {
        return $this->request('GET', "/v1/customers/$customerId");
    }

    /**
     * Atualiza cliente
     */
    public function updateCustomer(int $customerId, array $data): array
    {
        return $this->request('PUT', "/v1/customers/$customerId", $data);
    }

    // ============================================
    // CHECKOUT
    // ============================================

    /**
     * Cria sessão de checkout
     */
    public function createCheckout(
        int $customerId,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): array {
        return $this->request('POST', '/v1/checkout', [
            'customer_id' => $customerId,
            'price_id' => $priceId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata
        ]);
    }

    /**
     * Obtém sessão de checkout
     */
    public function getCheckout(string $checkoutId): array
    {
        return $this->request('GET', "/v1/checkout/$checkoutId");
    }

    // ============================================
    // SUBSCRIPTIONS
    // ============================================

    /**
     * Cria assinatura
     */
    public function createSubscription(
        int $customerId,
        string $priceId,
        ?int $trialPeriodDays = null,
        array $metadata = []
    ): array {
        $data = [
            'customer_id' => $customerId,
            'price_id' => $priceId,
            'metadata' => $metadata
        ];

        if ($trialPeriodDays !== null) {
            $data['trial_period_days'] = $trialPeriodDays;
        }

        return $this->request('POST', '/v1/subscriptions', $data);
    }

    /**
     * Lista assinaturas
     */
    public function listSubscriptions(): array
    {
        return $this->request('GET', '/v1/subscriptions');
    }

    /**
     * Obtém assinatura por ID
     */
    public function getSubscription(int $subscriptionId): array
    {
        return $this->request('GET', "/v1/subscriptions/$subscriptionId");
    }

    /**
     * Atualiza assinatura
     */
    public function updateSubscription(int $subscriptionId, array $data): array
    {
        return $this->request('PUT', "/v1/subscriptions/$subscriptionId", $data);
    }

    /**
     * Cancela assinatura
     */
    public function cancelSubscription(int $subscriptionId, bool $immediately = false): array
    {
        $endpoint = "/v1/subscriptions/$subscriptionId";
        if ($immediately) {
            $endpoint .= '?immediately=true';
        }
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Reativa assinatura
     */
    public function reactivateSubscription(int $subscriptionId): array
    {
        return $this->request('POST', "/v1/subscriptions/$subscriptionId/reactivate");
    }

    /**
     * Obtém histórico de mudanças da assinatura
     */
    public function getSubscriptionHistory(int $subscriptionId, int $limit = 100, int $offset = 0): array
    {
        $endpoint = "/v1/subscriptions/$subscriptionId/history?limit=$limit&offset=$offset";
        return $this->request('GET', $endpoint);
    }

    // ============================================
    // STATS
    // ============================================

    /**
     * Obtém estatísticas
     */
    public function getStats(?string $period = null): array
    {
        $endpoint = '/v1/stats';
        if ($period) {
            $endpoint .= "?period=$period";
        }
        return $this->request('GET', $endpoint);
    }

    // ============================================
    // AUDIT LOGS
    // ============================================

    /**
     * Lista logs de auditoria
     */
    public function listAuditLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $params = http_build_query(array_merge($filters, [
            'limit' => $limit,
            'offset' => $offset
        ]));
        return $this->request('GET', "/v1/audit-logs?$params");
    }

    /**
     * Obtém log de auditoria por ID
     */
    public function getAuditLog(int $logId): array
    {
        return $this->request('GET', "/v1/audit-logs/$logId");
    }
}


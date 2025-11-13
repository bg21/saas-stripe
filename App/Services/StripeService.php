<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Config;
use App\Services\Logger;

/**
 * Serviço wrapper para Stripe API
 */
class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $secretKey = Config::get('STRIPE_SECRET');
        if (empty($secretKey)) {
            throw new \RuntimeException("STRIPE_SECRET não configurado");
        }

        $this->client = new StripeClient($secretKey);
    }

    /**
     * Cria um cliente no Stripe
     */
    public function createCustomer(array $data): \Stripe\Customer
    {
        try {
            $customer = $this->client->customers->create([
                'email' => $data['email'] ?? null,
                'name' => $data['name'] ?? null,
                'metadata' => $data['metadata'] ?? []
            ]);

            Logger::info("Cliente Stripe criado", ['customer_id' => $customer->id]);
            return $customer;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao criar cliente Stripe", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cria sessão de checkout
     * 
     * @param array $data Dados da sessão:
     *   - customer_id (opcional): ID do customer no Stripe
     *   - customer_email (opcional): Email do customer (se não tiver customer_id)
     *   - payment_method_types (opcional): Tipos de pagamento (padrão: ['card'])
     *   - line_items (obrigatório): Itens da sessão
     *   - mode (obrigatório): 'subscription' ou 'payment'
     *   - success_url (obrigatório): URL de sucesso
     *   - cancel_url (obrigatório): URL de cancelamento
     *   - metadata (opcional): Metadados
     *   - payment_method_collection (opcional): 'always' para sempre coletar método de pagamento
     */
    public function createCheckoutSession(array $data): \Stripe\Checkout\Session
    {
        try {
            $sessionParams = [
                'payment_method_types' => $data['payment_method_types'] ?? ['card'],
                'line_items' => $data['line_items'] ?? [],
                'mode' => $data['mode'] ?? 'subscription',
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'metadata' => $data['metadata'] ?? []
            ];

            // Se tiver customer_id, usa customer. Caso contrário, usa customer_email
            // Não pode passar ambos ao mesmo tempo
            if (!empty($data['customer_id'])) {
                $sessionParams['customer'] = $data['customer_id'];
                // Se tiver customer_id, sempre coletar método de pagamento para salvar
                $sessionParams['payment_method_collection'] = 'always';
            } elseif (!empty($data['customer_email'])) {
                $sessionParams['customer_email'] = $data['customer_email'];
            }

            // Permite sobrescrever payment_method_collection se fornecido
            if (isset($data['payment_method_collection'])) {
                $sessionParams['payment_method_collection'] = $data['payment_method_collection'];
            }

            $session = $this->client->checkout->sessions->create($sessionParams);

            Logger::info("Sessão de checkout criada", ['session_id' => $session->id]);
            return $session;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao criar sessão de checkout", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cria assinatura
     * 
     * @param array $data Dados da assinatura:
     *   - customer_id (obrigatório): ID do customer no Stripe
     *   - price_id (obrigatório): ID do preço no Stripe
     *   - metadata (opcional): Metadados da assinatura
     *   - trial_period_days (opcional): Dias de trial period
     *   - payment_behavior (opcional): Comportamento de pagamento (default_incomplete, etc)
     *   - default_payment_method (opcional): ID do método de pagamento padrão
     */
    public function createSubscription(array $data): \Stripe\Subscription
    {
        try {
            $subscriptionParams = [
                'customer' => $data['customer_id'],
                'items' => [['price' => $data['price_id']]],
                'metadata' => $data['metadata'] ?? []
            ];

            // Adiciona trial_period_days se fornecido
            if (isset($data['trial_period_days'])) {
                $subscriptionParams['trial_period_days'] = (int)$data['trial_period_days'];
            }

            // Adiciona payment_behavior se fornecido
            if (isset($data['payment_behavior'])) {
                $subscriptionParams['payment_behavior'] = $data['payment_behavior'];
            }

            // Adiciona default_payment_method se fornecido
            if (isset($data['default_payment_method'])) {
                $subscriptionParams['default_payment_method'] = $data['default_payment_method'];
            }

            $subscription = $this->client->subscriptions->create($subscriptionParams);

            Logger::info("Assinatura criada", ['subscription_id' => $subscription->id]);
            return $subscription;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao criar assinatura", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cancela assinatura
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): \Stripe\Subscription
    {
        try {
            if ($immediately) {
                $subscription = $this->client->subscriptions->cancel($subscriptionId);
            } else {
                $subscription = $this->client->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true
                ]);
            }

            Logger::info("Assinatura cancelada", ['subscription_id' => $subscriptionId]);
            return $subscription;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao cancelar assinatura", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cria sessão de portal de cobrança
     * 
     * @param string $customerId ID do customer no Stripe
     * @param string $returnUrl URL para redirecionar após o cliente sair do portal
     * @param array $options Opções adicionais:
     *   - 'configuration' (string): ID da configuração do portal (opcional)
     *   - 'locale' (string): Idioma do portal (ex: 'pt-BR', 'en', 'es') (opcional)
     *   - 'on_behalf_of' (string): ID da conta conectada (opcional)
     * @return \Stripe\BillingPortal\Session
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl, array $options = []): \Stripe\BillingPortal\Session
    {
        try {
            $params = [
                'customer' => $customerId,
                'return_url' => $returnUrl
            ];

            // Adiciona configuração específica se fornecida
            if (!empty($options['configuration'])) {
                $params['configuration'] = $options['configuration'];
            }

            // Adiciona locale se fornecido
            if (!empty($options['locale'])) {
                $params['locale'] = $options['locale'];
            }

            // Adiciona on_behalf_of se fornecido (para contas conectadas)
            if (!empty($options['on_behalf_of'])) {
                $params['on_behalf_of'] = $options['on_behalf_of'];
            }

            $session = $this->client->billingPortal->sessions->create($params);

            Logger::info("Sessão de portal criada", [
                'session_id' => $session->id,
                'customer' => $customerId,
                'configuration' => $options['configuration'] ?? 'default',
                'locale' => $options['locale'] ?? 'auto'
            ]);
            
            return $session;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao criar sessão de portal", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtém Payment Intent por ID
     */
    public function getPaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        try {
            return $this->client->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao obter payment intent", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtém fatura por ID
     */
    public function getInvoice(string $invoiceId): \Stripe\Invoice
    {
        try {
            return $this->client->invoices->retrieve($invoiceId);
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao obter fatura", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtém customer por ID
     */
    public function getCustomer(string $customerId): \Stripe\Customer
    {
        try {
            return $this->client->customers->retrieve($customerId);
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao obter customer", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtém assinatura por ID
     */
    public function getSubscription(string $subscriptionId): \Stripe\Subscription
    {
        try {
            return $this->client->subscriptions->retrieve($subscriptionId);
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao obter assinatura", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Atualiza assinatura
     * 
     * @param string $subscriptionId ID da assinatura no Stripe
     * @param array $data Dados para atualização:
     *   - price_id (opcional): Novo preço para upgrade/downgrade
     *   - quantity (opcional): Nova quantidade
     *   - metadata (opcional): Metadados atualizados
     *   - proration_behavior (opcional): Comportamento de proratação (create_prorations, none, always_invoice)
     *   - cancel_at_period_end (opcional): Se true, cancela no final do período
     *   - trial_end (opcional): Data de fim do trial (timestamp ou 'now')
     * @return \Stripe\Subscription
     */
    public function updateSubscription(string $subscriptionId, array $data): \Stripe\Subscription
    {
        try {
            $updateParams = [];

            // Atualiza preço (upgrade/downgrade)
            if (!empty($data['price_id'])) {
                // Primeiro, obtém a assinatura atual para pegar o subscription_item
                $currentSubscription = $this->client->subscriptions->retrieve($subscriptionId);
                
                if (!empty($currentSubscription->items->data)) {
                    $subscriptionItemId = $currentSubscription->items->data[0]->id;
                    $updateParams['items'] = [
                        [
                            'id' => $subscriptionItemId,
                            'price' => $data['price_id']
                        ]
                    ];
                } else {
                    // Se não tem items, adiciona novo
                    $updateParams['items'] = [
                        ['price' => $data['price_id']]
                    ];
                }
            }

            // Atualiza quantidade
            if (isset($data['quantity'])) {
                if (empty($updateParams['items'])) {
                    // Se não tem items definidos, precisa pegar o item atual
                    $currentSubscription = $this->client->subscriptions->retrieve($subscriptionId);
                    if (!empty($currentSubscription->items->data)) {
                        $subscriptionItemId = $currentSubscription->items->data[0]->id;
                        $updateParams['items'] = [
                            [
                                'id' => $subscriptionItemId,
                                'quantity' => (int)$data['quantity']
                            ]
                        ];
                    }
                } else {
                    // Se já tem items, adiciona quantity ao primeiro item
                    $updateParams['items'][0]['quantity'] = (int)$data['quantity'];
                }
            }

            // Atualiza metadata
            if (isset($data['metadata'])) {
                $updateParams['metadata'] = $data['metadata'];
            }

            // Comportamento de proratação
            if (!empty($data['proration_behavior'])) {
                $updateParams['proration_behavior'] = $data['proration_behavior'];
            }

            // Cancelar no final do período
            if (isset($data['cancel_at_period_end'])) {
                $updateParams['cancel_at_period_end'] = (bool)$data['cancel_at_period_end'];
            }

            // Trial end
            if (isset($data['trial_end'])) {
                $updateParams['trial_end'] = $data['trial_end'];
            }

            $subscription = $this->client->subscriptions->update($subscriptionId, $updateParams);

            Logger::info("Assinatura atualizada", [
                'subscription_id' => $subscriptionId,
                'updated_fields' => array_keys($updateParams)
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao atualizar assinatura", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtém sessão de checkout por ID
     */
    public function getCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        try {
            return $this->client->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent', 'subscription']
            ]);
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao obter sessão de checkout", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Anexa método de pagamento ao customer e define como padrão
     * Se o payment method já estiver anexado, apenas define como padrão
     */
    public function attachPaymentMethodToCustomer(string $paymentMethodId, string $customerId): void
    {
        try {
            // Verifica se o payment method já está anexado ao customer
            $paymentMethod = $this->client->paymentMethods->retrieve($paymentMethodId);
            $isAttached = $paymentMethod->customer === $customerId;

            // Se não estiver anexado, anexa
            if (!$isAttached) {
                $this->client->paymentMethods->attach($paymentMethodId, [
                    'customer' => $customerId,
                ]);
                Logger::info("Payment method anexado ao customer", [
                    'payment_method_id' => $paymentMethodId,
                    'customer_id' => $customerId
                ]);
            }

            // Define como método de pagamento padrão (sempre, mesmo se já estiver anexado)
            $this->client->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            Logger::info("Payment method definido como padrão", [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
                'was_already_attached' => $isAttached
            ]);
        } catch (ApiErrorException $e) {
            // Se o erro for que o payment method já está anexado a outro customer, tenta apenas definir como padrão
            if (strpos($e->getMessage(), 'already been attached') !== false) {
                try {
                    $this->client->customers->update($customerId, [
                        'invoice_settings' => [
                            'default_payment_method' => $paymentMethodId,
                        ],
                    ]);
                    Logger::info("Payment method já estava anexado, apenas definido como padrão", [
                        'payment_method_id' => $paymentMethodId,
                        'customer_id' => $customerId
                    ]);
                } catch (ApiErrorException $e2) {
                    Logger::error("Erro ao definir payment method como padrão", ['error' => $e2->getMessage()]);
                    throw $e2;
                }
            } else {
                Logger::error("Erro ao anexar payment method", ['error' => $e->getMessage()]);
                throw $e;
            }
        }
    }

    /**
     * Valida webhook signature
     */
    public function validateWebhook(string $payload, string $signature): \Stripe\Event
    {
        $webhookSecret = Config::get('STRIPE_WEBHOOK_SECRET');
        if (empty($webhookSecret)) {
            throw new \RuntimeException("STRIPE_WEBHOOK_SECRET não configurado");
        }

        try {
            return \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Logger::error("Webhook signature inválida", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}


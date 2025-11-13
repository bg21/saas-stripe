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
     * Reativa assinatura cancelada
     * 
     * Remove a flag cancel_at_period_end para reativar uma assinatura que estava
     * marcada para cancelar no final do período.
     * 
     * Nota: Assinaturas já canceladas (status = 'canceled') não podem ser reativadas.
     * Nesses casos, é necessário criar uma nova assinatura.
     * 
     * @param string $subscriptionId ID da assinatura no Stripe
     * @return \Stripe\Subscription Assinatura reativada
     * @throws ApiErrorException Se a assinatura não puder ser reativada
     */
    public function reactivateSubscription(string $subscriptionId): \Stripe\Subscription
    {
        try {
            // Primeiro, obtém a assinatura atual para verificar o status
            $currentSubscription = $this->client->subscriptions->retrieve($subscriptionId);
            
            // Se já está cancelada, não pode reativar
            if ($currentSubscription->status === 'canceled') {
                throw new \RuntimeException("Assinatura já está cancelada e não pode ser reativada. Crie uma nova assinatura.");
            }
            
            // Se não está marcada para cancelar, não precisa reativar
            if (!$currentSubscription->cancel_at_period_end) {
                Logger::info("Assinatura não estava marcada para cancelar", ['subscription_id' => $subscriptionId]);
                return $currentSubscription;
            }
            
            // Remove a flag cancel_at_period_end para reativar
            $subscription = $this->client->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => false
            ]);

            Logger::info("Assinatura reativada", [
                'subscription_id' => $subscriptionId,
                'status' => $subscription->status
            ]);
            
            return $subscription;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao reativar assinatura", [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
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
     * Cria Payment Intent para pagamento único
     * 
     * @param array $data Dados do payment intent:
     *   - amount (obrigatório): Valor em centavos (ex: 2999 para R$ 29,99)
     *   - currency (obrigatório): Moeda (ex: 'brl', 'usd')
     *   - customer_id (opcional): ID do customer no Stripe
     *   - payment_method (opcional): ID do método de pagamento
     *   - payment_method_types (opcional): Tipos de pagamento (padrão: ['card'])
     *   - description (opcional): Descrição do pagamento
     *   - metadata (opcional): Metadados
     *   - confirm (opcional): Se true, confirma o pagamento imediatamente (padrão: false)
     *   - capture_method (opcional): 'automatic' ou 'manual' (padrão: 'automatic')
     * @return \Stripe\PaymentIntent
     */
    public function createPaymentIntent(array $data): \Stripe\PaymentIntent
    {
        try {
            $params = [
                'amount' => (int)$data['amount'],
                'currency' => strtolower($data['currency']),
                'payment_method_types' => $data['payment_method_types'] ?? ['card']
            ];

            if (!empty($data['customer_id'])) {
                $params['customer'] = $data['customer_id'];
            }

            if (!empty($data['payment_method'])) {
                $params['payment_method'] = $data['payment_method'];
            }

            if (!empty($data['description'])) {
                $params['description'] = $data['description'];
            }

            if (isset($data['metadata'])) {
                $params['metadata'] = $data['metadata'];
            }

            if (isset($data['confirm'])) {
                $params['confirm'] = (bool)$data['confirm'];
            }

            if (!empty($data['capture_method'])) {
                $params['capture_method'] = $data['capture_method'];
            }

            $paymentIntent = $this->client->paymentIntents->create($params);

            Logger::info("Payment Intent criado", [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status
            ]);

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao criar payment intent", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reembolsa um pagamento
     * 
     * @param string $paymentIntentId ID do Payment Intent a ser reembolsado
     * @param array $options Opções de reembolso:
     *   - amount (opcional): Valor em centavos para reembolso parcial (se não fornecido, reembolsa total)
     *   - reason (opcional): Motivo do reembolso ('duplicate', 'fraudulent', 'requested_by_customer')
     *   - metadata (opcional): Metadados do reembolso
     * @return \Stripe\Refund
     */
    public function refundPayment(string $paymentIntentId, array $options = []): \Stripe\Refund
    {
        try {
            // Primeiro, obtém o Payment Intent para verificar o status
            $paymentIntent = $this->client->paymentIntents->retrieve($paymentIntentId);
            
            // Verifica se o pagamento foi bem-sucedido
            if ($paymentIntent->status !== 'succeeded') {
                throw new \RuntimeException("Pagamento não pode ser reembolsado. Status atual: {$paymentIntent->status}");
            }

            // Prepara parâmetros do reembolso
            $refundParams = [
                'payment_intent' => $paymentIntentId
            ];

            // Reembolso parcial
            if (isset($options['amount'])) {
                $refundParams['amount'] = (int)$options['amount'];
            }

            // Motivo do reembolso
            if (!empty($options['reason'])) {
                $refundParams['reason'] = $options['reason'];
            }

            // Metadados
            if (isset($options['metadata'])) {
                $refundParams['metadata'] = $options['metadata'];
            }

            $refund = $this->client->refunds->create($refundParams);

            Logger::info("Pagamento reembolsado", [
                'refund_id' => $refund->id,
                'payment_intent_id' => $paymentIntentId,
                'amount' => $refund->amount,
                'status' => $refund->status
            ]);

            return $refund;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao reembolsar pagamento", [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
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
     * Lista faturas de um customer
     * 
     * @param string $customerId ID do customer no Stripe
     * @param array $options Opções de filtro:
     *   - limit (int): Número máximo de resultados (padrão: 10)
     *   - starting_after (string): ID da fatura para paginação
     *   - ending_before (string): ID da fatura para paginação reversa
     *   - status (string): Filtrar por status (draft, open, paid, uncollectible, void)
     * @return \Stripe\Collection Lista de faturas
     */
    public function listInvoices(string $customerId, array $options = []): \Stripe\Collection
    {
        try {
            $params = [
                'customer' => $customerId,
                'limit' => $options['limit'] ?? 10
            ];

            if (!empty($options['starting_after'])) {
                $params['starting_after'] = $options['starting_after'];
            }

            if (!empty($options['ending_before'])) {
                $params['ending_before'] = $options['ending_before'];
            }

            if (!empty($options['status'])) {
                $params['status'] = $options['status'];
            }

            $invoices = $this->client->invoices->all($params);

            Logger::info("Faturas listadas", [
                'customer_id' => $customerId,
                'count' => count($invoices->data)
            ]);

            return $invoices;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao listar faturas", [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lista métodos de pagamento de um customer
     * 
     * @param string $customerId ID do customer no Stripe
     * @param array $options Opções de filtro:
     *   - limit (int): Número máximo de resultados (padrão: 10)
     *   - starting_after (string): ID do payment method para paginação
     *   - ending_before (string): ID do payment method para paginação reversa
     *   - type (string): Filtrar por tipo (card, us_bank_account, etc)
     * @return \Stripe\Collection Lista de métodos de pagamento
     */
    public function listPaymentMethods(string $customerId, array $options = []): \Stripe\Collection
    {
        try {
            $params = [
                'customer' => $customerId,
                'limit' => $options['limit'] ?? 10
            ];

            if (!empty($options['starting_after'])) {
                $params['starting_after'] = $options['starting_after'];
            }

            if (!empty($options['ending_before'])) {
                $params['ending_before'] = $options['ending_before'];
            }

            if (!empty($options['type'])) {
                $params['type'] = $options['type'];
            }

            $paymentMethods = $this->client->paymentMethods->all($params);

            Logger::info("Métodos de pagamento listados", [
                'customer_id' => $customerId,
                'count' => count($paymentMethods->data)
            ]);

            return $paymentMethods;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao listar métodos de pagamento", [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
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
     * Lista customers do Stripe
     * 
     * @param array $options Opções de filtro:
     *   - limit (int): Número máximo de resultados (padrão: 10)
     *   - starting_after (string): ID do customer para paginação
     *   - ending_before (string): ID do customer para paginação reversa
     *   - email (string): Filtrar por email
     *   - created (array): Filtrar por data de criação (gte, lte, gt, lt)
     * @return \Stripe\Collection Lista de customers
     */
    public function listCustomers(array $options = []): \Stripe\Collection
    {
        try {
            $params = [
                'limit' => $options['limit'] ?? 10
            ];

            if (!empty($options['starting_after'])) {
                $params['starting_after'] = $options['starting_after'];
            }

            if (!empty($options['ending_before'])) {
                $params['ending_before'] = $options['ending_before'];
            }

            if (!empty($options['email'])) {
                $params['email'] = $options['email'];
            }

            if (isset($options['created']) && is_array($options['created'])) {
                $params['created'] = $options['created'];
            }

            $customers = $this->client->customers->all($params);

            Logger::info("Customers listados", [
                'count' => count($customers->data),
                'filters' => array_keys($options)
            ]);

            return $customers;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao listar customers", [
                'error' => $e->getMessage(),
                'filters' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza customer no Stripe
     * 
     * @param string $customerId ID do customer no Stripe
     * @param array $data Dados para atualização:
     *   - email (opcional): Novo email
     *   - name (opcional): Novo nome
     *   - metadata (opcional): Metadados atualizados
     *   - address (opcional): Endereço completo
     *   - phone (opcional): Telefone
     *   - description (opcional): Descrição
     * @return \Stripe\Customer
     */
    public function updateCustomer(string $customerId, array $data): \Stripe\Customer
    {
        try {
            $updateParams = [];

            // Campos permitidos para atualização
            $allowedFields = ['email', 'name', 'metadata', 'address', 'phone', 'description'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateParams[$field] = $data[$field];
                }
            }

            // Se não há nada para atualizar, retorna customer atual
            if (empty($updateParams)) {
                return $this->getCustomer($customerId);
            }

            $customer = $this->client->customers->update($customerId, $updateParams);

            Logger::info("Customer atualizado no Stripe", [
                'customer_id' => $customerId,
                'updated_fields' => array_keys($updateParams)
            ]);

            return $customer;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao atualizar customer", ['error' => $e->getMessage()]);
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
     * Lista preços (prices) do Stripe
     * 
     * @param array $options Opções de filtro:
     *   - limit (int): Número máximo de resultados (padrão: 10)
     *   - starting_after (string): ID do preço para paginação
     *   - ending_before (string): ID do preço para paginação reversa
     *   - active (bool): Filtrar apenas preços ativos (true) ou inativos (false)
     *   - type (string): Filtrar por tipo (one_time, recurring)
     *   - product (string): Filtrar por ID do produto
     *   - currency (string): Filtrar por moeda (ex: 'brl', 'usd')
     * @return \Stripe\Collection Lista de preços
     */
    public function listPrices(array $options = []): \Stripe\Collection
    {
        try {
            $params = [
                'limit' => $options['limit'] ?? 10
            ];

            if (!empty($options['starting_after'])) {
                $params['starting_after'] = $options['starting_after'];
            }

            if (!empty($options['ending_before'])) {
                $params['ending_before'] = $options['ending_before'];
            }

            if (isset($options['active'])) {
                $params['active'] = (bool)$options['active'];
            }

            if (!empty($options['type'])) {
                $params['type'] = $options['type'];
            }

            if (!empty($options['product'])) {
                $params['product'] = $options['product'];
            }

            if (!empty($options['currency'])) {
                $params['currency'] = strtolower($options['currency']);
            }

            $prices = $this->client->prices->all($params);

            Logger::info("Preços listados", [
                'count' => count($prices->data),
                'filters' => array_keys($options)
            ]);

            return $prices;
        } catch (ApiErrorException $e) {
            Logger::error("Erro ao listar preços", [
                'error' => $e->getMessage(),
                'filters' => $options
            ]);
            throw $e;
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


<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\StripeEvent;
use App\Services\StripeService;
use App\Services\Logger;

/**
 * Serviço central de pagamentos
 * Coordena lógica de negócio entre Stripe e banco de dados
 */
class PaymentService
{
    private StripeService $stripeService;
    private Customer $customerModel;
    private Subscription $subscriptionModel;
    private StripeEvent $eventModel;

    public function __construct(
        StripeService $stripeService,
        Customer $customerModel,
        Subscription $subscriptionModel,
        StripeEvent $eventModel
    ) {
        $this->stripeService = $stripeService;
        $this->customerModel = $customerModel;
        $this->subscriptionModel = $subscriptionModel;
        $this->eventModel = $eventModel;
    }

    /**
     * Cria cliente e persiste no banco
     */
    public function createCustomer(int $tenantId, array $data): array
    {
        // Cria no Stripe
        $stripeCustomer = $this->stripeService->createCustomer($data);

        // Persiste no banco
        $customerId = $this->customerModel->createOrUpdate(
            $tenantId,
            $stripeCustomer->id,
            [
                'email' => $stripeCustomer->email,
                'name' => $stripeCustomer->name,
                'metadata' => $stripeCustomer->metadata->toArray()
            ]
        );

        Logger::info("Cliente criado", [
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'stripe_customer_id' => $stripeCustomer->id
        ]);

        return [
            'id' => $customerId,
            'stripe_customer_id' => $stripeCustomer->id,
            'email' => $stripeCustomer->email,
            'name' => $stripeCustomer->name
        ];
    }

    /**
     * Cria assinatura e persiste no banco
     * 
     * @param int $tenantId ID do tenant
     * @param int $customerId ID do customer no banco
     * @param string $priceId ID do preço no Stripe
     * @param array $metadata Metadados adicionais
     * @param int|null $trialPeriodDays Dias de trial period (opcional)
     * @param string|null $paymentBehavior Comportamento de pagamento (opcional)
     */
    public function createSubscription(
        int $tenantId, 
        int $customerId, 
        string $priceId, 
        array $metadata = [],
        ?int $trialPeriodDays = null,
        ?string $paymentBehavior = null
    ): array {
        $customer = $this->customerModel->findById($customerId);
        if (!$customer || $customer['tenant_id'] != $tenantId) {
            throw new \RuntimeException("Cliente não encontrado");
        }

        // Prepara dados para criar no Stripe
        $subscriptionData = [
            'customer_id' => $customer['stripe_customer_id'],
            'price_id' => $priceId,
            'metadata' => array_merge($metadata, ['tenant_id' => $tenantId])
        ];

        // Adiciona trial_period_days se fornecido
        if ($trialPeriodDays !== null) {
            $subscriptionData['trial_period_days'] = $trialPeriodDays;
        }

        // Adiciona payment_behavior se fornecido
        if ($paymentBehavior !== null) {
            $subscriptionData['payment_behavior'] = $paymentBehavior;
        }

        // Cria no Stripe
        $stripeSubscription = $this->stripeService->createSubscription($subscriptionData);

        // Persiste no banco
        $subscriptionId = $this->subscriptionModel->createOrUpdate(
            $tenantId,
            $customerId,
            $stripeSubscription->toArray()
        );

        Logger::info("Assinatura criada", [
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'stripe_subscription_id' => $stripeSubscription->id
        ]);

        return [
            'id' => $subscriptionId,
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => $stripeSubscription->status,
            'plan_id' => $stripeSubscription->items->data[0]->price->id ?? null
        ];
    }

    /**
     * Processa webhook do Stripe
     */
    public function processWebhook(\Stripe\Event $event): void
    {
        $eventId = $event->id;
        $eventType = $event->type;

        // Verifica idempotência
        if ($this->eventModel->isProcessed($eventId)) {
            Logger::info("Evento já processado", ['event_id' => $eventId]);
            return;
        }

        // Registra evento
        $this->eventModel->register($eventId, $eventType, $event->toArray());

        try {
            // Processa evento
            switch ($eventType) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event);
                    break;

                case 'invoice.paid':
                    $this->handleInvoicePaid($event);
                    break;

                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionUpdate($event);
                    break;

                default:
                    Logger::debug("Evento não tratado", ['event_type' => $eventType]);
            }

            // Marca como processado
            $this->eventModel->markAsProcessed($eventId, $eventType, $event->toArray());

            Logger::info("Webhook processado com sucesso", [
                'event_id' => $eventId,
                'event_type' => $eventType
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao processar webhook", [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Trata checkout.session.completed
     * Salva método de pagamento e define como padrão
     */
    private function handleCheckoutCompleted(\Stripe\Event $event): void
    {
        $session = $event->data->object;
        $customerId = $session->customer;

        if (!$customerId) {
            Logger::warning("Checkout session sem customer", ['session_id' => $session->id]);
            return;
        }

        // Busca customer no banco
        $customer = $this->customerModel->findByStripeId($customerId);
        if (!$customer) {
            Logger::warning("Customer não encontrado no banco", [
                'stripe_customer_id' => $customerId,
                'session_id' => $session->id
            ]);
            return;
        }

        // Obtém a sessão completa com dados expandidos
        $fullSession = $this->stripeService->getCheckoutSession($session->id);

        // Obtém o payment method da sessão
        $paymentMethodId = null;

        // Tenta obter payment method diretamente da sessão (se disponível)
        if (isset($fullSession->payment_method)) {
            $paymentMethodId = is_string($fullSession->payment_method)
                ? $fullSession->payment_method
                : $fullSession->payment_method->id;
        }

        // Para modo subscription, o payment method está na subscription
        if (!$paymentMethodId && $fullSession->mode === 'subscription' && $fullSession->subscription) {
            $subscription = is_string($fullSession->subscription)
                ? $this->stripeService->getSubscription($fullSession->subscription)
                : $fullSession->subscription;
            
            // Tenta obter do default_payment_method da subscription
            if ($subscription && isset($subscription->default_payment_method)) {
                $paymentMethodId = is_string($subscription->default_payment_method)
                    ? $subscription->default_payment_method
                    : $subscription->default_payment_method->id;
            }
            
            // Se não encontrou, tenta obter do customer da subscription
            if (!$paymentMethodId && $subscription && isset($subscription->customer)) {
                $stripeCustomer = is_string($subscription->customer)
                    ? $this->stripeService->getCustomer($subscription->customer)
                    : $subscription->customer;
                
                if ($stripeCustomer && isset($stripeCustomer->invoice_settings->default_payment_method)) {
                    $paymentMethodId = is_string($stripeCustomer->invoice_settings->default_payment_method)
                        ? $stripeCustomer->invoice_settings->default_payment_method
                        : $stripeCustomer->invoice_settings->default_payment_method->id;
                }
            }
        }

        // Para modo payment, o payment method está no payment_intent
        if (!$paymentMethodId && $fullSession->mode === 'payment' && $fullSession->payment_intent) {
            $paymentIntent = is_string($fullSession->payment_intent)
                ? $this->stripeService->getPaymentIntent($fullSession->payment_intent)
                : $fullSession->payment_intent;
            
            if ($paymentIntent && isset($paymentIntent->payment_method)) {
                $paymentMethodId = is_string($paymentIntent->payment_method)
                    ? $paymentIntent->payment_method
                    : $paymentIntent->payment_method->id;
            }
        }

        // Se encontrou payment method, anexa ao customer e define como padrão
        if ($paymentMethodId) {
            try {
                $this->stripeService->attachPaymentMethodToCustomer($paymentMethodId, $customerId);
                
                Logger::info("Payment method salvo e definido como padrão", [
                    'payment_method_id' => $paymentMethodId,
                    'customer_id' => $customer['id'],
                    'stripe_customer_id' => $customerId,
                    'session_id' => $session->id
                ]);
            } catch (\Exception $e) {
                Logger::error("Erro ao salvar payment method", [
                    'error' => $e->getMessage(),
                    'payment_method_id' => $paymentMethodId,
                    'customer_id' => $customerId
                ]);
            }
        } else {
            Logger::warning("Payment method não encontrado na sessão", [
                'session_id' => $session->id,
                'mode' => $fullSession->mode
            ]);
        }

        // Se for modo subscription, cria/atualiza assinatura no banco
        if ($fullSession->mode === 'subscription' && $fullSession->subscription) {
            $subscription = is_string($fullSession->subscription)
                ? $this->stripeService->getSubscription($fullSession->subscription)
                : $fullSession->subscription;

            if ($subscription) {
                $this->subscriptionModel->createOrUpdate(
                    $customer['tenant_id'],
                    $customer['id'],
                    $subscription->toArray()
                );

                Logger::info("Assinatura criada/atualizada após checkout", [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $customer['id']
                ]);
            }
        }

        Logger::info("Checkout completado e processado", [
            'session_id' => $session->id,
            'customer_id' => $customer['id'],
            'mode' => $fullSession->mode
        ]);
    }

    /**
     * Trata invoice.paid
     */
    private function handleInvoicePaid(\Stripe\Event $event): void
    {
        $invoice = $event->data->object;
        Logger::info("Fatura paga", [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer
        ]);
    }

    /**
     * Trata atualização de assinatura
     */
    private function handleSubscriptionUpdate(\Stripe\Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $subscription = $this->subscriptionModel->findByStripeId($stripeSubscription->id);

        if ($subscription) {
            $this->subscriptionModel->createOrUpdate(
                $subscription['tenant_id'],
                $subscription['customer_id'],
                $stripeSubscription->toArray()
            );

            Logger::info("Assinatura atualizada", [
                'subscription_id' => $subscription['id'],
                'status' => $stripeSubscription->status
            ]);
        }
    }
}


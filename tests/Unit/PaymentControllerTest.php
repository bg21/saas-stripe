<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Controllers\PaymentController;
use App\Services\StripeService;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Exception\InvalidRequestException;
use Flight;

/**
 * Testes unitários para PaymentController
 */
class PaymentControllerTest extends TestCase
{
    private PaymentController $controller;
    private $mockStripeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockStripeService = $this->createMock(StripeService::class);
        $this->controller = new PaymentController($this->mockStripeService);
    }

    /**
     * Testa criação de payment intent
     */
    public function testCreatePaymentIntent(): void
    {
        $mockPaymentIntent = $this->createMock(PaymentIntent::class);
        $mockPaymentIntent->id = 'pi_test_123';
        $mockPaymentIntent->client_secret = 'pi_test_123_secret_abc';
        $mockPaymentIntent->amount = 2999;
        $mockPaymentIntent->currency = 'brl';
        $mockPaymentIntent->status = 'requires_payment_method';
        $mockPaymentIntent->description = 'Teste';
        $mockPaymentIntent->customer = null;
        $mockPaymentIntent->payment_method = null;
        $mockPaymentIntent->created = time();
        $mockPaymentIntent->metadata = new \stdClass();
        $mockPaymentIntent->metadata->toArray = function() { return []; };

        $this->mockStripeService
            ->expects($this->once())
            ->method('createPaymentIntent')
            ->with($this->callback(function($data) {
                return isset($data['amount']) && 
                       $data['amount'] == 2999 &&
                       $data['currency'] === 'brl';
            }))
            ->willReturn($mockPaymentIntent);

        Flight::set('tenant_id', 1);
        
        $GLOBALS['php_input'] = json_encode([
            'amount' => 2999,
            'currency' => 'brl',
            'description' => 'Teste'
        ]);

        ob_start();
        $this->controller->createPaymentIntent();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals('pi_test_123', $response['data']['id'] ?? null);
    }

    /**
     * Testa validação de campos obrigatórios para payment intent
     */
    public function testCreatePaymentIntentValidatesRequiredFields(): void
    {
        Flight::set('tenant_id', 1);
        
        $GLOBALS['php_input'] = json_encode([
            'currency' => 'brl'
            // amount faltando
        ]);

        ob_start();
        $this->controller->createPaymentIntent();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
    }

    /**
     * Testa criação de reembolso
     */
    public function testCreateRefund(): void
    {
        $mockRefund = $this->createMock(Refund::class);
        $mockRefund->id = 're_test_123';
        $mockRefund->amount = 2999;
        $mockRefund->currency = 'brl';
        $mockRefund->status = 'succeeded';
        $mockRefund->reason = 'requested_by_customer';
        $mockRefund->payment_intent = 'pi_test_123';
        $mockRefund->created = time();
        $mockRefund->metadata = new \stdClass();
        $mockRefund->metadata->toArray = function() { return []; };

        $this->mockStripeService
            ->expects($this->once())
            ->method('refundPayment')
            ->with('pi_test_123', $this->anything())
            ->willReturn($mockRefund);

        Flight::set('tenant_id', 1);
        
        $GLOBALS['php_input'] = json_encode([
            'payment_intent_id' => 'pi_test_123',
            'reason' => 'requested_by_customer'
        ]);

        ob_start();
        $this->controller->createRefund();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals('re_test_123', $response['data']['id'] ?? null);
    }

    /**
     * Testa validação de payment_intent_id para reembolso
     */
    public function testCreateRefundValidatesPaymentIntentId(): void
    {
        Flight::set('tenant_id', 1);
        
        $GLOBALS['php_input'] = json_encode([
            'amount' => 1000
            // payment_intent_id faltando
        ]);

        ob_start();
        $this->controller->createRefund();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
    }
}


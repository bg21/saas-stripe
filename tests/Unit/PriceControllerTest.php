<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Controllers\PriceController;
use App\Services\StripeService;
use Stripe\Price;
use Stripe\Collection;
use Flight;

/**
 * Testes unitários para PriceController
 */
class PriceControllerTest extends TestCase
{
    private PriceController $controller;
    private $mockStripeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockStripeService = $this->createMock(StripeService::class);
        $this->controller = new PriceController($this->mockStripeService);
    }

    /**
     * Testa listagem de preços
     */
    public function testListPrices(): void
    {
        $mockPrice = $this->createMock(Price::class);
        $mockPrice->id = 'price_test_123';
        $mockPrice->active = true;
        $mockPrice->currency = 'brl';
        $mockPrice->type = 'recurring';
        $mockPrice->unit_amount = 2999;
        $mockPrice->unit_amount_decimal = '2999';
        $mockPrice->created = time();
        $mockPrice->product = 'prod_test_123';
        $mockPrice->recurring = new \stdClass();
        $mockPrice->recurring->interval = 'month';
        $mockPrice->recurring->interval_count = 1;
        $mockPrice->metadata = new \stdClass();
        $mockPrice->metadata->toArray = function() { return []; };

        $mockCollection = $this->createMock(Collection::class);
        $mockCollection->data = [$mockPrice];
        $mockCollection->has_more = false;

        $this->mockStripeService
            ->expects($this->once())
            ->method('listPrices')
            ->willReturn($mockCollection);

        Flight::set('tenant_id', 1);
        
        ob_start();
        $this->controller->list();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertIsArray($response['data'] ?? null);
        $this->assertCount(1, $response['data'] ?? []);
    }

    /**
     * Testa listagem de preços com filtros
     */
    public function testListPricesWithFilters(): void
    {
        $mockCollection = $this->createMock(Collection::class);
        $mockCollection->data = [];
        $mockCollection->has_more = false;

        $this->mockStripeService
            ->expects($this->once())
            ->method('listPrices')
            ->with($this->callback(function($options) {
                return isset($options['active']) && 
                       $options['active'] === true &&
                       $options['type'] === 'recurring';
            }))
            ->willReturn($mockCollection);

        Flight::set('tenant_id', 1);
        
        // Simula query params
        $_GET['active'] = 'true';
        $_GET['type'] = 'recurring';
        
        ob_start();
        $this->controller->list();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
    }
}


<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ScheduleController;
use App\Services\ScheduleService;
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Models\Professional;
use Flight;

/**
 * Testes unitários para ScheduleController
 */
class ScheduleControllerTest extends TestCase
{
    private ScheduleController $controller;
    private $mockScheduleService;
    private $mockScheduleModel;
    private $mockBlockModel;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('TESTING')) {
            define('TESTING', true);
        }

        Flight::clear();
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->mockScheduleService = $this->createMock(ScheduleService::class);
        $this->mockScheduleModel = $this->createMock(ProfessionalSchedule::class);
        $this->mockBlockModel = $this->createMock(ScheduleBlock::class);
        
        $this->controller = new ScheduleController(
            $this->mockScheduleService,
            $this->mockScheduleModel,
            $this->mockBlockModel
        );
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Flight::clear();
        unset($GLOBALS['__php_input_mock']);
        $_GET = [];
        \App\Utils\RequestCache::clear();
        parent::tearDown();
    }

    public function testGetSchedule(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $schedules = [
            ['id' => 1, 'professional_id' => $professionalId, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '18:00']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        // Mock Professional model
        $mockProfessional = $this->createMock(Professional::class);
        $mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(['id' => $professionalId, 'tenant_id' => $tenantId]);

        $this->mockScheduleModel->expects($this->once())
            ->method('findByTenantAndProfessional')
            ->with($tenantId, $professionalId)
            ->willReturn($schedules);

        ob_start();
        $this->controller->getSchedule($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetScheduleProfessionalNotFound(): void
    {
        $tenantId = 1;
        $professionalId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        // Mock Professional model
        $mockProfessional = $this->createMock(Professional::class);
        $mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(null);

        ob_start();
        $this->controller->getSchedule($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdateSchedule(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $data = [
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '08:00',
                    'end_time' => '18:00',
                    'is_available' => true
                ]
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        // Mock Professional model
        $mockProfessional = $this->createMock(Professional::class);
        $mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(['id' => $professionalId, 'tenant_id' => $tenantId]);

        $this->mockScheduleModel->expects($this->once())
            ->method('findByProfessional')
            ->with($professionalId)
            ->willReturn([]);

        $this->mockScheduleModel->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->mockScheduleModel->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn(['id' => 1, 'professional_id' => $professionalId]);

        ob_start();
        $this->controller->updateSchedule($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetAvailableSlots(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $date = '2024-01-15';
        $slots = ['08:00', '08:30', '09:00'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['date'] = $date;
        $_GET['duration'] = '30';
        
        // Mock Flight::request()->query como array
        $mockQuery = ['date' => $date, 'duration' => '30'];
        Flight::request()->query = $mockQuery;

        $this->mockScheduleService->expects($this->once())
            ->method('calculateAvailableSlots')
            ->with($tenantId, $professionalId, $date, 30)
            ->willReturn($slots);

        ob_start();
        $this->controller->getAvailableSlots($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetAvailableSlotsWithoutDate(): void
    {
        $tenantId = 1;
        $professionalId = 1;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];

        ob_start();
        $this->controller->getAvailableSlots($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testCreateBlock(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $data = [
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:00:00',
            'reason' => 'Reunião'
        ];

        $blockId = 1;
        $block = [
            'id' => $blockId,
            'professional_id' => $professionalId,
            'tenant_id' => $tenantId,
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:00:00'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        $this->mockScheduleService->expects($this->once())
            ->method('createBlock')
            ->willReturn($blockId);

        $this->mockBlockModel->expects($this->once())
            ->method('findById')
            ->with($blockId)
            ->willReturn($block);

        ob_start();
        $this->controller->createBlock($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateBlockWithoutDatetime(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $data = ['reason' => 'Reunião'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        ob_start();
        $this->controller->createBlock($professionalId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testDeleteBlock(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $blockId = 1;
        $block = [
            'id' => $blockId,
            'professional_id' => $professionalId,
            'tenant_id' => $tenantId
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockBlockModel->expects($this->once())
            ->method('findById')
            ->with($blockId)
            ->willReturn($block);

        $this->mockScheduleService->expects($this->once())
            ->method('removeBlock')
            ->with($blockId);

        ob_start();
        $this->controller->deleteBlock($professionalId, $blockId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testDeleteBlockNotFound(): void
    {
        $tenantId = 1;
        $professionalId = 1;
        $blockId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockBlockModel->expects($this->once())
            ->method('findById')
            ->with($blockId)
            ->willReturn(null);

        ob_start();
        $this->controller->deleteBlock($professionalId, $blockId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }
}


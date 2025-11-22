<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\AppointmentController;
use App\Services\AppointmentService;
use App\Models\Appointment;
use App\Models\AppointmentHistory;
use Flight;

/**
 * Testes unitários para AppointmentController
 */
class AppointmentControllerTest extends TestCase
{
    private AppointmentController $controller;
    private $mockAppointmentService;
    private $mockAppointmentModel;

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

        $this->mockAppointmentService = $this->createMock(AppointmentService::class);
        $this->mockAppointmentModel = $this->createMock(Appointment::class);
        
        $this->controller = new AppointmentController(
            $this->mockAppointmentService,
            $this->mockAppointmentModel
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

    public function testListAppointments(): void
    {
        $tenantId = 1;
        $appointments = [
            ['id' => 1, 'tenant_id' => $tenantId, 'appointment_date' => '2024-01-15', 'appointment_time' => '10:00']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];

        $this->mockAppointmentModel->expects($this->once())
            ->method('findAllWithCount')
            ->willReturn(['data' => $appointments, 'total' => 1]);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testListAppointmentsWithFilters(): void
    {
        $tenantId = 1;
        $appointments = [
            ['id' => 1, 'tenant_id' => $tenantId, 'professional_id' => 1, 'status' => 'scheduled']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['professional_id'] = '1';
        $_GET['status'] = 'scheduled';

        $this->mockAppointmentModel->expects($this->once())
            ->method('findAllWithCount')
            ->willReturn(['data' => $appointments, 'total' => 1]);

        ob_start();
        $this->controller->list();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCreateAppointment(): void
    {
        $tenantId = 1;
        $userId = 1;
        $data = [
            'professional_id' => 1,
            'client_id' => 1,
            'pet_id' => 1,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => '10:00',
            'duration_minutes' => 30
        ];

        $appointment = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'professional_id' => 1,
            'client_id' => 1,
            'pet_id' => 1,
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => '10:00',
            'status' => 'scheduled'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('user_id', $userId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);
        \App\Utils\RequestCache::clear();

        $this->mockAppointmentService->expects($this->once())
            ->method('create')
            ->with($tenantId, $this->callback(function($arg) use ($data) {
                return $arg['professional_id'] === $data['professional_id'];
            }), $userId)
            ->willReturn($appointment);

        ob_start();
        $this->controller->create();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetAppointment(): void
    {
        $tenantId = 1;
        $appointmentId = 1;
        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'professional_id' => 1,
            'appointment_date' => '2024-01-15',
            'appointment_time' => '10:00'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockAppointmentModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $appointmentId)
            ->willReturn($appointment);

        ob_start();
        $this->controller->get($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetAppointmentNotFound(): void
    {
        $tenantId = 1;
        $appointmentId = 999;

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockAppointmentModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $appointmentId)
            ->willReturn(null);

        ob_start();
        $this->controller->get($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }

    public function testUpdateAppointment(): void
    {
        $tenantId = 1;
        $userId = 1;
        $appointmentId = 1;
        $updateData = ['appointment_time' => '11:00', 'notes' => 'Atualizado'];
        $updatedAppointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'appointment_time' => '11:00',
            'notes' => 'Atualizado'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('user_id', $userId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($updateData);

        $this->mockAppointmentService->expects($this->once())
            ->method('update')
            ->with($tenantId, $appointmentId, $this->callback(function($arg) use ($updateData) {
                return isset($arg['appointment_time']) && $arg['appointment_time'] === $updateData['appointment_time'];
            }), $userId)
            ->willReturn($updatedAppointment);

        ob_start();
        $this->controller->update($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCancelAppointment(): void
    {
        $tenantId = 1;
        $userId = 1;
        $appointmentId = 1;
        $data = ['reason' => 'Cliente desistiu'];
        $cancelledAppointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'cancelled'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('user_id', $userId);
        Flight::set('is_user_auth', false);

        $GLOBALS['__php_input_mock'] = json_encode($data);

        $this->mockAppointmentService->expects($this->once())
            ->method('cancel')
            ->with($tenantId, $appointmentId, 'Cliente desistiu', $userId)
            ->willReturn($cancelledAppointment);

        ob_start();
        $this->controller->cancel($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testConfirmAppointment(): void
    {
        $tenantId = 1;
        $userId = 1;
        $appointmentId = 1;
        $confirmedAppointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'confirmed'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('user_id', $userId);
        Flight::set('is_user_auth', false);

        $this->mockAppointmentService->expects($this->once())
            ->method('confirm')
            ->with($tenantId, $appointmentId, $userId)
            ->willReturn($confirmedAppointment);

        ob_start();
        $this->controller->confirm($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testCompleteAppointment(): void
    {
        $tenantId = 1;
        $userId = 1;
        $appointmentId = 1;
        $completedAppointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'completed'
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('user_id', $userId);
        Flight::set('is_user_auth', false);

        $this->mockAppointmentService->expects($this->once())
            ->method('complete')
            ->with($tenantId, $appointmentId, $userId)
            ->willReturn($completedAppointment);

        ob_start();
        $this->controller->complete($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetAvailableSlots(): void
    {
        $tenantId = 1;
        $slots = ['08:00', '08:30', '09:00'];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['professional_id'] = '1';
        $_GET['date'] = '2024-01-15';
        $_GET['duration'] = '30';

        $this->mockAppointmentService->expects($this->once())
            ->method('getAvailableSlots')
            ->with($tenantId, 1, '2024-01-15', 30)
            ->willReturn($slots);

        ob_start();
        $this->controller->getAvailableSlots();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }

    public function testGetHistory(): void
    {
        $tenantId = 1;
        $appointmentId = 1;
        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId
        ];
        $history = [
            ['id' => 1, 'appointment_id' => $appointmentId, 'action' => 'created', 'changed_by' => 1]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);

        $this->mockAppointmentModel->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $appointmentId)
            ->willReturn($appointment);

        // Mock AppointmentHistory model
        $mockHistoryModel = $this->createMock(AppointmentHistory::class);
        $mockHistoryModel->expects($this->once())
            ->method('findByAppointment')
            ->with($appointmentId)
            ->willReturn($history);

        ob_start();
        $this->controller->getHistory($appointmentId);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }
}


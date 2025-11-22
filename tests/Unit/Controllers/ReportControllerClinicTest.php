<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ReportController;
use App\Services\ReportService;
use App\Services\StripeService;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Pet;
use App\Models\Client;
use Flight;

/**
 * Testes unitários para endpoints de relatórios da clínica no ReportController
 */
class ReportControllerClinicTest extends TestCase
{
    private ReportController $controller;
    private $mockReportService;
    private $mockStripeService;

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

        $this->mockStripeService = $this->createMock(StripeService::class);
        $this->mockReportService = $this->createMock(ReportService::class);
        
        // ReportController cria ReportService internamente, então precisamos mockar de outra forma
        // Por enquanto, vamos criar o controller normalmente e mockar os models que ele usa
        $this->controller = new ReportController($this->mockStripeService);
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

    public function testClinicAppointments(): void
    {
        $tenantId = 1;
        $appointments = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'professional_id' => 1,
                'status' => 'completed',
                'appointment_date' => '2024-01-15'
            ],
            [
                'id' => 2,
                'tenant_id' => $tenantId,
                'professional_id' => 1,
                'status' => 'cancelled',
                'appointment_date' => '2024-01-16'
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];
        
        // Mock Flight::request()->query com método getData()
        $mockQuery = $this->createMock(\stdClass::class);
        $mockQuery->method('getData')->willReturn([]);
        Flight::request()->query = $mockQuery;

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->once())
            ->method('findByTenant')
            ->willReturn($appointments);

        ob_start();
        $this->controller->clinicAppointments();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }

    public function testClinicProfessionals(): void
    {
        $tenantId = 1;
        $professionals = [
            ['id' => 1, 'tenant_id' => $tenantId, 'user_id' => 1, 'status' => 'active']
        ];
        $appointments = [
            ['id' => 1, 'professional_id' => 1, 'appointment_date' => '2024-01-15', 'duration_minutes' => 30, 'status' => 'completed']
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];

        // Mock Professional model
        $mockProfessionalModel = $this->createMock(Professional::class);
        $mockProfessionalModel->expects($this->once())
            ->method('findByTenant')
            ->willReturn($professionals);

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->atLeastOnce())
            ->method('findByTenant')
            ->willReturn($appointments);

        ob_start();
        $this->controller->clinicProfessionals();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }

    public function testClinicPets(): void
    {
        $tenantId = 1;
        $appointments = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'pet_id' => 1,
                'appointment_date' => '2024-01-15'
            ],
            [
                'id' => 2,
                'tenant_id' => $tenantId,
                'pet_id' => 1,
                'appointment_date' => '2024-01-20'
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->once())
            ->method('findByTenant')
            ->willReturn($appointments);

        // Mock Pet model
        $mockPetModel = $this->createMock(Pet::class);
        $mockPetModel->expects($this->atLeastOnce())
            ->method('findById')
            ->willReturn(['id' => 1, 'species' => 'Cachorro', 'client_id' => 1]);

        // Mock Client model
        $mockClientModel = $this->createMock(Client::class);
        $mockClientModel->expects($this->atLeastOnce())
            ->method('findById')
            ->willReturn(['id' => 1, 'tenant_id' => $tenantId]);

        ob_start();
        $this->controller->clinicPets();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }

    public function testClinicDashboard(): void
    {
        $tenantId = 1;
        $today = date('Y-m-d');
        $appointments = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'appointment_date' => $today,
                'status' => 'scheduled'
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET = [];

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->atLeastOnce())
            ->method('findByTenant')
            ->willReturn($appointments);

        ob_start();
        $this->controller->clinicDashboard();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }

    public function testClinicAppointmentsWithPeriod(): void
    {
        $tenantId = 1;
        $appointments = [
            [
                'id' => 1,
                'tenant_id' => $tenantId,
                'professional_id' => 1,
                'status' => 'completed',
                'appointment_date' => '2024-01-15'
            ]
        ];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['period'] = 'month';

        // Mock ReportService
        $mockReportService = $this->createMock(ReportService::class);
        $mockReportService->expects($this->once())
            ->method('processPeriodFilter')
            ->willReturn([
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-31'
            ]);

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->once())
            ->method('findByTenant')
            ->willReturn($appointments);

        ob_start();
        $this->controller->clinicAppointments();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }

    public function testClinicProfessionalsWithPeriod(): void
    {
        $tenantId = 1;
        $professionals = [
            ['id' => 1, 'tenant_id' => $tenantId, 'user_id' => 1, 'status' => 'active']
        ];
        $appointments = [];

        Flight::set('tenant_id', $tenantId);
        Flight::set('is_user_auth', false);
        $_GET['period'] = 'week';

        // Mock Professional model
        $mockProfessionalModel = $this->createMock(Professional::class);
        $mockProfessionalModel->expects($this->once())
            ->method('findByTenant')
            ->willReturn($professionals);

        // Mock Appointment model
        $mockAppointmentModel = $this->createMock(Appointment::class);
        $mockAppointmentModel->expects($this->atLeastOnce())
            ->method('findByTenant')
            ->willReturn($appointments);

        ob_start();
        $this->controller->clinicProfessionals();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertNotEmpty($output);
    }
}


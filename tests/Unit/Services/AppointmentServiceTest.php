<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\AppointmentService;
use App\Services\ScheduleService;
use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\Professional;
use App\Models\Client;
use App\Models\Pet;
use App\Models\ScheduleBlock;

/**
 * Testes unitários para AppointmentService
 */
class AppointmentServiceTest extends TestCase
{
    private AppointmentService $service;
    private $mockAppointment;
    private $mockHistory;
    private $mockProfessional;
    private $mockClient;
    private $mockPet;
    private $mockBlock;
    private $mockScheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria mocks
        $this->mockAppointment = $this->createMock(Appointment::class);
        $this->mockHistory = $this->createMock(AppointmentHistory::class);
        $this->mockProfessional = $this->createMock(Professional::class);
        $this->mockClient = $this->createMock(Client::class);
        $this->mockPet = $this->createMock(Pet::class);
        $this->mockBlock = $this->createMock(ScheduleBlock::class);
        $this->mockScheduleService = $this->createMock(ScheduleService::class);

        // Cria service com mocks
        $this->service = new AppointmentService(
            $this->mockAppointment,
            $this->mockHistory,
            $this->mockProfessional,
            $this->mockClient,
            $this->mockPet,
            $this->mockBlock,
            $this->mockScheduleService
        );
    }

    public function testCreateAppointment(): void
    {
        // Arrange
        $tenantId = 1;
        $userId = 1;
        // Usa data futura (30 dias a partir de hoje)
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $data = [
            'professional_id' => 1,
            'client_id' => 1,
            'pet_id' => 1,
            'appointment_date' => $futureDate,
            'appointment_time' => '10:00:00',
            'duration_minutes' => 30,
            'status' => 'scheduled'
        ];

        $professional = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'name' => 'Dr. Teste'
        ];

        $client = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'name' => 'Cliente Teste'
        ];

        $pet = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'client_id' => 1,
            'name' => 'Pet Teste'
        ];

        $appointmentCreated = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'professional_id' => 1,
            'client_id' => 1,
            'pet_id' => 1,
            'appointment_date' => $futureDate,
            'appointment_time' => '10:00:00',
            'status' => 'scheduled'
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, 1)
            ->willReturn($professional);

        $this->mockClient->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, 1)
            ->willReturn($client);

        $this->mockPet->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, 1)
            ->willReturn($pet);

        $this->mockScheduleService->expects($this->once())
            ->method('isWithinClinicHours')
            ->willReturn(true);

        $this->mockBlock->expects($this->once())
            ->method('hasBlock')
            ->willReturn(false);

        $this->mockAppointment->expects($this->once())
            ->method('hasConflict')
            ->willReturn(false);

        $this->mockAppointment->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->mockAppointment->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($appointmentCreated);

        $this->mockHistory->expects($this->once())
            ->method('logChange');

        // Act
        $result = $this->service->create($tenantId, $data, $userId);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('scheduled', $result['status']);
    }

    public function testCreateAppointmentWithInvalidProfessional(): void
    {
        // Arrange
        $tenantId = 1;
        $data = [
            'professional_id' => 999,
            'client_id' => 1,
            'pet_id' => 1,
            'appointment_date' => '2024-01-15',
            'appointment_time' => '10:00:00'
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn(null);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Profissional não encontrado');

        // Act
        $this->service->create($tenantId, $data);
    }

    public function testCreateAppointmentWithConflict(): void
    {
        // Arrange
        $tenantId = 1;
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $data = [
            'professional_id' => 1,
            'client_id' => 1,
            'pet_id' => 1,
            'appointment_date' => $futureDate,
            'appointment_time' => '10:00:00',
            'duration_minutes' => 30
        ];

        $professional = ['id' => 1, 'tenant_id' => $tenantId];
        $client = ['id' => 1, 'tenant_id' => $tenantId];
        $pet = ['id' => 1, 'tenant_id' => $tenantId, 'client_id' => 1];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($professional);

        $this->mockClient->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($client);

        $this->mockPet->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($pet);

        $this->mockScheduleService->expects($this->once())
            ->method('isWithinClinicHours')
            ->willReturn(true);

        $this->mockBlock->expects($this->once())
            ->method('hasBlock')
            ->willReturn(false);

        $this->mockAppointment->expects($this->once())
            ->method('hasConflict')
            ->willReturn(true); // Conflito!

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Horário já está ocupado');

        // Act
        $this->service->create($tenantId, $data);
    }

    public function testConfirmAppointment(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 1;
        $userId = 1;

        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'scheduled'
        ];

        $appointmentConfirmed = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'confirmed'
        ];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $appointmentId)
            ->willReturn($appointment);

        $this->mockAppointment->expects($this->once())
            ->method('update')
            ->with($appointmentId, ['status' => 'confirmed']);

        $this->mockAppointment->expects($this->once())
            ->method('findById')
            ->with($appointmentId)
            ->willReturn($appointmentConfirmed);

        $this->mockHistory->expects($this->once())
            ->method('logChange');

        // Act
        $result = $this->service->confirm($tenantId, $appointmentId, $userId);

        // Assert
        $this->assertEquals('confirmed', $result['status']);
    }

    public function testConfirmAppointmentWithInvalidStatus(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 1;

        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'completed' // Já concluído
        ];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($appointment);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Apenas agendamentos com status 'scheduled' podem ser confirmados");

        // Act
        $this->service->confirm($tenantId, $appointmentId);
    }

    public function testCancelAppointment(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 1;
        $userId = 1;
        $reason = 'Cliente desistiu';

        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'scheduled'
        ];

        $appointmentCancelled = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($appointment);

        $this->mockAppointment->expects($this->once())
            ->method('update');

        $this->mockAppointment->expects($this->once())
            ->method('findById')
            ->willReturn($appointmentCancelled);

        $this->mockHistory->expects($this->once())
            ->method('logChange');

        // Act
        $result = $this->service->cancel($tenantId, $appointmentId, $reason, $userId);

        // Assert
        $this->assertEquals('cancelled', $result['status']);
    }

    public function testCancelAppointmentAlreadyCancelled(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 1;

        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'cancelled'
        ];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($appointment);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Agendamento já está cancelado');

        // Act
        $this->service->cancel($tenantId, $appointmentId, 'Teste');
    }

    public function testCompleteAppointment(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 1;
        $userId = 1;

        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'confirmed'
        ];

        $appointmentCompleted = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'completed'
        ];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($appointment);

        $this->mockAppointment->expects($this->once())
            ->method('update')
            ->with($appointmentId, ['status' => 'completed']);

        $this->mockAppointment->expects($this->once())
            ->method('findById')
            ->willReturn($appointmentCompleted);

        $this->mockHistory->expects($this->once())
            ->method('logChange');

        // Act
        $result = $this->service->complete($tenantId, $appointmentId, $userId);

        // Assert
        $this->assertEquals('completed', $result['status']);
    }

    public function testUpdateAppointment(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 1;
        $userId = 1;
        $data = ['notes' => 'Nova observação'];

        $appointment = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'scheduled',
            'notes' => 'Observação antiga'
        ];

        $appointmentUpdated = [
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'status' => 'scheduled',
            'notes' => 'Nova observação'
        ];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($appointment);

        $this->mockAppointment->expects($this->once())
            ->method('update')
            ->with($appointmentId, $data);

        $this->mockAppointment->expects($this->once())
            ->method('findById')
            ->willReturn($appointmentUpdated);

        $this->mockHistory->expects($this->once())
            ->method('logChange');

        // Act
        $result = $this->service->update($tenantId, $appointmentId, $data, $userId);

        // Assert
        $this->assertEquals('Nova observação', $result['notes']);
    }

    public function testUpdateAppointmentNotFound(): void
    {
        // Arrange
        $tenantId = 1;
        $appointmentId = 999;
        $data = ['notes' => 'Teste'];

        $this->mockAppointment->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn(null);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Agendamento não encontrado');

        // Act
        $this->service->update($tenantId, $appointmentId, $data);
    }
}


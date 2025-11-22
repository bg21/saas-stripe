<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\ScheduleService;
use App\Models\ClinicConfiguration;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\ScheduleBlock;
use App\Models\Appointment;
use PDO;

/**
 * Testes unitários para ScheduleService
 */
class ScheduleServiceTest extends TestCase
{
    private ScheduleService $service;
    private $mockClinicConfig;
    private $mockProfessional;
    private $mockSchedule;
    private $mockBlock;
    private $mockAppointment;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria mocks dos models
        $this->mockClinicConfig = $this->createMock(ClinicConfiguration::class);
        $this->mockProfessional = $this->createMock(Professional::class);
        $this->mockSchedule = $this->createMock(ProfessionalSchedule::class);
        $this->mockBlock = $this->createMock(ScheduleBlock::class);
        $this->mockAppointment = $this->createMock(Appointment::class);

        // Cria service com mocks
        $this->service = new ScheduleService(
            $this->mockClinicConfig,
            $this->mockProfessional,
            $this->mockSchedule,
            $this->mockBlock,
            $this->mockAppointment
        );
    }

    public function testCalculateAvailableSlotsWithValidData(): void
    {
        // Arrange
        $tenantId = 1;
        $professionalId = 1;
        $date = '2024-01-15'; // Segunda-feira
        $dayOfWeek = 1;

        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'name' => 'Dr. Teste',
            'default_consultation_duration' => 30
        ];

        $clinicConfig = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'default_appointment_duration' => 30,
            'time_slot_interval' => 15,
            'opening_time_monday' => '08:00:00',
            'closing_time_monday' => '18:00:00'
        ];

        $schedule = [
            'id' => 1,
            'professional_id' => $professionalId,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => 1
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn($professional);

        $this->mockClinicConfig->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn($clinicConfig);

        $this->mockSchedule->expects($this->once())
            ->method('findAvailableByDay')
            ->with($professionalId, $dayOfWeek)
            ->willReturn($schedule);

        $this->mockBlock->expects($this->atLeastOnce())
            ->method('hasBlock')
            ->willReturn(false);

        $this->mockAppointment->expects($this->atLeastOnce())
            ->method('hasConflict')
            ->willReturn(false);

        // Act
        $slots = $this->service->calculateAvailableSlots($tenantId, $professionalId, $date);

        // Assert
        $this->assertIsArray($slots);
        $this->assertGreaterThan(0, count($slots));
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('time', $slot);
            $this->assertArrayHasKey('available', $slot);
            $this->assertTrue($slot['available']);
        }
    }

    public function testCalculateAvailableSlotsWithProfessionalNotFound(): void
    {
        // Arrange
        $tenantId = 1;
        $professionalId = 999;
        $date = '2024-01-15';

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn(null);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Profissional não encontrado');

        // Act
        $this->service->calculateAvailableSlots($tenantId, $professionalId, $date);
    }

    public function testCalculateAvailableSlotsWithClinicConfigNotFound(): void
    {
        // Arrange
        $tenantId = 1;
        $professionalId = 1;
        $date = '2024-01-15';

        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'name' => 'Dr. Teste'
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($professional);

        $this->mockClinicConfig->expects($this->once())
            ->method('findByTenant')
            ->willReturn(null);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configurações da clínica não encontradas');

        // Act
        $this->service->calculateAvailableSlots($tenantId, $professionalId, $date);
    }

    public function testCalculateAvailableSlotsWhenProfessionalNotAvailable(): void
    {
        // Arrange
        $tenantId = 1;
        $professionalId = 1;
        $date = '2024-01-15';
        $dayOfWeek = 1;

        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'name' => 'Dr. Teste'
        ];

        $clinicConfig = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'default_appointment_duration' => 30
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($professional);

        $this->mockClinicConfig->expects($this->once())
            ->method('findByTenant')
            ->willReturn($clinicConfig);

        $this->mockSchedule->expects($this->once())
            ->method('findAvailableByDay')
            ->willReturn(null);

        // Act
        $slots = $this->service->calculateAvailableSlots($tenantId, $professionalId, $date);

        // Assert
        $this->assertIsArray($slots);
        $this->assertCount(0, $slots);
    }

    public function testIsWithinClinicHours(): void
    {
        // Arrange
        $tenantId = 1;
        $datetime = new \DateTime('2024-01-15 10:00:00'); // Segunda-feira
        $dayOfWeek = 1;

        $clinicConfig = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'opening_time_monday' => '08:00:00',
            'closing_time_monday' => '18:00:00'
        ];

        $this->mockClinicConfig->expects($this->once())
            ->method('findByTenant')
            ->with($tenantId)
            ->willReturn($clinicConfig);

        // Act
        $result = $this->service->isWithinClinicHours($datetime, $tenantId);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsWithinClinicHoursOutsideHours(): void
    {
        // Arrange
        $tenantId = 1;
        $datetime = new \DateTime('2024-01-15 20:00:00'); // Fora do horário
        $dayOfWeek = 1;

        $clinicConfig = [
            'id' => 1,
            'tenant_id' => $tenantId,
            'opening_time_monday' => '08:00:00',
            'closing_time_monday' => '18:00:00'
        ];

        $this->mockClinicConfig->expects($this->once())
            ->method('findByTenant')
            ->willReturn($clinicConfig);

        // Act
        $result = $this->service->isWithinClinicHours($datetime, $tenantId);

        // Assert
        $this->assertFalse($result);
    }

    public function testCreateBlock(): void
    {
        // Arrange
        $tenantId = 1;
        $professionalId = 1;
        $start = new \DateTime('2024-01-15 10:00:00');
        $end = new \DateTime('2024-01-15 12:00:00');
        $reason = 'Reunião';

        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'name' => 'Dr. Teste'
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->with($tenantId, $professionalId)
            ->willReturn($professional);

        $this->mockBlock->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        // Act
        $blockId = $this->service->createBlock($tenantId, $professionalId, $start, $end, $reason);

        // Assert
        $this->assertEquals(1, $blockId);
    }

    public function testCreateBlockWithInvalidDates(): void
    {
        // Arrange
        $tenantId = 1;
        $professionalId = 1;
        $start = new \DateTime('2024-01-15 12:00:00');
        $end = new \DateTime('2024-01-15 10:00:00'); // End antes de start

        $professional = [
            'id' => $professionalId,
            'tenant_id' => $tenantId,
            'name' => 'Dr. Teste'
        ];

        $this->mockProfessional->expects($this->once())
            ->method('findByTenantAndId')
            ->willReturn($professional);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Data/hora final deve ser posterior à inicial');

        // Act
        $this->service->createBlock($tenantId, $professionalId, $start, $end);
    }

    public function testRemoveBlock(): void
    {
        // Arrange
        $blockId = 1;

        $this->mockBlock->expects($this->once())
            ->method('delete')
            ->with($blockId)
            ->willReturn(true);

        // Act
        $result = $this->service->removeBlock($blockId);

        // Assert
        $this->assertTrue($result);
    }
}


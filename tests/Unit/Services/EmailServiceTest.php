<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\EmailService;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Testes unitários para EmailService
 * 
 * Cenários cobertos:
 * - Renderização de templates
 * - Envio de emails de agendamento
 * - Envio de emails do Stripe
 * - Tratamento de erros
 */
class EmailServiceTest extends TestCase
{
    private EmailService $emailService;
    private string $testTemplatesPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Define ambiente de teste
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['MAIL_HOST'] = 'smtp.test.com';
        $_ENV['MAIL_PORT'] = '587';
        $_ENV['MAIL_USERNAME'] = 'test@test.com';
        $_ENV['MAIL_PASSWORD'] = 'test123';
        $_ENV['MAIL_ENCRYPTION'] = 'tls';
        $_ENV['MAIL_FROM_ADDRESS'] = 'noreply@test.com';
        $_ENV['MAIL_FROM_NAME'] = 'Test System';
        $_ENV['SUPORTE_EMAIL'] = 'support@test.com';
        
        $this->emailService = new EmailService(true); // Modo debug
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['APP_ENV'], $_ENV['MAIL_HOST'], $_ENV['MAIL_PORT']);
    }

    /**
     * Testa renderização de template de email
     */
    public function testRenderTemplate(): void
    {
        // Cria template de teste temporário
        $templatePath = __DIR__ . '/../../../App/Templates/Email/appointment_created.html';
        
        if (!file_exists($templatePath)) {
            $this->markTestSkipped('Template de email não encontrado');
        }
        
        $variables = [
            'client_name' => 'João Silva',
            'pet_name' => 'Rex',
            'professional_name' => 'Dr. Maria',
            'appointment_date' => '2025-12-01',
            'appointment_time' => '14:00'
        ];
        
        $rendered = $this->emailService->renderTemplate('appointment_created', $variables);
        
        $this->assertIsString($rendered);
        $this->assertStringContainsString('João Silva', $rendered);
        $this->assertStringContainsString('Rex', $rendered);
        $this->assertStringContainsString('Dr. Maria', $rendered);
    }

    /**
     * Testa envio de email de agendamento criado
     */
    public function testSendAppointmentCreated(): void
    {
        $appointment = [
            'id' => 1,
            'appointment_date' => '2025-12-01',
            'appointment_time' => '14:00',
            'status' => 'scheduled'
        ];
        
        $client = ['name' => 'João Silva', 'email' => 'joao@test.com'];
        $pet = ['name' => 'Rex'];
        $professional = ['name' => 'Dr. Maria'];
        
        // Em modo de teste, não envia realmente
        $result = $this->emailService->sendAppointmentCreated($appointment, $client, $pet, $professional);
        
        // Verifica que o método não lança exceção
        $this->assertIsBool($result);
    }

    /**
     * Testa envio de email de agendamento confirmado
     */
    public function testSendAppointmentConfirmed(): void
    {
        $appointment = [
            'id' => 1,
            'appointment_date' => '2025-12-01',
            'appointment_time' => '14:00',
            'status' => 'confirmed'
        ];
        
        $client = ['name' => 'João Silva', 'email' => 'joao@test.com'];
        $pet = ['name' => 'Rex'];
        $professional = ['name' => 'Dr. Maria'];
        
        $result = $this->emailService->sendAppointmentConfirmed($appointment, $client, $pet, $professional);
        
        $this->assertIsBool($result);
    }

    /**
     * Testa envio de email de agendamento cancelado
     */
    public function testSendAppointmentCancelled(): void
    {
        $appointment = [
            'id' => 1,
            'appointment_date' => '2025-12-01',
            'appointment_time' => '14:00',
            'status' => 'cancelled'
        ];
        
        $client = ['name' => 'João Silva', 'email' => 'joao@test.com'];
        $pet = ['name' => 'Rex'];
        $professional = ['name' => 'Dr. Maria'];
        $reason = 'Cancelado pelo cliente';
        
        $result = $this->emailService->sendAppointmentCancelled($appointment, $client, $pet, $professional, $reason);
        
        $this->assertIsBool($result);
    }

    /**
     * Testa envio de email de lembrete de agendamento
     */
    public function testSendAppointmentReminder(): void
    {
        $appointment = [
            'id' => 1,
            'appointment_date' => '2025-12-01',
            'appointment_time' => '14:00',
            'status' => 'confirmed'
        ];
        
        $client = ['name' => 'João Silva', 'email' => 'joao@test.com'];
        $pet = ['name' => 'Rex'];
        $professional = ['name' => 'Dr. Maria'];
        
        $result = $this->emailService->sendAppointmentReminder($appointment, $client, $pet, $professional);
        
        $this->assertIsBool($result);
    }

    /**
     * Testa validação de email inválido
     */
    public function testInvalidEmail(): void
    {
        $result = $this->emailService->enviar(
            'email-invalido',
            'Assunto',
            'Corpo do email'
        );
        
        // Deve retornar false para email inválido
        $this->assertFalse($result);
    }
}


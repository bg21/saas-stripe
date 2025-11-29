<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Appointment;

$appointmentModel = new Appointment();
$apt = $appointmentModel->findById(3);

if ($apt) {
    echo "Agendamento ID: " . $apt['id'] . "\n";
    echo "Status: " . ($apt['status'] ?? 'N/A') . "\n";
    echo "Tenant ID: " . ($apt['tenant_id'] ?? 'N/A') . "\n";
    echo "Professional ID: " . ($apt['professional_id'] ?? 'N/A') . "\n";
    echo "Date: " . ($apt['appointment_date'] ?? 'N/A') . "\n";
    echo "Time: " . ($apt['appointment_time'] ?? 'N/A') . "\n";
} else {
    echo "Agendamento não encontrado\n";
}


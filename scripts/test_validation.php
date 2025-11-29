<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

$model = new App\Models\ClinicConfiguration();
$data = [
    'default_appointment_duration' => 300, // Muito alto (máx: 240)
    'time_slot_interval' => 100, // Muito alto (máx: 60)
    'cancellation_hours' => 200 // Muito alto (máx: 168)
];

$errors = $model->validate($data);

echo "Erros encontrados: " . count($errors) . PHP_EOL;
foreach($errors as $field => $error) {
    echo "  - {$field}: {$error}" . PHP_EOL;
}


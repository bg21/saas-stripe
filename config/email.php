<?php

/**
 * Configurações de email
 * Pode ser sobrescrito por variáveis de ambiente no .env
 */

return [
    'development' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.titan.email',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? 'suporte@orcamentum.com',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'suporte@orcamentum.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? ($_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'),
        'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com'
    ],
    'production' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.titan.email',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? 'suporte@orcamentum.com',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'suporte@orcamentum.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? ($_ENV['APP_NAME'] ?? 'Sistema de Pagamentos'),
        'support_email' => $_ENV['SUPORTE_EMAIL'] ?? 'suporte@orcamentum.com'
    ]
];


<?php
$GLOBALS['SMTP'] = [
    'host' => getenv('SMTP_HOST') ?: '127.0.0.1',
    'port' => getenv('SMTP_PORT') ?: 25,
    'username' => getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'from' => [
        'email' => getenv('SMTP_FROM_EMAIL') ?: 'no-reply@samuelcheston.com',
        'name' => getenv('SMTP_FROM_NAME') ?: 'HRPAuth',
    ],
];

<?php
declare(strict_types=1);
$settings = [
    'db_dsn' => getenv('RGR_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3310;dbname=furniture_rgr;charset=utf8mb4',
    'db_user' => getenv('RGR_DB_USER') ?: 'root',
    'db_password' => getenv('RGR_DB_PASSWORD') ?: '',
    'smtp_host' => getenv('SMTP_HOST') ?: '127.0.0.1',
    'smtp_port' => (int)(getenv('SMTP_PORT') ?: 1025),
    'smtp_tls' => getenv('SMTP_TLS') ?: '',
    'smtp_user' => getenv('SMTP_USER') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'mail_from' => getenv('MAIL_FROM') ?: 'orders@forma.example',
    'mail_to' => getenv('MAIL_TO') ?: '',
    'delivery_minor' => 150000,
    'debug' => getenv('RGR_DEBUG') === '1',
];
$localFile = __DIR__ . '/local.php';
return is_file($localFile) ? array_replace($settings, require $localFile) : $settings;

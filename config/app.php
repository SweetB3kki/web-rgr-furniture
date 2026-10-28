<?php
declare(strict_types=1);
$settings = [
    'db_dsn' => getenv('RGR_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3310;dbname=furniture_rgr;charset=utf8mb4',
    'db_user' => getenv('RGR_DB_USER') ?: 'root',
    'db_password' => getenv('RGR_DB_PASSWORD') ?: '',
    'db_ssl_ca' => getenv('RGR_DB_SSL_CA') ?: (getenv('VERCEL')==='1' ? __DIR__.'/certs/ca-bundle.pem' : ''),
    'session_storage' => getenv('RGR_SESSION_STORAGE') ?: (getenv('VERCEL')==='1' ? 'database' : 'files'),
    'upload_storage' => getenv('RGR_UPLOAD_STORAGE') ?: (getenv('VERCEL')==='1' ? 'database' : 'files'),
    'upload_max_bytes' => getenv('VERCEL')==='1' ? 3*1024*1024 : 4*1024*1024,
    'smtp_host' => getenv('SMTP_HOST') ?: '127.0.0.1',
    'smtp_port' => (int)(getenv('SMTP_PORT') ?: 1025),
    'smtp_tls' => getenv('SMTP_TLS') ?: '',
    'smtp_user' => getenv('SMTP_USER') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'mail_from' => getenv('MAIL_FROM') ?: 'orders@forma.example',
    'mail_to' => getenv('MAIL_TO') ?: '',
    'mail_enabled' => getenv('VERCEL')!=='1' || (bool)getenv('SMTP_HOST'),
    'delivery_minor' => 150000,
    'debug' => getenv('RGR_DEBUG') === '1',
];
$localFile = __DIR__ . '/local.php';
return is_file($localFile) ? array_replace($settings, require $localFile) : $settings;

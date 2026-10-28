<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$config = require ROOT . '/config/app.php';
date_default_timezone_set('Europe/Moscow');
spl_autoload_register(static function (string $class): void {
    foreach (['core', 'models', 'controllers'] as $directory) {
        $path = ROOT . '/app/' . $directory . '/' . $class . '.php';
        if (is_file($path)) { require $path; return; }
    }
});
function config(string $key): mixed { global $config; return $config[$key] ?? null; }
function h(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lang(): string { return ($_SESSION['lang'] ?? 'ru') === 'en' ? 'en' : 'ru'; }
function t(string $ru, string $en): string { return lang() === 'ru' ? $ru : $en; }
function tr(array $row, string $key = 'title'): string { return (string)($row[$key . '_' . lang()] ?? ''); }
function money(int $minor): string { return number_format($minor / 100, 0, ',', ' ') . ' ₽'; }
function asset(string $path): string { return $path . '?v=' . filemtime(ROOT . '/public' . $path); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function verifyCsrf(): void {
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf(), $token)) { throw new HttpError(419, t('Сессия формы истекла. Обновите страницу.', 'Form session expired. Reload the page.')); }
}
if (PHP_SAPI !== 'cli') {
    if (config('session_storage')==='database') session_set_save_handler(new DatabaseSession(),true);
    ini_set('session.use_strict_mode','1');
    session_name('FurnitureRgr');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => getenv('VERCEL')==='1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

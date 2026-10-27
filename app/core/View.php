<?php
declare(strict_types=1);
class View {
    public static function show(string $template, array $data = [], bool $admin = false): void {
        extract($data, EXTR_SKIP);
        ob_start(); require ROOT . '/app/views/' . $template . '.php'; $content = ob_get_clean();
        require ROOT . '/app/views/' . ($admin ? 'admin_layout' : 'layout') . '.php';
    }
}

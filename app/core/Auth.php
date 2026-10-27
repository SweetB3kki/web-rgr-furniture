<?php
declare(strict_types=1);
class Auth {
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        return DB::one('SELECT id, login, role FROM users WHERE id=? AND active=1', [$_SESSION['user_id']]);
    }
    public static function can(string $section): bool {
        $user = self::user();
        return $user && ($user['role'] === 'admin' || (bool)DB::one('SELECT user_id FROM user_permissions WHERE user_id=? AND section=?', [$user['id'], $section]));
    }
    public static function require(string $section = ''): array {
        $user = self::user();
        if (!$user) redirect('/admin/login');
        if ($section && !self::can($section)) throw new HttpError(403, t('Нет доступа к этому разделу.', 'Access to this section is denied.'));
        return $user;
    }
    public static function administrator(): array {
        $user = self::require();
        if ($user['role'] !== 'admin') throw new HttpError(403, t('Только для администратора.', 'Administrators only.'));
        return $user;
    }
    public static function login(string $login, string $password): bool {
        if (($_SESSION['login_blocked_until'] ?? 0) > time()) throw new HttpError(429, t('Слишком много попыток. Подождите минуту.', 'Too many attempts. Wait one minute.'));
        $user = DB::one('SELECT * FROM users WHERE login=? AND active=1', [$login]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= 5) { $_SESSION['login_blocked_until'] = time() + 60; $_SESSION['login_failures'] = 0; }
            return false;
        }
        session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id'];
        unset($_SESSION['login_failures'], $_SESSION['login_blocked_until']);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return true;
    }
}

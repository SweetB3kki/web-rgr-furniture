<?php
declare(strict_types=1);
class DB {
    private static ?PDO $pdo = null;
    public static function get(): PDO {
        return self::$pdo ??= new PDO(config('db_dsn'), config('db_user'), config('db_password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    public static function all(string $sql, array $args = []): array { $q = self::get()->prepare($sql); $q->execute($args); return $q->fetchAll(); }
    public static function one(string $sql, array $args = []): ?array { return self::all($sql, $args)[0] ?? null; }
    public static function run(string $sql, array $args = []): PDOStatement { $q = self::get()->prepare($sql); $q->execute($args); return $q; }
}

<?php
declare(strict_types=1);
class DB {
    private static ?PDO $pdo = null;
    public static function get(): PDO {
        return self::$pdo ??= self::connect();
    }
    public static function connect(): PDO {
        $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>8];
        if ($certificate=config('db_ssl_ca')) {
            if (!is_file($certificate)) throw new RuntimeException('Database TLS certificate bundle is missing.');
            $options[PDO::MYSQL_ATTR_SSL_CA]=$certificate;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
        }
        return new PDO(config('db_dsn'),config('db_user'),config('db_password'),$options);
    }
    public static function all(string $sql, array $args = []): array { $q = self::get()->prepare($sql); $q->execute($args); return $q->fetchAll(); }
    public static function one(string $sql, array $args = []): ?array { return self::all($sql, $args)[0] ?? null; }
    public static function run(string $sql, array $args = []): PDOStatement { $q = self::get()->prepare($sql); $q->execute($args); return $q; }
}

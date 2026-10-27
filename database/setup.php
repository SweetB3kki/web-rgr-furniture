<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if(!in_array('--skip-create',$argv,true)) {
    if(!preg_match('/dbname=([a-zA-Z0-9_]+)/',config('db_dsn'),$match))throw new RuntimeException('Database name missing in DSN.');
    $pdo=new PDO(preg_replace('/dbname=[^;]+;?/','',config('db_dsn')),config('db_user'),config('db_password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$match[1].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
DB::get()->exec(file_get_contents(__DIR__.'/schema.sql'));
if(!DB::one('SELECT id FROM users LIMIT 1')) {
    $password=getenv('RGR_ADMIN_PASSWORD')?:('Forma!'.bin2hex(random_bytes(6)));
    DB::run("INSERT INTO users(login,password_hash,role,active) VALUES ('admin',?,'admin',1)",[password_hash($password,PASSWORD_DEFAULT)]);
    if(!is_dir(ROOT.'/storage'))mkdir(ROOT.'/storage',0700,true);
    file_put_contents(ROOT.'/storage/first-login.txt',"Local administration: http://127.0.0.1:8020/admin\nLogin: admin\nPassword: ".$password."\nChange for deployment via tools/password.php\n");
    echo "Administrator created. Credentials: storage/first-login.txt\n";
}
if(!DB::one('SELECT id FROM furniture_types LIMIT 1')) require __DIR__.'/seed.php';
echo "Database ready. Existing records preserved.\n";

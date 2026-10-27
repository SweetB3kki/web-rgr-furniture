<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';
$login=$argv[1]??'';
if(!$login||!DB::one('SELECT id FROM users WHERE login=?',[$login]))exit("Usage: php tools/password.php LOGIN\n");
$password=bin2hex(random_bytes(10)).'!';
DB::run('UPDATE users SET password_hash=? WHERE login=?',[password_hash($password,PASSWORD_DEFAULT),$login]);
echo "New password for {$login}: {$password}\n";

<?php
declare(strict_types=1);
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
$root=realpath(__DIR__.'/public');$file=realpath($root.$path);
if($file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)&&preg_match('~^/(assets/|uploads/|documents/)~',$path)&&!preg_match('/\.(php|phtml|phar|htaccess)$/i',$file)) {
    $types=['css'=>'text/css','js'=>'application/javascript','json'=>'application/json','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','svg'=>'image/svg+xml','woff2'=>'font/woff2','txt'=>'text/plain','html'=>'text/html','pdf'=>'application/pdf'];
    $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));header('Content-Type: '.($types[$ext]??'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=86400');readfile($file);return true;
}
require __DIR__.'/public/index.php';

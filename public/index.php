<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
$method=$_SERVER['REQUEST_METHOD'];$site=new SiteController();$admin=new AdminController();
if($path==='/favicon.ico'){http_response_code(204);exit;}
if(str_starts_with($path,'/admin'))header('Cache-Control: no-store');
$routes=[
 ['GET','~^/uploads/([a-f0-9]{32}\.(?:jpg|png|webp))$~',fn($filename)=>Upload::serve($filename)],
 ['GET','~^/$~',fn()=>$site->home()],
 ['GET','~^/language$~',fn()=>$site->language()],
 ['GET','~^/catalog$~',fn()=>$site->catalog()],
 ['GET','~^/catalog/type/(\d+)$~',fn($id)=>$site->type((int)$id)],
 ['GET','~^/catalog/product/(\d+)$~',fn($id)=>$site->product((int)$id)],
 ['GET','~^/page/([a-z0-9-]+)$~',fn($slug)=>$site->page($slug)],
 ['GET','~^/news$~',fn()=>$site->articles('news')],
 ['GET','~^/promotions$~',fn()=>$site->articles('promotion')],
 ['GET','~^/article/(\d+)$~',fn($id)=>$site->article((int)$id)],
 ['GET','~^/designers$~',fn()=>$site->designers()],
 ['GET','~^/contacts$~',fn()=>$site->contacts()],
 ['GET','~^/directions$~',fn()=>$site->directions()],
 ['GET','~^/sitemap$~',fn()=>$site->sitemap()],
 ['GET|POST','~^/order$~',fn()=>$site->order()],
 ['POST','~^/order/quote$~',fn()=>$site->quote()],
 ['GET','~^/order/success/([a-f0-9]{32})$~',fn($ref)=>$site->success($ref)],
 ['GET|POST','~^/admin/login$~',fn()=>$admin->login()],
 ['POST','~^/admin/logout$~',fn()=>$admin->logout()],
 ['GET','~^/admin$~',fn()=>$admin->index()],
 ['GET','~^/admin/content/([a-z]+)$~',fn($key)=>$admin->content($key)],
 ['GET|POST','~^/admin/content/([a-z]+)/create$~',fn($key)=>$admin->edit($key)],
 ['GET|POST','~^/admin/content/([a-z]+)/edit/(\d+)$~',fn($key,$id)=>$admin->edit($key,(int)$id)],
 ['POST','~^/admin/content/([a-z]+)/delete/(\d+)$~',fn($key,$id)=>$admin->delete($key,(int)$id)],
 ['POST','~^/admin/gallery/([a-z]+)/(\d+)/(\d+)/delete$~',fn($key,$id,$photo)=>$admin->galleryDelete($key,(int)$id,(int)$photo)],
 ['POST','~^/admin/upload$~',fn()=>$admin->upload()],
 ['GET|POST','~^/admin/contacts$~',fn()=>$admin->contacts()],
 ['GET','~^/admin/orders$~',fn()=>$admin->orders()],
 ['POST','~^/admin/orders/(\d+)$~',fn($id)=>$admin->orderUpdate((int)$id)],
 ['GET|POST','~^/admin/managers$~',fn()=>$admin->managers()],
];
try {
    $pathMatched=false;
    foreach($routes as [$methods,$pattern,$handler]) {
        if(!preg_match($pattern,$path,$matches))continue;
        $pathMatched=true;
        if(!in_array($method,explode('|',$methods),true))continue;
        array_shift($matches);$handler(...$matches);exit;
    }
    throw new HttpError($pathMatched?405:404,$pathMatched?t('Метод не разрешён.','Method not allowed.'):t('Страница не найдена.','Page not found.'));
}catch(Throwable $e){
    $status=$e instanceof HttpError?$e->status:500;http_response_code($status);
    if($status===500)error_log((string)$e);
    $message=$status===500?t('Не удалось загрузить страницу. Попробуйте ещё раз немного позже.','We could not load this page. Please try again shortly.'):$e->getMessage();
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')||$path==='/order/quote'||$path==='/admin/upload') {header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);}
    elseif($status===500)echo '<!doctype html><html lang="'.h(lang()).'"><meta charset="utf-8"><title>500</title><h1>500</h1><p>'.h($message).'</p></html>';
    else View::show('error',['pageTitle'=>(string)$status,'message'=>$message,'status'=>$status]);
}

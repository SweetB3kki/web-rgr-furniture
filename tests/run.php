<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$_SESSION=[];
$base='http://127.0.0.1:8020';$results=[];$cookies=[];
function check(string $name,bool $pass):void { global $results;$results[]=['test'=>$name,'passed'=>$pass];echo ($pass?'PASS ':'FAIL ').$name."\n"; }
function request(string $path,?array $post=null,string $session='public',array $files=[]):array {
    global $base,$cookies;
    $cookies[$session]??=tempnam(sys_get_temp_dir(),'rgr-cookie-');
    $ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$cookies[$session],CURLOPT_COOKIEFILE=>$cookies[$session],CURLOPT_TIMEOUT=>15]);
    if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$files?array_merge($post,$files):http_build_query($post)]);
    $response=curl_exec($ch);if($response===false)throw new RuntimeException(curl_error($ch));
    $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    return ['status'=>$status,'headers'=>substr($response,0,$size),'body'=>substr($response,$size)];
}
function token(array $response,string $name='csrf'):string { if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$response['body'],$m))throw new RuntimeException('Token not found: '.$name);return html_entity_decode($m[1]); }
function location(array $r):string { preg_match('/^Location:\s*(.+)$/mi',$r['headers'],$m);return trim($m[1]??''); }
$nonce='qa-'.bin2hex(random_bytes(4));$newArticle=null;$managerId=null;$upload=null;
try {
    foreach(['/','/catalog','/catalog/type/1','/catalog/type/1?subtype=1','/catalog/product/1','/page/about','/page/documents','/news','/promotions','/designers','/contacts','/directions','/sitemap','/order'] as $path){$r=request($path);check('GET '.$path,$r['status']===200&&!preg_match('~(?:<b>)?(?:Warning|Notice|Deprecated|Fatal error)(?:</b>)?:~',$r['body'])&&!str_contains($r['body'],'Undefined array key'));}
    check('Private configuration not exposed',request('/config/app.php')['status']===404);
    check('Local secrets not exposed',request('/config/local.php')['status']===404);
    check('Contacts have no map',!str_contains(request('/contacts')['body'],'<iframe'));
    $directions=request('/directions')['body'];
    check('Directions have map and route choices',str_contains($directions,'<iframe')&&str_contains($directions,'rtt=auto')&&str_contains($directions,'rtt=mt')&&str_contains($directions,'rtt=pd'));
    foreach(require ROOT.'/config/documents.php' as $document) {
        $pdf=request('/documents/'.$document['file']);
        check('PDF '.$document['file'],$pdf['status']===200&&str_starts_with($pdf['body'],'%PDF-')&&str_contains($pdf['headers'],'application/pdf'));
    }
    check('Database setup not exposed',request('/database/setup.php')['status']===404);
    check('Unknown product is 404',request('/catalog/product/999999')['status']===404);
    request('/language?value=en');$r=request('/');check('English public interface',$r['status']===200&&str_contains($r['body'],'Home takes')&&!str_contains($r['body'],'Дом начинается'));request('/language?value=ru');
    $form=request('/order');$csrf=token($form);$key=token($form,'submission_key');
    check('CSRF protects quote',request('/order/quote',['qty'=>[1=>1]])['status']===419);
    $r=request('/order/quote',['csrf'=>$csrf,'qty'=>[1=>2,3=>1],'delivery'=>1,'price'=>1]);$quote=json_decode($r['body'],true);
    check('Server price, mixed types and delivery',$r['status']===200&&$quote['total']===22550000);
    foreach(['-1','1.5','21','99999999999'] as $bad)check('Invalid quantity '.$bad,request('/order/quote',['csrf'=>$csrf,'qty'=>[1=>$bad]])['status']===422);
    check('Unknown product rejected',request('/order/quote',['csrf'=>$csrf,'qty'=>[999999=>1]])['status']===422);
    check('Empty order rejected',request('/order/quote',['csrf'=>$csrf,'qty'=>[1=>0]])['status']===422);
    $count=(int)DB::one('SELECT COUNT(*) AS n FROM orders')['n'];
    $r=request('/order',['csrf'=>$csrf,'submission_key'=>$key,'name'=>'a','email'=>'bad','phone'=>'x','qty'=>[1=>1]]);
    check('Invalid customer does not create order',$r['status']===200&&(int)DB::one('SELECT COUNT(*) AS n FROM orders')['n']===$count);
    $payload=['csrf'=>$csrf,'submission_key'=>$key,'name'=>'Тестовый заказ РГР','email'=>'buyer@example.test','phone'=>'+7 900 111-22-33','address'=>'Учебный адрес, 1','comment'=>'Автоматическая проверка. Не настоящий заказ.','consent'=>'1','delivery'=>'1','qty'=>[1=>2,3=>1],'total_minor'=>1];
    $r=request('/order',$payload);$success=location($r);check('Order saved with redirect',$r['status']===303&&str_starts_with($success,'/order/success/'));
    $order=DB::one('SELECT * FROM orders WHERE submission_key=?',[$key]);
    check('Order total persisted from catalog',$order&&(int)$order['total_minor']===22550000);
    check('Two item snapshots stored',count(DB::all('SELECT * FROM order_items WHERE order_id=?',[$order['id']]))===2);
    check('Confirmation accessible',request($success)['status']===200);
    $again=request('/order',$payload);check('Double submission is idempotent',$again['status']===303&&location($again)===$success&&(int)DB::one('SELECT COUNT(*) AS n FROM orders')['n']===$count+1);
    check('SMTP notification accepted by local server',DB::one('SELECT status FROM mail_outbox WHERE order_id=?',[$order['id']])['status']==='sent');
    $savedPort=$config['smtp_port'];$config['smtp_port']=1;
    DB::run("UPDATE mail_outbox SET status='pending' WHERE order_id=?",[$order['id']]);
    check('SMTP failure preserves order',!Mailer::sendOrder((int)$order['id']) && (bool)DB::one('SELECT id FROM orders WHERE id=?',[$order['id']]) && DB::one('SELECT status FROM mail_outbox WHERE order_id=?',[$order['id']])['status']==='failed');
    $config['smtp_port']=$savedPort;
    check('Failed notification can be retried',Mailer::sendOrder((int)$order['id']) && DB::one('SELECT status FROM mail_outbox WHERE order_id=?',[$order['id']])['status']==='sent');
    check('Unauthenticated admin redirected',request('/admin/content/products')['status']===303);
    $login=request('/admin/login',null,'admin');$adminCsrf=token($login);
    check('Wrong admin password rejected',request('/admin/login',['csrf'=>$adminCsrf,'login'=>'admin','password'=>'wrong'],'admin')['status']===200);
    preg_match('/Password: (.+)/',file_get_contents(ROOT.'/storage/first-login.txt'),$m);
    $r=request('/admin/login',['csrf'=>$adminCsrf,'login'=>'admin','password'=>trim($m[1])],'admin');check('Admin login',$r['status']===303&&location($r)==='/admin');
    $adminCsrf=token(request('/admin',null,'admin'));
    check('Admin sees all sections',request('/admin/content/products',null,'admin')['status']===200);
    check('Delete is POST-only',request('/admin/content/news/delete/1',null,'admin')['status']===405);
    $body='<p onclick="alert(1)">Текст проверки</p><script>alert(2)</script><a href="javascript:alert(3)">link</a>';
    $r=request('/admin/content/news/create',['csrf'=>$adminCsrf,'title_ru'=>'Проверка '.$nonce,'title_en'=>'Check '.$nonce,'body_ru'=>$body,'body_en'=>'<p>English QA text</p>','slug'=>$nonce,'publish_date'=>date('Y-m-d'),'sort_order'=>0,'published'=>1],'admin');
    $article=DB::one('SELECT * FROM articles WHERE slug=?',[$nonce]);$newArticle=$article['id']??null;
    check('Admin content create',$r['status']===303&&$article);
    check('Rich HTML is sanitized',$article&&!str_contains($article['body_ru'],'script')&&!str_contains($article['body_ru'],'onclick')&&!str_contains($article['body_ru'],'javascript:'));
    check('Published content visible',str_contains(request('/news')['body'],'Проверка '.$nonce));
    $edit=['csrf'=>$adminCsrf,'title_ru'=>'Проверка обновлена','title_en'=>'Updated check','body_ru'=>'<p>Обновлённый текст</p>','body_en'=>'<p>Updated text</p>','slug'=>$nonce,'publish_date'=>date('Y-m-d'),'sort_order'=>0];
    check('Admin content update',request('/admin/content/news/edit/'.$newArticle,$edit,'admin')['status']===303);
    check('Unpublished content is hidden',request('/article/'.$newArticle)['status']===404);
    $r=request('/admin/upload?section=news',['csrf'=>$adminCsrf],'admin',['file'=>new CURLFile(ROOT.'/public/assets/images/sofa.jpg','image/jpeg','sofa.jpg')]);$uploaded=json_decode($r['body'],true);$upload=$uploaded['location']??null;
    check('Valid inline image uploaded',$r['status']===200&&$upload&&is_file(ROOT.'/public'.$upload));
    $r=request('/admin/upload?section=news',['csrf'=>$adminCsrf],'admin',['file'=>new CURLFile(__FILE__,'image/jpeg','fake.jpg')]);check('Fake image rejected',$r['status']===422);
    $managerLogin='qa_'.bin2hex(random_bytes(4));$managerPassword='Check!'.bin2hex(random_bytes(6));
    $r=request('/admin/managers',['csrf'=>$adminCsrf,'login'=>$managerLogin,'password'=>$managerPassword,'active'=>'1','permissions'=>['news'],'role'=>'admin'],'admin');$managerId=DB::one('SELECT id FROM users WHERE login=?',[$managerLogin])['id']??null;
    check('Manager created with selected permissions',$r['status']===303&&$managerId);
    $managerCsrf=token(request('/admin/login',null,'manager'));
    request('/admin/login',['csrf'=>$managerCsrf,'login'=>$managerLogin,'password'=>$managerPassword],'manager');
    $managerHome=request('/admin',null,'manager');$managerCsrf=token($managerHome);
    check('Manager navigation hides forbidden sections',!str_contains($managerHome['body'],'href="/admin/content/products"'));
    check('Manager allowed section',request('/admin/content/news',null,'manager')['status']===200);
    check('Manager forbidden GET',request('/admin/content/products',null,'manager')['status']===403);
    check('Manager forbidden POST',request('/admin/content/products/delete/1',['csrf'=>$managerCsrf],'manager')['status']===403);
    check('Manager cannot manage accounts',request('/admin/managers',null,'manager')['status']===403);
    DB::run('UPDATE users SET active=0 WHERE id=?',[$managerId]);check('Disabling account invalidates access',request('/admin/content/news',null,'manager')['status']===303);
    $safe=Html::clean('<p>Keep <b>bold</b></p><img src="//evil.test/a.jpg" onerror="x"><iframe src="https://evil.test"></iframe>');
    check('Sanitizer preserves safe markup and removes embeds',str_contains($safe,'<b>bold</b>')&&!str_contains($safe,'iframe')&&!str_contains($safe,'<img'));
    check('Parent with children cannot be deleted',request('/admin/content/types/delete/1',['csrf'=>$adminCsrf],'admin')['status']===303&&(bool)DB::one('SELECT id FROM furniture_types WHERE id=1'));
    $home=DB::one("SELECT id FROM articles WHERE kind='page' AND slug='home'");
    check('System home page cannot be deleted',request('/admin/content/pages/delete/'.$home['id'],['csrf'=>$adminCsrf],'admin')['status']===303 && request('/')['status']===200);
}finally {
    if($newArticle)DB::run('DELETE FROM articles WHERE id=?',[$newArticle]);
    if($managerId)DB::run('DELETE FROM users WHERE id=?',[$managerId]);
    if($upload&&str_starts_with($upload,'/uploads/'))@unlink(ROOT.'/public'.$upload);
    foreach($cookies as $file)@unlink($file);
}
$report=['date'=>date(DATE_ATOM),'passed'=>count(array_filter($results,fn($x)=>$x['passed'])),'total'=>count($results),'tests'=>$results];
if(!is_dir(ROOT.'/reports'))mkdir(ROOT.'/reports',0775,true);
file_put_contents(ROOT.'/reports/functional-tests.json',json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "Result: {$report['passed']}/{$report['total']}\n";
exit($report['passed']===$report['total']?0:1);

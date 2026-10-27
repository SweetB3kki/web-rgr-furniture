<?php
declare(strict_types=1);
class SiteController {
    public function home():void { View::show('home',['pageTitle'=>t('Мебель с характером','Furniture with character'),'products'=>array_slice(Catalog::products(),0,4),'types'=>Catalog::types(),'intro'=>Catalog::page('home'),'news'=>array_slice(Catalog::articles('news'),0,2)]); }
    public function page(string $slug):void { $page=Catalog::page($slug);View::show($slug==='documents'?'documents':'page',['pageTitle'=>tr($page),'page'=>$page]); }
    public function catalog():void { View::show('catalog',['pageTitle'=>t('Каталог мебели','Furniture catalog'),'types'=>Catalog::types(),'products'=>Catalog::products()]); }
    public function type(int $id):void {
        $type=Catalog::type($id);
        $subtype=filter_input(INPUT_GET,'subtype',FILTER_VALIDATE_INT)?:null;
        $subtypes=Catalog::subtypes($id);
        if($subtype&&!in_array($subtype,array_column($subtypes,'id')))throw new HttpError(404,'Подтип не найден.');
        View::show('type',['pageTitle'=>tr($type),'type'=>$type,'subtypes'=>$subtypes,'selectedSubtype'=>$subtype,'products'=>Catalog::products($id,$subtype),'images'=>Catalog::images('types',$id)]);
    }
    public function product(int $id):void { $product=Catalog::product($id);View::show('product',['pageTitle'=>tr($product),'product'=>$product,'images'=>Catalog::images('products',$id)]); }
    public function articles(string $kind):void { View::show('articles',['pageTitle'=>$kind==='news'?t('Новости','News'):t('Акции','Offers'),'articles'=>Catalog::articles($kind)]); }
    public function article(int $id):void { $article=Catalog::article($id);View::show('page',['pageTitle'=>tr($article),'page'=>$article]); }
    public function designers():void { View::show('designers',['pageTitle'=>t('Дизайнеры','Designers'),'designers'=>Catalog::designers()]); }
    public function contacts():void { View::show('contacts',['pageTitle'=>t('Контакты','Contacts'),'contact'=>Catalog::contacts()]); }
    public function directions():void { View::show('directions',['pageTitle'=>t('Схема проезда','Directions'),'contact'=>Catalog::contacts()]); }
    public function sitemap():void { View::show('sitemap',['pageTitle'=>t('Карта сайта','Sitemap'),'types'=>Catalog::types(),'pages'=>Catalog::articles('page')]); }
    public function language():void {
        if(in_array($_GET['value']??'', ['ru','en'],true))$_SESSION['lang']=$_GET['value'];
        $return=$_GET['return']??'/';
        redirect(is_string($return)&&preg_match('~^/(?!/)[a-zA-Z0-9/?=&_-]*$~D',$return)?$return:'/');
    }
    public function order():void {
        $errors=[];$old=['name'=>'','email'=>'','phone'=>'','address'=>'','comment'=>''];
        if($_SERVER['REQUEST_METHOD']==='POST') {
            verifyCsrf();$old=array_replace($old,$_POST);
            $nonce=$_POST['submission_key']??'';
            if(!is_string($nonce)||!isset($_SESSION['order_tokens'][$nonce]))throw new HttpError(419,t('Обновите форму заказа.','Reload the order form.'));
            if(is_string($_SESSION['order_tokens'][$nonce]))redirect('/order/success/'.$_SESSION['order_tokens'][$nonce]);
            $errors=Order::validate($old);
            if(!is_array($old['qty']??null))$errors[]=t('Выберите товары.','Select furniture.');
            if(!$errors) {
                try { $order=Order::create($old,$nonce);$_SESSION['order_tokens'][$nonce]=$order['reference'];redirect('/order/success/'.$order['reference']); }
                catch(InvalidArgumentException $e){$errors[]=$e->getMessage();}
            }
        }
        $nonce=bin2hex(random_bytes(16));$_SESSION['order_tokens'][$nonce]=true;
        if(count($_SESSION['order_tokens'])>15)array_shift($_SESSION['order_tokens']);
        View::show('order',['pageTitle'=>t('Оформить заказ','Place an order'),'products'=>Catalog::products(),'errors'=>$errors,'old'=>$old,'nonce'=>$nonce]);
    }
    public function quote():void {
        verifyCsrf();header('Content-Type: application/json; charset=utf-8');
        try { $qty=$_POST['qty']??[];if(!is_array($qty))throw new InvalidArgumentException('Неверный список товаров.');$quote=Order::quote($qty,!empty($_POST['delivery']));echo json_encode(['subtotal'=>$quote['subtotal'],'delivery'=>$quote['delivery'],'total'=>$quote['total'],'formatted'=>money($quote['total'])]); }
        catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    }
    public function success(string $reference):void {
        $order=DB::one('SELECT id,total_minor FROM orders WHERE reference=?',[$reference])??throw new HttpError(404,'Заказ не найден.');
        View::show('success',['pageTitle'=>t('Заказ принят','Order received'),'order'=>$order]);
    }
}

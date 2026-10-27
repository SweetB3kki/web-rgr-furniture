<?php
declare(strict_types=1);
class AdminController {
    public function login():void {
        $error='';
        if($_SERVER['REQUEST_METHOD']==='POST') {
            verifyCsrf();
            if(is_string($_POST['login']??null)&&is_string($_POST['password']??null)&&Auth::login($_POST['login'],$_POST['password']))redirect('/admin');
            $error=t('Неверный логин или пароль.','Invalid login or password.');
        }
        View::show('admin_login',['pageTitle'=>t('Вход в управление','Administration login'),'error'=>$error],true);
    }
    public function logout():void { verifyCsrf();unset($_SESSION['user_id']);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));redirect('/'); }
    public function index():void { Auth::require();View::show('admin_home',['pageTitle'=>t('Управление сайтом','Site administration')],true); }
    public function content(string $key):void {
        Auth::require($key);$meta=Content::section($key);
        View::show('admin_list',['pageTitle'=>t($meta['name'],$meta['en']),'key'=>$key,'rows'=>Content::all($key)],true);
    }
    public function edit(string $key,?int $id=null):void {
        Auth::require($key);$meta=Content::section($key);$row=$id?Content::find($key,$id):[];$error='';
        if($_SERVER['REQUEST_METHOD']==='POST') {
            verifyCsrf();
            try { Content::save($key,$id,$_POST,$_FILES);flash(t('Изменения сохранены.','Changes saved.'));redirect('/admin/content/'.$key); }
            catch(InvalidArgumentException $e){$error=$e->getMessage();$row=array_merge($row,$_POST);}
            catch(PDOException $e){if($e->getCode()!=='23000')throw $e;$error=t('Такой адрес страницы уже существует или нарушена связь записей.','Duplicate page address or invalid record relationship.');$row=array_merge($row,$_POST);}
        }
        View::show('admin_edit',['pageTitle'=>t($meta['name'],$meta['en']),'key'=>$key,'row'=>$row,'id'=>$id,'error'=>$error,'meta'=>$meta],true);
    }
    public function delete(string $key,int $id):void {
        Auth::require($key);verifyCsrf();
        try { Content::delete($key,$id);flash(t('Запись удалена.','Record deleted.')); }
        catch(InvalidArgumentException $e){flash($e->getMessage());}
        catch(PDOException $e){if($e->getCode()!=='23000')throw $e;flash(t('Сначала удалите или перенесите дочерние записи.','Remove or move child records first.'));}
        redirect('/admin/content/'.$key);
    }
    public function upload():void {
        $section=$_GET['section']??'';if(!is_string($section))throw new HttpError(400,'Неверный раздел.');Content::section($section);Auth::require($section);verifyCsrf();
        header('Content-Type: application/json; charset=utf-8');
        try { echo json_encode(['location'=>Upload::image($_FILES['file']??[])],JSON_UNESCAPED_SLASHES); }
        catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    }
    public function galleryDelete(string $key,int $id,int $photo):void {
        Auth::require($key);verifyCsrf();Content::find($key,$id);
        DB::run('DELETE FROM gallery WHERE id=? AND entity=? AND entity_id=?',[$photo,$key,$id]);redirect('/admin/content/'.$key.'/edit/'.$id);
    }
    public function contacts():void {
        Auth::require('contacts');$row=Catalog::contacts();$error='';
        if($_SERVER['REQUEST_METHOD']==='POST') {
            verifyCsrf();
            try {
                $fields=['company_ru','company_en','address_ru','address_en','phone','email','latitude','longitude','hours_ru','hours_en'];$data=[];
                foreach($fields as $f){$v=$_POST[$f]??'';if(!is_string($v)||trim($v)===''||mb_strlen($v)>180)throw new InvalidArgumentException('Все поля обязательны, не больше 180 символов.');$data[$f]=trim($v);}
                if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Неверный email.');
                if(!preg_match('/^\+?[0-9 ()-]{7,25}$/D',$data['phone']))throw new InvalidArgumentException('Неверный телефон.');
                if(!is_numeric($data['latitude'])||abs((float)$data['latitude'])>90||!is_numeric($data['longitude'])||abs((float)$data['longitude'])>180)throw new InvalidArgumentException('Неверные координаты.');
                DB::run('UPDATE contacts SET '.implode(',',array_map(fn($f)=>$f.'=?',$fields)).' WHERE id=1',array_values($data));flash('Контакты сохранены.');redirect('/admin/contacts');
            }catch(InvalidArgumentException $e){$error=$e->getMessage();$row=array_merge($row,$_POST);}
        }
        View::show('admin_contacts',['pageTitle'=>t('Контакты','Contacts'),'row'=>$row,'error'=>$error],true);
    }
    public function orders():void {
        Auth::require('orders');$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);$order=$id?DB::one('SELECT o.*,m.status AS mail_status,m.last_error FROM orders o JOIN mail_outbox m ON m.order_id=o.id WHERE o.id=?',[$id]):null;
        if($id&&!$order)throw new HttpError(404,'Заказ не найден.');
        View::show('admin_orders',['pageTitle'=>t('Заказы','Orders'),'orders'=>DB::all('SELECT o.*,m.status AS mail_status FROM orders o JOIN mail_outbox m ON m.order_id=o.id ORDER BY o.id DESC'),'order'=>$order,'items'=>$order?DB::all('SELECT * FROM order_items WHERE order_id=?',[$id]):[]],true);
    }
    public function orderUpdate(int $id):void {
        Auth::require('orders');verifyCsrf();
        if(!DB::one('SELECT id FROM orders WHERE id=?',[$id]))throw new HttpError(404,'Заказ не найден.');
        if(($_POST['action']??'')==='retry') {Mailer::sendOrder($id);flash(t('Проверен результат отправки уведомления.','Notification delivery was checked.'));}
        else {$status=$_POST['status']??'';if(!in_array($status,['new','confirmed','completed','cancelled'],true))throw new HttpError(422,'Неверный статус.');DB::run('UPDATE orders SET status=? WHERE id=?',[$status,$id]);flash(t('Статус сохранён.','Status saved.'));}
        redirect('/admin/orders?id='.$id);
    }
    public function managers():void {
        $actor=Auth::administrator();$error='';
        $sections=array_merge(Content::sections(),['contacts'=>['name'=>'Контакты','en'=>'Contacts'],'orders'=>['name'=>'Заказы','en'=>'Orders']]);
        if($_SERVER['REQUEST_METHOD']==='POST') {
            verifyCsrf();
            try {
                $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:null;
                $existing=$id?DB::one('SELECT * FROM users WHERE id=?',[$id]):null;
                if($id&&!$existing)throw new HttpError(404,'Пользователь не найден.');
                if($existing&&$existing['role']==='admin')throw new InvalidArgumentException('Администратора через форму менеджеров изменять нельзя.');
                $login=$_POST['login']??'';$password=$_POST['password']??'';
                if(!is_string($login)||!preg_match('/^[a-zA-Z0-9_-]{3,60}$/D',$login))throw new InvalidArgumentException('Логин: от 3 до 60 латинских букв, цифр, дефисов.');
                if(!is_string($password)||(!$id&&strlen($password)<8)||($password!==''&&(strlen($password)<8||strlen($password)>100)))throw new InvalidArgumentException('Пароль: от 8 до 100 символов.');
                $permissions=$_POST['permissions']??[];
                if(!is_array($permissions)||array_diff($permissions,array_keys($sections)))throw new InvalidArgumentException('Некорректные права.');
                $db=DB::get();$db->beginTransaction();
                if($id){DB::run('UPDATE users SET login=?,active=? WHERE id=?',[$login,isset($_POST['active'])?1:0,$id]);if($password!=='')DB::run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);}
                else{DB::run("INSERT INTO users(login,password_hash,role,active) VALUES (?,?,'manager',?)",[$login,password_hash($password,PASSWORD_DEFAULT),isset($_POST['active'])?1:0]);$id=(int)$db->lastInsertId();}
                DB::run('DELETE FROM user_permissions WHERE user_id=?',[$id]);
                foreach(array_unique($permissions) as $permission)DB::run('INSERT INTO user_permissions(user_id,section) VALUES (?,?)',[$id,$permission]);
                $db->commit();flash(t('Менеджер и права сохранены.','Manager and permissions saved.'));redirect('/admin/managers');
            }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            catch(PDOException $e){if(DB::get()->inTransaction())DB::get()->rollBack();if($e->getCode()!=='23000')throw $e;$error='Логин уже занят.';}
        }
        $edit=filter_input(INPUT_GET,'edit',FILTER_VALIDATE_INT);$row=$edit?DB::one("SELECT id,login,active FROM users WHERE id=? AND role='manager'",[$edit]):null;
        if($edit&&!$row)throw new HttpError(404,'Менеджер не найден.');
        View::show('admin_managers',['pageTitle'=>t('Менеджеры контента','Content managers'),'sections'=>$sections,'users'=>DB::all('SELECT id,login,role,active FROM users ORDER BY id'),'row'=>$row,'permissions'=>$row?array_column(DB::all('SELECT section FROM user_permissions WHERE user_id=?',[$edit]),'section'):[],'error'=>$error],true);
    }
}

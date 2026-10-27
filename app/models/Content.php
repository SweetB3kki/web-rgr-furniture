<?php
declare(strict_types=1);
class Content {
    public static function sections(): array {
        return [
            'news'=>['table'=>'articles','kind'=>'news','name'=>'Новости','en'=>'News'],
            'promotions'=>['table'=>'articles','kind'=>'promotion','name'=>'Акции','en'=>'Offers'],
            'types'=>['table'=>'furniture_types','name'=>'Типы мебели','en'=>'Furniture types'],
            'subtypes'=>['table'=>'furniture_subtypes','name'=>'Подтипы мебели','en'=>'Subtypes'],
            'products'=>['table'=>'products','name'=>'Мебель','en'=>'Furniture'],
            'designers'=>['table'=>'designers','name'=>'Дизайнеры','en'=>'Designers'],
            'pages'=>['table'=>'articles','kind'=>'page','name'=>'Страницы и документы','en'=>'Pages and documents'],
        ];
    }
    public static function section(string $key): array {
        return self::sections()[$key] ?? throw new HttpError(404,'Раздел не найден.');
    }
    public static function all(string $key): array {
        $meta=self::section($key);
        return DB::all('SELECT * FROM ' . $meta['table'] . (isset($meta['kind']) ? ' WHERE kind=?' : '') . ' ORDER BY id DESC', isset($meta['kind']) ? [$meta['kind']] : []);
    }
    public static function find(string $key,int $id): array {
        $meta=self::section($key);
        return DB::one('SELECT * FROM '.$meta['table'].' WHERE id=?'.(isset($meta['kind'])?' AND kind=?':''),isset($meta['kind'])?[$id,$meta['kind']]:[$id]) ?? throw new HttpError(404,'Запись не найдена.');
    }
    public static function save(string $key, ?int $id, array $input, array $files): int {
        $meta=self::section($key); $old=$id?self::find($key,$id):[]; $data=[];
        foreach (['title_ru','title_en','body_ru','body_en'] as $field) {
            if (!is_string($input[$field]??null)) throw new InvalidArgumentException('Заполните заголовки и тексты на двух языках.');
            $value=trim($input[$field]);
            if (str_starts_with($field,'body')) {
                if (strlen($value)>200000) throw new InvalidArgumentException('Текст слишком длинный.');
                $value=Html::clean($value);
                if (trim(strip_tags($value))==='' && !str_contains($value,'<img')) throw new InvalidArgumentException('Текст не должен быть пустым.');
            } elseif ($value==='' || mb_strlen($value)>180) throw new InvalidArgumentException('Заголовок: от 1 до 180 символов.');
            $data[$field]=$value;
        }
        $data['sort_order']=filter_var($input['sort_order']??0,FILTER_VALIDATE_INT);
        if ($data['sort_order']===false || abs($data['sort_order'])>10000) throw new InvalidArgumentException('Порядок: целое число от -10000 до 10000.');
        $data['published']=isset($input['published'])?1:0;
        $data['image']=$old['image']??'';
        if (($files['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $data['image']=Upload::image($files['image']);
        if (isset($meta['kind'])) {
            $data['kind']=$meta['kind'];
            $slug=$input['slug']??'';
            if (!is_string($slug)||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)||strlen($slug)>120) throw new InvalidArgumentException('Адрес страницы: латинские буквы, цифры и дефисы.');
            $data['slug']=$slug;
            if ($key==='pages' && in_array($old['slug']??'',['home','about','documents'],true) && ($slug!==$old['slug'] || !$data['published'])) throw new InvalidArgumentException('Системная страница должна оставаться опубликованной с прежним адресом.');
            $date=$input['publish_date']??'';
            $parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
            if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Неверная дата.');
            $data['publish_date']=$date;
        }
        foreach (['subtypes'=>['type_id','furniture_types'],'products'=>['subtype_id','furniture_subtypes']] as $section=>[$field,$table]) {
            if ($key!==$section) continue;
            $value=filter_var($input[$field]??null,FILTER_VALIDATE_INT);
            if (!$value||!DB::one('SELECT id FROM '.$table.' WHERE id=?',[$value])) throw new InvalidArgumentException('Выберите родительский раздел.');
            $data[$field]=$value;
        }
        if ($key==='products') {
            $price=$input['price']??'';
            if (!is_string($price)||!preg_match('/^\d{1,7}(?:[.,]\d{1,2})?$/D',$price)) throw new InvalidArgumentException('Цена: положительное число, не больше 9 999 999,99.');
            [$rub,$kop]=array_pad(explode('.',str_replace(',','.',$price)),2,'0');
            $data['price_minor']=(int)$rub*100+(int)str_pad($kop,2,'0');
            if ($data['price_minor']<=0) throw new InvalidArgumentException('Цена должна быть больше нуля.');
            $designer=$input['designer_id']??'';
            if ($designer!=='' && (!ctype_digit((string)$designer)||!DB::one('SELECT id FROM designers WHERE id=?',[$designer]))) throw new InvalidArgumentException('Дизайнер не найден.');
            $data['designer_id']=$designer===''?null:(int)$designer;
        }
        $db=DB::get(); $db->beginTransaction();
        try {
            if ($id) DB::run('UPDATE '.$meta['table'].' SET '.implode(',',array_map(fn($f)=>$f.'=?',array_keys($data))).' WHERE id=?',[...array_values($data),$id]);
            else { DB::run('INSERT INTO '.$meta['table'].' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data)); $id=(int)$db->lastInsertId(); }
            if (in_array($key,['types','products'],true)&&isset($files['gallery'])) {
                $gallery=$files['gallery'];
                if (count((array)$gallery['name'])>10) throw new InvalidArgumentException('Не больше 10 фото за раз.');
                foreach ((array)$gallery['name'] as $i=>$name) {
                    if ($gallery['error'][$i]===UPLOAD_ERR_NO_FILE) continue;
                    $file=['name'=>$name,'tmp_name'=>$gallery['tmp_name'][$i],'size'=>$gallery['size'][$i],'error'=>$gallery['error'][$i]];
                    DB::run('INSERT INTO gallery (entity,entity_id,path,sort_order) VALUES (?,?,?,?)',[$key,$id,Upload::image($file),$i]);
                }
            }
            $db->commit();return $id;
        } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
    }
    public static function delete(string $key,int $id): void {
        $row=self::find($key,$id);$meta=self::section($key);
        if($key==='pages' && in_array($row['slug'],['home','about','documents'],true))throw new InvalidArgumentException('Системную страницу можно редактировать, но нельзя удалить.');
        $db=DB::get();$db->beginTransaction();
        try {
            DB::run('DELETE FROM '.$meta['table'].' WHERE id=?',[$id]);
            if(in_array($key,['types','products'],true))DB::run('DELETE FROM gallery WHERE entity=? AND entity_id=?',[$key,$id]);
            $db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
}

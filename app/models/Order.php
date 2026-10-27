<?php
declare(strict_types=1);
class Order {
    public static function quote(array $quantities, bool $delivery): array {
        $items = []; $subtotal = 0;
        if (count($quantities) > 40) throw new InvalidArgumentException(t('Слишком много позиций.','Too many items.'));
        foreach ($quantities as $id => $raw) {
            if (!is_scalar($raw) || !preg_match('/^\d{1,2}$/D', (string)$raw)) throw new InvalidArgumentException(t('Количество должно быть целым числом от 0 до 20.','Quantity must be a whole number from 0 to 20.'));
            $qty = (int)$raw;
            if ($qty < 0 || $qty > 20) throw new InvalidArgumentException(t('Количество должно быть от 0 до 20.','Quantity must be between 0 and 20.'));
            if ($qty === 0) continue;
            if (!ctype_digit((string)$id)) throw new InvalidArgumentException(t('Неверный код товара.','Invalid product ID.'));
            try { $product = Catalog::product((int)$id); } catch (HttpError) { throw new InvalidArgumentException(t('Один из товаров больше не доступен.','One of the selected items is no longer available.')); }
            $sum = (int)$product['price_minor'] * $qty; $subtotal += $sum;
            $items[] = ['product' => $product, 'quantity' => $qty, 'sum' => $sum];
        }
        if (!$items) throw new InvalidArgumentException(t('Выберите хотя бы один товар.','Select at least one item.'));
        $fee = $delivery ? (int)config('delivery_minor') : 0;
        return ['items'=>$items,'subtotal'=>$subtotal,'delivery'=>$fee,'total'=>$subtotal+$fee];
    }
    public static function validate(array $data): array {
        $errors = [];
        foreach (['name','email','phone','address','comment'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) $errors[] = 'Некорректное поле ' . $field;
        }
        if ($errors) return $errors;
        $name = trim($data['name'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = t('Имя: от 2 до 120 символов.', 'Name: 2 to 120 characters.');
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL) || strlen($data['email'] ?? '') > 190) $errors[] = t('Укажите корректный email.', 'Enter a valid email.');
        if (!preg_match('/^\+?[0-9 ()-]{7,25}$/D', $data['phone'] ?? '') || strlen(preg_replace('/\D/', '', $data['phone'] ?? '')) < 7) $errors[] = t('Проверьте номер телефона.', 'Check the phone number.');
        if (!empty($data['delivery']) && mb_strlen(trim($data['address'] ?? '')) < 5) $errors[] = t('Для доставки нужен адрес.', 'Delivery requires an address.');
        if (mb_strlen($data['address'] ?? '') > 250 || mb_strlen($data['comment'] ?? '') > 2000) $errors[] = t('Адрес или комментарий слишком длинный.', 'Address or comment is too long.');
        if (($data['consent'] ?? '') !== '1') $errors[] = t('Подтвердите согласие на обработку данных.', 'Confirm consent to data processing.');
        return $errors;
    }
    public static function create(array $data, string $nonce): array {
        $db = DB::get(); $db->beginTransaction();
        try {
            $quote = self::quote($data['qty'] ?? [], !empty($data['delivery']));
            $reference = bin2hex(random_bytes(16));
            DB::run('INSERT INTO orders (reference,submission_key,customer_name,email,phone,address,comment,language,subtotal_minor,delivery_minor,total_minor) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$reference,$nonce,trim($data['name']),trim($data['email']),trim($data['phone']),trim($data['address'] ?? ''),trim($data['comment'] ?? ''),lang(),$quote['subtotal'],$quote['delivery'],$quote['total']]);
            $id = (int)$db->lastInsertId();
            foreach ($quote['items'] as $item) DB::run('INSERT INTO order_items (order_id,product_id,title,quantity,unit_price_minor) VALUES (?,?,?,?,?)', [$id,$item['product']['id'],tr($item['product']),$item['quantity'],$item['product']['price_minor']]);
            DB::run('INSERT INTO mail_outbox (order_id) VALUES (?)', [$id]);
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        Mailer::sendOrder($id);
        return DB::one('SELECT * FROM orders WHERE id=?', [$id]);
    }
}

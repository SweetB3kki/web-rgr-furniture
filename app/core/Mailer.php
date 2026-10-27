<?php
declare(strict_types=1);
class Mailer {
    public static function sendOrder(int $id): bool {
        $order = DB::one('SELECT * FROM orders WHERE id=?', [$id]);
        $outbox = DB::one('SELECT * FROM mail_outbox WHERE order_id=?', [$id]);
        if (!$order || !$outbox || $outbox['status'] === 'sent') return false;
        try {
            $contact = Catalog::contacts(); $to = config('mail_to') ?: ($contact['email'] ?? '');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var(config('mail_from'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Неверный адрес отправителя или получателя.');
            $body = "Заказ №{$id}\r\nИмя: {$order['customer_name']}\r\nТелефон: {$order['phone']}\r\nEmail: {$order['email']}\r\nАдрес: {$order['address']}\r\n\r\n";
            foreach (DB::all('SELECT * FROM order_items WHERE order_id=?', [$id]) as $item) $body .= $item['title'] . ' × ' . $item['quantity'] . ': ' . money((int)$item['unit_price_minor'] * (int)$item['quantity']) . "\r\n";
            $body .= "\r\nДоставка: " . money((int)$order['delivery_minor']) . "\r\nИтого: " . money((int)$order['total_minor']) . "\r\nКомментарий: " . $order['comment'];
            $headers = 'From: ' . config('mail_from') . "\r\nTo: {$to}\r\nSubject: =?UTF-8?B?" . base64_encode('Новый заказ №' . $id) . "?=\r\nDate: " . date(DATE_RFC2822) . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            self::smtp($to, $headers . chunk_split(base64_encode($body)));
            DB::run("UPDATE mail_outbox SET status='sent', attempts=attempts+1,last_error=NULL,sent_at=NOW() WHERE order_id=?", [$id]);
            return true;
        } catch (Throwable $e) {
            DB::run("UPDATE mail_outbox SET status='failed',attempts=attempts+1,last_error=? WHERE order_id=?", [mb_substr($e->getMessage(),0,500),$id]);
            error_log('Order notification failed: ' . $e->getMessage());
            return false;
        }
    }
    private static function smtp(string $to, string $message): void {
        $host = (string)config('smtp_host');
        $socket = @stream_socket_client('tcp://' . $host . ':' . config('smtp_port'), $errno, $error, 4);
        if (!$socket) throw new RuntimeException('SMTP недоступен: ' . $error);
        stream_set_timeout($socket, 5);
        $read = static function (array $codes) use ($socket): void {
            $last = '';
            do { $line = fgets($socket, 1024); if ($line === false) throw new RuntimeException('SMTP: соединение закрыто.'); $last = $line; } while (isset($line[3]) && $line[3] === '-');
            if (!in_array((int)substr($last,0,3), $codes, true)) throw new RuntimeException('SMTP: ' . trim($last));
        };
        $command = static function (string $line, array $codes) use ($socket,$read): void { fwrite($socket,$line . "\r\n"); $read($codes); };
        try {
            $read([220]); $command('EHLO localhost', [250]);
            if (config('smtp_tls') === 'starttls') {
                $command('STARTTLS',[220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Не удалось включить TLS.');
                $command('EHLO localhost',[250]);
            }
            if (config('smtp_user') !== '') {
                if (config('smtp_tls') !== 'starttls' && !in_array($host,['127.0.0.1','localhost'],true)) throw new RuntimeException('Для внешнего SMTP с паролем требуется STARTTLS.');
                $command('AUTH LOGIN',[334]); $command(base64_encode(config('smtp_user')),[334]); $command(base64_encode(config('smtp_password')),[235]);
            }
            $command('MAIL FROM:<' . config('mail_from') . '>',[250]); $command('RCPT TO:<' . $to . '>',[250,251]); $command('DATA',[354]);
            fwrite($socket,preg_replace('/(?m)^\./','..',$message) . "\r\n.\r\n"); $read([250]);
            fwrite($socket,"QUIT\r\n");
        } finally { fclose($socket); }
    }
}

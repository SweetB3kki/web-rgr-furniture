<?php
declare(strict_types=1);
class Upload {
    public static function image(array $file): string {
        $limit=(int)(config('upload_max_bytes') ?: 4*1024*1024);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $limit) throw new InvalidArgumentException(t('Не удалось загрузить изображение. Максимум ','Image upload failed. Maximum size: ').(int)($limit/1024/1024).' '.t('МБ.','MB.'));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $size = @getimagesize($file['tmp_name']);
        if (!isset($extensions[$mime]) || !$size || $size[0] > 5000 || $size[1] > 5000) throw new InvalidArgumentException('Нужен JPG, PNG или WebP размером до 5000×5000.');
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (config('upload_storage')==='database') {
            DB::run('INSERT INTO stored_uploads(filename,mime,contents) VALUES (?,?,?)',[$name,$mime,file_get_contents($file['tmp_name'])]);
            return '/uploads/'.$name;
        }
        $directory = ROOT . '/public/uploads';
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('Не удалось сохранить изображение.');
        return '/uploads/' . $name;
    }
    public static function serve(string $filename):void {
        $image=DB::one('SELECT mime,contents FROM stored_uploads WHERE filename=?',[$filename]);
        if (!$image) throw new HttpError(404,t('Изображение не найдено.','Image not found.'));
        $tag='"'.$filename.'"';
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: '.$tag);
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['HTTP_IF_NONE_MATCH']??'')===$tag) { http_response_code(304);return; }
        header('Content-Type: '.$image['mime']);
        header('Content-Length: '.strlen($image['contents']));
        echo $image['contents'];
    }
}

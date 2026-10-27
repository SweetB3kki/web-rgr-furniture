<?php
declare(strict_types=1);
class Upload {
    public static function image(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 4 * 1024 * 1024) throw new InvalidArgumentException('Изображение не загружено или превышает 4 МБ.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $size = @getimagesize($file['tmp_name']);
        if (!isset($extensions[$mime]) || !$size || $size[0] > 5000 || $size[1] > 5000) throw new InvalidArgumentException('Нужен JPG, PNG или WebP размером до 5000×5000.');
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $directory = ROOT . '/public/uploads';
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('Не удалось сохранить изображение.');
        return '/uploads/' . $name;
    }
}

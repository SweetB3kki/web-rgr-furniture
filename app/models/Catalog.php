<?php
declare(strict_types=1);
class Catalog {
    public static function type(int $id): array { return DB::one('SELECT * FROM furniture_types WHERE id=? AND published=1',[$id]) ?? throw new HttpError(404,'Тип мебели не найден.'); }
    public static function subtypes(int $type): array { return DB::all('SELECT * FROM furniture_subtypes WHERE type_id=? AND published=1 ORDER BY sort_order,id',[$type]); }
    public static function designers(): array { return DB::all('SELECT * FROM designers WHERE published=1 ORDER BY sort_order,id'); }
    public static function article(int $id): array { return DB::one("SELECT * FROM articles WHERE id=? AND published=1 AND kind IN ('news','promotion')",[$id]) ?? throw new HttpError(404,'Запись не найдена.'); }
    public static function types(): array { return DB::all('SELECT * FROM furniture_types WHERE published=1 ORDER BY sort_order,id'); }
    public static function products(?int $type = null, ?int $subtype = null): array {
        $sql = 'SELECT p.*, s.type_id, s.title_ru AS subtype_ru, s.title_en AS subtype_en FROM products p JOIN furniture_subtypes s ON s.id=p.subtype_id JOIN furniture_types t ON t.id=s.type_id WHERE p.published=1 AND s.published=1 AND t.published=1'; $args = [];
        if ($type !== null) { $sql .= ' AND s.type_id=?'; $args[] = $type; }
        if ($subtype !== null) { $sql .= ' AND s.id=?'; $args[] = $subtype; }
        return DB::all($sql . ' ORDER BY p.sort_order,p.id', $args);
    }
    public static function product(int $id): array {
        $row = DB::one('SELECT p.*,s.type_id,s.title_ru AS subtype_ru,s.title_en AS subtype_en,t.title_ru AS type_ru,t.title_en AS type_en,d.title_ru AS designer_ru,d.title_en AS designer_en FROM products p JOIN furniture_subtypes s ON s.id=p.subtype_id JOIN furniture_types t ON t.id=s.type_id LEFT JOIN designers d ON d.id=p.designer_id WHERE p.id=? AND p.published=1 AND s.published=1 AND t.published=1', [$id]);
        if (!$row) throw new HttpError(404, t('Мебель не найдена.', 'Furniture not found.'));
        return $row;
    }
    public static function articles(string $kind): array { return DB::all('SELECT * FROM articles WHERE kind=? AND published=1 ORDER BY publish_date DESC,id DESC', [$kind]); }
    public static function page(string $slug): array {
        $row = DB::one("SELECT * FROM articles WHERE kind='page' AND slug=? AND published=1", [$slug]);
        if (!$row) throw new HttpError(404, t('Страница не найдена.', 'Page not found.'));
        return $row;
    }
    public static function contacts(): array { return DB::one('SELECT * FROM contacts WHERE id=1') ?? []; }
    public static function images(string $entity, int $id): array {
        if (!in_array($entity, ['types','products'], true)) return [];
        return DB::all('SELECT * FROM gallery WHERE entity=? AND entity_id=? ORDER BY sort_order,id', [$entity,$id]);
    }
}

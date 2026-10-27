<?php
declare(strict_types=1);
$types=[
 ['Гостиная','Living room','<p>Мягкая мебель и выразительные силуэты для спокойных вечеров дома.</p>','<p>Soft seating and distinctive silhouettes for quiet evenings at home.</p>','living.jpg'],
 ['Столовая','Dining room','<p>Столы и стулья для завтраков, разговоров и встреч за большим столом.</p>','<p>Tables and chairs for breakfast, conversation and gatherings.</p>','dining.jpg'],
 ['Спальня','Bedroom','<p>Лаконичные формы и тёплые оттенки для личного пространства.</p>','<p>Simple shapes and warm shades for your private space.</p>','bedroom.jpg'],
];
foreach($types as $i=>[$ru,$en,$bodyRu,$bodyEn,$image]) {
    DB::run('INSERT INTO furniture_types(title_ru,title_en,body_ru,body_en,image,sort_order) VALUES (?,?,?,?,?,?)',[$ru,$en,$bodyRu,$bodyEn,'/assets/images/'.$image,$i]);
    $id=(int)DB::get()->lastInsertId();
    foreach([$image,'room.jpg'] as $j=>$photo)DB::run("INSERT INTO gallery(entity,entity_id,path,sort_order) VALUES ('types',?,?,?)",[$id,'/assets/images/'.$photo,$j]);
}
foreach([[1,'Диваны','Sofas'],[1,'Кресла','Armchairs'],[2,'Столы','Tables'],[2,'Стулья','Chairs'],[3,'Кровати','Beds'],[3,'Системы хранения','Storage']] as [$type,$ru,$en])DB::run('INSERT INTO furniture_subtypes(type_id,title_ru,title_en,body_ru,body_en) VALUES (?,?,?,?,?)',[$type,$ru,$en,'<p>'.$ru.' коллекции ФОРМА.</p>','<p>FORMA '.$en.' collection.</p>']);
foreach([['Анна Миронова','Anna Mironova','Спокойные пропорции и внимание к тактильности материалов.','Quiet proportions and attention to the feel of materials.'],['Марк Левин','Mark Levin','Функциональная мебель для небольших и открытых пространств.','Functional furniture for small and open spaces.']] as [$ru,$en,$br,$be])DB::run('INSERT INTO designers(title_ru,title_en,body_ru,body_en) VALUES (?,?,?,?)',[$ru,$en,'<p>'.$br.'</p>','<p>'.$be.'</p>']);
$products=[
 [1,1,'Диван «Линия»','Linea sofa',8900000,'sofa.jpg','Трёхместный диван. Обивка: ткань, основание: деревянный каркас. Ширина 220 см.','Three-seat sofa. Fabric upholstery, timber frame. Width 220 cm.'],
 [2,1,'Кресло «Тихо»','Quiet armchair',3250000,'chair.jpg','Компактное кресло для чтения и отдыха. Обивка: ткань. Ширина 78 см.','Compact armchair for reading and relaxing. Fabric upholstery. Width 78 cm.'],
 [3,2,'Стол «Круг»','Circle table',4600000,'dining.jpg','Обеденный стол для семейных встреч. Деревянная столешница, диаметр 110 см.','Dining table for family gatherings. Wooden top, diameter 110 cm.'],
 [4,2,'Стул «Ритм»','Rhythm chair',1450000,'chair.jpg','Обеденный стул с мягким сиденьем. Деревянное основание. Высота сиденья 46 см.','Dining chair with an upholstered seat and wooden base. Seat height 46 cm.'],
 [5,1,'Кровать «Покой»','Rest bed',6800000,'bedroom.jpg','Кровать с мягким изголовьем. Спальное место 160×200 см. Матрас приобретается отдельно.','Bed with a soft headboard. Sleeping area 160×200 cm. Mattress sold separately.'],
 [6,2,'Комод «Грань»','Facet chest',3900000,'room.jpg','Система хранения для спальни. Три выдвижных ящика, ширина 120 см.','Bedroom storage with three drawers. Width 120 cm.'],
];
foreach($products as $i=>[$sub,$designer,$ru,$en,$price,$image,$br,$be]) {
    DB::run('INSERT INTO products(subtype_id,designer_id,title_ru,title_en,body_ru,body_en,price_minor,image,sort_order) VALUES (?,?,?,?,?,?,?,?,?)',[$sub,$designer,$ru,$en,'<p>'.$br.'</p>','<p>'.$be.'</p>',$price,'/assets/images/'.$image,$i]);
    $id=(int)DB::get()->lastInsertId();
    foreach([$image,'living.jpg'] as $j=>$photo)DB::run("INSERT INTO gallery(entity,entity_id,path,sort_order) VALUES ('products',?,?,?)",[$id,'/assets/images/'.$photo,$j]);
}
$articles=[
 ['page','home','Сделано для жизни, а не для витрины','Made for living, not for display','<p>ФОРМА — мебельная фабрика. Мы соединяем простые силуэты, практичные решения и тёплую палитру дерева. В каталоге — мебель для гостиной, столовой и спальни.</p>','<p>FORMA is a furniture brand. We combine simple silhouettes, practical ideas and warm wood tones. Explore furniture for living, dining and sleeping.</p>',''],
 ['page','about','О фабрике','Our factory','<p>Нам близка мебель, которая не спорит с пространством. Коллекции ФОРМА построены на ясных пропорциях, функциональности и сочетании коричневого, белого и бежевого.</p><h2>От идеи до предмета</h2><p>Дизайнер задаёт образ, конструктор прорабатывает детали, а производство превращает проект в готовый предмет. Покупатель выбирает модели в каталоге и отправляет общий заказ.</p>','<p>We value furniture that belongs in a space. FORMA collections combine clear proportions, function and a palette of brown, white and beige.</p><h2>From idea to object</h2><p>A designer develops the concept, an engineer defines the details and the factory makes the piece. Customers select products and submit one combined order.</p>','living.jpg'],
 ['page','documents','Документы фабрики','Factory documents','<p>Паспорта коллекции, внутренний стандарт качества и рекомендации по уходу за мебелью. Все документы можно открыть или скачать в формате PDF.</p>','<p>Collection specifications, our internal quality standard and furniture care instructions. Open or download each document as a PDF.</p>',''],
 ['news','new-collection','Новая коллекция спокойных форм','A new collection of quiet forms','<p>В каталоге появились предметы для гостиной, столовой и спальни. Объединяющая идея — простота, тёплые оттенки и сочетание мебели между коллекциями.</p>','<p>Discover pieces for the living room, dining room and bedroom. The collection shares simple forms, warm shades and easy combinations.</p>','room.jpg'],
 ['news','material-story','История одного материала','The story of a material','<p>Фактура дерева и ткань задают ощущение от предмета не меньше, чем его форма. В журнале фабрики рассказываем о материалах и сочетаниях.</p>','<p>Wood grain and fabric shape the feel of a piece as much as its silhouette. Our journal explores materials and combinations.</p>','dining.jpg'],
 ['promotion','collection-pickup','Самовывоз без дополнительной платы','Complimentary collection','<p>При оформлении заказа выберите самовывоз: дополнительная плата за получение не начисляется. Стоимость мебели остаётся указанной в каталоге.</p>','<p>Select collection when placing your order: no delivery fee will be added. Furniture prices remain as listed in the catalog.</p>','sofa.jpg'],
];
foreach($articles as [$kind,$slug,$ru,$en,$br,$be,$image])DB::run('INSERT INTO articles(kind,slug,title_ru,title_en,body_ru,body_en,publish_date,image) VALUES (?,?,?,?,?,?,?,?)',[$kind,$slug,$ru,$en,$br,$be,date('Y-m-d'),$image?'/assets/images/'.$image:'']);
DB::run('INSERT INTO contacts VALUES (1,?,?,?,?,?,?,?,?,?,?)',['Мебельная фабрика ФОРМА','FORMA furniture factory','Севастополь, ул. Ленина, 1','1 Lenina Street, Sevastopol','+7 900 000-00-00','factory@forma.example',44.6100,33.5250,'Пн–Сб: 10:00–19:00','Mon–Sat: 10:00–19:00']);
echo "Initial catalog created.\n";

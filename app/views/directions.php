<?php
$destination=$contact['address_ru'];
$route='https://yandex.ru/maps/?'.http_build_query(['rtext'=>'~'.$destination,'mode'=>'routes']);
$mapSource='https://yandex.ru/map-widget/v1/?'.http_build_query(['mode'=>'search','text'=>$destination,'z'=>17,'lang'=>lang()==='en'?'en_RU':'ru_RU']);
?>
<header class="page-heading"><span class="eyebrow"><?=t('ПЛАНИРУЙТЕ ВИЗИТ','PLAN YOUR VISIT')?></span><h1><?=h($pageTitle)?></h1><p><?=h(tr($contact,'address'))?> · <?=h(tr($contact,'hours'))?></p></header>
<div class="contact-grid directions-grid">
    <section><h2><?=t('Как добраться','Getting here')?></h2>
        <h3><?=t('На автомобиле','By car')?></h3><p><?=t('Постройте маршрут к магазину от вашего адреса. Навигатор покажет повороты, время в пути и текущую дорожную обстановку.','Get driving directions from your address, including turns, travel time and current traffic.')?></p>
        <h3><?=t('Общественным транспортом','By public transport')?></h3><p><?=t('Укажите остановку отправления: карта подберёт подходящие маршруты и покажет, от какой остановки пройти до магазина.','Enter your departure stop to see suitable services and the walk from your arrival stop to the store.')?></p>
        <h3><?=t('Пешком','On foot')?></h3><p><?=t('Выберите пешеходный маршрут, чтобы увидеть путь от вашего местоположения до отмеченной на карте точки.','Choose walking directions to see the path from your location to the marked destination.')?></p>
        <p><?=t('Если нужна помощь по дороге, позвоните нам:','Need help on the way? Call us:')?> <a class="text-link" href="tel:<?=h(preg_replace('/[^+0-9]/','',$contact['phone']))?>"><?=h($contact['phone'])?></a></p>
    </section>
    <div class="map"><iframe title="<?=t('Расположение магазина ФОРМА','FORMA store location')?>" src="<?=h($mapSource)?>" width="100%" height="480" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        <nav class="route-links" aria-label="<?=t('Построить маршрут','Get directions')?>"><a class="button" href="<?=h($route.'&rtt=auto')?>" target="_blank" rel="noopener"><?=t('На машине','Driving')?> ↗</a><a class="button outline" href="<?=h($route.'&rtt=mt')?>" target="_blank" rel="noopener"><?=t('Транспорт','Transit')?> ↗</a><a class="button outline" href="<?=h($route.'&rtt=pd')?>" target="_blank" rel="noopener"><?=t('Пешком','Walking')?> ↗</a></nav>
    </div>
</div>

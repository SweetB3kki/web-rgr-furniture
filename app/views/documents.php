<?php $documents=require ROOT.'/config/documents.php'; ?>
<header class="page-heading"><span class="eyebrow"><?=t('ФАБРИКА ФОРМА','FORMA FACTORY')?></span><h1><?=h(tr($page))?></h1><div class="rich"><?=Html::clean(tr($page,'body'))?></div></header>
<div class="document-grid">
<?php foreach($documents as $document):$file='/documents/'.$document['file']; ?>
    <article class="document-card"><span class="eyebrow"><?=h($document['code'])?> / PDF</span><h2><?=h(tr($document))?></h2><p><?=h(tr($document,'description'))?></p><div class="document-meta"><?=t('Русский / English','English / Русский')?> · <?=t('Редакция','Edition')?> 01/2026 · <?=max(1,(int)ceil(filesize(ROOT.'/public'.$file)/1024))?> <?=t('КБ','KB')?></div><a class="text-link" href="<?=h($file)?>" target="_blank" rel="noopener"><?=t('Открыть документ','Open document')?> ↗</a><a class="text-link" href="<?=h($file)?>" download><?=t('Скачать PDF','Download PDF')?> ↓</a></article>
<?php endforeach;?>
</div>

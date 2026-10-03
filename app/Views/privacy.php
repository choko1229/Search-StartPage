<?php declare(strict_types=1); ?>
<section class="panel"><h1><?= $e($t->get('privacy_policy')) ?></h1>
<?php foreach(['storage','cookies','account','statistics','external','logs'] as $section): ?>
<h2><?= $e($t->get('privacy_'.$section.'_title')) ?></h2><p><?= $e($t->get('privacy_'.$section.'_text')) ?></p>
<?php endforeach; ?></section>

<?php declare(strict_types=1); ?>
<section class="panel">
    <p class="eyebrow"><?= $e($t->get('error')) ?></p>
    <h1><?= $e($data['message']) ?></h1>
    <p class="muted"><?= $e($t->get('request_id')) ?>: <?= $e($data['request_id']) ?></p>
    <a class="button" href="/"><?= $e($t->get('home')) ?></a>
</section>

<?php declare(strict_types=1); ?>
<section class="panel">
<h1><?= $e($t->get('maintenance_message')) ?></h1>
<form method="get" action="/"><button type="submit"><?= $e($t->get('maintenance_reload')) ?></button></form>
</section>

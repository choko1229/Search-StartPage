<?php declare(strict_types=1); ?>
<section class="panel"><h1><?= $e($t->get('logged_out')) ?></h1><a href="/"><?= $e($t->get('app_name')) ?></a></section>
<p id="logout-data" data-user-id="<?= $e($data['user_id']) ?>" hidden><?= $e($t->get('local_cleanup_failed')) ?></p>
<script type="module" src="/assets/js/account.js"></script>

<?php declare(strict_types=1); use App\Auth\Session; ?>
<section class="panel">
<h1><?= $e($t->get('account')) ?></h1>
<?php if (!$data['user']): ?>
    <?php if ($data['oauth_configured']): ?>
    <form method="post" action="/auth/discord">
        <input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>">
        <button type="submit"><?= $e($t->get('discord_login')) ?></button>
    </form>
    <?php else: ?><p><?= $e($t->get('OAUTH_NOT_CONFIGURED')) ?></p><?php endif; ?>
<?php else: $user=$data['user']; ?>
    <h2><?= $e($user['discord_display_name'] ?: $user['discord_username']) ?></h2>
    <p>Discord: <?= $e($user['discord_username']) ?></p>
    <p><?= $e($t->get('sync_not_enabled')) ?></p>
    <h2><?= $e($t->get('devices')) ?></h2>
    <?php foreach ($data['devices'] as $device): ?>
    <article class="panel">
        <h3><?= $e($device['name']) ?> <?= $device['id']===$data['current_device'] ? $e($t->get('current_device')) : '' ?></h3>
        <p><?= $e($device['browser']) ?> / <?= $e($device['os']) ?></p>
        <p><?= $e($t->get('login_date')) ?>: <?= $e($device['created_at']) ?> UTC</p>
        <p><?= $e($t->get('last_active')) ?>: <?= $e($device['last_active_at']) ?> UTC</p>
        <form method="post" action="/account/device">
            <input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>">
            <input type="hidden" name="device_id" value="<?= $e($device['id']) ?>">
            <label><?= $e($t->get('device_name')) ?><input name="name" value="<?= $e($device['name']) ?>" maxlength="100" required></label>
            <button name="action" value="rename"><?= $e($t->get('save')) ?></button>
            <button name="action" value="revoke" formnovalidate><?= $e($t->get('device_logout')) ?></button>
        </form>
    </article>
    <?php endforeach; ?>
    <form method="post" action="/auth/logout">
        <input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>">
        <button type="submit"><?= $e($t->get('logout')) ?></button>
    </form>
<?php endif; ?>
</section>

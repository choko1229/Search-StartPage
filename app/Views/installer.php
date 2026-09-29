<?php
declare(strict_types=1);

use App\Auth\Session;

$step = $data['step'];
$steps = ['environment', 'database', 'site', 'discord', 'admin', 'migration', 'complete'];
?>
<section class="installer panel">
    <p class="eyebrow"><?= $e($t->get('setup')) ?> · <?= $e($step) ?> / 7</p>
    <h1><?= $e($t->get($steps[$step - 1])) ?></h1>
    <nav aria-label="<?= $e($t->get('setup_progress')) ?>">
        <ol class="steps">
            <?php foreach ($steps as $index => $label): ?>
                <li <?= $index + 1 === $step ? 'aria-current="step"' : '' ?>><?= $e($t->get($label)) ?></li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php if (!empty($data['error'])): ?>
        <p class="error-message" role="alert"><?= $e($t->get($data['error'])) ?></p>
    <?php endif; ?>
    <?php if ($step < 7): ?>
    <form method="post" action="/installer" class="setup-form" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>">
        <input type="hidden" name="_step" value="<?= $e($step) ?>">
        <?php if ($step === 1): ?>
            <p><?= $e($t->get('environment_description')) ?></p>
            <ul class="checks">
                <?php foreach ($data['checks'] as $check): ?>
                    <li><span><?= $e($check['name']) ?></span><span><?= $e($t->get($check['ok'] ? 'available' : ($check['required'] ? 'required_missing' : 'optional_missing'))) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="muted"><?= $e($t->get('setup_key_help')) ?> <code>php bin/setup-key.php</code></p>
            <label for="setup-key"><?= $e($t->get('setup_key')) ?></label>
            <input id="setup-key" name="setup_key" type="password" required maxlength="64" autocomplete="off">
        <?php elseif ($step === 2): ?>
            <p><?= $e($t->get('database_help')) ?></p>
            <label for="db-host"><?= $e($t->get('db_host')) ?></label>
            <input id="db-host" name="host" value="127.0.0.1" required maxlength="253">
            <label for="db-port"><?= $e($t->get('db_port')) ?></label>
            <input id="db-port" name="port" type="number" value="3306" min="1" max="65535" required>
            <label for="db-name"><?= $e($t->get('db_name')) ?></label>
            <input id="db-name" name="name" required pattern="[A-Za-z0-9_]+" maxlength="64">
            <label for="db-user"><?= $e($t->get('db_user')) ?></label>
            <input id="db-user" name="user" required maxlength="128" autocomplete="username">
            <label for="db-password"><?= $e($t->get('db_password')) ?></label>
            <input id="db-password" name="password" type="password" autocomplete="new-password" maxlength="1024">
        <?php elseif ($step === 3): ?>
            <label for="site-name"><?= $e($t->get('site_name')) ?></label>
            <input id="site-name" name="site_name" value="<?= $e($t->get('app_name')) ?>" required maxlength="100">
            <label for="site-url"><?= $e($t->get('site_url')) ?></label>
            <input id="site-url" name="site_url" type="url" placeholder="https://search.example.com" required maxlength="2048">
            <p class="muted"><?= $e($t->get('site_url_help')) ?></p>
        <?php elseif ($step === 4): ?>
            <p><?= $e($t->get('discord_help')) ?></p>
            <label for="client-id"><?= $e($t->get('client_id')) ?></label>
            <input id="client-id" name="client_id" inputmode="numeric" pattern="[0-9]{17,20}" maxlength="20">
            <label for="client-secret"><?= $e($t->get('client_secret')) ?></label>
            <input id="client-secret" name="client_secret" type="password" autocomplete="new-password" maxlength="256">
            <p><?= $e($t->get('callback')) ?></p>
            <code class="breakable"><?= $e(($data['draft']['site']['url'] ?? '') . '/api/auth/discord/callback') ?></code>
        <?php elseif ($step === 5): ?>
            <p><?= $e($t->get('admin_help')) ?></p>
            <label for="admin-id"><?= $e($t->get('admin_id')) ?></label>
            <input id="admin-id" name="admin_id" required inputmode="numeric" pattern="[0-9]{17,20}" maxlength="20">
        <?php elseif ($step === 6): ?>
            <p><?= $e($t->get('migration_help')) ?></p>
            <dl>
                <dt><?= $e($t->get('site_name')) ?></dt><dd><?= $e($data['draft']['site']['name']) ?></dd>
                <dt><?= $e($t->get('site_url')) ?></dt><dd><?= $e($data['draft']['site']['url']) ?></dd>
                <dt><?= $e($t->get('db_name')) ?></dt><dd><?= $e($data['draft']['database']['name']) ?></dd>
                <dt><?= $e($t->get('admin_id')) ?></dt><dd><?= $e($data['draft']['admin']) ?></dd>
            </dl>
        <?php endif; ?>
        <button type="submit"><?= $e($t->get($step === 6 ? 'install' : 'continue')) ?></button>
    </form>
    <?php else: ?>
        <p><?= $e($t->get('complete_help')) ?></p>
        <a class="button" href="/"><?= $e($t->get('home')) ?></a>
    <?php endif; ?>
</section>

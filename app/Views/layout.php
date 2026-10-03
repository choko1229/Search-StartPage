<?php
declare(strict_types=1);

use App\Auth\Session;
?>
<!doctype html>
<html lang="<?= $e($t->locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $e($data['site_name'] ?? $t->get('app_name')) ?></title>
    <link rel="stylesheet" href="/assets/css/core.css">
    <link rel="stylesheet" href="/assets/css/glass.css">
    <link rel="icon" href="/assets/icons/search.svg" type="image/svg+xml">
</head>
<body>
    <a class="skip-link" href="#main"><?= $e($t->get('skip_content')) ?></a>
    <?php if (empty($data['maintenance_only'])): ?><header class="site-header">
        <a class="brand" data-header-item="brand" href="/"><?= $e($t->get('app_name')) ?></a>
        <a data-header-item="settings" href="/#settings"><?= $e($t->get('settings_title')) ?></a>
        <a data-header-item="history" href="/#history" aria-label="<?= $e($t->get('history')) ?>" title="<?= $e($t->get('history')) ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M3 11a9 9 0 1 1 2.6 7M3 4v7h7M12 7v5l3 2"/></svg>
        </a>
        <a data-header-item="account" href="/account"><?= $e($t->get('account')) ?></a>
        <form data-header-item="language" method="post" action="/locale" class="language-form">
            <input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>">
            <label for="locale"><?= $e($t->get('language')) ?></label>
            <select id="locale" name="locale">
                <option value="ja" <?= $t->locale === 'ja' ? 'selected' : '' ?>>日本語</option>
                <option value="en" <?= $t->locale === 'en' ? 'selected' : '' ?>>English</option>
            </select>
            <button type="submit" class="secondary"><?= $e($t->get('apply')) ?></button>
        </form>
    </header><?php endif; ?>
    <main id="main" tabindex="-1"><?= $content ?></main>
    <?php if(empty($data['maintenance_only'])): ?><footer><a href="/privacy"><?= $e($t->get('privacy_policy')) ?></a></footer><?php endif; ?>
</body>
</html>

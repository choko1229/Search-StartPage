<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="<?= $e($t->locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $e($data['site_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/core.css">
    <link rel="stylesheet" href="/assets/css/glass.css">
    <link rel="icon" href="/assets/icons/search.svg" type="image/svg+xml">
    <script src="/assets/js/extension-shell.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main"><?= $e($t->get('skip_content')) ?></a>
    <header class="site-header">
        <a class="brand" data-header-item="brand" href="<?= $e($page) ?>"><?= $e($t->get('app_name')) ?></a>
        <a data-header-item="settings" href="#settings"><?= $e($t->get('settings_title')) ?></a>
        <a data-header-item="history" href="#history"><?= $e($t->get('history')) ?></a>
        <a data-header-item="account" href="<?= $e($serverOrigin) ?>/account" target="_blank" rel="noopener"><?= $e($t->get('account')) ?></a>
        <div data-header-item="language">
            <label for="extension-locale"><?= $e($t->get('language')) ?></label>
            <select id="extension-locale"><option value="ja">日本語</option><option value="en">English</option></select>
        </div>
    </header>
    <main id="main" tabindex="-1"><?= $content ?></main>
    <footer><details>
        <summary><?= $e($t->get('extension_startup_title')) ?></summary>
        <p><?= $e($t->get('extension_startup_help')) ?> <a href="<?= $e($serverOrigin) ?>/" target="_blank" rel="noopener"><?= $e($serverOrigin) ?>/</a></p>
        <p><?= $e($t->get('extension_account_help')) ?></p>
    </details></footer>
</body>
</html>

<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="/assets/css/search.css">
<?php if(($data['update_notice']??null)!==null): ?><p role="status" class="update-notice"><a href="/admin/update"><?= $e($t->get('update_available')) ?> <?= $e($data['update_notice']['tag']) ?></a></p><?php endif; ?>
<section class="search-home" aria-labelledby="search-title">
    <p class="eyebrow"><?= $e($t->get('search_tagline')) ?></p>
    <h1 id="search-title" class="sr-only"><?= $e($data['site_name']) ?></h1>
    <div class="search-tools">
        <button type="button" id="history-open" class="secondary"><?= $e($t->get('history')) ?></button>
        <button type="button" id="search-settings-open" class="secondary"><?= $e($t->get('search_settings')) ?></button>
    </div>
    <div class="search-box">
        <svg class="search-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/></svg>
        <label class="sr-only" for="provider"><?= $e($t->get('provider')) ?></label>
        <label class="sr-only" for="query"><?= $e($t->get('search_input')) ?></label>
        <textarea id="query" rows="1" maxlength="12000" placeholder="<?= $e($t->get('search_placeholder')) ?>" role="combobox" aria-autocomplete="list" aria-controls="suggestions" aria-expanded="false" aria-describedby="search-help ai-hint"></textarea>
        <div class="search-mode-switch" role="group" aria-label="<?= $e($t->get('search_mode')) ?>">
            <button type="button" id="mode-web" aria-pressed="true"><?= $e($t->get('web_mode')) ?></button>
            <button type="button" id="mode-ai" aria-pressed="false"><?= $e($t->get('ai_mode')) ?></button>
        </div>
        <select id="provider"></select>
        <button type="button" id="search-execute" aria-label="<?= $e($t->get('search')) ?>" title="<?= $e($t->get('search')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></button>
    </div>
    <p id="ai-hint" hidden><?= $e($t->get('ai_recommend')) ?></p>
    <ul id="suggestions" role="listbox" aria-label="<?= $e($t->get('suggestions')) ?>" hidden></ul>
    <p id="search-help" class="muted"><?= $e($t->get('keyboard_help')) ?></p>
    <div id="provider-shortcuts" class="provider-shortcuts" aria-label="<?= $e($t->get('provider')) ?>"></div>
    <p id="search-status" role="status" aria-live="polite"></p>
</section>
<?php require __DIR__ . '/favorites.php'; ?>
<section id="history-area" hidden aria-label="<?= $e($t->get('history')) ?>"></section>
<link rel="stylesheet" href="/assets/css/favorites.css">
<dialog id="history-dialog" aria-labelledby="history-title">
    <h2 id="history-title"><?= $e($t->get('history')) ?></h2>
    <button type="button" data-close><?= $e($t->get('close')) ?></button>
    <button type="button" id="history-clear"><?= $e($t->get('clear_history')) ?></button>
    <div id="history-list"></div>
</dialog>
<dialog id="search-settings" aria-labelledby="search-settings-title">
    <h2 id="search-settings-title"><?= $e($t->get('settings_title')) ?></h2>
    <button type="button" data-close aria-label="<?= $e($t->get('close')) ?>">×</button>
    <div id="search-preferences"></div>
    <section id="provider-settings">
    <h3><?= $e($t->get('manage_providers')) ?></h3>
    <label for="provider-kind"><?= $e($t->get('search_mode')) ?></label>
    <select id="provider-kind"><option value="web"><?= $e($t->get('web_mode')) ?></option><option value="ai"><?= $e($t->get('ai_mode')) ?></option></select>
    <div id="provider-list"></div>
    <form id="provider-form">
        <input type="hidden" name="id">
        <label><?= $e($t->get('name')) ?><input name="name" required maxlength="100"></label>
        <label><?= $e($t->get('template_url')) ?><input name="url" required maxlength="2048" placeholder="https://example.com/?q={query}"></label>
        <label><?= $e($t->get('prefix')) ?><input name="prefix" required pattern="[A-Za-z0-9_-]{1,32}" maxlength="32"></label>
        <label><?= $e($t->get('icon')) ?><input name="icon" maxlength="4"></label>
        <button type="submit"><?= $e($t->get('save')) ?></button>
        <button type="reset" class="secondary"><?= $e($t->get('new_provider')) ?></button>
        <p id="provider-error" role="alert"></p>
    </form>
    </section>
</dialog>
<dialog id="ai-copy-dialog" aria-labelledby="ai-copy-title">
    <h2 id="ai-copy-title"><?= $e($t->get('copy_query')) ?></h2>
    <p><?= $e($t->get('copy_help')) ?></p>
    <textarea id="copy-query" readonly aria-label="<?= $e($t->get('search_input')) ?>"></textarea>
    <button type="button" id="copy-query-button"><?= $e($t->get('copy')) ?></button>
    <button type="button" id="open-ai-button"><?= $e($t->get('open_ai')) ?></button>
    <button type="button" data-close><?= $e($t->get('close')) ?></button>
</dialog>
<script type="application/json" id="search-bootstrap"><?= json_encode(['providers' => $data['providers'] ?? ['web' => [], 'ai' => []], 'messages' => $t->messages(), 'platform'=>$data['platform']??['kind'=>'web']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="/assets/js/search.js"></script>

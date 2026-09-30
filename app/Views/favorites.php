<?php declare(strict_types=1); ?>
<section id="favorites-section" aria-labelledby="favorites-title">
    <div class="favorites-toolbar">
        <h2 id="favorites-title"><?= $e($t->get('favorites')) ?></h2>
        <button id="favorite-add" type="button"><?= $e($t->get('add_favorite')) ?></button>
        <button id="folder-manage" type="button" class="secondary"><?= $e($t->get('folders')) ?></button>
    </div>
    <div id="folder-tabs" role="tablist" aria-label="<?= $e($t->get('folders')) ?>"></div>
    <details><summary><?= $e($t->get('favorite_options')) ?></summary>
        <label><input id="favorite-context-setting" type="checkbox"><?= $e($t->get('favorite_context_setting')) ?></label>
        <label><input id="favorite-stats-setting" type="checkbox"><?= $e($t->get('favorite_stats_setting')) ?></label>
        <label><?= $e($t->get('favorite_search_setting')) ?><select id="favorite-search-setting">
            <option value="both"><?= $e($t->get('favorite_search_both')) ?></option>
            <option value="main"><?= $e($t->get('favorite_search_main')) ?></option>
            <option value="dedicated"><?= $e($t->get('favorite_search_dedicated')) ?></option>
        </select></label>
        <div id="favorite-layout-settings" class="favorite-layout-settings"></div>
    </details>
    <div class="favorites-toolbar">
        <input id="favorite-filter" type="search" aria-label="<?= $e($t->get('find_favorites')) ?>" placeholder="<?= $e($t->get('find_favorites')) ?>">
        <select id="favorite-sort" aria-label="<?= $e($t->get('sort')) ?>">
            <?php foreach (['manual','usage','recent','name'] as $sort): ?><option value="<?= $e($sort) ?>"><?= $e($t->get($sort)) ?></option><?php endforeach; ?>
        </select>
        <select id="favorite-display" aria-label="<?= $e($t->get('display')) ?>">
            <?php foreach (['icon-name','icon','card','auto'] as $display): ?><option value="<?= $e($display) ?>"><?= $e($t->get($display)) ?></option><?php endforeach; ?>
        </select>
        <label><input type="checkbox" id="favorite-show-hidden"><?= $e($t->get('show_hidden')) ?></label>
    </div>
    <button type="button" id="favorite-tag-reset" hidden></button>
    <div id="favorites-grid" role="list"></div>
    <button type="button" id="favorites-more" class="secondary" hidden></button>
    <p id="favorites-status" role="status"></p>
</section>
<dialog id="favorite-editor" aria-labelledby="favorite-editor-title">
    <h2 id="favorite-editor-title"><?= $e($t->get('edit_favorite')) ?></h2>
    <form id="favorite-form">
        <input type="hidden" name="id">
        <label><?= $e($t->get('url_category')) ?><input type="url" name="url" required maxlength="2048"></label>
        <button id="favorite-metadata" type="button" class="secondary"><?= $e($t->get('fetch_metadata')) ?></button>
        <p id="metadata-status" role="status"></p>
        <label><?= $e($t->get('name')) ?><input name="name" required maxlength="100"></label>
        <label><?= $e($t->get('favorite_icon')) ?><input name="icon" maxlength="2048"></label>
        <label><?= $e($t->get('folder')) ?><select name="folderId"></select></label>
        <label><?= $e($t->get('tags')) ?><input name="tags" maxlength="820"></label>
        <label><?= $e($t->get('color')) ?><input type="color" name="color" value="#304fc3"></label>
        <label><?= $e($t->get('description')) ?><textarea name="description" maxlength="2000"></textarea></label>
        <label><?= $e($t->get('shortcut')) ?><input name="shortcut" placeholder="Alt+1" maxlength="80"></label>
        <label><input name="pinned" type="checkbox"><?= $e($t->get('pin')) ?></label>
        <label><input name="visible" type="checkbox" checked><?= $e($t->get('visible')) ?></label>
        <p id="favorite-error" role="alert"></p>
        <button type="submit"><?= $e($t->get('save')) ?></button>
        <button type="button" data-close><?= $e($t->get('cancel')) ?></button>
    </form>
</dialog>
<dialog id="folder-editor" aria-labelledby="folder-editor-title">
    <h2 id="folder-editor-title"><?= $e($t->get('folders')) ?></h2>
    <button type="button" data-close><?= $e($t->get('close')) ?></button>
    <label><?= $e($t->get('folder_sort')) ?><select id="folder-sort"><option value="manual"><?= $e($t->get('manual')) ?></option><option value="usage"><?= $e($t->get('usage')) ?></option></select></label>
    <div id="folder-list"></div>
    <form id="folder-form"><input name="id" type="hidden"><label><?= $e($t->get('folder_name')) ?><input name="name" required maxlength="100"></label><button type="submit"><?= $e($t->get('save')) ?></button><button type="reset"><?= $e($t->get('new_folder')) ?></button><p id="folder-error" role="alert"></p></form>
</dialog>
<dialog id="favorite-menu" aria-label="<?= $e($t->get('favorite_actions')) ?>"><div id="favorite-menu-actions"></div><button type="button" data-close><?= $e($t->get('close')) ?></button></dialog>
<dialog id="favorite-confirm" aria-labelledby="favorite-confirm-message">
    <p id="favorite-confirm-message"></p>
    <button id="favorite-confirm-delete" type="button"><?= $e($t->get('delete')) ?></button>
    <button type="button" data-close><?= $e($t->get('cancel')) ?></button>
</dialog>

<?php declare(strict_types=1);
$path=$data['storage'] ? '/admin/storage' : '/admin/users';
$pageLink=static fn(int $page):string=>$path.'?'.http_build_query(['q'=>$data['search'],'page'=>$page,'page_size'=>$data['page_size']]);
?>
<section class="panel">
<a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a>
<h1><?= $e($t->get($data['storage'] ? 'admin_storage' : 'admin_users')) ?></h1>
<form method="get" action="<?= $e($path) ?>">
<label><?= $e($t->get('admin_user_search')) ?><input name="q" value="<?= $e($data['search']) ?>" maxlength="100"></label>
<button type="submit"><?= $e($t->get('admin_search')) ?></button>
</form>
<p><?= $e($t->get('admin_total')) ?>: <?= $e($data['total']) ?></p>
<?php if (!$data['items']): ?><p><?= $e($t->get('admin_no_results')) ?></p><?php endif; ?>
<?php foreach ($data['items'] as $user): ?>
<article class="panel">
<h2><?= $e($user['discord_display_name'] ?: $user['discord_username']) ?></h2>
<dl>
<dt>Discord ID</dt><dd><?= $e($user['discord_id']) ?></dd>
<dt><?= $e($t->get('admin_username')) ?></dt><dd><?= $e($user['discord_username']) ?></dd>
<dt><?= $e($t->get('admin_role')) ?></dt><dd><?= $e($t->get($user['admin_flag']===1 ? 'admin_administrator' : 'admin_regular_user')) ?></dd>
<dt><?= $e($t->get('admin_created')) ?></dt><dd><?= $e($user['created_at']) ?> UTC</dd>
<dt><?= $e($t->get('admin_last_login')) ?></dt><dd><?= $e($user['last_login_at'] ?? $t->get('never')) ?></dd>
<dt><?= $e($t->get('admin_count_backgrounds')) ?></dt><dd><?= $e($user['background_count']) ?></dd>
<dt><?= $e($t->get('admin_count_background_bytes')) ?></dt><dd><?= $e($user['background_bytes']) ?></dd>
</dl>
<?php if(!$data['storage']): ?>
<form method="post" action="/admin/users/role">
<input type="hidden" name="_csrf" value="<?= $e(App\Auth\Session::csrf()) ?>">
<input type="hidden" name="user_id" value="<?= $e($user['id']) ?>"><input type="hidden" name="version" value="<?= $e($data['role_version']) ?>">
<input type="hidden" name="expected_admin_flag" value="<?= $e($user['admin_flag']) ?>"><input type="hidden" name="admin_flag" value="<?= $user['admin_flag']===1?'0':'1' ?>">
<button type="submit"><?= $e($t->get($user['admin_flag']===1?'admin_role_revoke':'admin_role_grant')) ?></button>
</form>
<?php endif; ?>
</article>
<?php endforeach; ?>
<nav aria-label="<?= $e($t->get('admin_pages')) ?>">
<?php if ($data['page']>1): ?><a href="<?= $e($pageLink($data['page']-1)) ?>"><?= $e($t->get('admin_previous')) ?></a><?php endif; ?>
<span><?= $e($data['page']) ?></span>
<?php if ($data['page']*$data['page_size']<$data['total']): ?><a href="<?= $e($pageLink($data['page']+1)) ?>"><?= $e($t->get('admin_next')) ?></a><?php endif; ?>
</nav>
</section>

<?php declare(strict_types=1); ?>
<section class="panel">
<h1><?= $e($t->get('admin_update')) ?></h1>
<p><a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a></p>
<dl>
<dt><?= $e($t->get('update_current')) ?></dt><dd><?= $e($data['current_version']) ?></dd>
<dt><?= $e($t->get('update_source')) ?></dt><dd><?= $e($data['repository']) ?></dd>
<dt><?= $e($t->get('update_checked')) ?></dt><dd><?= $e($data['checked_at']===null?$t->get('update_unchecked'):gmdate('Y-m-d H:i:s',$data['checked_at']).' UTC') ?></dd>
</dl>
<?php if($data['error']!==null): ?><p role="alert"><?= $e($t->get($data['error'])) ?></p>
<?php elseif($data['available']===true): ?><p role="status"><?= $e($t->get('update_available')) ?> <?= $e($data['release']['tag']) ?></p>
<?php elseif($data['available']===false): ?><p role="status"><?= $e($t->get('update_none')) ?></p>
<?php else: ?><p role="status"><?= $e($t->get('update_unchecked')) ?></p><?php endif; ?>
<form method="post" action="/admin/update" class="admin-update-form">
<input type="hidden" name="_csrf" value="<?= $e(\App\Auth\Session::csrf()) ?>">
<input type="hidden" name="revision" value="<?= $e($data['revision']) ?>">
<label><?= $e($t->get('update_channel')) ?><select name="channel">
<?php foreach(['stable'=>'Stable','beta'=>'Beta','nightly'=>'Nightly','custom'=>'Custom Tag'] as $value=>$label): ?><option value="<?= $e($value) ?>" <?= $data['channel']===$value?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?>
</select></label>
<label><?= $e($t->get('update_custom_tag')) ?><input name="custom_tag" value="<?= $e($data['custom_tag']) ?>" maxlength="128" autocomplete="off"></label>
<button type="submit"><?= $e($t->get('update_check')) ?></button>
</form>
<p class="muted"><?= $e($t->get('update_check_help')) ?></p>
</section>

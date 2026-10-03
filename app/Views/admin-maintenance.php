<?php declare(strict_types=1); use App\Auth\Session; ?>
<section class="panel">
<a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a>
<h1><?= $e($t->get('admin_maintenance')) ?></h1>
<p><?= $e($t->get('admin_maintenance_help')) ?></p>
<form method="post" action="/admin/maintenance">
<input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>">
<input type="hidden" name="version" value="<?= $e($data['version']) ?>">
<label><?= $e($t->get('admin_maintenance')) ?><select name="enabled"><option value="0"<?= !$data['enabled'] ? ' selected' : '' ?>><?= $e($t->get('admin_maintenance_off')) ?></option><option value="1"<?= $data['enabled'] ? ' selected' : '' ?>><?= $e($t->get('admin_maintenance_on')) ?></option></select></label>
<button type="submit"><?= $e($t->get('save')) ?></button>
</form>
</section>

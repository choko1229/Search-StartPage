<?php declare(strict_types=1); use App\Auth\Session; ?>
<section class="panel"><a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a>
<h1><?= $e($t->get('admin_policy')) ?></h1>
<form class="setup-form" method="post" action="/admin/policy">
<input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>"><input type="hidden" name="version" value="<?= $e($data['version']) ?>">
<h2><?= $e($t->get('admin_flags')) ?></h2>
<?php foreach(App\Services\SitePolicy::FLAGS as $key): ?><label><?= $e($t->get('policy_'.$key)) ?><select name="<?= $e($key) ?>"><option value="1"<?= $data['policy']['flags'][$key] ? ' selected' : '' ?>><?= $e($t->get('policy_enabled')) ?></option><option value="0"<?= !$data['policy']['flags'][$key] ? ' selected' : '' ?>><?= $e($t->get('policy_disabled')) ?></option></select></label><?php endforeach; ?>
<h2><?= $e($t->get('admin_limits')) ?></h2><p><?= $e($t->get('policy_limits_help')) ?></p>
<?php foreach(App\Services\SitePolicy::LIMITS as $key=>[$min,$max]): ?><label><?= $e($t->get('policy_'.$key)) ?><input type="number" name="<?= $e($key) ?>" min="<?= $e($min) ?>" max="<?= $e($max) ?>" step="1" value="<?= $e($data['policy']['limits'][$key] ?? '') ?>" placeholder="<?= $e($data['effective']['limits'][$key]) ?>"></label><?php endforeach; ?>
<button type="submit"><?= $e($t->get('save')) ?></button></form></section>

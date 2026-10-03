<?php declare(strict_types=1); ?>
<section class="panel">
<h1><?= $e($t->get('admin_dashboard')) ?></h1>
<nav aria-label="<?= $e($t->get('admin_dashboard')) ?>"><a href="/admin/users"><?= $e($t->get('admin_users')) ?></a> · <a href="/admin/storage"><?= $e($t->get('admin_storage')) ?></a></nav>
<a href="/admin/maintenance"><?= $e($t->get('admin_maintenance')) ?></a>
<a href="/admin/policy"><?= $e($t->get('admin_policy')) ?></a>
<a href="/admin/statistics"><?= $e($t->get('admin_statistics')) ?></a>
<a href="/admin/logs"><?= $e($t->get('admin_logs')) ?></a> · <a href="/admin/audit-logs"><?= $e($t->get('admin_audit_logs')) ?></a>
<dl>
<?php foreach ($data['counts'] as $key=>$value): ?>
<dt><?= $e($t->get('admin_count_'.$key)) ?></dt><dd><?= $e($value) ?></dd>
<?php endforeach; ?>
</dl>
<h2><?= $e($t->get('admin_compression')) ?></h2>
<?php if ((!$data['compression']['imagick'] && !$data['compression']['gd']) || !$data['compression']['ffmpeg']): ?>
<p role="status"><?= $e($t->get('admin_compression_warning')) ?></p>
<?php endif; ?>
<dl>
<?php foreach ($data['compression'] as $key=>$enabled): ?>
<dt><?= $e($key) ?></dt><dd><?= $e($t->get($enabled ? 'admin_available' : 'admin_unavailable')) ?></dd>
<?php endforeach; ?>
</dl>
</section>

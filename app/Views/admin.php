<?php declare(strict_types=1); ?>
<section class="panel">
<h1><?= $e($t->get('admin_dashboard')) ?></h1>
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

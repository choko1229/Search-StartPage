<?php declare(strict_types=1);
$path=$data['audit_only'] ? '/admin/audit-logs' : '/admin/logs';
$filters=$data['filters'];
$pageLink=static fn(int $page):string=>$path.'?'.http_build_query(array_replace($filters,['page'=>$page]));
?>
<section class="panel">
<a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a>
<h1><?= $e($t->get($data['audit_only'] ? 'admin_audit_logs' : 'admin_logs')) ?></h1>
<p><?= $e($t->get('admin_log_retention')) ?></p>
<form method="get" action="<?= $e($path) ?>">
<?php if (!$data['audit_only']): ?>
<label><?= $e($t->get('admin_log_type')) ?><select name="type"><option value=""><?= $e($t->get('all')) ?></option>
<?php foreach (App\Services\LogFilters::TYPES as $type): ?><option value="<?= $e($type) ?>"<?= $filters['type']===$type ? ' selected' : '' ?>><?= $e($t->get('log_type_'.$type)) ?></option><?php endforeach; ?>
</select></label>
<?php endif; ?>
<?php foreach (['from'=>'date','to'=>'date','user_id'=>'text','error_code'=>'text','q'=>'search'] as $key=>$type): ?>
<label><?= $e($t->get('admin_log_'.$key)) ?><input type="<?= $e($type) ?>" name="<?= $e($key) ?>" value="<?= $e($filters[$key]) ?>"></label>
<?php endforeach; ?>
<button type="submit"><?= $e($t->get('admin_search')) ?></button>
</form>
<p><?= $e($t->get('admin_total')) ?>: <?= $e($data['total']) ?></p>
<?php if (!$data['items']): ?><p><?= $e($t->get('admin_no_logs')) ?></p><?php endif; ?>
<?php foreach ($data['items'] as $entry): ?>
<article class="panel">
<h2><?= $e($entry['error_code']) ?></h2>
<dl><dt><?= $e($t->get('admin_log_type')) ?></dt><dd><?= $e($t->get('log_type_'.$entry['type'])) ?></dd>
<dt><?= $e($t->get('admin_created')) ?></dt><dd><?= $e($entry['created_at']) ?> UTC</dd>
<dt><?= $e($t->get('admin_log_user_id')) ?></dt><dd><?= $e($entry['user_id'] ?? '—') ?></dd>
<dt><?= $e($t->get('admin_log_file')) ?></dt><dd><?= $e($t->get($entry['file_written'] ? 'admin_log_delivered' : 'admin_log_pending')) ?></dd></dl>
<pre class="admin-log-context"><?= $e(json_encode($entry['context'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)) ?></pre>
</article>
<?php endforeach; ?>
<nav aria-label="<?= $e($t->get('admin_pages')) ?>">
<?php if ($data['page']>1): ?><a href="<?= $e($pageLink($data['page']-1)) ?>"><?= $e($t->get('admin_previous')) ?></a><?php endif; ?>
<span><?= $e($data['page']) ?></span>
<?php if ($data['page']*$data['page_size']<$data['total']): ?><a href="<?= $e($pageLink($data['page']+1)) ?>"><?= $e($t->get('admin_next')) ?></a><?php endif; ?>
</nav></section>

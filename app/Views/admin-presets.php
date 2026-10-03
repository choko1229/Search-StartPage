<?php declare(strict_types=1); use App\Auth\Session; ?>
<section class="panel"><a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a><h1><?= $e($t->get('admin_presets')) ?></h1>
<p><?= $e($t->get('presets_help')) ?></p>
<form class="setup-form" action="/admin/presets" method="post"><input type="hidden" name="_csrf" value="<?= $e(Session::csrf()) ?>"><input type="hidden" name="version" value="<?= $e($data['version']) ?>">
<?php foreach(['web','ai'] as $mode): ?><h2><?= $e($t->get('presets_'.$mode)) ?></h2>
<?php $rows=$data['presets'][$mode]; if(count($rows)<50)$rows[]=['id'=>'','name'=>'','url'=>'','prefix'=>'','icon'=>'','enabled'=>true,'copy'=>false,'sort_order'=>count($rows)];
foreach($rows as $index=>$row): ?><fieldset><legend><?= $e($row['id']===''?$t->get('presets_new'):$row['name']) ?></legend>
<?php foreach(['id','name','url','prefix','icon','sort_order'] as $field): ?><label><?= $e($t->get('presets_'.$field)) ?><input name="<?= $e($mode.'['.$index.']['.$field.']') ?>" type="<?= $field==='sort_order'?'number':'text' ?>" value="<?= $e($row[$field]) ?>"<?= $field==='sort_order'?' min="0" max="9999" step="1"':'' ?>></label><?php endforeach; ?>
<?php foreach(['enabled','copy'] as $field): ?><label><?= $e($t->get('presets_'.$field)) ?><select name="<?= $e($mode.'['.$index.']['.$field.']') ?>"><option value="1"<?= $row[$field]?' selected':'' ?>><?= $e($t->get('policy_enabled')) ?></option><option value="0"<?= !$row[$field]?' selected':'' ?>><?= $e($t->get('policy_disabled')) ?></option></select></label><?php endforeach; ?>
<?php if($row['id']!==''): ?><label><input type="checkbox" name="<?= $e($mode.'['.$index.'][remove]') ?>" value="1"> <?= $e($t->get('presets_remove')) ?></label><?php endif; ?>
</fieldset><?php endforeach; endforeach; ?><button type="submit"><?= $e($t->get('save')) ?></button></form></section>

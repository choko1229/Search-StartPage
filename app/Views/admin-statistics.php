<?php declare(strict_types=1); ?>
<section class="panel"><a href="/admin"><?= $e($t->get('admin_dashboard')) ?></a>
<h1><?= $e($t->get('admin_statistics')) ?></h1>
<p><?= $e($t->get('statistics_help')) ?></p>
<form class="setup-form" action="/admin/statistics" method="get">
<label><?= $e($t->get('statistics_start')) ?><input type="date" name="start" required value="<?= $e($data['period']['start']) ?>"></label>
<label><?= $e($t->get('statistics_end')) ?><input type="date" name="end" required value="<?= $e($data['period']['end']) ?>"></label>
<button type="submit"><?= $e($t->get('statistics_apply')) ?></button></form>
<dl><?php foreach($data['summary'] as $key=>$value): ?><dt><?= $e($t->get('statistics_'.$key)) ?></dt><dd><?= $e(number_format($value)) ?></dd><?php endforeach; ?></dl>
<h2><?= $e($t->get('statistics_retention')) ?></h2><p><?= $e($t->get('statistics_retention_help')) ?></p>
<p><?= $e($data['retention_d7']['percent']===null?$t->get('statistics_no_cohort'):$data['retention_d7']['percent'].'%') ?>
 (<?= $e($data['retention_d7']['returning']) ?> / <?= $e($data['retention_d7']['eligible']) ?>)</p>
<?php foreach($data['breakdowns'] as $key=>$rows): ?>
<h2><?= $e($t->get('statistics_'.$key)) ?></h2>
<?php if(!$rows): ?><p><?= $e($t->get('statistics_empty')) ?></p><?php else: ?>
<ul><?php $max=max(array_column($rows,'total')); foreach($rows as $row): ?><li>
<?= $e($row['label']) ?>: <?= $e($row['total']) ?><?= isset($row['percent'])?' ('.$e($row['percent']).'%)':'' ?>
<meter min="0" max="<?= $e(max(1,$max)) ?>" value="<?= $e($row['total']) ?>" aria-label="<?= $e($row['label']) ?>"><?= $e($row['total']) ?></meter>
</li><?php endforeach; ?></ul><?php endif; ?>
<?php endforeach; ?>
<h2><?= $e($t->get('statistics_daily')) ?></h2>
<?php foreach(['dau','searches','ai_searches','favorite_opens'] as $key):
 $values=array_column($data['daily'],$key);$max=max(1,max($values));$points=[];$n=count($values);
 foreach($values as $i=>$value)$points[]=round(20+($n>1?$i*560/($n-1):280),2).','.round(160-$value*140/$max,2);
?>
<figure><figcaption><?= $e($t->get('statistics_'.$key)) ?> (<?= $e($data['period']['start']) ?> – <?= $e($data['period']['end']) ?> UTC)</figcaption>
<svg class="statistics-chart" viewBox="0 0 600 180" role="img" aria-label="<?= $e($t->get('statistics_'.$key)) ?>">
<title><?= $e($t->get('statistics_'.$key)) ?></title><path d="M20 20V160H580" fill="none" stroke="currentColor"/>
<polyline points="<?= $e(implode(' ',$points)) ?>" fill="none" stroke="currentColor" stroke-width="2"/>
<text x="0" y="15" fill="currentColor" font-size="12"><?= $e($max) ?></text><text x="0" y="175" fill="currentColor" font-size="12">0</text>
</svg></figure><?php endforeach; ?>
<details><summary><?= $e($t->get('statistics_daily_table')) ?></summary>
<div class="statistics-table"><table><caption><?= $e($t->get('statistics_daily')) ?> (UTC)</caption><thead><tr>
<th scope="col"><?= $e($t->get('statistics_date')) ?></th><?php foreach(['dau','searches','ai_searches','favorite_opens'] as $key): ?><th scope="col"><?= $e($t->get('statistics_'.$key)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($data['daily'] as $row): ?><tr><th scope="row"><?= $e($row['day']) ?></th><?php foreach(['dau','searches','ai_searches','favorite_opens'] as $key): ?><td><?= $e($row[$key]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div></details></section>

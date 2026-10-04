<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('TEST_BACKUP_HOST')!=='search-update-backup-mariadb-20261004')exit(1);
$root=dirname(__DIR__);$live=$root.'/live';
if(!is_file($root.'/storage/web-cache-test-only')||!is_file($root.'/storage/ui-proof.json'))throw new RuntimeException('Isolated UI proof required');
$config=App\Config::load($live);$pdo=App\Database\Database::connect($config->get('database'));$proof=json_decode(file_get_contents($root.'/storage/ui-proof.json'),true,flags:JSON_THROW_ON_ERROR);
$history=(new App\Repositories\UpdateHistoryRepository($pdo))->listing();$journal=(new App\Services\UpdateJournal($live.'/storage/updates/journal'))->status();
echo json_encode(['current_version'=>trim(file_get_contents($live.'/VERSION')),'original_version'=>$proof['version'],'configuration_unchanged'=>hash_equals($proof['configuration_sha256'],hash_file('sha256',$live.'/config/config.php')),'history_statuses'=>array_column($history,'status'),'job_phase'=>$journal['job']['phase']??null,'template_v2'=>str_contains(file_get_contents($live.'/app/Views/admin-update.php'),'generated-update-code-v2')],JSON_THROW_ON_ERROR)."\n";

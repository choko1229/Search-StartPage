<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('TEST_POLICY_UI_HOST')!=='search-policy-ui-mysql-20261004')exit(1);
require dirname(__DIR__).'/app/autoload.php';$root=dirname(__DIR__);
if(!is_file($root.'/storage/policy-ui-test-only'))throw new RuntimeException('Dedicated policy UI required');
$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$flags=(new App\Repositories\SitePolicyRepository($pdo))->read()['policy']['flags'];
$query=$pdo->prepare('SELECT COUNT(*) AS rows_count,COALESCE(SUM(file_size),0) AS bytes_count,SUM(file_path IS NOT NULL) AS files_count FROM backgrounds WHERE user_id=(SELECT id FROM users WHERE discord_id=?)');$query->execute(['999999999999999978']);$row=$query->fetch();
$query=$pdo->prepare('SELECT document FROM sync_states WHERE user_id=(SELECT id FROM users WHERE discord_id=?)');$query->execute(['999999999999999978']);$document=$query->fetchColumn();$region=$document===false?null:(json_decode($document,true,32,JSON_THROW_ON_ERROR)['settings']['themeRegion']??null);
echo json_encode(['uploads_enabled'=>$flags['background_uploads'],'weather_enabled'=>$flags['weather'],'cloud_rows'=>(int)$row['rows_count'],'cloud_files'=>(int)$row['files_count'],'cloud_bytes'=>(int)$row['bytes_count'],'public_test_region_synced'=>is_array($region)&&($region['latitude']??null)===35.68&&($region['longitude']??null)===139.69],JSON_THROW_ON_ERROR)."\n";

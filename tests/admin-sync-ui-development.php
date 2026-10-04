<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
$hosts=['search-sync-ui-mysql-20261004'=>['mysql',8111],'search-sync-ui-mariadb-20261004'=>['mariadb',8112]];
$host=getenv('TEST_SYNC_UI_HOST');$mode=$argv[1]??'';$root=dirname(__DIR__);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||!isset($hosts[$host])||!in_array($mode,['setup','seed','observe','stop-sync','start-sync'],true)||!is_file($root.'/storage/sync-ui-test-only'))exit(1);
[$engine,$port]=$hosts[$host];$settings=require $root.'/config/config.example.php';$settings['installed']=true;
$settings['database']=['host'=>$host,'port'=>3306,'name'=>'sync_ui','user'=>'sync','password'=>getenv('TEST_SYNC_UI_PASSWORD')];
$settings['site']['url']='http://sync-a-'.$engine.'.localhost:'.$port;$settings['session']=['name'=>'isolated_sync_ui','secure'=>false];
$deadline=microtime(true)+60;
do{try{$pdo=App\Database\Database::connect($settings['database']);break;}catch(PDOException){if(microtime(true)>$deadline)throw new RuntimeException('Dedicated sync UI DB unavailable');usleep(100000);}}while(true);
if($mode==='setup'){
    if(file_exists($root.'/config/config.php')||$pdo->query('SHOW TABLES')->fetchAll()!==[])throw new RuntimeException('Fresh dedicated sync deployment required');
    $migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');if(count($migrator->migrate())!==17||$migrator->migrate()!==[])throw new RuntimeException('Fresh/repeat migrations failed');
    file_put_contents($root.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($root.'/config/config.php',0600);
    echo "Dedicated sync UI configuration and all 17 fresh/repeat migrations prepared.\n";exit;
}
if(!file_exists($root.'/config/config.php')||App\Config::load($root)->get('database.host')!==$host)throw new RuntimeException('Dedicated configuration mismatch');
if($mode==='seed'){
    if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0)throw new RuntimeException('Empty generated user environment required');
    $auth=new App\Repositories\AuthRepository($pdo);$owner=$auth->upsertIdentity(['id'=>'999999999999999973','username'=>'Generated isolated sync owner','display_name'=>null,'avatar'=>null],'ja');
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$owner]);
    if(!is_dir($root.'/public/_test'))mkdir($root.'/public/_test',0700);
    foreach(['a','b'] as $deviceLabel){
        $device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($owner,$device,hash('sha256',$token),['browser'=>'Generated '.$deviceLabel,'os'=>'Test'],time());
        $source='<?php if(getenv("SEARCH_TEST_MODE")!=="1"||getenv("SEARCH_LOCAL_DEVELOPMENT")!=="1"||!is_file(dirname(__DIR__,2)."/storage/sync-ui-test-only")){http_response_code(404);exit;}setcookie("search_remember",'.var_export($device.'.'.$token,true).',["path"=>"/","httponly"=>true,"samesite"=>"Lax"]);header("Location: /account",true,303);';
        file_put_contents($root.'/public/_test/sync-'.$deviceLabel.'-login.php',$source);chmod($root.'/public/_test/sync-'.$deviceLabel.'-login.php',0600);
    }
    echo "Generated isolated sync owner and two independent devices prepared.\n";exit;
}
$query=$pdo->prepare('SELECT id FROM users WHERE discord_id=?');$query->execute(['999999999999999973']);$owner=(int)$query->fetchColumn();if($owner<1)throw new RuntimeException('Generated owner required');
$policyRepository=new App\Repositories\SitePolicyRepository($pdo);
if($mode==='stop-sync'||$mode==='start-sync'){
    if(!(new App\Repositories\AdminRepository($pdo))->isAdministrator($owner))throw new RuntimeException('Generated administrator required');
    $current=$policyRepository->read();$policy=$current['policy'];$policy['flags']['cloud_sync']=$mode==='start-sync';
    $policyRepository->update($policy,$current['version'],$owner,new App\Services\PolicyState($root.'/storage/policy'));
    (new App\Services\AdminAuditLogger($pdo,new App\Services\FileLogger($root.'/storage/logs')))->flush();
    echo "Dedicated cloud sync policy changed.\n";exit;
}
$query=$pdo->prepare('SELECT version,document FROM sync_states WHERE user_id=?');$query->execute([$owner]);$row=$query->fetch(PDO::FETCH_ASSOC);$document=$row===false?[]:json_decode($row['document'],true,32,JSON_THROW_ON_ERROR);$region=$document['settings']['themeRegion']??null;
$query=$pdo->prepare('SELECT COUNT(*) FROM devices WHERE user_id=?');$query->execute([$owner]);
echo json_encode(['generated_device_count'=>(int)$query->fetchColumn(),'cloud_sync_enabled'=>$policyRepository->read()['policy']['flags']['cloud_sync'],'cloud_version'=>$row===false?0:(int)$row['version'],'public_tokyo_region'=>is_array($region)&&($region['latitude']??null)===35.68&&($region['longitude']??null)===139.69,'public_osaka_region'=>is_array($region)&&($region['latitude']??null)===34.69&&(float)($region['longitude']??0)===135.5],JSON_THROW_ON_ERROR)."\n";

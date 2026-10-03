<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$pdo=App\Database\Database::connect(App\Config::load(dirname(__DIR__))->get('database'));
$roles=new App\Repositories\AdminRoleRepository($pdo);
if(($argv[1]??'')==='worker'){
    touch($argv[5].'/'.$argv[6].'.ready');
    try{$roles->update((int)$argv[3],false,true,(int)$argv[4],(int)$argv[2]);echo 'accepted';}
    catch(App\Http\HttpException $error){echo $error->errorCode;}exit;
}
if((int)$pdo->query('SELECT COUNT(*) FROM administrators WHERE admin_flag=1')->fetchColumn()!==0)throw new RuntimeException('Empty disposable administrator environment required');
$directory=sys_get_temp_dir().'/admin-role-race-'.bin2hex(random_bytes(8));mkdir($directory,0700);$ids=[];$children=[];$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);$count++;echo "PASS: $name\n";};
$start=static function(string $name,int $actor,int $target,int $version)use($directory,&$children):void{
    $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$actor,(string)$target,(string)$version,$directory,$name],[0=>['file','/dev/null','r'],1=>['file',$directory.'/'.$name.'.result','w'],2=>['file',$directory.'/'.$name.'.error','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Worker launch failed');$children[$name]=$process;
};
$ready=static function(array $names)use($directory,&$children,$check):void{
    $deadline=microtime(true)+5;
    do{$all=true;foreach($names as $name)$all=$all&&is_file($directory.'/'.$name.'.ready');if($all)break;usleep(10000);}while(microtime(true)<$deadline);
    $check($all,'real writers ready');usleep(100000);
    foreach($names as $name)$check(proc_get_status($children[$name])['running'],'writer '.$name.' waits on membership mutex');
};
$finish=static function(array $names)use($directory,&$children,$check):array{
    $results=[];foreach($names as $name){$check(proc_close($children[$name])===0,'writer '.$name.' completed');unset($children[$name]);$results[]=file_get_contents($directory.'/'.$name.'.result');}return $results;
};
try{
    $auth=new App\Repositories\AuthRepository($pdo);
    foreach(range(0,1) as $index){$id=$auth->upsertIdentity(['id'=>'999'.str_pad((string)random_int(1,999999999999999),15,'0',STR_PAD_LEFT),'username'=>'Role concurrency','display_name'=>null,'avatar'=>null],'en');$ids[]=$id;$pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$id]);}
    $version=$roles->version();$pdo->beginTransaction();$pdo->query("SELECT version FROM site_settings WHERE setting_key='admin_roles' FOR UPDATE")->fetchColumn();
    $start('a',$ids[0],$ids[0],$version);$start('b',$ids[1],$ids[1],$version);$ready(['a','b']);$pdo->commit();
    $results=$finish(['a','b']);sort($results);$check($results===['ADMIN_SETTINGS_CONFLICT','accepted'],'two self revocations accept exactly one');
    $remaining=$pdo->query('SELECT user_id FROM administrators WHERE admin_flag=1')->fetchAll(PDO::FETCH_COLUMN);$check(count($remaining)===1,'concurrent edits preserve one administrator');
    $survivor=(int)$remaining[0];
    try{$roles->update($survivor,false,true,$roles->version(),$survivor);throw new RuntimeException('last role removed');}catch(App\Http\HttpException $error){$check($error->errorCode==='LAST_ADMIN_REQUIRED','surviving administrator cannot be removed');}
    $pdo->prepare('UPDATE administrators SET admin_flag=1 WHERE user_id IN (?,?)')->execute($ids);
    $version=$roles->version();$pdo->beginTransaction();$pdo->query("SELECT version FROM site_settings WHERE setting_key='admin_roles' FOR UPDATE")->fetchColumn();
    $start('stale',$ids[1],$ids[0],$version);$ready(['stale']);
    // Simulate another committed role edit while this already-authorized writer waits.
    $pdo->prepare('UPDATE administrators SET admin_flag=0 WHERE user_id=?')->execute([$ids[1]]);$pdo->exec("UPDATE site_settings SET version=version+1 WHERE setting_key='admin_roles'");$pdo->commit();
    $check($finish(['stale'])===['ADMIN_REQUIRED'],'queued revoked actor is rejected before stale version check');
    $check((int)$pdo->query('SELECT COUNT(*) FROM administrators WHERE admin_flag=1')->fetchColumn()===1,'revoked writer cannot remove the remaining administrator');
}finally{
    if($pdo->inTransaction())$pdo->rollBack();foreach($children as $process){proc_terminate($process);proc_close($process);}
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);foreach(glob($directory.'/*') as $path)unlink($path);rmdir($directory);
}
echo "$count role concurrency checks passed.\n";

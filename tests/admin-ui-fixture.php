<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$path=$root.'/storage/admin-ui-fixture.json';
$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$mode=$argv[1]??'';
if($mode==='cleanup'){
    if(!is_file($path))throw new RuntimeException('No fixture');
    $fixture=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    $policy=new App\Repositories\SitePolicyRepository($pdo);
    $policy->update($fixture['policy'],$policy->read()['version'],$fixture['user_id'],new App\Services\PolicyState($root.'/storage/policy'));
    if(isset($fixture['presets'])){
        $presets=new App\Repositories\ProviderPresetRepository($pdo);
        $presets->update($fixture['presets'],$presets->read()['version'],$fixture['user_id'],new App\Services\PresetState($root.'/storage/presets'));
    }
    if(isset($fixture['role_target']))$pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$fixture['role_target'],'999999999999999962']);
    // Remove only files referenced by the disposable identity, under the same
    // owner lock used for upload publication. Never scan or purge storage.
    $storage=new App\Services\BackgroundUpload($root);
    $storage->withOwnerLock((int)$fixture['user_id'],function()use($pdo,$fixture,$storage):void{
        $owner=(int)$fixture['user_id'];
        $identity=$pdo->prepare('SELECT id FROM users WHERE id=? AND discord_id=?');
        $identity->execute([$owner,'999999999999999961']);
        if($identity->fetchColumn()===false)throw new RuntimeException('Disposable identity no longer matches');
        $files=(new App\Repositories\BackgroundRepository($pdo))->list($owner);
        foreach(array_unique(array_filter(array_column($files,'file_path'))) as $filename){
            try{$file=$storage->existingPath($owner,$filename);}
            catch(App\Http\HttpException $error){if($error->errorCode==='NOT_FOUND')continue;throw $error;}
            if(!unlink($file))throw new RuntimeException('Disposable upload cleanup failed');
        }
        $pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$owner,'999999999999999961']);
    });
    unlink($path);echo "Admin UI fixture cleaned and saved settings restored.\n";exit;
}
if($mode!=='prepare'||is_file($path))throw new RuntimeException('Specify prepare, or clean the existing fixture');
if(($argv[2]??'')==='roles'){
    $query=$pdo->prepare('SELECT id FROM users WHERE discord_id IN (?,?)');$query->execute(['999999999999999961','999999999999999962']);
    if($query->fetchColumn()!==false)throw new RuntimeException('Role fixture users already exist; refusing to reuse');
    if((int)$pdo->query('SELECT COUNT(*) FROM administrators WHERE admin_flag=1')->fetchColumn()!==0)throw new RuntimeException('Empty disposable administrator environment required');
}
$auth=new App\Repositories\AuthRepository($pdo);
$user=$auth->upsertIdentity(['id'=>'999999999999999961','username'=>'Admin UI verification','display_name'=>null,'avatar'=>null],'ja');
$pdo->prepare('INSERT INTO administrators(user_id,admin_flag,created_at,updated_at) VALUES (?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$user]);
$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$key=bin2hex(random_bytes(32));
$auth->createDevice($user,$device,hash('sha256',$token),['browser'=>'UI verification','os'=>'Test'],time());
$policy=(new App\Repositories\SitePolicyRepository($pdo))->read()['policy'];
$fixture=['user_id'=>$user,'key'=>$key,'cookie'=>$device.'.'.$token,'expires'=>time()+900,'policy'=>$policy];
if(($argv[2]??'')==='roles'){
    $fixture['role_target']=$auth->upsertIdentity(['id'=>'999999999999999962','username'=>'Disposable role target','display_name'=>null,'avatar'=>null],'en');
}
try{$fixture['presets']=(new App\Repositories\ProviderPresetRepository($pdo))->read()['presets'];}
catch(App\Http\HttpException $error){if($error->errorCode!=='ADMIN_SETTINGS_UNAVAILABLE')throw $error;}
$encoded=json_encode($fixture,JSON_THROW_ON_ERROR);$handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Cannot write fixture');
try{if(fwrite($handle,$encoded)!==strlen($encoded))throw new RuntimeException('Incomplete fixture');}finally{fclose($handle);}chmod($path,0600);
// The invoking runner stores this locally; do not print the key in chat.
echo $key;

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$mode=$argv[1]??'';if(!in_array($mode,['create','cleanup'],true))exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\Database;use App\Repositories\{AuthRepository,BackgroundRepository};use App\Services\BackgroundUpload;
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$identities=['999999999999999961','999999999999999962'];
$auth=new AuthRepository($pdo);$backgrounds=new BackgroundRepository($pdo);$storage=new BackgroundUpload($root);
$lookup=$pdo->prepare('SELECT id FROM users WHERE discord_id IN (?,?)');$lookup->execute($identities);
foreach($lookup->fetchAll(PDO::FETCH_COLUMN) as $owner){
    foreach($backgrounds->list((int)$owner) as $row)if($row['file_path']!==null){try{@unlink($storage->existingPath((int)$owner,$row['file_path']));}catch(\App\Http\HttpException){}}
}
$pdo->prepare('DELETE FROM users WHERE discord_id IN (?,?)')->execute($identities);
if($mode==='cleanup')exit;
$result=[];
foreach($identities as $discord){
    $user=$auth->upsertIdentity(['id'=>$discord,'username'=>'Background HTTP Test','display_name'=>null,'avatar'=>null],'en');$devices=[];
    for($index=0;$index<2;$index++){
        $id=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($user,$id,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());$devices[]=['search_remember'=>$id.'.'.$token];
    }
    $result[]=['userId'=>(string)$user,'devices'=>$devices];
}
// Captured by the isolated runner into a Git-ignored file; never print to chat.
echo json_encode($result,JSON_THROW_ON_ERROR);

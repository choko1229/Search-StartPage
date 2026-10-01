<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\Database;use App\Repositories\AuthRepository;
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));
// Fixed identities belong only to this test in an isolated database.
$identities=['999999999999999951','999999999999999952'];
if(($argv[1]??'')==='cleanup') {$pdo->prepare('DELETE FROM users WHERE discord_id IN (?,?)')->execute($identities);exit;}
if(($argv[1]??'')!=='create')exit(1);
$pdo->prepare('DELETE FROM users WHERE discord_id IN (?,?)')->execute($identities);
$repo=new AuthRepository($pdo);$result=[];
foreach($identities as $index=>$discord) {
    $user=$repo->upsertIdentity(['id'=>$discord,'username'=>'Sync HTTP Test','display_name'=>null,'avatar'=>null],'en');
    $devices=[];
    for($deviceIndex=0;$deviceIndex<2;$deviceIndex++) {
        $id=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));
        $repo->createDevice($user,$id,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());
        $devices[]=['search_remember'=>$id.'.'.$token];
    }
    $result[]=['userId'=>(string)$user,'devices'=>$devices];
}
// Only the test runner receives this output. Never print it to a chat or commit it.
echo json_encode($result,JSON_THROW_ON_ERROR);

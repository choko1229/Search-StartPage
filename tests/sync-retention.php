<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\Database;use App\Repositories\{AuthRepository,SyncRepository};use App\Services\SyncRetention;
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));$auth=new AuthRepository($pdo);$repo=new SyncRepository($pdo);
$user=$auth->upsertIdentity(['id'=>'999999999999999941','username'=>'Retention Test','display_name'=>null,'avatar'=>null],'en');
$count=0;$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;echo "PASS: $label\n";};
$now=(int)floor(microtime(true)*1000);
$row=static fn($id,$at)=>(object)['id'=>$id,'at'=>$at,'query'=>'Test','provider'=>'g','mode'=>'web'];
try {
    $doc=(object)['settings'=>(object)['historyLimit'=>2,'historyDays'=>1],'history'=>(object)['a'=>$row('a',$now),'b'=>$row('b',$now-1000),'c'=>$row('c',$now-2000),'expired'=>$row('expired',$now-86400000)]];
    $pruned=SyncRetention::prune($doc,$now);$check(array_keys((array)$pruned->history)===['a','b'],'quota keeps most recent and excludes expiration boundary');
    $check(count((array)$doc->history)===4,'pruning does not mutate caller');
    $saved=$repo->write($user,0,$doc);$check(count((array)$saved['document']->history)===2,'write enforces retention');
    $query=$pdo->prepare('SELECT COUNT(*) FROM search_history WHERE user_id=?');$query->execute([$user]);$check((int)$query->fetchColumn()===2,'quota physically deletes relational history');
    // Advance logical time without changing the wall clock: an existing row is
    // made old in the canonical payload, simulating expiry between requests.
    $expired=$saved['document'];$expired->history->a->at=$now-172800000;$expired->history->b->at=$now-172800000;
    $pdo->prepare('UPDATE sync_states SET document=? WHERE user_id=?')->execute([json_encode($expired),$user]);
    $current=SyncRetention::read($repo,$user);$check($current['version']===2 && count((array)$current['document']->history)===0,'read-time expiry advances canonical version');
    $query->execute([$user]);$check((int)$query->fetchColumn()===0,'read-time expiry removes physical rows');
    $check(SyncRetention::read($repo,$user)['version']===2,'unchanged read does not advance version');
    $versions=$pdo->prepare("SELECT version FROM sync_versions WHERE user_id=? AND entity_type='history' AND entity_id='a'");$versions->execute([$user]);$check((int)$versions->fetchColumn()===2,'expiration records deletion version');
    $check($repo->write($user,1,$doc)===null,'expired snapshot cannot resurrect history');
}finally {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user]);}
echo "$count retention assertions passed.\n";

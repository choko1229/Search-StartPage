<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\Database;use App\Repositories\{AuthRepository,BackgroundRepository};use App\Services\BackgroundInput;use App\Http\HttpException;
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));
if(($argv[1]??'')==='worker') {
    $user=(int)$argv[2];$id=$argv[3];$directory=$argv[4];touch($directory.'/'.$id.'.ready');
    try{(new BackgroundRepository($pdo,1))->save($user,BackgroundInput::validate((object)['id'=>$id,'name'=>'Quota'],true),null,
        ['filename'=>str_repeat($id==='a'?'a':'b',48).'.png','bytes'=>1,'type'=>'image','mime'=>'image/png']);echo 'accepted';}
    catch(HttpException $error){if($error->errorCode!=='BACKGROUND_QUOTA_EXCEEDED')throw $error;echo 'quota';}exit;
}
$auth=new AuthRepository($pdo);$user=$auth->upsertIdentity(['id'=>'999999999999999943','username'=>'Parallel Background','display_name'=>null,'avatar'=>null],'en');
$directory=sys_get_temp_dir().'/search-quota-'.bin2hex(random_bytes(8));mkdir($directory,0700);$children=[];$passed=0;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";};
try {
    $pdo->beginTransaction();$statement=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$statement->execute([$user]);
    foreach(['a','b'] as $id){$process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$user,$id,$directory],
        [0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$directory.'/'.$id.'.result','w'],2=>['file',$directory.'/'.$id.'.error','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Worker launch failed');$children[$id]=$process;
    }
    $deadline=microtime(true)+5;while((!is_file($directory.'/a.ready')||!is_file($directory.'/b.ready'))&&microtime(true)<$deadline)usleep(10000);
    $check(is_file($directory.'/a.ready')&&is_file($directory.'/b.ready'),'both real quota writers ready');usleep(100000);
    $check(proc_get_status($children['a'])['running']&&proc_get_status($children['b'])['running'],'both writers blocked by owner row lock');
    $pdo->commit();
    foreach($children as $id=>$process){$check(proc_close($process)===0,'writer '.$id.' completed without DB error');unset($children[$id]);}
    $results=[file_get_contents($directory.'/a.result'),file_get_contents($directory.'/b.result')];sort($results);
    $check($results===['accepted','quota'],'exactly one upload fits final byte quota');
    $check((new BackgroundRepository($pdo))->usage($user)['used_bytes']===1,'parallel writes never exceed quota');
    echo "$passed background quota concurrency assertions passed.\n";
} finally {
    if($pdo->inTransaction())$pdo->rollBack();foreach($children as $process){proc_terminate($process);proc_close($process);}
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user]);
    foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);
}

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\{AuthRepository,BackgroundRepository};
use App\Services\{BackgroundCleanup,BackgroundUpload,BackgroundInput};
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));$auth=new AuthRepository($pdo);$repository=new BackgroundRepository($pdo);
$identity='999999999999999966';$lookup=$pdo->prepare('SELECT id FROM users WHERE discord_id=?');$lookup->execute([$identity]);
if($lookup->fetchColumn())throw new RuntimeException('Cleanup fixture already exists; inspect interrupted fixture');
$owner=$auth->upsertIdentity(['id'=>$identity,'username'=>'Cleanup test','display_name'=>null,'avatar'=>null],'en');
$work=sys_get_temp_dir().'/search-cleanup-'.bin2hex(random_bytes(8));mkdir($work,0700);
$storage=new BackgroundUpload($work);$cleanup=new BackgroundCleanup($work,$repository);$now=time();$passed=0;$process=null;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";};
try {
    $check($cleanup->run()===['candidates'=>0,'deleted'=>0,'busy_owners'=>0,'failed'=>0],'missing upload storage is harmless');
    $storage->withOwnerLock($owner,static fn()=>null);$directory=$work.'/storage/uploads/backgrounds/'.$owner;
    $make=static function(string $name,bool $old=true)use($directory,$now):string{$path=$directory.'/'.$name;file_put_contents($path,'generated');touch($path,$old?$now-BackgroundCleanup::GRACE_SECONDS-1:$now);return $path;};
    $active=$make(str_repeat('a',48).'.png');$archived=$make(str_repeat('b',48).'.png');$off=$make(str_repeat('c',48).'.png');
    foreach([['active',$active,false,true],['archived',$archived,true,true],['off',$off,false,false]] as [$id,$file,$deleted,$cloud]){
        $item=BackgroundInput::validate((object)['id'=>$id,'name'=>$id,'type'=>'image','deleted'=>$deleted,'cloudSync'=>$cloud],true);
        $repository->save($owner,$item,null,['filename'=>basename($file),'type'=>'image','bytes'=>9,'mime'=>'image/png']);
    }
    $orphan=$make(str_repeat('d',48).'.png');$recent=$make(str_repeat('e',48).'.mp4',false);$unknown=$make('keep-not-upload.txt');
    $external=$work.'/outside.txt';file_put_contents($external,'outside');$link=$directory.'/'.str_repeat('f',48).'.png';symlink($external,$link);
    $check($cleanup->run(false,$now)['candidates']===1&&is_file($orphan),'default dry run preserves orphan bytes');
    $locked=$storage->withOwnerLock($owner,fn()=> $cleanup->run(true,$now));
    $check($locked['busy_owners']===1&&$locked['deleted']===0&&is_file($orphan),'collector skips owner while upload publication lock is held');
    $result=$cleanup->run(true,$now);clearstatcache();
    $check($result['deleted']===1&&!is_file($orphan),'old unreferenced private file reclaimed');
    $check(is_file($active)&&is_file($archived)&&is_file($off),'active archived and sync-off DB references retained');
    $check(is_file($recent)&&is_file($unknown)&&is_link($link)&&file_get_contents($external)==='outside','recent staging unknown names and external symlink target retained');
    $check($repository->usage($owner)['used_bytes']===27,'filesystem collection does not alter metadata or quota');
    $check($cleanup->run(true,$now)['deleted']===0,'repeated cleanup is idempotent');
    if(function_exists('posix_geteuid')&&posix_geteuid()===0){
        $lockPath=$directory.'/.background-upload.lock';unlink($lockPath);chown($directory,33);
        $storage->withOwnerLock($owner,static fn()=>null);clearstatcache(true,$lockPath);
        $check(fileowner($lockPath)===33,'privileged maintenance preserves PHP worker ownership of newly created lock');
    }
    // A separate process simulates an upload killed before its finally block.
    $abandoned=$make(str_repeat('1',48).'.mp4');$ready=$work.'/ready';
    $script=$work.'/hold.php';
    file_put_contents($script,'<?php require '.var_export($root.'/app/autoload.php',true).'; (new App\\Services\\BackgroundUpload('.var_export($work,true).'))->withOwnerLock('.$owner.',function(){file_put_contents('.var_export($ready,true).',"ready");sleep(30);});');
    $process=proc_open([PHP_BINARY,$script],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Lock process unavailable');
    for($attempt=0;$attempt<100&&!is_file($ready);$attempt++){clearstatcache(true,$ready);usleep(10000);}
    $check(is_file($ready)&&$cleanup->run(true,$now)['busy_owners']===1,'actual concurrent process lock protects abandoned candidate');
    proc_terminate($process);proc_close($process);$process=null;
    $result=$cleanup->run(true,$now);clearstatcache();
    $check($result['deleted']===1&&!is_file($abandoned),'process termination releases lock and permits later recovery');
    $pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$owner,$identity]);
    $result=$cleanup->run(true,$now);clearstatcache();
    $check($result['deleted']===3&&!is_file($active)&&!is_file($archived)&&!is_file($off),'deleted account files recovered after DB references disappear');
    symlink($work,$work.'/storage/uploads/backgrounds/999999999');
    $check($cleanup->run(true,$now)['deleted']===0&&is_file($external),'symlink owner directory is skipped without traversal');
    unlink($directory.'/.background-upload.lock');symlink($external,$directory.'/.background-upload.lock');
    $refused=false;try{$cleanup->run(true,$now);}catch(App\Http\HttpException $error){$refused=$error->status===503;}
    $check($refused&&file_get_contents($external)==='outside','symlink lock is rejected without modifying target');
    unlink($directory.'/.background-upload.lock');
    rename($work.'/storage',$work.'/saved-storage');symlink($work.'/saved-storage',$work.'/storage');
    $refused=false;try{$cleanup->run(true,$now);}catch(App\Http\HttpException $error){$refused=$error->status===503;}
    $check($refused,'symlink storage ancestor refuses entire collection');
    echo "$passed background cleanup assertions passed.\n";
} finally {
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    $pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$owner,$identity]);
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $entry){if($entry->isLink()||$entry->isFile())unlink($entry->getPathname());elseif($entry->isDir())rmdir($entry->getPathname());}rmdir($work);
}

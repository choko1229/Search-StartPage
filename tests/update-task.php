<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{UpdateAccess,UpdateProcess};
use App\Http\HttpException;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$directory=sys_get_temp_dir().'/update-task-'.bin2hex(random_bytes(8));mkdir($directory,0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $call)use($check){try{$call();throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='UPDATE_PROCESS_FAILED','unauthorized child fails safely');}};
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
$root=dirname(__DIR__);$access=new UpdateAccess($directory.'/access');$process=new UpdateProcess();
try{
    $child=$directory.'/child.php';file_put_contents($child,'<?php require '.var_export($root.'/app/Services/UpdateAccess.php',true).';try{$lease=App\\Services\\UpdateAccess::authorizeInherited($argv[1]);echo json_encode([App\\Services\\UpdateAccess::PROTOCOL,getmypid()]);}catch(Throwable){exit(1);}');
    try{$access->childDescriptors();throw new LogicException('Unexpected success');}catch(RuntimeException $error){$check($error->getMessage()==='UPDATE_TASK_NOT_AUTHORIZED','descriptors unavailable without exclusive drain');}
    $reject(fn()=>$process->script($child,[$directory.'/access']));$ordinary=$access->enter();$check($ordinary!==null,'normal access available');
    try{$access->childDescriptors();throw new LogicException('Unexpected success');}catch(RuntimeException $error){$check($error->getMessage()==='UPDATE_TASK_NOT_AUTHORIZED','ordinary shared lease cannot authorize a task');}$ordinary->release();
    $access->exclusive(function()use($access,$process,$child,$directory,$check,$reject){
        $value=json_decode($process->guardedScript($child,[$directory.'/access'],$access),true,4,JSON_THROW_ON_ERROR);$check($value[0]===1&&$value[1]!==getmypid(),'drained owner authorizes real child with protocol 1');
        $check($access->enter()===null,'child closing duplicate handles does not unlock parent');
        $reject(fn()=>$process->script($child,[$directory.'/access']));
        $check($access->enter()===null,'missing inherited descriptors cannot release update lock');
    });$lease=$access->enter();$check($lease!==null,'access reopens after parent completion');$lease->release();
    // A pending marker and shared access lock are insufficient, even with inherited descriptors.
    file_put_contents($directory.'/access/pending','update');$shared=fopen($directory.'/access/access.lock','r+');$owner=fopen($directory.'/access/operation.lock','r+');flock($shared,LOCK_SH);flock($owner,LOCK_EX);
    $childProcess=proc_open([PHP_BINARY,$child,$directory.'/access'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],3=>$shared,4=>$owner],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$check(proc_close($childProcess)===1&&$out===''&&$err==='','pending without exclusive drain rejected');flock($shared,LOCK_UN);flock($owner,LOCK_UN);fclose($shared);fclose($owner);unlink($directory.'/access/pending');
    mkdir($directory.'/other');$other=new UpdateAccess($directory.'/other/access');$otherLease=$other->enter();$otherLease->release();
    $access->exclusive(function()use($process,$child,$directory,$access,$reject){$reject(fn()=>$process->guardedScript($child,[$directory.'/other/access'],$access));});
    $clone=$directory.'/clone';mkdir($clone);foreach(['app','app/Services','bin','config','storage'] as $path)mkdir($clone.'/'.$path,0700);copy($root.'/app/Services/UpdateAccess.php',$clone.'/app/Services/UpdateAccess.php');copy($root.'/bin/update-task.php',$clone.'/bin/update-task.php');
    $marker=$directory.'/config-read';file_put_contents($clone.'/config/config.php','<?php file_put_contents('.var_export($marker,true).',"read");return [];');
    $reject(fn()=>$process->script($clone.'/bin/update-task.php',['health','1.0.0']));$check(!file_exists($marker),'unauthorized runtime task reads no configuration');
    $access->exclusive(function()use($process,$clone,$access,$reject){$reject(fn()=>$process->guardedScript($clone.'/bin/update-task.php',['health','1.0.0'],$access));});
    $check(!file_exists($marker),'descriptors from another installation rejected before config');
    $orphanDirectory=$directory.'/orphan';mkdir($orphanDirectory);$started=$directory.'/orphan-started';$done=$directory.'/orphan-done';$orphanChild=$directory.'/orphan-child.php';$orphanParent=$directory.'/orphan-parent.php';
    file_put_contents($orphanChild,'<?php require '.var_export($root.'/app/Services/UpdateAccess.php',true).';$lease=App\\Services\\UpdateAccess::authorizeInherited($argv[1]);file_put_contents('.var_export($started,true).',"ready");usleep(2000000);file_put_contents('.var_export($done,true).',"done");');
    file_put_contents($orphanParent,'<?php require '.var_export($root.'/app/Services/UpdateAccess.php',true).';$gate=new App\\Services\\UpdateAccess($argv[1]);$gate->exclusive(function()use($gate){$process=proc_open([PHP_BINARY,'.var_export($orphanChild,true).','.'$GLOBALS["argv"][1]],'.var_export([0=>['file','/dev/null','r'],1=>['file',$directory.'/orphan-out','w'],2=>['file',$directory.'/orphan-error','w']],true).'+$gate->childDescriptors(),$pipes);if(!is_resource($process))exit(1);sleep(30);});');
    $parent=proc_open([PHP_BINARY,$orphanParent,$orphanDirectory.'/access'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$parentPipes);fclose($parentPipes[0]);$deadline=microtime(true)+5;
    while(!is_file($started)&&microtime(true)<$deadline){clearstatcache(true,$started);usleep(10000);}
    $ready=is_file($started);proc_terminate($parent,9);$parentOutput=stream_get_contents($parentPipes[1]);$parentError=stream_get_contents($parentPipes[2]);fclose($parentPipes[1]);fclose($parentPipes[2]);$exit=proc_close($parent);$check($ready&&$exit!==0&&$parentOutput===''&&$parentError==='','real exclusive parent killed while child is running');
    $probe=fopen($orphanDirectory.'/access/access.lock','r+');$locked=flock($probe,LOCK_SH|LOCK_NB);if($locked)flock($probe,LOCK_UN);fclose($probe);$check(!$locked,'child retains exclusive access after parent death');
    $orphanGate=new UpdateAccess($orphanDirectory.'/access');try{$orphanGate->exclusive(static fn()=>null,true);throw new LogicException('Unexpected recovery');}catch(App\Services\UpdateAccessPaused){$check(true,'recovery cannot race still-running orphan child');}
    $deadline=microtime(true)+5;while(!is_file($done)&&microtime(true)<$deadline){clearstatcache(true,$done);usleep(10000);}$check(is_file($done)&&file_get_contents($directory.'/orphan-error')==='','child completed normally with inherited locks');
    $recovered=false;for($attempt=0;$attempt<100;++$attempt){try{$orphanGate->exclusive(static fn()=>null,true);$recovered=true;break;}catch(App\Services\UpdateAccessPaused){usleep(10000);}}
    $check($recovered,'recovery permitted only after child closes inherited descriptors');
    echo "$count inherited update task checks passed.\n";
}finally{$remove($directory);}

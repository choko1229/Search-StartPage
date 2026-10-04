<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\UpdateRescue;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$directory=sys_get_temp_dir().'/update-rescue-'.bin2hex(random_bytes(8));mkdir($directory,0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$remove=function(string $path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
$run=static function(string $file,string $action):array{$process=proc_open([PHP_BINARY,$file,$action],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Test child failed');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($process),$out,$err];};
try{
    mkdir($directory.'/storage',0700);mkdir($directory.'/bin',0700);copy($root.'/bin/update-rescue.php',$directory.'/bin/update-rescue.php');
    foreach(UpdateRescue::FILES as $file){if(!is_dir(dirname($directory.'/'.$file)))mkdir(dirname($directory.'/'.$file),0700,true);copy($root.'/'.$file,$directory.'/'.$file);}
    mkdir($directory.'/config',0700);$marker=$directory.'/config-read';file_put_contents($directory.'/config/config.php','<?php file_put_contents('.var_export($marker,true).',"read");throw new RuntimeException("fixture-secret");');
    $service=new UpdateRescue();$service->prepare($directory);$runtime=$directory.'/storage/updates/rescue/runtime.json';$json=file_get_contents($runtime);$manifest=json_decode($json,true,8,JSON_THROW_ON_ERROR);$capsule=dirname($runtime).'/'.$manifest['id'];$launcher=$directory.'/storage/updates/rescue.php';
    $check(array_keys($manifest['files'])===UpdateRescue::FILES,'fixed recovery dependency allowlist captured');
    $check(!is_file($capsule.'/config/config.php')&&!is_file($capsule.'/app/autoload.php')&&!is_dir($capsule.'/storage'),'config user data and live autoloader excluded');
    $check((fileperms($launcher)&0077)===0&&(fileperms($runtime)&0077)===0&&(fileperms($capsule)&0077)===0,'private launcher manifest and capsule permissions');
    [$code,$out,$err]=$run($launcher,'status');$check($code===0&&$err===''&&json_decode($out,true)===['format'=>1,'phase'=>null,'version'=>null],'standalone status starts without live app or database');
    file_put_contents($directory.'/app/autoload.php','<?php file_put_contents('.var_export($marker,true).',"live");throw new RuntimeException("fixture-secret");');
    file_put_contents($directory.'/app/Services/UpdateEngine.php','<?php syntax broken fixture-secret');file_put_contents($directory.'/app/Services/UpdateDatabase.php','<?php syntax broken fixture-secret');
    [$code,$out,$err]=$run($launcher,'status');$check($code===0&&$err===''&&!is_file($marker),'broken live autoloader engine and database code never executed');
    $file=$capsule.'/app/Services/UpdateFiles.php';$saved=file_get_contents($file);file_put_contents($file,$saved.'broken');[$code,$out,$err]=$run($launcher,'status');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n"&&!is_file($marker),'capsule hash mismatch refuses startup without secret output');file_put_contents($file,$saved);
    $bad=$manifest;$bad['id']='../app';file_put_contents($runtime,json_encode($bad));[$code,$out,$err]=$run($launcher,'status');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n",'capsule path traversal refused');file_put_contents($runtime,$json);
    $bad=$manifest;$bad['files']['config/config.php']=['bytes'=>1,'sha256'=>str_repeat('a',64)];file_put_contents($runtime,json_encode($bad));[$code,$out,$err]=$run($launcher,'status');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n",'extra executable file refused');file_put_contents($runtime,$json);
    chmod($capsule,0755);[$code,$out,$err]=$run($launcher,'status');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n",'public capsule directory refused');chmod($capsule,0700);
    rename($runtime,$runtime.'.saved');symlink($runtime.'.saved',$runtime);[$code,$out,$err]=$run($launcher,'status');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n",'linked manifest refused');unlink($runtime);rename($runtime.'.saved',$runtime);
    [$code,$out,$err]=$run($launcher,'apply');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n",'recovery entry cannot install arbitrary package');
    [$code,$out,$err]=$run($directory.'/bin/update-rescue.php','status');$check($code===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n",'distribution launcher cannot target another root');
    try{$service->prepare($directory);throw new LogicException('Invalid capsule published');}catch(Throwable $error){$check(!$error instanceof LogicException&&file_get_contents($runtime)===$json,'invalid live PHP cannot replace valid rescue pointer');}
    foreach(UpdateRescue::FILES as $file)copy($root.'/'.$file,$directory.'/'.$file);$service->prepare($directory);$new=json_decode(file_get_contents($runtime),true);
    $check($new['id']!==$manifest['id']&&!is_dir($capsule)&&count(array_filter(scandir(dirname($runtime)),fn($name)=>preg_match('/^[a-f0-9]{32}$/D',$name)))===1,'new verified capsule retires earlier and failed preparations');
    [$code,$out,$err]=$run($launcher,'status');$check($code===0&&$err===''&&!is_file($marker),'refreshed capsule remains independent from private config');
    $lock=fopen(dirname($runtime).'/.write.lock','c');flock($lock,LOCK_EX);
    $process=proc_open([PHP_BINARY,$launcher,'status'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);usleep(150000);$waiting=proc_get_status($process)['running'];flock($lock,LOCK_UN);fclose($lock);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $check($waiting&&proc_close($process)===0&&$err===''&&json_decode($out,true)['phase']===null,'launcher waits until capsule publication lock is released');
    mkdir($directory.'/storage/updates/engine',0700);$lock=fopen($directory.'/storage/updates/engine/.write.lock','c');chmod($directory.'/storage/updates/engine/.write.lock',0600);flock($lock,LOCK_EX);
    $process=proc_open([PHP_BINARY,$launcher,'recover'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);usleep(150000);$waiting=proc_get_status($process)['running'];
    $service->prepare($directory);flock($lock,LOCK_UN);fclose($lock);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $check($waiting&&proc_close($process)===1&&$out===''&&$err==="UPDATE_RESCUE_FAILED\n"&&!is_file($marker),'waiting engine recovery releases rescue lock and permits capsule rotation');
    echo "$count update rescue checks passed.\n";
}finally{$remove($directory);}

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{UpdateProcess,UpdateStage,ReleasePackageBuilder,UpdatePackage};
use App\Http\HttpException;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$directory=sys_get_temp_dir().'/update-stage-'.bin2hex(random_bytes(8));mkdir($directory,0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $call,string $code)use($check){try{$call();throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode===$code,'reject '.$code);$check(!str_contains($error->getMessage(),'generated secret'),'child diagnostics not exposed');}};
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
try{
    $source=$directory.'/source';mkdir($source);foreach(['app','config','public'] as $sub)mkdir($source.'/'.$sub);
    $marker=$directory.'/executed';
    foreach(['VERSION'=>'1.0.0','app/autoload.php'=>'<?php','app/bootstrap.php'=>'<?php throw new RuntimeException("generated secret");','public/index.php'=>'<?php file_put_contents('.var_export($marker,true).',"executed");','config/config.example.php'=>'<?php','app/helper.inc'=>'<?php function helper(): string {return "ok";}','public/template.phtml'=>'<html><?php echo "ok"; ?></html>'] as $path=>$body)file_put_contents($source.'/'.$path,$body);
    $build=(new ReleasePackageBuilder())->build($source,$directory.'/package.tar');$manifest=(new UpdatePackage())->verify($directory.'/package.tar',$directory.'/stage','1.0.0');$validator=new UpdateStage();
    $check($validator->validate($directory.'/stage',$manifest)===6,'PHP and include/template files linted');$check(!file_exists($marker),'candidate runtime code never executed by lint');
    $invalid='<?php function broken( { /* generated secret */';file_put_contents($directory.'/stage/app/bootstrap.php',$invalid);$bad=$manifest;$bad['files']['app/bootstrap.php']=['bytes'=>strlen($invalid),'sha256'=>hash('sha256',$invalid)];
    $reject(fn()=>$validator->validate($directory.'/stage',$bad),'UPDATE_PROCESS_FAILED');$check(!file_exists($marker),'syntax failure executes no other candidate files');
    $reject(fn()=>$validator->validate($directory.'/stage',$manifest),'INVALID_UPDATE_PACKAGE');
    file_put_contents($directory.'/stage/app/bootstrap.php',file_get_contents($source.'/app/bootstrap.php'));file_put_contents($directory.'/stage/VERSION','2.0.0');$bad=$manifest;$bad['files']['VERSION']=['bytes'=>5,'sha256'=>hash('sha256','2.0.0')];$reject(fn()=>$validator->validate($directory.'/stage',$bad),'INVALID_UPDATE_PACKAGE');file_put_contents($directory.'/stage/VERSION','1.0.0');
    unlink($directory.'/stage/app/bootstrap.php');symlink($source.'/app/bootstrap.php',$directory.'/stage/app/bootstrap.php');$reject(fn()=>$validator->validate($directory.'/stage',$manifest),'INVALID_UPDATE_PACKAGE');unlink($directory.'/stage/app/bootstrap.php');file_put_contents($directory.'/stage/app/bootstrap.php',file_get_contents($source.'/app/bootstrap.php'));
    $process=new UpdateProcess();$script=$directory.'/child.php';file_put_contents($script,'<?php echo json_encode([getmypid(),$argv[1]]);');
    $value=json_decode($process->script($script,['literal; $(generated secret)']),true,4,JSON_THROW_ON_ERROR);$check($value[0]!==getmypid()&&$value[1]==='literal; $(generated secret)','new PHP process receives shell characters literally');
    file_put_contents($script,'<?php fwrite(STDERR,"generated secret");exit(3);');$reject(fn()=>$process->script($script),'UPDATE_PROCESS_FAILED');
    file_put_contents($script,'<?php while(true){fwrite(STDOUT,str_repeat("x",8192));fwrite(STDERR,str_repeat("generated secret",1024));}');$reject(fn()=>$process->script($script),'UPDATE_PROCESS_OUTPUT_LIMIT');
    file_put_contents($script,'<?php usleep(3000000);file_put_contents('.var_export($marker,true).',"executed");');$started=hrtime(true);$reject(fn()=>$process->script($script,[],1),'UPDATE_PROCESS_TIMEOUT');$check((hrtime(true)-$started)/1e9<2.5&&!file_exists($marker),'timeout terminates and reaps child before later writes');
    $reject(fn()=>$process->script($script,[],0),'INVALID_UPDATE_PROCESS');$reject(fn()=>$process->script($script,["bad\0argument"]),'INVALID_UPDATE_PROCESS');
    $reject(fn()=>$process->script($script,['key'=>'value']),'INVALID_UPDATE_PROCESS');symlink($script,$directory.'/linked.php');$reject(fn()=>$process->script($directory.'/linked.php'),'INVALID_UPDATE_PATH');
    file_put_contents($script,'<?php echo "clean child";');$check($process->script($script)==='clean child','later process remains usable after all failures');
    $working=getcwd();try{chdir($directory);$check($process->script('child.php')==='clean child','relative script resolves before changing child working directory');}finally{chdir($working);}
    echo "$count update stage/process checks passed.\n";
}finally{$remove($directory);}

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{UpdateFiles,ReleasePackageBuilder,UpdatePackage};
use App\Http\HttpException;
if(PHP_SAPI!=='cli')exit(1);
$checks=0;$check=static function(bool $ok,string $name)use(&$checks){if(!$ok)throw new RuntimeException($name);++$checks;echo "PASS: $name\n";};
$directory=sys_get_temp_dir().'/update-files-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
try{
    foreach(['live','new'] as $name){$root=$directory.'/'.$name;mkdir($root);foreach(['app','config','public','storage'] as $sub)mkdir($root.'/'.$sub);foreach(['VERSION'=>'1.0.0','README.md'=>'old readme','composer.json'=>'{}','app/autoload.php'=>'<?php','app/bootstrap.php'=>'<?php','config/config.example.php'=>'<?php','public/index.php'=>'<?php','app/old.php'=>'old'] as $path=>$body)file_put_contents($root.'/'.$path,$body);file_put_contents($root.'/config/config.php','secret marker');file_put_contents($root.'/storage/upload.png','upload marker');}
    $live=$directory.'/live';$new=$directory.'/new';file_put_contents($new.'/VERSION','2.0.0');file_put_contents($new.'/README.md','new readme');unlink($new.'/app/old.php');file_put_contents($new.'/app/new.php','new');
    $files=new UpdateFiles();$files->snapshot($live,$directory.'/before.tar');(new ReleasePackageBuilder())->build($new,$directory.'/new.tar');
    $old=(new UpdatePackage())->verify($directory.'/before.tar',$directory.'/old-stage','1.0.0');$next=(new UpdatePackage())->verify($directory.'/new.tar',$directory.'/new-stage','2.0.0');
    $protected=fn()=>file_get_contents($live.'/config/config.php')==='secret marker'&&file_get_contents($live.'/storage/upload.png')==='upload marker';
    $files->replace($directory.'/new-stage',$live,$next,$old);
    $check(file_get_contents($live.'/VERSION')==='2.0.0','version replaced');$check(file_get_contents($live.'/README.md')==='new readme','changed content replaced');$check(is_file($live.'/app/new.php')&&!file_exists($live.'/app/old.php'),'added and removed app files reconciled');$check($protected(),'config and uploads preserved during replace');
    $restored=$files->restore($directory.'/before.tar',$directory.'/restore-stage',$live,'v1.0.0',$next);
    $check($restored===$old,'restore uses exact old manifest');$check(file_get_contents($live.'/VERSION')==='1.0.0'&&file_get_contents($live.'/README.md')==='old readme','old version and content restored');$check(is_file($live.'/app/old.php')&&!file_exists($live.'/app/new.php'),'rollback recovers removed files and removes new files');$check($protected(),'config and uploads preserved during restore');$check(!file_exists($directory.'/restore-stage'),'temporary restore stage removed');
    foreach($old['files'] as $path=>$meta)$check(hash_file('sha256',$live.'/'.$path)===$meta['sha256'],'restored hash '.$path);
    $changed=0;try{$files->replace($directory.'/new-stage',$live,$next,$old,static function()use(&$changed){if(++$changed===3)throw new RuntimeException('generated fault');});throw new LogicException('Unexpected success');}catch(RuntimeException $error){$check($changed===3&&$error->getMessage()==='generated fault','real partial replace failure injected');}
    $files->restore($directory.'/before.tar',$directory.'/failure-restore',$live,'1.0.0',$next);foreach($old['files'] as $path=>$meta)$check(hash_file('sha256',$live.'/'.$path)===$meta['sha256'],'partial failure restored '.$path);$check($protected(),'protected data intact after partial failure restore');
    file_put_contents($directory.'/new-stage/README.md','tampered');try{$files->replace($directory.'/new-stage',$live,$next,$old);throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='INVALID_UPDATE_PACKAGE','tampered stage rejected');}$check(file_get_contents($live.'/VERSION')==='1.0.0','preflight failure changes no version');
    file_put_contents($directory.'/new-stage/README.md','new readme');unlink($live.'/README.md');symlink($live.'/config/config.php',$live.'/README.md');try{$files->replace($directory.'/new-stage',$live,$next,$old);throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='INVALID_UPDATE_PATH','destination symlink rejected');}$check($protected(),'symlink cannot overwrite config');unlink($live.'/README.md');file_put_contents($live.'/README.md','old readme');
    mkdir($live.'/app/new.php');try{$files->replace($directory.'/new-stage',$live,$next,$old);throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='INVALID_UPDATE_PATH','destination directory rejected');}rmdir($live.'/app/new.php');
    $bad=$next;$bad['files']['config/config.php']=['bytes'=>13,'sha256'=>hash('sha256','secret marker')];try{$files->replace($directory.'/new-stage',$live,$bad,$old);throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='INVALID_UPDATE_PACKAGE','protected manifest path rejected');}
    try{$files->replace($live,$live,$old,$old);throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='INVALID_UPDATE_PATH','stage and live cannot overlap');}
    $bad=$next;$bad['version']='3.0.0';try{$files->replace($directory.'/new-stage',$live,$bad,$old);throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='INVALID_UPDATE_PACKAGE','manifest and VERSION cannot differ');}
    $check(glob($live.'/.update-*')===[]&&glob($live.'/app/.update-*')===[],'no temporary replacement files remain');
    echo "$checks update file checks passed.\n";
}finally{$remove($directory);}

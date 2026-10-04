<?php
declare(strict_types=1);
// This file is installed at storage/updates/rescue.php before file replacement.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
set_error_handler(static function(){throw new RuntimeException('Recovery I/O failed');});
$lock=null;$bufferLevel=ob_get_level();
try{
    if(count($argv)!==2||!in_array($argv[1],['recover','rollback','status'],true))throw new RuntimeException('Invalid recovery action');
    $root=dirname(__DIR__,2);$directory=$root.'/storage/updates/rescue';
    if(str_replace('\\','/',__FILE__)!==str_replace('\\','/',$root.'/storage/updates/rescue.php'))throw new RuntimeException('Private launcher required');
    $checkDirectory=static function(string $path,bool $private=true):void{
        $cursor=$path;while(true){if(is_link($cursor))throw new RuntimeException('Recovery link refused');$parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;}
        clearstatcache(true,$path);$mode=fileperms($path);if(!is_dir($path)||$mode===false||($private&&($mode&0077)!==0))throw new RuntimeException('Recovery directory refused');
    };
    $read=static function(string $path,int $limit)use($checkDirectory):string{
        $checkDirectory(dirname($path));clearstatcache(true,$path);$mode=fileperms($path);
        if(is_link($path)||!is_file($path)||$mode===false||($mode&0077)!==0||filesize($path)<1||filesize($path)>$limit)throw new RuntimeException('Recovery file refused');
        $bytes=file_get_contents($path);if($bytes===false)throw new RuntimeException('Recovery read failed');return $bytes;
    };
    $checkDirectory($root,false);$checkDirectory($root.'/storage',false);$checkDirectory($root.'/storage/updates');$checkDirectory($directory);
    $lockPath=$directory.'/.write.lock';clearstatcache(true,$lockPath);
    if(is_link($lockPath)||!is_file($lockPath)||(fileperms($lockPath)&0077)!==0)throw new RuntimeException('Recovery lock refused');
    $lock=fopen($lockPath,'r');if($lock===false||!flock($lock,LOCK_SH))throw new RuntimeException('Recovery lock failed');
    $manifest=json_decode($read($directory.'/runtime.json',65536),true,8,JSON_THROW_ON_ERROR);
    $expected=[
        'app/Http/HttpException.php','app/Config.php','app/Database/Database.php',
        'app/Services/UpdatePackagePaths.php','app/Services/ReleaseCatalog.php','app/Services/UpdateManifest.php',
        'app/Services/LogFileLock.php','app/Services/UpdateAccess.php','app/Services/UpdateJournal.php',
        'app/Services/UpdateProcess.php','app/Services/UpdateStage.php','app/Services/UpdatePackage.php',
        'app/Services/ReleasePackageBuilder.php','app/Services/UpdateFiles.php','app/Services/UpdateDatabase.php',
        'app/Services/UpdateRuntime.php','app/Services/UpdateCompatibility.php','app/Services/UpdateEngine.php',
        'app/Services/UpdateRescue.php',
    ];
    if(!is_array($manifest)||array_keys($manifest)!==['format','id','files']||$manifest['format']!==1||!is_string($manifest['id'])||!preg_match('/^[a-f0-9]{32}$/D',$manifest['id'])||!is_array($manifest['files'])||array_keys($manifest['files'])!==$expected)throw new RuntimeException('Recovery manifest refused');
    $capsule=$directory.'/'.$manifest['id'];$checkDirectory($capsule);
    foreach($expected as $file){
        $metadata=$manifest['files'][$file];$bytes=$read($capsule.'/'.$file,1048576);
        if(!is_array($metadata)||array_keys($metadata)!==['bytes','sha256']||!is_int($metadata['bytes'])||strlen($bytes)!==$metadata['bytes']||!is_string($metadata['sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$metadata['sha256'])||!hash_equals($metadata['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Recovery hash refused');
    }
    // Fixed allowlist; no autoloader or config is taken from the potentially broken live app.
    foreach($expected as $file)require_once $capsule.'/'.$file;
    if(App\Services\UpdateRescue::FILES!==$expected)throw new RuntimeException('Recovery protocol mismatch');
    flock($lock,LOCK_UN);fclose($lock);$lock=null;
    ob_start();$engine=new App\Services\UpdateEngine($root);$state=match($argv[1]){'recover'=>$engine->recover(),'rollback'=>$engine->rollback(),default=>$engine->status()};
    if(ob_get_clean()!=='')throw new RuntimeException('Unexpected recovery output');
    echo json_encode(['format'=>1,'phase'=>$state['job']['phase']??null,'version'=>($state['job']['phase']??null)==='complete'?$state['job']['to_version']:($state['job']['from_version']??null)],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable){
    while(ob_get_level()>$bufferLevel)ob_end_clean();fwrite(STDERR,"UPDATE_RESCUE_FAILED\n");exit(1);
}finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}

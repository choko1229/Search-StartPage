<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Private, immutable recovery code captured before touching managed application files. */
final class UpdateRescue
{
    public const FILES=[
        'app/Http/HttpException.php','app/Config.php','app/Database/Database.php',
        'app/Services/UpdatePackagePaths.php','app/Services/ReleaseCatalog.php','app/Services/UpdateManifest.php',
        'app/Services/LogFileLock.php','app/Services/UpdateAccess.php','app/Services/UpdateJournal.php',
        'app/Services/UpdateProcess.php','app/Services/UpdateStage.php','app/Services/UpdatePackage.php',
        'app/Services/ReleasePackageBuilder.php','app/Services/UpdateFiles.php','app/Services/UpdateDatabase.php',
        'app/Services/UpdateRuntime.php','app/Services/UpdateCompatibility.php','app/Services/UpdateEngine.php',
        'app/Services/UpdateRescue.php',
    ];
    private static function directory(string $path): void
    {
        if(!is_dir($path)&&!is_link($path)&&!@mkdir($path,0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        UpdatePackagePaths::directory($path);clearstatcache(true,$path);
        $mode=fileperms($path);if($mode===false||($mode&0077)!==0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    private static function write(string $path,string $bytes): void
    {
        $stream=@fopen($path,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        try{
            if(!chmod($path,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $offset=0;while($offset<strlen($bytes)){$written=fwrite($stream,substr($bytes,$offset));if($written===false||$written===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$written;}
            if(!fflush($stream)||!fsync($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        }finally{fclose($stream);}
    }
    private static function publish(string $path,string $bytes): void
    {
        if(is_link($path)||(file_exists($path)&&!is_file($path)))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        $temporary=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try{self::write($temporary,$bytes);if(!rename($temporary,$path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        finally{if(is_file($temporary)&&!unlink($temporary))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
    }
    private static function source(string $root,string $file): string
    {
        $path=$root.'/'.$file;UpdatePackagePaths::directory(dirname($path));clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||filesize($path)<1||filesize($path)>1048576)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        $bytes=file_get_contents($path);if($bytes===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');return $bytes;
    }
    private static function remove(string $path): void
    {
        if(is_link($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        if(is_dir($path)){UpdatePackagePaths::directory($path);foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')self::remove($path.'/'.$name);if(!rmdir($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        elseif(!is_file($path)||!unlink($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    /** Caller owns the engine lock. No credentials, user data, or HTTP entry is copied. */
    public function prepare(string $root): void
    {
        if(PHP_SAPI!=='cli')throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
        UpdatePackagePaths::directory($root);self::directory($root.'/storage/updates');$directory=$root.'/storage/updates/rescue';self::directory($directory);
        LogFileLock::run($directory,function()use($root,$directory){
            $id=bin2hex(random_bytes(16));$capsule=$directory.'/'.$id;self::directory($capsule);$files=[];
            foreach(self::FILES as $file){
                $bytes=self::source($root,$file);$parent=$capsule;foreach(explode('/',dirname($file)) as $part){$parent.='/'.$part;self::directory($parent);}
                self::write($capsule.'/'.$file,$bytes);$files[$file]=['bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];
            }
            $process=new UpdateProcess();foreach(self::FILES as $file)$process->lint($capsule.'/'.$file);
            // Load every recovery dependency before replacement, avoiding later live-code autoload.
            foreach(self::FILES as $file){$class='App\\'.str_replace('/','\\',substr($file,4,-4));if(!class_exists($class,false))require_once $capsule.'/'.$file;}
            $launcher=self::source($root,'bin/update-rescue.php');
            $process->lint($root.'/bin/update-rescue.php');
            self::publish($root.'/storage/updates/rescue.php',$launcher);
            self::publish($directory.'/runtime.json',json_encode(['format'=>1,'id'=>$id,'files'=>$files],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
            foreach(scandir($directory) as $name){
                if(in_array($name,['.','..','.write.lock','runtime.json',$id],true))continue;
                if(!preg_match('/^[a-f0-9]{32}$/D',$name))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');self::remove($directory.'/'.$name);
            }
        });
    }
}

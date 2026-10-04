<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Called only while the update engine holds its lock and stops application writes. */
final class UpdateFiles
{
    public function snapshot(string $root,string $archive): array
    {
        return (new ReleasePackageBuilder())->build($root,$archive);
    }
    private static function target(string $root,string $path,bool $create=false): string
    {
        UpdatePackagePaths::validate($path);UpdatePackagePaths::directory($root);
        $parts=explode('/',$path);array_pop($parts);$directory=$root;
        foreach($parts as $part){
            $directory.='/'.$part;
            if(is_link($directory)||(!is_dir($directory)&&file_exists($directory)))throw new HttpException(422,'INVALID_UPDATE_PATH');
            if(!is_dir($directory)&&$create&&!mkdir($directory,0755))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        }
        $target=$root.'/'.$path;
        if(is_link($target)||(file_exists($target)&&!is_file($target)))throw new HttpException(422,'INVALID_UPDATE_PATH');
        return $target;
    }
    private static function manifest(array $manifest): array
    {
        return UpdateManifest::decode(json_encode($manifest,JSON_THROW_ON_ERROR),$manifest['version']??'');
    }
    /** Full preflight before the first mutation. A later I/O error must trigger engine rollback. */
    public function replace(string $stage,string $root,array $manifest,array $previous,?\Closure $afterFile=null): void
    {
        $manifest=self::manifest($manifest);$previous=self::manifest($previous);UpdatePackagePaths::directory($stage);UpdatePackagePaths::directory($root);
        $stagePath=str_replace('\\','/',realpath($stage));$rootPath=str_replace('\\','/',realpath($root));
        if($stagePath===$rootPath||str_starts_with($stagePath,$rootPath.'/')||str_starts_with($rootPath,$stagePath.'/'))throw new HttpException(422,'INVALID_UPDATE_PATH');
        foreach($manifest['files'] as $path=>$metadata){
            $source=self::target($stage,$path);self::target($root,$path);
            if(!is_file($source)||filesize($source)!==$metadata['bytes']||!hash_equals($metadata['sha256'],hash_file('sha256',$source)))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        }
        foreach(array_keys($previous['files']) as $path)self::target($root,$path);
        if(trim(file_get_contents(self::target($stage,'VERSION')))!==$manifest['version'])throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        foreach($manifest['files'] as $path=>$metadata){
            $target=self::target($root,$path,true);$temporary=tempnam(dirname($target),'.update-');
            if($temporary===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            try{
                if(!chmod($temporary,0600)||!copy(self::target($stage,$path),$temporary)||filesize($temporary)!==$metadata['bytes']
                    ||!hash_equals($metadata['sha256'],hash_file('sha256',$temporary))||!rename($temporary,$target))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            }finally{if(is_file($temporary)&&!unlink($temporary))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
            if($afterFile)$afterFile($path);
        }
        foreach(array_diff(array_keys($previous['files']),array_keys($manifest['files'])) as $path){
            $target=self::target($root,$path);if(is_file($target)&&!unlink($target))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            if($afterFile)$afterFile($path);
        }
    }
    /** Snapshot is verified into a fresh private stage before restoring managed files. */
    public function restore(string $archive,string $stage,string $root,string $version,array $replacedManifest): array
    {
        $manifest=(new UpdatePackage())->verify($archive,$stage,$version);
        try{$this->replace($stage,$root,$manifest,$replacedManifest);return $manifest;}
        finally{self::removeStage($stage);}
    }
    private static function removeStage(string $path): void
    {
        UpdatePackagePaths::directory($path);
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file){
            if($file->isLink())throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            if(!($file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname())))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        }
        if(!rmdir($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
}

<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class ReleasePackageBuilder
{
    public function build(string $root,string $archive): array
    {
        UpdatePackagePaths::directory($root);UpdatePackagePaths::directory(dirname($archive));
        if(file_exists($archive)||is_link($archive))throw new HttpException(422,'UPDATE_PACKAGE_EXISTS');
        $files=[];
        foreach(['app','public','lang','database','bin','extension'] as $directory){
            if(!is_dir($root.'/'.$directory))continue;UpdatePackagePaths::directory($root.'/'.$directory);
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory,\FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                $path=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
                if($file->isLink())throw new HttpException(422,'INVALID_UPDATE_PATH');
                if(!$file->isFile())continue;
                // Development previews and tests are never shipped as application files.
                if(preg_match('~(?:^|/)(?:_test|tests)(?:/|$)~i',$path))continue;
                UpdatePackagePaths::validate($path);
                if($file->getSize()>UpdateManifest::FILE_LIMIT||count($files)>=10000)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
                $files[$path]=['bytes'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getPathname())];
            }
        }
        foreach(['VERSION','README.md','composer.json','config/config.example.php','config/providers.php'] as $path){
            if(!is_file($root.'/'.$path))continue;
            UpdatePackagePaths::directory(dirname($root.'/'.$path));
            if(is_link($root.'/'.$path))throw new HttpException(422,'INVALID_UPDATE_PATH');
            if(filesize($root.'/'.$path)>($path==='VERSION'?512:UpdateManifest::FILE_LIMIT))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
            UpdatePackagePaths::validate($path);$files[$path]=['bytes'=>filesize($root.'/'.$path),'sha256'=>hash_file('sha256',$root.'/'.$path)];
        }
        ksort($files);$version=UpdateManifest::version(trim(file_get_contents($root.'/VERSION')));
        $manifest=['format'=>1,'version'=>$version,'php_min'=>'8.2.0','files'=>$files];
        $json=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);UpdateManifest::decode($json,$version);
        $stream=fopen($archive,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$success=false;
        try{
            self::write($stream,UpdatePackage::header('release-manifest.json',strlen($json)));self::write($stream,$json);self::padding($stream,strlen($json));
            foreach($files as $path=>$metadata){
                self::write($stream,UpdatePackage::header($path,$metadata['bytes']));
                $source=fopen($root.'/'.$path,'rb');if($source===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$hash=hash_init('sha256');$bytes=0;
                try{while(!feof($source)){$data=fread($source,65536);if($data===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');if($data==='')break;$bytes+=strlen($data);if($bytes>$metadata['bytes'])throw new HttpException(409,'UPDATE_SOURCE_CHANGED');hash_update($hash,$data);self::write($stream,$data);}}
                finally{fclose($source);}
                if($bytes!==$metadata['bytes']||!hash_equals($metadata['sha256'],hash_final($hash)))throw new HttpException(409,'UPDATE_SOURCE_CHANGED');
                self::padding($stream,$bytes);
            }
            self::write($stream,str_repeat("\0",1024));if(!fflush($stream)||!chmod($archive,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$success=true;
        }finally{fclose($stream);if(!$success&&is_file($archive))unlink($archive);}
        return ['version'=>$version,'files'=>count($files),'bytes'=>filesize($archive),'sha256'=>hash_file('sha256',$archive)];
    }
    private static function write($stream,string $data): void {if(fwrite($stream,$data)!==strlen($data))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
    private static function padding($stream,int $size): void {if($size%512)self::write($stream,str_repeat("\0",512-$size%512));}
}

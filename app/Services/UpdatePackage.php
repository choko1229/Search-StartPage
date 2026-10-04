<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** A small USTAR subset: ordinary files, manifest first, no links or extensions. */
final class UpdatePackage
{
    public static function header(string $path,int $size): string
    {
        $name=$path;$prefix='';
        if(strlen($name)>100){$split=strrpos($path,'/');if($split===false)throw new HttpException(422,'INVALID_UPDATE_PATH');$prefix=substr($path,0,$split);$name=substr($path,$split+1);}
        if(strlen($name)>100||strlen($prefix)>155)throw new HttpException(422,'INVALID_UPDATE_PATH');
        $header=str_pad($name,100,"\0")."0000644\0"."0000000\0"."0000000\0".sprintf('%011o',$size)."\0"."00000000000\0".str_repeat(' ',8).'0'.str_repeat("\0",100)."ustar\0".'00'.str_repeat("\0",80).str_pad($prefix,155,"\0").str_repeat("\0",12);
        $checksum=array_sum(unpack('C*',$header));return substr_replace($header,sprintf('%06o',$checksum)."\0 ",148,8);
    }
    private static function octal(string $field): int
    {
        $value=trim($field," \0");if(!preg_match('/^[0-7]{1,11}$/D',$value))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');return intval($value,8);
    }
    private static function block($stream,int $length): string
    {
        $value='';while(strlen($value)<$length){$next=fread($stream,min(65536,$length-strlen($value)));if($next===false||$next==='')throw new HttpException(422,'INVALID_UPDATE_PACKAGE');$value.=$next;}return $value;
    }
    private static function metadata(string $header): array
    {
        $checksum=self::octal(substr($header,148,8));$copy=substr_replace($header,str_repeat(' ',8),148,8);
        if($checksum!==array_sum(unpack('C*',$copy))||substr($header,257,8)!=="ustar\0".'00'||!in_array($header[156],['0',"\0"],true)
            ||trim(substr($header,157,100),"\0")!==''||self::octal(substr($header,100,8))!==0644)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        $name=rtrim(substr($header,0,100),"\0");$prefix=rtrim(substr($header,345,155),"\0");
        return [$prefix===''?$name:$prefix.'/'.$name,self::octal(substr($header,124,12))];
    }
    /** Stage must not already exist. All callers use a private, generated path. */
    public function verify(string $archive,string $stage,string $expectedVersion,bool $lint=false): array
    {
        UpdatePackagePaths::directory(dirname($stage));
        if(file_exists($stage)||is_link($stage)||is_link($archive)||!is_file($archive)||filesize($archive)>UpdateManifest::TOTAL_LIMIT+12582912)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        $stream=fopen($archive,'rb');if($stream===false)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');$created=false;
        try{
            [$name,$size]=self::metadata(self::block($stream,512));
            if($name!=='release-manifest.json'||$size<1||$size>2097152)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
            $manifest=UpdateManifest::decode(self::block($stream,$size),$expectedVersion);
            if($size%512&&self::block($stream,512-$size%512)!==str_repeat("\0",512-$size%512))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
            if(!mkdir($stage,0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$created=true;$seen=[];
            while(true){
                $header=self::block($stream,512);
                if($header===str_repeat("\0",512)){
                    if(self::block($stream,512)!==str_repeat("\0",512))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
                    $tail=stream_get_contents($stream,10241);if($tail===false||strlen($tail)>10240||trim($tail,"\0")!=='')throw new HttpException(422,'INVALID_UPDATE_PACKAGE');break;
                }
                [$name,$size]=self::metadata($header);UpdatePackagePaths::validate($name);
                if(isset($seen[$name])||!isset($manifest['files'][$name])||$manifest['files'][$name]['bytes']!==$size)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
                $seen[$name]=true;$path=$stage.'/'.$name;$parent=dirname($path);
                if(!is_dir($parent)&&!mkdir($parent,0700,true))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
                $target=fopen($path,'xb');if($target===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$hash=hash_init('sha256');
                try{for($remaining=$size;$remaining>0;){$data=self::block($stream,min(65536,$remaining));hash_update($hash,$data);if(fwrite($target,$data)!==strlen($data))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$remaining-=strlen($data);}}
                finally{fclose($target);}
                if(!chmod($path,0600)||!hash_equals($manifest['files'][$name]['sha256'],hash_final($hash)))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
                if($size%512&&self::block($stream,512-$size%512)!==str_repeat("\0",512-$size%512))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
            }
            if(count($seen)!==count($manifest['files'])||trim(file_get_contents($stage.'/VERSION'))!==$manifest['version'])throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
            if($lint)(new UpdateStage())->validate($stage,$manifest);
            return $manifest;
        }catch(\Throwable $error){if($created)$this->removeStage($stage);throw $error;}
        finally{fclose($stream);}
    }
    private function removeStage(string $stage): void
    {
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file){if($file->isLink())throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$removed=$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());if(!$removed)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        if(!rmdir($stage))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
}

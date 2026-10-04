<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class UpdateManifest
{
    public const FILE_LIMIT=67108864;
    public const TOTAL_LIMIT=268435456;
    public static function version(string $version): string
    {
        if(!preg_match('~^[A-Za-z0-9][A-Za-z0-9.+_/-]{0,127}$~D',$version))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        return $version;
    }
    public static function matches(string $actual,string $expected): bool
    {
        return $actual===$expected||(ReleaseCatalog::compare($actual,$expected)===0
            &&($actual==='v'.$expected||$expected==='v'.$actual));
    }
    public static function decode(string $json,string $expectedVersion): array
    {
        try{
            if(strlen($json)>2097152)throw new \RuntimeException();
            $value=json_decode($json,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($value)||count($value)!==4||($value['format']??null)!==1||!is_string($value['version']??null)||!self::matches(self::version($value['version']),self::version($expectedVersion))
                ||!is_string($value['php_min']??null)||!preg_match('/^[1-9][0-9]?\.(?:0|[1-9][0-9]?)\.(?:0|[1-9][0-9]{0,2})$/D',$value['php_min'])||version_compare($value['php_min'],'8.2.0','<')
                ||!is_array($value['files']??null)||array_is_list($value['files'])||count($value['files'])>10000)throw new \RuntimeException();
            $total=0;$seen=[];
            foreach($value['files'] as $path=>$file){
                if(!is_string($path)||!is_array($file)||count($file)!==2||!is_int($file['bytes']??null)||$file['bytes']<0||$file['bytes']>self::FILE_LIMIT
                    ||!is_string($file['sha256']??null)||!preg_match('/^[a-f0-9]{64}$/D',$file['sha256']))throw new \RuntimeException();
                UpdatePackagePaths::validate($path);$lower=strtolower($path);
                if(isset($seen[$lower]))throw new \RuntimeException();$seen[$lower]=true;$total+=$file['bytes'];
                if($total>self::TOTAL_LIMIT)throw new \RuntimeException();
            }
            foreach(array_keys($seen) as $path){$parent=dirname($path);while($parent!=='.'){if(isset($seen[$parent]))throw new \RuntimeException();$parent=dirname($parent);}}
            foreach(['VERSION','app/autoload.php','app/bootstrap.php','public/index.php','config/config.example.php','config/providers.php'] as $required)if(!isset($value['files'][$required]))throw new \RuntimeException();
            if($value['files']['VERSION']['bytes']<1||$value['files']['VERSION']['bytes']>512)throw new \RuntimeException();
            if(version_compare(PHP_VERSION,$value['php_min'],'<'))throw new HttpException(422,'UPDATE_PHP_REQUIRED');
            return $value;
        }catch(HttpException $error){if($error->errorCode==='UPDATE_PHP_REQUIRED')throw $error;throw new HttpException(422,'INVALID_UPDATE_PACKAGE');}
        catch(\Throwable){throw new HttpException(422,'INVALID_UPDATE_PACKAGE');}
    }
}

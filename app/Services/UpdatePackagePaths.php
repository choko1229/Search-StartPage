<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Distribution files only. Writable installation data is never a package target. */
final class UpdatePackagePaths
{
    public static function validate(string $path): string
    {
        if(strlen($path)>240||str_contains($path,'\\')||!preg_match('~^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$~D',$path))throw new HttpException(422,'INVALID_UPDATE_PATH');
        foreach(explode('/',$path) as $part){
            if($part==='.'||$part==='..'||($part[0]==='.'&&$part!=='.htaccess')||in_array(strtolower($part),['_test','tests'],true)
                ||preg_match('/^(?:con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/i',$part)||str_ends_with($part,'.'))throw new HttpException(422,'INVALID_UPDATE_PATH');
        }
        if(in_array($path,['VERSION','README.md','composer.json','config/config.example.php','config/providers.php'],true))return $path;
        if(!str_contains($path,'/')||!in_array(explode('/',$path,2)[0],['app','public','lang','database','bin','extension'],true))throw new HttpException(422,'INVALID_UPDATE_PATH');
        return $path;
    }
    public static function directory(string $path): void
    {
        $cursor=$path;
        while(true){if(is_link($cursor))throw new HttpException(422,'INVALID_UPDATE_PATH');$parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;}
        if(!is_dir($path))throw new HttpException(422,'INVALID_UPDATE_PATH');
    }
}

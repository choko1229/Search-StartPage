<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Syntax preflight never executes candidate PHP, bootstrap, or configuration. */
final class UpdateStage
{
    private function verify(string $stage,array $manifest): void
    {
        UpdatePackagePaths::directory($stage);
        foreach($manifest['files'] as $path=>$metadata){
            $file=$stage.'/'.$path;UpdatePackagePaths::directory(dirname($file));clearstatcache(true,$file);
            if(is_link($file)||!is_file($file)||filesize($file)!==$metadata['bytes']||!hash_equals($metadata['sha256'],hash_file('sha256',$file)))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        }
        if(trim(file_get_contents($stage.'/VERSION'))!==$manifest['version'])throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
    }
    public function validate(string $stage,array $manifest): int
    {
        $manifest=UpdateManifest::decode(json_encode($manifest,JSON_THROW_ON_ERROR),$manifest['version']??'');
        $this->verify($stage,$manifest);$process=new UpdateProcess();$count=0;$started=hrtime(true);
        foreach(array_keys($manifest['files']) as $path){
            if(!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['php','phtml','inc'],true))continue;
            if((hrtime(true)-$started)/1000000000>=300)throw new HttpException(422,'UPDATE_PROCESS_TIMEOUT');
            $process->lint($stage.'/'.$path);++$count;
        }
        $this->verify($stage,$manifest);
        return $count;
    }
}

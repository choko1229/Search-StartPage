<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{ReleaseCatalog,UpdatePackagePaths,LogFileLock};
use App\Http\HttpException;
try{
    if(count($argv)!==1)throw new HttpException(422,'INVALID_INPUT');
    $input=stream_get_contents(STDIN,4097);
    if($input===false||strlen($input)>4096)throw new HttpException(422,'INVALID_INPUT');
    $data=json_decode($input,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($data)||array_diff(array_keys($data),['repository','token'])!==[]||!is_string($data['repository']??null)||!is_string($data['token']??null)
        ||($data['token']!==''&&!preg_match('/^[A-Za-z0-9_.-]{1,512}$/D',$data['token'])))throw new HttpException(422,'INVALID_INPUT');
    ReleaseCatalog::repository($data['repository']);
    $directory=dirname(__DIR__).'/config';UpdatePackagePaths::directory($directory);$path=$directory.'/config.php';
    if(is_link($path)||!is_file($path)||(fileperms($path)&0077)!==0)throw new HttpException(503,'UPDATE_CONFIGURATION_UNAVAILABLE');
    LogFileLock::run($directory,static function()use($directory,$path,$data):void{
        clearstatcache(true,$path);if(is_link($path)||!is_file($path)||(fileperms($path)&0077)!==0)throw new HttpException(503,'UPDATE_CONFIGURATION_UNAVAILABLE');
        ob_start();try{$config=require $path;}finally{$unexpected=ob_get_clean();}
        if($unexpected!==''||!is_array($config)||($config['installed']??false)!==true||!is_array($config['updates']??[]))throw new HttpException(503,'UPDATE_CONFIGURATION_UNAVAILABLE');
        $config['updates']=array_replace($config['updates']??[],['repository'=>$data['repository'],'token'=>$data['token']]);
        $temporary=tempnam($directory,'.config-');if($temporary===false)throw new HttpException(503,'UPDATE_CONFIGURATION_UNAVAILABLE');
        try{
            $body="<?php\ndeclare(strict_types=1);\nreturn ".var_export($config,true).";\n";
            if(!chmod($temporary,0600)||file_put_contents($temporary,$body,LOCK_EX)!==strlen($body)||!rename($temporary,$path))throw new HttpException(503,'UPDATE_CONFIGURATION_UNAVAILABLE');
        }finally{if(is_file($temporary)&&!unlink($temporary))throw new HttpException(503,'UPDATE_CONFIGURATION_UNAVAILABLE');}
    });
    echo "Update source configuration saved.\n";
}catch(Throwable){fwrite(STDERR,"UPDATE_CONFIGURATION_FAILED\n");exit(1);}

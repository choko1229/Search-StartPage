<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
try{
    if(count($argv)!==1)throw new App\Http\HttpException(422,'INVALID_INPUT');
    $root=dirname(__DIR__);$updateLease=(new App\Services\UpdateAccess($root.'/storage/updates/access'))->enter();if($updateLease===null)throw new App\Services\UpdateAccessPaused();$directory=$root.'/storage/updates/builds';
    $result=App\Services\LogFileLock::run($directory,static function()use($root,$directory):array{
        $archive=$directory.'/search-startpage-'.bin2hex(random_bytes(8)).'.tar';
        return (new App\Services\ReleasePackageBuilder())->build($root,$archive)+['archive'=>basename($archive)];
    });
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,($error instanceof App\Http\HttpException?$error->errorCode:'UPDATE_PACKAGE_BUILD_FAILED')."\n");exit(1);}

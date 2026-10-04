<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';

use App\Services\{UpdateManifest,UpdatePackagePaths,ReleasePackageBuilder,UpdatePackage,UpdateCompatibility};
use App\Http\HttpException;

$created=false;$destination=null;
$remove=static function(string $path)use(&$remove):void{
    if(is_link($path))throw new RuntimeException('Unexpected release link');
    if(is_dir($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);if(!rmdir($path))throw new RuntimeException('Release cleanup failed');}
    elseif(!is_file($path)||!unlink($path))throw new RuntimeException('Release cleanup failed');
};
try{
    if(count($argv)!==3)throw new HttpException(422,'INVALID_INPUT');
    $root=realpath(dirname(__DIR__));$tag=UpdateManifest::version($argv[1]);
    if(!UpdateManifest::matches(trim(file_get_contents($root.'/VERSION')),$tag))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
    $parent=dirname($argv[2]);UpdatePackagePaths::directory($parent);$parent=realpath($parent);
    // Keep build outputs outside the source; never package a partially built archive.
    if($parent===$root||str_starts_with($parent,$root.DIRECTORY_SEPARATOR))throw new HttpException(422,'INVALID_UPDATE_PATH');
    $name=basename($argv[2]);if($name===''||$name==='.'||$name==='..')throw new HttpException(422,'INVALID_UPDATE_PATH');
    $destination=$parent.DIRECTORY_SEPARATOR.$name;
    if(file_exists($destination)||is_link($destination))throw new HttpException(409,'UPDATE_PACKAGE_EXISTS');
    if(!mkdir($destination,0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$created=true;
    $built=(new ReleasePackageBuilder())->build($root,$destination.'/search-startpage.tar');
    $manifest=(new UpdatePackage())->verify($destination.'/search-startpage.tar',$destination.'/stage',$tag,true,
        static fn(string $stage,array $candidate)=>(new UpdateCompatibility())->validate($stage,$candidate));
    $remove($destination.'/stage');
    $metadata=['format'=>1,'tag'=>$tag,'version'=>$manifest['version'],'asset'=>'search-startpage.tar','bytes'=>$built['bytes'],'sha256'=>$built['sha256'],'files'=>$built['files']];
    $json=json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    foreach(['release.json'=>$json,'search-startpage.tar.sha256'=>$built['sha256']."  search-startpage.tar\n"] as $file=>$body){
        if(file_put_contents($destination.'/'.$file,$body)!==strlen($body)||!chmod($destination.'/'.$file,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    echo $json;
}catch(Throwable $error){
    try{if($created)$remove($destination);}catch(Throwable){fwrite(STDERR,"UPDATE_STORAGE_UNAVAILABLE\n");exit(1);}
    fwrite(STDERR,($error instanceof HttpException?$error->errorCode:'UPDATE_PACKAGE_BUILD_FAILED')."\n");exit(1);
}

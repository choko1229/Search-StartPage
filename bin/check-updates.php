<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
try {
    $root=dirname(__DIR__);$config=App\Config::load($root);
    $repository=$config->get('updates.repository','choko1229/Search-StartPage');
    $channel=$config->get('updates.channel','stable');$tag=$config->get('updates.custom_tag','');
    $token=$config->get('updates.token','');
    if(!is_string($repository)||!is_string($channel)||!is_string($tag)||!is_string($token))throw new App\Http\HttpException(422,'INVALID_UPDATE_CONFIGURATION');
    App\Services\ReleaseCatalog::channel($channel,$tag);
    $release=App\Services\ReleaseCatalog::select((new App\Services\GitHubReleases($repository,null,$token))->releases(),$channel,$tag);
    $current=trim(file_get_contents($root.'/VERSION'));
    $comparison=$release?App\Services\ReleaseCatalog::compare($release['tag'],$current):null;
    echo json_encode(['current_version'=>$current,'channel'=>$channel,'release'=>$release,'available'=>$release!==null&&($comparison===null?$release['tag']!==$current:$comparison>0)],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){fwrite(STDERR,($error instanceof App\Http\HttpException?$error->errorCode:'UPDATE_CHECK_FAILED')."\n");exit(1);}

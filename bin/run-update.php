<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
try{
    if(count($argv)!==1)throw new App\Http\HttpException(422,'INVALID_INPUT');
    $root=dirname(__DIR__);$config=App\Config::load($root);
    if(!$config->get('installed'))throw new App\Http\HttpException(503,'NOT_INSTALLED');
    $pdo=App\Database\Database::connect($config->get('database'));
    $admin=new App\Repositories\AdminRepository($pdo);$history=new App\Repositories\UpdateHistoryRepository($pdo);
    class_exists(App\Repositories\LogRepository::class);
    $commands=new App\Services\UpdateCommands($root.'/storage/updates/commands',fn($id)=>$admin->isAdministrator($id),fn($request,$event)=>$history->record($request,$event),fn($request)=>$history->matches($request));
    $prepare=static function(array $request,string $archive,string $stage)use($config):array{
        $repository=$config->get('updates.repository','choko1229/Search-StartPage');
        if($repository!==$request['repository'])throw new App\Http\HttpException(409,'UPDATE_SOURCE_CHANGED');
        return (new App\Services\GitHubUpdateAsset($repository,$config->get('updates.token','')))->prepare($request['release_id'],$request['to_version'],$archive,$stage);
    };
    $state=(new App\Services\UpdateRunner($root,$commands,new App\Services\UpdateEngine($root),$prepare))->run();
    $request=$state['request'];
    echo json_encode(['format'=>1,'request_id'=>$request['id']??null,'status'=>$request['status']??'idle','error'=>$request['error']??null],JSON_THROW_ON_ERROR)."\n";
    if($request!==null&&in_array($request['status'],['failed','recovery_required'],true))exit(1);
}catch(Throwable $error){fwrite(STDERR,($error instanceof App\Http\HttpException&&in_array($error->errorCode,['UPDATE_STATE_CHANGED','UPDATE_ROLLBACK_FAILED','UPDATE_AUDIT_FAILED','UPDATE_COMMAND_INVALID','NOT_INSTALLED','INVALID_INPUT'],true)?$error->errorCode:'UPDATE_WORKER_FAILED')."\n");exit(1);}

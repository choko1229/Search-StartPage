<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
$root=dirname(__DIR__);$task=$argv[1]??'';$version=$argv[2]??'';$taskLease=null;$bufferLevel=ob_get_level();
try{
    require_once $root.'/app/Services/UpdateAccess.php';
    $taskLease=App\Services\UpdateAccess::authorizeInherited($root.'/storage/updates/access');
    if(count($argv)!==3||!in_array($task,['migrate','health'],true))throw new RuntimeException('Invalid task');
    require $root.'/app/autoload.php';
    App\Services\UpdateManifest::version($version);
    if(trim(file_get_contents($root.'/VERSION'))!==$version)throw new RuntimeException('Version mismatch');
    $config=App\Config::load($root);if(!$config->get('installed'))throw new RuntimeException('Not installed');
    if(!(new App\Services\EnvironmentCheck($root))->ready())throw new RuntimeException('Environment not ready');
    $pdo=App\Database\Database::connect($config->get('database'));
    $result=['format'=>1,'protocol'=>App\Services\UpdateAccess::PROTOCOL,'task'=>$task,'version'=>$version,'pid'=>getmypid()];
    if($task==='migrate'){
        $result['applied']=count((new App\Database\Migrator($pdo,$root.'/database/migrations'))->migrate());
    }else{
        $applied=$pdo->query('SELECT name,checksum FROM migrations')->fetchAll(PDO::FETCH_KEY_PAIR);$files=glob($root.'/database/migrations/*.php');
        if($files===false||count($files)===0||count($files)!==count($applied))throw new RuntimeException('Migration set mismatch');
        foreach($files as $file)if(!isset($applied[basename($file)])||!hash_equals(hash_file('sha256',$file),$applied[basename($file)]))throw new RuntimeException('Migration checksum mismatch');
        $_GET=[];$_POST=[];$_COOKIE=[];$_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/','HTTP_HOST'=>'localhost','REMOTE_ADDR'=>'127.0.0.1','SERVER_PORT'=>'80'];
        ob_start();$updateHealthProbe=true;$router=require $root.'/app/bootstrap.php';$unexpected=ob_get_clean();if($unexpected!=='')throw new RuntimeException('Unexpected bootstrap output');
        if(!$router instanceof App\Router\Router)throw new RuntimeException('Bootstrap failed');
        $health=$router->dispatch(new App\Http\Request('GET','/api/health'));$data=json_decode($health->body,true,8,JSON_THROW_ON_ERROR);
        if($health->status!==200||($data['data']['status']??null)!=='ok')throw new RuntimeException('Database health failed');
        $sizes=[];foreach(['ja','en'] as $locale){
            $translator=new App\Helpers\Translator($root,$locale);$view=new App\Helpers\View($root,$translator);
            $response=(new App\Controllers\CoreController($config,$view))->home(new App\Http\Request('GET','/'));
            if($response->status!==200||!str_contains($response->body,'<!doctype html>')||!str_contains($response->body,'lang="'.$locale.'"'))throw new RuntimeException('Home rendering failed');
            $sizes[$locale]=strlen($response->body);
        }
        $result['migrations']=count($files);$result['html_bytes']=$sizes;
    }
    if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable){
    while(ob_get_level()>$bufferLevel)ob_end_clean();
    if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
    fwrite(STDERR,"UPDATE_TASK_FAILED\n");exit(1);
}
// $taskLease remains alive through application shutdown logging.

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$lock=null;$exit=1;
try{
    $cycles=0;
    if(count($argv)>2||isset($argv[1])&&(getenv('SEARCH_TEST_MODE')!=='1'||!preg_match('/^--cycles=([1-9][0-9]{0,3})$/D',$argv[1],$match)))throw new RuntimeException('Invalid option');
    if(isset($argv[1]))$cycles=(int)$match[1];
    $storage=$root.'/storage';$path=$storage.'/update-check-worker.lock';
    if(is_link($storage)||!is_dir($storage)||is_link($path))throw new RuntimeException('Unsafe update lock');
    $lock=fopen($path,'c');
    if($lock===false||!chmod($path,0600)||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Update worker unavailable');
    for($cycle=0;$cycles===0||$cycle<$cycles;$cycle++){
        $delay=3600;$at=time();$status='failed';$available=null;$exit=1;
        try{
            // Reload configuration so a server-side token change applies without exposing it.
            $config=App\Config::load($root);
            if(!$config->get('installed'))throw new App\Http\HttpException(503,'NOT_INSTALLED');
            $checks=new App\Services\UpdateChecks($root.'/storage/updates/checks',$config,trim(file_get_contents($root.'/VERSION')));
            $state=$checks->check(onlyIfDue:true);
            $delay=max(1,$state['checked_at']+($state['error']===null?86400:3600)-time());
            if($state['error']!==null)throw new App\Http\HttpException(502,$state['error']);
            $status='success';$available=$state['available'];$exit=0;
        }catch(Throwable $error){
            try{
                $pdo=null;try{$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));}catch(Throwable){}
                (new App\Services\ApplicationLogger($pdo?new App\Repositories\LogRepository($pdo):null,new App\Services\FileLogger($root.'/storage/logs'),$root.'/storage/log-pending'))
                    ->record($error instanceof App\Http\HttpException?$error:new App\Http\HttpException(502,'UPDATE_CHECK_FAILED'),'/admin/update',bin2hex(random_bytes(8)),null,'GET');
            }catch(Throwable){}
        }
        echo json_encode(['at'=>gmdate(DATE_ATOM,$at),'status'=>$status,'available'=>$available,'next_run_in'=>$delay],JSON_THROW_ON_ERROR)."\n";flush();
        if($cycles===0||$cycle+1<$cycles)sleep($delay);
    }
}catch(Throwable){fwrite(STDERR,"Update check worker unavailable.\n");}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
exit($exit);

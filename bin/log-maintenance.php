<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$lock=null;
try {
    $options=['interval'=>86400,'retry'=>3600,'cycles'=>0];
    foreach(array_slice($argv,1) as $argument){
        if(!preg_match('/^--(interval|retry|cycles)=(0|[1-9][0-9]{0,5})$/D',$argument,$match))throw new RuntimeException('Invalid option');
        $options[$match[1]]=(int)$match[2];
    }
    if(getenv('SEARCH_TEST_MODE')!=='1'&&($options['interval']<60||$options['retry']<60||$options['cycles']!==0))throw new RuntimeException('Invalid production schedule');
    $storage=$root.'/storage';$path=$storage.'/log-maintenance.lock';
    if(is_link($storage)||!is_dir($storage)||is_link($path))throw new RuntimeException('Unsafe log worker lock');
    $lock=fopen($path,'c');
    if($lock===false||!chmod($path,0600)||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Log worker already running or unavailable');
    $access=new App\Services\UpdateAccess($root.'/storage/updates/access');$startupLease=$access->enter();
    if($startupLease===null){echo json_encode(['at'=>gmdate(DATE_ATOM),'status'=>'paused','next_run_in'=>30],JSON_THROW_ON_ERROR)."\n";$exit=0;}
    else{
        try{$maintenance=new App\Services\LogMaintenance($root,$access->generation());$schedule=new App\Services\LogSchedule();}finally{$startupLease->release();}
        $exit=$schedule->run($maintenance->run(...),static function(array $event):void{echo json_encode($event,JSON_THROW_ON_ERROR)."\n";flush();},$options['interval'],$options['retry'],$options['cycles']);
    }
}catch(Throwable){fwrite(STDERR,"Log maintenance unavailable.\n");$exit=1;}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
exit($exit);

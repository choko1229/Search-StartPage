<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$fileFault=array_slice($argv,1)===['--file-lock-outage'];
if(count($argv)>1&&!$fileFault)throw new RuntimeException('Invalid test mode');
$root=dirname(__DIR__);
$host=getenv('TEST_LOG_RETRY_HOST');
if(!in_array($host,['search-log-retry-mysql-20261004','search-log-retry-mariadb-20261004'],true)
    ||!str_starts_with($root,'/tmp/search-log-retry-')||is_link($root)
    ||!is_file($root.'/storage/log-retry-test-only')||file_exists($root.'/config/config.php'))throw new RuntimeException('Fresh isolated test deployment required');
$settings=require $root.'/config/config.example.php';
$settings['installed']=true;
$settings['database']=['host'=>$host,'port'=>3306,'name'=>'log_retry','user'=>'retry','password'=>getenv('TEST_LOG_RETRY_PASSWORD')];
$deadline=microtime(true)+60;
do{try{$pdo=App\Database\Database::connect($settings['database']);break;}catch(PDOException){if(microtime(true)>$deadline)throw new RuntimeException('Dedicated DB unavailable');usleep(100000);}}while(true);
(new App\Database\Migrator($pdo,$root.'/database/migrations'))->migrate();
$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$publish=static function(array $config)use($root):void{
    $temporary=tempnam($root.'/config','retry-');
    if($temporary===false)throw new RuntimeException('Test configuration unavailable');
    try{if(file_put_contents($temporary,'<?php return '.var_export($config,true).';')===false||!chmod($temporary,0600)||!rename($temporary,$root.'/config/config.php'))throw new RuntimeException('Test publication failed');}
    finally{if(is_file($temporary))unlink($temporary);}
};
$process=null;$pipes=[];$ids=[bin2hex(random_bytes(16)),bin2hex(random_bytes(16))];$statId=bin2hex(random_bytes(16));
$expired=time()-91*86400;$recent=time();$files=new App\Services\FileLogger($root.'/storage/logs');
try{
    $insert=$pdo->prepare('INSERT INTO log_entries(event_id,type,error_code,context_json,created_at,file_written) VALUES (?, ?, ?, ?, ?, ?)');
    foreach([$expired,$recent] as $index=>$time)$insert->execute([$ids[$index],'php_error','LOG_RETRY_TEST','{}',gmdate('Y-m-d H:i:s',$time),1]);
    $files->write('error','LOG_RETRY_TEST',[],$expired);$files->write('error','LOG_RETRY_TEST',[],$recent);
    $expiredPath=$root.'/storage/logs/'.gmdate('Y-m-d',$expired).'.jsonl';
    $recentPath=$root.'/storage/logs/'.gmdate('Y-m-d',$recent).'.jsonl';
    $queue=new App\Services\ApplicationLogger(null,$files,$root.'/storage/log-pending');
    $queued=$queue->record(new RuntimeException('generated-private-error-message'),'/api/user',bin2hex(random_bytes(16)));
    $queuePath=$root.'/storage/log-pending/'.$queued.'.json';$check(is_file($queuePath),'durable pending record exists before recovery');
    $ids[]=$queued;
    $pdo->prepare('INSERT INTO statistics_events(event_id,anonymous_id,event_type,event_data,source,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())')->execute([$statId,bin2hex(random_bytes(16)),'visit','{}','web']);
    $statistics=$pdo->query('SELECT * FROM statistics_events ORDER BY id')->fetchAll();
    if($fileFault){
        $publish($settings);$protected=$root.'/storage/protected-test-file';file_put_contents($protected,'generated protected log target');
        unlink($root.'/storage/logs/.write.lock');
        $check(symlink($protected,$root.'/storage/logs/.write.lock'),'real unsafe file lock created in isolated deployment');
    }else{
        $bad=$settings;$bad['database']['host']='127.0.0.1';$bad['database']['port']=1;$publish($bad);
        try{App\Database\Database::connect($bad['database']);throw new RuntimeException('Unreachable connection accepted');}catch(PDOException){$check(true,'real PDO connection failure confirmed');}
    }
    $process=proc_open([PHP_BINARY,$root.'/bin/log-maintenance.php','--interval=5','--retry=2','--cycles=2'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Worker did not start');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $pid=proc_get_status($process)['pid'];$output='';$error='';$deadline=microtime(true)+10;
    do{$output.=stream_get_contents($pipes[1]);$error.=stream_get_contents($pipes[2]);if(str_contains($output,"\n"))break;usleep(10000);}while(microtime(true)<$deadline);
    $first=json_decode(strtok($output,"\n"),true,512,JSON_THROW_ON_ERROR);$observed=microtime(true);
    $check($first['status']==='failed'&&$first['next_run_in']===2&&!isset($first['counts']),'real worker reports failure and schedules retry');
    $read=$pdo->prepare('SELECT COUNT(*) FROM log_entries WHERE event_id=?');$read->execute([$ids[0]]);
    $check((int)$read->fetchColumn()===($fileFault?0:1)&&is_file($expiredPath)&&is_file($queuePath),'failure preserves pending records and files; DB cleanup reflects completed work');
    if($fileFault){
        $check(file_get_contents($protected)==='generated protected log target','unsafe lock target remains unchanged after failure');
        unlink($root.'/storage/logs/.write.lock');
    }else{$publish($settings);}
    $state=proc_get_status($process);$check($state['running']&&$state['pid']===$pid,'same worker survives cleanup failure');
    $deadline=microtime(true)+10;$exit=null;
    do{$output.=stream_get_contents($pipes[1]);$error.=stream_get_contents($pipes[2]);$state=proc_get_status($process);if(!$state['running']){$exit=$state['exitcode'];break;}usleep(10000);}while(microtime(true)<$deadline);
    $elapsed=microtime(true)-$observed;
    $output.=stream_get_contents($pipes[1]);$error.=stream_get_contents($pipes[2]);
    $check($exit===0,'finite worker exits successfully after recovery');
    foreach([1,2] as $index)fclose($pipes[$index]);$closed=proc_close($process);$process=null;
    $events=array_map(static fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),array_filter(explode("\n",trim($output))));
    $check(count($events)===2&&array_column($events,'status')===['failed','success'],'same process retries without external restart');
    $check($elapsed>=1.5&&$events[1]['next_run_in']===5,'actual retry wait and normal interval restoration');
    $check($error===''&&!str_contains($output,$settings['database']['password'])&&!str_contains($output,'generated-private-error-message')&&!str_contains($output,'SQLSTATE'),'worker output contains no credentials or exception details');
    $read->execute([$ids[0]]);clearstatcache(true,$expiredPath);
    $check((int)$read->fetchColumn()===0&&!file_exists($expiredPath),'recovered worker removes expired DB and file records');
    $read->execute([$ids[1]]);$check((int)$read->fetchColumn()===1&&is_file($recentPath),'recovered worker preserves recent DB and file records');
    $read->execute([$queued]);$check((int)$read->fetchColumn()===1&&!file_exists($queuePath)&&$events[1]['counts']['recovery']['delivered']===1,'recovered worker delivers pending record exactly once');
    $check($pdo->query('SELECT * FROM statistics_events ORDER BY id')->fetchAll()===$statistics,'log recovery leaves statistics unchanged');
    $check((new App\Services\LogMaintenance($root))->run()['recovery']['delivered']===0,'subsequent cleanup does not replay delivered event');
    $read->execute([$queued]);$check((int)$read->fetchColumn()===1,'DB event remains unique after repeated cleanup');
    $check(!str_contains(file_get_contents($recentPath),'generated-private-error-message'),'file output excludes private exception message');
    $check((new App\Database\Migrator($pdo,$root.'/database/migrations'))->migrate()===[],'all migrations remain repeat safe');
    if($fileFault)$check(file_get_contents($protected)==='generated protected log target','protected lock target remains unchanged after recovery');
    echo "$count real log worker ".($fileFault?'file-lock':'connection')." outage/recovery checks passed.\n";
}finally{
    if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
    $delete=$pdo->prepare('DELETE FROM log_entries WHERE event_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$delete->execute($ids);
    $pdo->prepare('DELETE FROM statistics_events WHERE event_id=?')->execute([$statId]);
    if(is_file($root.'/config/config.php'))unlink($root.'/config/config.php');
}

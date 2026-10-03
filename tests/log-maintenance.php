<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$ids=[bin2hex(random_bytes(16)),bin2hex(random_bytes(16))];$expired=time()-91*86400;
$query=$pdo->prepare('INSERT INTO log_entries(event_id,type,error_code,context_json,created_at,file_written) VALUES (?, ?, ?, ?, ?, ?)');
try {
    foreach([$expired,time()] as $i=>$time)$query->execute([$ids[$i],'php_error','SCHEDULE_TEST','{}',gmdate('Y-m-d H:i:s',$time),1]);
    (new App\Services\FileLogger($root.'/storage/logs'))->write('error','SCHEDULE_TEST',[],$expired);
    $statistics=(int)$pdo->query('SELECT COUNT(*) FROM statistics_events')->fetchColumn();
    $process=proc_open([PHP_BINARY,$root.'/bin/log-maintenance.php','--interval=1','--retry=1','--cycles=2'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Worker did not start');
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $events=array_map(fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),array_filter(explode("\n",trim($output))));
    if($exit!==0||$error!==''||count($events)!==2||array_column($events,'status')!==['success','success'])throw new RuntimeException('Worker cycles failed');
    $read=$pdo->prepare('SELECT COUNT(*) FROM log_entries WHERE event_id=?');$read->execute([$ids[0]]);if((int)$read->fetchColumn()!==0)throw new RuntimeException('Expired DB log retained');
    $read->execute([$ids[1]]);if((int)$read->fetchColumn()!==1)throw new RuntimeException('Recent DB log removed');
    if(is_file($root.'/storage/logs/'.gmdate('Y-m-d',$expired).'.jsonl'))throw new RuntimeException('Expired file log retained');
    if((int)$pdo->query('SELECT COUNT(*) FROM statistics_events')->fetchColumn()!==$statistics)throw new RuntimeException('Statistics changed');
    $lock=fopen($root.'/storage/log-maintenance.lock','c');flock($lock,LOCK_EX);
    try {
        $blocked=proc_open([PHP_BINARY,$root.'/bin/log-maintenance.php','--interval=1','--retry=1','--cycles=1'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($blocked)!==1||$output!==''||$error!=="Log maintenance unavailable.\n")throw new RuntimeException('Duplicate worker not rejected');
    }finally{flock($lock,LOCK_UN);fclose($lock);}
    echo "Log maintenance: two actual timed cycles, expired DB/file removal, recent log/statistics retention and duplicate worker refusal passed.\n";
}finally{$delete=$pdo->prepare('DELETE FROM log_entries WHERE event_id IN (?,?)');$delete->execute($ids);}

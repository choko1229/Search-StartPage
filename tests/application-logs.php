<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\{Database,Migrator};
use App\Repositories\LogRepository;
use App\Services\{ApplicationLogger,FileLogger};
use App\Http\HttpException;
if (PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1') exit(1);
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$count=0;$events=[];
$check=static function(bool $ok,string $name)use(&$count):void {if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$check((new Migrator($pdo,$root.'/database/migrations'))->migrate()===[],'event migration repeat safe');
$migration=require $root.'/database/migrations/012_log_event_ids.php';$migration->up($pdo);
$directory=sys_get_temp_dir().'/application-log-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$repository=new LogRepository($pdo);$files=new FileLogger($directory.'/files');$queue=$directory.'/queue';
$logger=new ApplicationLogger($repository,$files,$queue);
$query=$pdo->prepare('SELECT type,error_code,user_id,context_json,file_written,created_at FROM log_entries WHERE event_id=?');
try {
    foreach ([[new RuntimeException('generated-password-secret-marker'),'/api/test?token=generated-query-marker','php_error'],
        [new HttpException(422,'INVALID_INPUT'),'/api/test','api_error'],
        [new HttpException(400,'OAUTH_STATE_INVALID'),'/api/auth/discord/callback?code=generated-oauth-marker','oauth_error'],
        [new HttpException(409,'SYNC_CONFLICT'),'/api/sync','sync_error'],
        [new HttpException(502,'UPDATE_FAILED'),'/api/admin/update','update_error'],
        [new HttpException(403,'CSRF_INVALID'),'/api/settings','security']] as [$error,$path,$type]) {
        $id=$logger->record($error,$path,bin2hex(random_bytes(8)),null,'POST');$events[]=$id;
        $query->execute([$id]);$row=$query->fetch(PDO::FETCH_ASSOC);
        $check($row['type']===$type && (int)$row['file_written']===1,'dual log category '.$type);
        $check(!file_exists($queue.'/'.$id.'.json'),'fully delivered queue removed '.$type);
    }
    $file=file_get_contents($directory.'/files/'.gmdate('Y-m-d').'.jsonl');
    $check(!str_contains($file,'generated-password-secret-marker') && !str_contains($file,'generated-query-marker') && !str_contains($file,'generated-oauth-marker'),'messages and query secrets omitted from files');
    $query->execute([$events[0]]);$check(!str_contains($query->fetch(PDO::FETCH_ASSOC)['context_json'],'secret-marker'),'messages omitted from DB');

    $offline=new ApplicationLogger(null,$files,$queue);
    $id=$offline->record(new HttpException(503,'DATABASE_UNAVAILABLE'),'/api/sync',bin2hex(random_bytes(8)));$events[]=$id;
    $saved=file_get_contents($queue.'/'.$id.'.json');$entry=json_decode($saved,true,512,JSON_THROW_ON_ERROR);
    $query->execute([$id]);$check($query->fetch()===false && $entry['file_written'],'DB outage retains durable queue and file');
    $before=substr_count(file_get_contents($directory.'/files/'.gmdate('Y-m-d').'.jsonl'),$id);
    $check($logger->recover()['delivered']===1 && !is_file($queue.'/'.$id.'.json'),'DB recovery imports queue');
    $query->execute([$id]);$check($query->fetch(PDO::FETCH_ASSOC)['type']==='sync_error','DB recovery preserves category');
    $check(substr_count(file_get_contents($directory.'/files/'.gmdate('Y-m-d').'.jsonl'),$id)===$before,'DB recovery does not append a second file event');
    file_put_contents($queue.'/'.$id.'.json',$saved);$logger->recover();
    $single=$pdo->prepare('SELECT COUNT(*) FROM log_entries WHERE event_id=?');$single->execute([$id]);
    $check((int)$single->fetchColumn()===1,'duplicate queue replay deduplicated in DB');

    file_put_contents($directory.'/blocked','generated fixture');
    $failing=new ApplicationLogger($repository,new FileLogger($directory.'/blocked'),$queue);
    set_error_handler(static function():never {throw new RuntimeException('Injected file failure');});
    try {$id=$failing->record(new HttpException(422,'FILE_RECOVERY_TEST'),'/api/test',bin2hex(random_bytes(8)));$events[]=$id;}finally{restore_error_handler();}
    $query->execute([$id]);$check((int)$query->fetch(PDO::FETCH_ASSOC)['file_written']===0 && is_file($queue.'/'.$id.'.json'),'file failure preserves DB and pending queue');
    $check($logger->recover()['delivered']===1,'file delivery retries after recovery');
    $query->execute([$id]);$check((int)$query->fetch(PDO::FETCH_ASSOC)['file_written']===1,'file recovery updates DB delivery');

    $expiredId=bin2hex(random_bytes(16));$entry['event_id']=$expiredId;$entry['created_at']=gmdate('Y-m-d H:i:s',time()-91*86400);
    file_put_contents($queue.'/'.$expiredId.'.json',json_encode($entry));
    $check($logger->recover()['expired']===1 && !is_file($queue.'/'.$expiredId.'.json'),'expired queued records not resurrected');
    $invalidId=bin2hex(random_bytes(16));$entry['event_id']=$invalidId;$entry['created_at']=gmdate('Y-m-d H:i:s');$entry['context']['password']='generated-rejected-marker';
    file_put_contents($queue.'/'.$invalidId.'.json',json_encode($entry));
    $check($logger->recover()['invalid']===1 && is_file($queue.'/'.$invalidId.'.invalid'),'unexpected sensitive context quarantined');
    $single->execute([$invalidId]);$check((int)$single->fetchColumn()===0,'invalid queue never enters DB');
    $check(!str_contains(file_get_contents($directory.'/files/'.gmdate('Y-m-d').'.jsonl'),'generated-rejected-marker'),'invalid queue never enters log files');
    touch($queue.'/'.$invalidId.'.invalid',time()-91*86400);
    $check($logger->recover()['expired']===1,'quarantined data also expires');
    file_put_contents($queue.'/queue-generated-orphan','generated temporary record');touch($queue.'/queue-generated-orphan',time()-91*86400);
    $check($logger->recover()['expired']===1 && !is_file($queue.'/queue-generated-orphan'),'orphan temporary records also expire');
} finally {
    foreach($events as $id)$pdo->prepare('DELETE FROM log_entries WHERE event_id=?')->execute([$id]);
    foreach(['queue','files'] as $child)if(is_dir($directory.'/'.$child)){foreach(glob($directory.'/'.$child.'/*') ?: [] as $path)if(is_file($path))unlink($path);if(is_file($directory.'/'.$child.'/.write.lock'))unlink($directory.'/'.$child.'/.write.lock');rmdir($directory.'/'.$child);}
    if(is_file($directory.'/blocked'))unlink($directory.'/blocked');rmdir($directory);
}
echo "$count application log checks passed.\n";

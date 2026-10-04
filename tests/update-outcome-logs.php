<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||!in_array(getenv('TEST_BACKUP_HOST'),['search-update-backup-mysql-20261004','search-update-backup-mariadb-20261004'],true))exit(1);
$pdo=null;$owned=false;$directory=sys_get_temp_dir().'/update-outcome-logs-'.bin2hex(random_bytes(8));mkdir($directory,0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
try{
    $deadline=microtime(true)+60;do{try{$pdo=App\Database\Database::connect(['host'=>getenv('TEST_BACKUP_HOST'),'port'=>3306,'name'=>'update_backup','user'=>'backup','password'=>getenv('TEST_BACKUP_PASSWORD')]);break;}catch(PDOException){if(microtime(true)>$deadline)throw new RuntimeException('Dedicated DB unavailable');usleep(100000);}}while(true);
    $check($pdo->query('SHOW TABLES')->fetchAll()===[],'dedicated outcome schema starts empty');$owned=true;
    $migrator=new App\Database\Migrator($pdo,dirname(__DIR__).'/database/migrations');$check(count($migrator->migrate())===17&&$migrator->migrate()===[],'all migrations fresh and repeat safe');
    $pdo->prepare('INSERT INTO users(id,discord_id,discord_username,locale,created_at,updated_at) VALUES(1,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute(['999999999999999977','Generated update outcome','en']);
    $request=['id'=>bin2hex(random_bytes(16)),'operation'=>'apply','from_version'=>'0.1.0-dev','to_version'=>'2.0.0','channel'=>'stable','repository'=>'owner/repo','release_id'=>1,'actor'=>1,'status'=>'prepared','created_at'=>time(),'updated_at'=>time(),'completed_at'=>null,'error'=>null,'audited'=>false];
    $history=new App\Repositories\UpdateHistoryRepository($pdo);$history->record($request,'requested');$request['status']='running';$history->record($request,'started');
    $check((int)$pdo->query("SELECT COUNT(*) FROM log_entries WHERE type='update_error'")->fetchColumn()===0,'accepted and started jobs create no pretend error');
    $request['status']='failed';$request['error']='UPDATE_SOURCE_NOT_FOUND';$request['updated_at']=$request['completed_at']=time();
    $pdo->exec("ALTER TABLE log_entries ADD CONSTRAINT reject_update_error CHECK (type <> 'update_error')");
    try{$history->record($request,'finished');throw new RuntimeException('Error outbox failure accepted');}catch(PDOException){$check(true,'real error outbox insert failure observed');}
    $check($history->listing()[0]['status']==='running'&&(int)$pdo->query("SELECT COUNT(*) FROM log_entries WHERE error_code='UPDATE_JOB_FAILED'")->fetchColumn()===0,'error insert failure rolls back history and outcome audit together');
    $pdo->exec(str_contains((string)$pdo->query('SELECT VERSION()')->fetchColumn(),'MariaDB')?'ALTER TABLE log_entries DROP CONSTRAINT reject_update_error':'ALTER TABLE log_entries DROP CHECK reject_update_error');$history->record($request,'finished');
    $query=$pdo->prepare("SELECT event_id,error_code,context_json,file_written FROM log_entries WHERE type='update_error'");$query->execute();$rows=$query->fetchAll();$context=json_decode($rows[0]['context_json'],true,flags:JSON_THROW_ON_ERROR);
    $check(count($rows)===1&&$rows[0]['error_code']==='UPDATE_SOURCE_NOT_FOUND'&&(int)$rows[0]['file_written']===0,'failed update records one pending update_error');
    $check(array_keys($context)===['outbox','request_id','operation','status']&&!str_contains($rows[0]['context_json'],'repository'),'error context contains only fixed safe execution fields');
    $history->record($request,'finished');$query->execute();$check(count($query->fetchAll())===1,'repeated projection deduplicates outcome error');
    $files=$directory.'/logs';mkdir($files,0700);$target=$directory.'/protected';file_put_contents($target,'generated protected target');symlink($target,$files.'/.write.lock');
    $originalErrorLog=ini_get('error_log');ini_set('error_log',$directory.'/delivery-error.log');
    $outbox=new App\Services\AdminAuditLogger($pdo,new App\Services\FileLogger($files));$outbox->flush();
    $check((int)$pdo->query("SELECT file_written FROM log_entries WHERE type='update_error'")->fetchColumn()===0&&file_get_contents($target)==='generated protected target','unsafe file lock preserves error outbox and target');
    unlink($files.'/.write.lock');$outbox->flush();$file=$files.'/'.gmdate('Y-m-d').'.jsonl';$lines=array_map(static fn($line)=>json_decode($line,true,flags:JSON_THROW_ON_ERROR),file($file,FILE_IGNORE_NEW_LINES));
    $errors=array_values(array_filter($lines,static fn($entry)=>$entry['level']==='update_error'));
    $check(count($errors)===1&&$errors[0]['code']==='UPDATE_SOURCE_NOT_FOUND'&&(int)$pdo->query("SELECT file_written FROM log_entries WHERE type='update_error'")->fetchColumn()===1,'repair delivers update error to file and acknowledges DB outbox');
    $check($errors[0]['context']['event_id']===$rows[0]['event_id'],'file error carries stable event identity for replay deduplication');
    $bytes=file_get_contents($file);$outbox->flush();$check(file_get_contents($file)===$bytes,'completed file delivery is not repeated');
    $pdo->exec("DELETE FROM log_entries WHERE type='update_error'");$history->record($request,'finished');$query->execute();$check(count($query->fetchAll())===1,'snapshot-lost outcome error is restored by private projection');
    $filters=App\Services\LogFilters::parse(['type'=>'update_error']);$listing=(new App\Repositories\LogRepository($pdo))->listing($filters);$check($listing['total']===1&&$listing['items'][0]['error_code']==='UPDATE_SOURCE_NOT_FOUND','admin update_error filter returns actual projected failure');
    $success=$request;$success['id']=bin2hex(random_bytes(16));$success['status']='complete';$success['error']=null;$history->record($success,'finished');$check((int)$pdo->query("SELECT COUNT(*) FROM log_entries WHERE type='update_error'")->fetchColumn()===1,'successful update creates no error record');
    // An ApplicationLogger pending record must stay with its own durable queue.
    unlink($files.'/.write.lock');$check(symlink($target,$files.'/.write.lock'),'application queue fault is actually installed');
    $application=new App\Services\ApplicationLogger(new App\Repositories\LogRepository($pdo),new App\Services\FileLogger($files),$directory.'/pending');
    // Use the actual update API classification, retaining no arbitrary exception text.
    $applicationId=$application->record(new App\Http\HttpException(503,'UPDATE_APPLY_FAILED'),'/api/admin/update/apply',bin2hex(random_bytes(16)),1);
    unlink($files.'/.write.lock');$outbox->flush();
    $pendingQuery=$pdo->prepare('SELECT file_written FROM log_entries WHERE event_id=?');$pendingQuery->execute([$applicationId]);
    $check((int)$pendingQuery->fetchColumn()===0&&is_file($directory.'/pending/'.$applicationId.'.json'),'outbox does not consume actual application error queue');
    $application->recover();$pendingQuery->execute([$applicationId]);
    $check((int)$pendingQuery->fetchColumn()===1&&!file_exists($directory.'/pending/'.$applicationId.'.json'),'application queue completes through its own recovery path');
    ini_set('error_log',$originalErrorLog);echo "$count update outcome log checks passed.\n";
}finally{
    if(isset($originalErrorLog))ini_set('error_log',$originalErrorLog);
    if($owned&&$pdo!==null){$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new RuntimeException('Unexpected dedicated table');$pdo->exec('DROP TABLE `'.$table.'`');}$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
    $remove=function($path)use(&$remove):void{if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $entry)if($entry!=='.'&&$entry!=='..')$remove($path.'/'.$entry);rmdir($path);}else unlink($path);};$remove($directory);
}

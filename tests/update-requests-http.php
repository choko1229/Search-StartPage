<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{ReleasePackageBuilder,UpdatePackage,UpdateChecks,UpdateCommands,UpdateJournal,UpdateEngine,UpdateRunner};
use App\Database\{Database,Migrator};
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||!in_array(getenv('TEST_BACKUP_HOST'),['search-update-backup-mysql-20261004','search-update-backup-mariadb-20261004'],true))exit(1);
$root=dirname(__DIR__);$apache=getenv('TEST_UPDATE_APACHE')==='1';
if($apache&&(!is_file($root.'/storage/web-cache-test-only')||file_exists($root.'/config/config.php')))throw new RuntimeException('Disposable Apache deployment required');
$directory=$apache?'/tmp/update-request-apache-fixture':sys_get_temp_dir().'/update-request-http-'.bin2hex(random_bytes(8));if(file_exists($directory)||is_link($directory)||!mkdir($directory,0700))throw new RuntimeException('Fresh fixture required');$pdo=null;$server=null;$count=0;$cookies=[];$csrf=null;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
try{
 $settings=require $root.'/config/config.example.php';$settings['installed']=true;$settings['database']=['host'=>getenv('TEST_BACKUP_HOST'),'port'=>3306,'name'=>'update_backup','user'=>'backup','password'=>getenv('TEST_BACKUP_PASSWORD')];$settings['session']=['name'=>'http_update_'.bin2hex(random_bytes(6)),'secure'=>false];$settings['updates']=['repository'=>'owner/repo','token'=>''];
 $until=time()+60;do{try{$pdo=Database::connect($settings['database']);break;}catch(PDOException){if(time()>=$until)throw new RuntimeException('Dedicated DB unavailable');usleep(200000);}}while(true);
 $check($pdo->query('SHOW TABLES')->fetchAll()===[],'HTTP update uses dedicated empty schema');
 $builder=new ReleasePackageBuilder();$builder->build($root,$directory.'/base.tar');$initial=trim(file_get_contents($root.'/VERSION'));$live=$directory.'/live';(new UpdatePackage())->verify($directory.'/base.tar',$live,$initial,true);mkdir($live.'/storage',0700);
 if($apache)$address='127.0.0.1:80';else{$socket=stream_socket_server('tcp://127.0.0.1:0');if($socket===false)throw new RuntimeException('Test port unavailable');$address=stream_socket_get_name($socket,false);fclose($socket);}$settings['site']['url']='http://'.$address;
 file_put_contents($live.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($live.'/config/config.php',0600);$configHash=hash_file('sha256',$live.'/config/config.php');(new Migrator($pdo,$live.'/database/migrations'))->migrate();
 $auth=new App\Repositories\AuthRepository($pdo);$user=$auth->upsertIdentity(['id'=>'999999999999999981','username'=>'Generated update admin','display_name'=>null,'avatar'=>null],'en');$pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$user]);
 $device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($user,$device,hash('sha256',$token),App\Auth\DeviceAgent::parse('Windows Chrome/120'),time());$cookies['search_remember']=$device.'.'.$token;
 if(!$apache){$server=proc_open([PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-S',$address,'-t',$live.'/public',$live.'/public/index.php'],[0=>['pipe','r'],1=>['file',$directory.'/server.out','a'],2=>['file',$directory.'/server.err','a']],$pipes,$live);if(!is_resource($server))throw new RuntimeException('HTTP test server unavailable');fclose($pipes[0]);}
 $until=time()+10;do{$ready=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1),$errorCode,$errorMessage,0.1);if($ready){fclose($ready);break;}if(time()>=$until)throw new RuntimeException('HTTP test server not ready');usleep(50000);}while(true);
 $http=static function(string $path,string $method='GET',?array $body=null,string $locale='en')use($address,&$cookies,&$csrf):array{
  $headers=['Accept-Language: '.$locale,'Cookie: '.implode('; ',array_map(static fn($key,$value)=>$key.'='.$value,array_keys($cookies),$cookies))];if($body!==null)$headers[]='Content-Type: application/json';if($csrf!==null)$headers[]='X-CSRF-Token: '.$csrf;
  $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>60];if($body!==null)$options['content']=json_encode($body,JSON_THROW_ON_ERROR);
  $response=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match))$cookies[$match[1]]=$match[2];return [(int)$status[1],$response];
 };
 [, $body]=$http('/api/csrf');$csrf=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data']['csrf_token'];
 $checks=new UpdateChecks($live.'/storage/updates/checks',new App\Config($settings),$initial,static fn()=>['status'=>200,'body'=>json_encode([['id'=>17,'tag_name'=>'2.0.0','prerelease'=>false,'draft'=>false,'published_at'=>'2026-10-01T00:00:00Z']])]);$checks->check('stable','',0);
 [$status,$body]=$http('/api/admin/update');$data=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data'];$check($status===200&&$data['execution']['request']===null&&$data['release']['tag']==='2.0.0','authenticated HTTP returns server release and execution revisions');
 $input=['command_revision'=>$data['execution']['command_revision'],'check_revision'=>$data['revision'],'engine_revision'=>$data['execution']['engine_revision']];
 [$status]=$http('/api/admin/update/apply','POST',[...$input,'actor'=>999,'to_version'=>'9.0.0']);$check($status===422,'HTTP rejects client-selected actor and target');
 [$status,$body]=$http('/api/admin/update','POST',$input);$accepted=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data'];$check($status===202&&$accepted['execution']['request']['status']==='queued','specified update API queues real audited request and returns 202');$requestId=$accepted['execution']['request']['id'];
 $check(!str_contains($body,$token)&&!isset($accepted['execution']['request']['actor'],$accepted['execution']['request']['release_id'],$accepted['execution']['request']['repository']),'HTTP response excludes token and private request context');
 $history=new App\Repositories\UpdateHistoryRepository($pdo);$check($history->listing()[0]['request_id']===$requestId&&$history->listing()[0]['status']==='queued','HTTP acceptance commits actual DB history');
 $check(trim(file_get_contents($live.'/VERSION'))===$initial&&(new UpdateJournal($live.'/storage/updates/journal'))->status()['job']===null,'HTTP request never replaces files or starts engine');
 [$status]=$http('/api/admin/update/apply','POST',$input);$check($status===409&&count($history->listing())===1,'repeated stale HTTP submit creates no duplicate request');
 foreach(['en'=>'Queued','ja'=>'受付済み'] as $locale=>$label){[$status,$html]=$http('/admin/update',locale:$locale);$check($status===200&&str_contains($html,$label),'queued status displayed '.$locale);}
 $candidate=$directory.'/candidate';(new UpdatePackage())->verify($directory.'/base.tar',$candidate,$initial,true);file_put_contents($candidate.'/VERSION','2.0.0');file_put_contents($candidate.'/app/Views/admin-update.php',"\n<!-- generated-update-code-v2 -->\n",FILE_APPEND);$builder->build($candidate,$directory.'/candidate.tar');
 $admin=new App\Repositories\AdminRepository($pdo);$commands=new UpdateCommands($live.'/storage/updates/commands',fn($id)=>$admin->isAdministrator($id),fn($request,$event)=>$history->record($request,$event),fn($request)=>$history->matches($request));$engine=new UpdateEngine($live);
 // Replace only the acquisition callback in the disposable runner entry, never the live project.
 $entry=file_get_contents($live.'/bin/run-update.php');$start=strpos($entry,'$prepare=static');$end=strpos($entry,'$state=(new',$start);if($start===false||$end===false)throw new RuntimeException('Fixture entry shape changed');
 $startedPath=$live.'/storage/worker-test-started';
 $fixture='$prepare=static function(array $request,string $archive,string $stage):array{file_put_contents('.var_export($startedPath,true).',"started");chmod('.var_export($startedPath,true).',0600);usleep(250000);copy('.var_export($directory.'/candidate.tar',true).',$archive);return (new App\\Services\\UpdatePackage())->verify($archive,$stage,$request["to_version"],true);};';
 file_put_contents($live.'/bin/run-update.php',substr($entry,0,$start).$fixture.substr($entry,$end));
 $scheduled=static function(bool $drain=false)use($live,$startedPath):array{
  $command=[PHP_BINARY,$live.'/bin/update-execution-worker.php'];if(!$drain)$command[]='--cycles=1';
  $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$live);if(!is_resource($process))throw new RuntimeException('Scheduler unavailable');fclose($pipes[0]);unset($pipes[0]);
  $stop=static function()use($live):int{$control=proc_open([PHP_BINARY,$live.'/bin/update-execution-worker.php','--stop'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$controlPipes,$live);if(!is_resource($control))throw new RuntimeException('Stop unavailable');fclose($controlPipes[0]);stream_get_contents($controlPipes[1]);stream_get_contents($controlPipes[2]);fclose($controlPipes[1]);fclose($controlPipes[2]);return proc_close($control);};
  try{
   if($drain){$until=microtime(true)+10;while(!is_file($startedPath)){if(microtime(true)>=$until)throw new RuntimeException('Real worker not ready');usleep(10000);}if($stop()!==0)throw new RuntimeException('Drain failed');}
   $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);$exit=proc_close($process);$process=null;$rows=array_map(fn($line)=>json_decode($line,true,flags:JSON_THROW_ON_ERROR),explode("\n",trim($out)));return [$exit,$rows[0],$err,$drain&&array_column($rows,'status')===['finished','stopped']];
  }finally{if(is_resource($process)){$stop();proc_close($process);}foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);}
 };
 // The HTTP response is complete; the scheduler creates a fresh child for the actual Engine.
 [$exit,$result,$err,$drained]=$scheduled(true);$check($exit===0&&$result['status']==='finished'&&$err==='','scheduler runs accepted HTTP update in separate PHP child');
 $check($drained,'stop request drains actual Engine child before daemon exits');
 $state=$commands->status();$check($state['request']['status']==='complete'&&$engine->status()['job']['request_id']===$requestId,'audited HTTP request reaches real update engine after response');
 [$status,$body]=$http('/api/admin/update');$data=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data'];$check($status===200&&$data['current_version']==='2.0.0'&&$data['execution']['request']['status']==='complete','fresh HTTP serves updated version and completed outcome');
 [$status,$html]=$http('/admin/update');$check($status===200&&str_contains($html,'generated-update-code-v2'),'HTTP renders changed PHP template after actual Engine replacement');
 $check($data['execution']['rollback']===['from_version'=>'2.0.0','to_version'=>$initial],'HTTP exposes actual retained rollback generation');
 $input=['command_revision'=>$data['execution']['command_revision'],'check_revision'=>$data['revision'],'engine_revision'=>$data['execution']['engine_revision']];[$status,$body]=$http('/api/admin/rollback','POST',$input);$accepted=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data'];$rollbackId=$accepted['execution']['request']['id'];$check($status===202&&$rollbackId!==$requestId&&$accepted['execution']['request']['operation']==='rollback','specified rollback API accepts a distinct request');
 $check(file_get_contents($live.'/bin/run-update.php')===$entry,'update replaces fixture acquisition with production runner entry');
 [$exit,$result,$err]=$scheduled();$check($exit===0&&$result['status']==='finished'&&$err==='','scheduler invokes updated production entry for real rollback');
 $state=$commands->status();$check($state['request']['status']==='rolled_back'&&trim(file_get_contents($live.'/VERSION'))===$initial,'HTTP rollback request restores real previous application');
 [$status,$body]=$http('/api/admin/update');$data=json_decode($body,true,flags:JSON_THROW_ON_ERROR)['data'];$check($status===200&&$data['execution']['request']['status']==='rolled_back'&&$data['execution']['rollback']===null&&count($data['history'])===2,'HTTP resumes with both outcomes and consumed generation');
 $check(hash_file('sha256',$live.'/config/config.php')===$configHash,'HTTP apply and rollback preserve private configuration');
 [$status,$html]=$http('/admin/update');$check($status===200&&!str_contains($html,'generated-update-code-v2'),'HTTP renders restored PHP template after actual rollback');
 echo "$count isolated HTTP update request checks passed.\n";
}finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}
 if($pdo!==null){$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $name){if(!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new RuntimeException('Unexpected test table');$pdo->exec('DROP TABLE `'.$name.'`');}$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
 $remove($directory);
}

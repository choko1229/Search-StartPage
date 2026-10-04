<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{UpdateChecks,UpdateCommands,UpdateJournal,UpdateRequests};
use App\Http\HttpException;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=sys_get_temp_dir().'/update-requests-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/storage',0700);mkdir($root.'/storage/updates',0700);file_put_contents($root.'/VERSION','1.0.0');$count=0;$allowed=true;$audits=[];$failure=false;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $action,string $code)use($check){try{$action();throw new LogicException('Unexpected success');}catch(HttpException $e){$check($e->errorCode===$code,$code);}};
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
$transport=static function()use(&$failure){if($failure)throw new RuntimeException('fixture_secret');return ['status'=>200,'body'=>json_encode([['id'=>17,'tag_name'=>'2.0.0','prerelease'=>false,'draft'=>false,'published_at'=>'2026-10-01T00:00:00Z']])];};
$config=new App\Config(['updates'=>['repository'=>'owner/repo','token'=>'fixture_secret']]);
$checks=new UpdateChecks($root.'/storage/updates/checks',$config,'1.0.0',$transport);
$commands=new UpdateCommands($root.'/storage/updates/commands',static function($actor)use(&$allowed){return $allowed&&$actor===1;},static function($request,$event)use(&$audits){$audits[$request['id']]=$request;},static function($request)use(&$audits){return isset($audits[$request['id']]);});
$journal=new UpdateJournal($root.'/storage/updates/state');$service=new UpdateRequests($root,$checks,$commands,$journal);
try{
 foreach(['ja','en'] as $locale){$messages=(new App\Helpers\Translator(dirname(__DIR__),$locale))->messages();$check(array_diff([...UpdateCommands::ERRORS,'UPDATE_IN_PROGRESS','UPDATE_STATE_CHANGED','UPDATE_COMMAND_INVALID','update_status_prepared'],array_keys($messages))===[],'all public update outcomes have descriptions '.$locale);}
 $initial=$service->status();$check($initial===['command_revision'=>0,'engine_revision'=>0,'request'=>null,'rollback'=>null,'worker_ready'=>false,'busy'=>false],'empty status never invents a job or rollback');
 mkdir($root.'/app');mkdir($root.'/public');$workerPath=$root.'/storage/update-execution-worker.lock';file_put_contents($workerPath,str_repeat('c',32));chmod($workerPath,0600);
 $check(!$service->status()['worker_ready'],'stale worker identity without held lock is unavailable');
 $worker=fopen($workerPath,'r+');flock($worker,LOCK_EX);$check($service->status()['worker_ready'],'held private worker lock makes writable deployment ready');
 file_put_contents($root.'/storage/update-execution-worker.stop',str_repeat('c',32));$check(!$service->status()['worker_ready'],'stopping worker disables execution controls');unlink($root.'/storage/update-execution-worker.stop');
 chmod($workerPath,0644);$check(!$service->status()['worker_ready'],'public worker lock does not enable execution');chmod($workerPath,0600);flock($worker,LOCK_UN);fclose($worker);
 $reject(fn()=>$service->enqueue(1,'apply',0,0,0),'INVALID_UPDATE_RELEASE');$check($commands->status()['request']===null,'no release never creates a request');$checks->check('stable','',0);
 $reject(fn()=>$service->enqueue(1,'apply',0,0,0),'UPDATE_STATE_CHANGED');$reject(fn()=>$service->enqueue(1,'apply',0,1,1),'UPDATE_STATE_CHANGED');$reject(fn()=>$service->enqueue(2,'apply',0,1,0),'FORBIDDEN');
 $reject(fn()=>$service->enqueue(1,'delete',0,1,0),'INVALID_INPUT');$reject(fn()=>$service->enqueue(1,'apply',-1,1,0),'INVALID_INPUT');
 $state=$service->enqueue(1,'apply',0,1,0);$private=$commands->status()['request'];$check($private['from_version']==='1.0.0'&&$private['to_version']==='2.0.0'&&$private['release_id']===17&&$private['repository']==='owner/repo'&&$private['actor']===1,'request selection and actor come from trusted server state');
 $check($state['busy']&&$state['request']['status']==='queued'&&!isset($state['request']['actor'],$state['request']['repository'],$state['request']['release_id'])&&!str_contains(json_encode($state),'fixture_secret'),'public status excludes private context and secrets');
 $reject(fn()=>$service->enqueue(1,'apply',0,1,0),'UPDATE_STATE_CHANGED');$reject(fn()=>$service->enqueue(1,'apply',$state['command_revision'],1,0),'UPDATE_IN_PROGRESS');
 $command=$commands->claim($commands->status()['revision']);$commands->finish($command['request']['id'],$command['revision'],'failed','UPDATE_SOURCE_NOT_FOUND');
 file_put_contents($root.'/VERSION','changed');$reject(fn()=>$service->enqueue(1,'apply',$commands->status()['revision'],1,0),'UPDATE_SOURCE_CHANGED');file_put_contents($root.'/VERSION','1.0.0');
 $job=$journal->start('1.0.0','2.0.0',0);$reject(fn()=>$service->enqueue(1,'apply',$commands->status()['revision'],1,$job['revision']),'UPDATE_IN_PROGRESS');$check($service->status()['busy'],'internal unfinished job also blocks admin acceptance');
 $job=$journal->advance($job['job']['id'],$job['revision'],'verified');$job=$journal->advance($job['job']['id'],$job['revision'],'backing_up');
 $backup=['files'=>['version'=>'1.0.0','files'=>7,'bytes'=>4096,'sha256'=>str_repeat('a',64)],'database'=>['bytes'=>500,'sha256'=>str_repeat('b',64),'database'=>str_repeat('c',64),'tables'=>2,'rows'=>3]];
 foreach(['backed_up','replacing','migrating','checking','complete'] as $phase)$job=$journal->advance($job['job']['id'],$job['revision'],$phase,$phase==='backed_up'?$backup:null);
 file_put_contents($root.'/VERSION','2.0.0');$checks=new UpdateChecks($root.'/storage/updates/checks',$config,'2.0.0',$transport);$service=new UpdateRequests($root,$checks,$commands,$journal);
 $reject(fn()=>$service->enqueue(1,'rollback',$commands->status()['revision'],0,$job['revision']),'UPDATE_BACKUP_UNAVAILABLE');
 $jobDirectory=$root.'/storage/updates/jobs/'.$job['job']['id'];mkdir($jobDirectory,0700,true);file_put_contents($jobDirectory.'/baseline.json','private fixture');chmod($jobDirectory.'/baseline.json',0600);
 $failure=true;$reject(fn()=>$checks->check('stable','',0),'UPDATE_CHECK_FAILED');$check($service->status()['rollback']===['from_version'=>'2.0.0','to_version'=>'1.0.0'],'rollback targets the retained generation');
 $reject(fn()=>$service->enqueue(1,'rollback',$commands->status()['revision'],0,$job['revision']),'UPDATE_STATE_CHANGED');
 $allowed=false;$reject(fn()=>$service->enqueue(1,'rollback',$commands->status()['revision'],1,$job['revision']),'FORBIDDEN');$allowed=true;
 $state=$service->enqueue(1,'rollback',$commands->status()['revision'],1,$job['revision']);$private=$commands->status()['request'];
 $check($state['request']['operation']==='rollback'&&$private['release_id']===null&&$private['to_version']==='1.0.0'&&$state['busy'],'local rollback can be accepted despite failed GitHub check');
 $check($journal->status()===$job&&file_get_contents($root.'/VERSION')==='2.0.0','acceptance never launches a worker or changes files and journal');
 echo "$count admin update request checks passed.\n";
}finally{$remove($root);}

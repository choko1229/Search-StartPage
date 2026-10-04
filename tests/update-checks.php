<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli')exit(1);
use App\Config;
use App\Services\UpdateChecks;
use App\Http\HttpException;
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $action,string $code)use($check):void{try{$action();throw new LogicException('Unexpected success');}catch(HttpException $e){$check($e->errorCode===$code,$code);}};
$directory=sys_get_temp_dir().'/search-update-checks-'.bin2hex(random_bytes(8));$now=1800000000;$calls=0;$failure=false;
$rows=[['id'=>1,'tag_name'=>'v1.2.0','prerelease'=>false,'draft'=>false,'published_at'=>'2026-10-01T00:00:00Z'],['id'=>2,'tag_name'=>'v2.0.0-beta.1','prerelease'=>true,'draft'=>false,'published_at'=>'2026-10-02T00:00:00Z']];
$config=new Config(['updates'=>['repository'=>'owner/repo','token'=>'server_secret_marker']]);
$transport=static function()use(&$calls,&$failure,&$rows):array{++$calls;if($failure)throw new RuntimeException('server_secret_marker');return ['status'=>200,'body'=>json_encode($rows)];};
$clock=static function()use(&$now):int{return $now;};
$make=static fn(string $current='1.0.0',?Config $other=null)=>new UpdateChecks($directory,$other??$config,$current,$transport,$clock);
try{
    $service=$make();$initial=$service->status();
    $check($initial['available']===null&&$initial['checked_at']===null&&$initial['revision']===0,'unchecked is not no update');
    $check($calls===0,'reading status does not contact external services');
    $first=$service->check('stable','',0);$check($first['available']&&$first['release']['tag']==='v1.2.0'&&$first['revision']===1,'stable candidate and initial revision');
    $check($make()->status()===$first,'process reload preserves metadata');
    $check(!str_contains(json_encode($first),'server_secret_marker')&&!str_contains(file_get_contents($directory.'/check.json'),'server_secret_marker'),'token absent from status and file');
    $check((fileperms($directory.'/check.json')&0777)===0600,'metadata is private');
    $reject(fn()=>$service->check('beta','',0),'UPDATE_STATE_CHANGED');$check($calls===1,'stale editors rejected before network');
    $now+=86399;$check($service->check(onlyIfDue:true)===$first&&$calls===1,'cached for less than 24 hours');
    ++$now;$next=$service->check(onlyIfDue:true);$check($calls===2&&$next['revision']===2,'24 hour boundary checks again');
    $beta=$service->check('beta','',2);$check($beta['release']['id']===2,'channel change persists');
    $custom=$service->check('custom','v1.2.0',3);$check($custom['release']['id']===1,'exact custom tag selected');
    $customReload=$make()->check();$check($customReload['custom_tag']==='v1.2.0'&&$customReload['release']['id']===1,'automatic check uses saved tag');
    $explicit=$service->check('custom','v2.0.0-beta.1');$check($explicit['release']['id']===2,'explicit tag replaces previous tag without revision');
    $empty=$service->check('nightly','',6);$check($empty['available']===false&&$empty['release']===null&&$empty['error']===null,'no channel candidate is successful empty result');
    $failure=true;$reject(fn()=>$service->check('stable','',7),'UPDATE_CHECK_FAILED');$failed=$service->status();
    $check($failed['available']===null&&$failed['release']===null&&$failed['error']==='UPDATE_CHECK_FAILED'&&$failed['revision']===8,'failure removes outdated availability and persists safe error');
    $check(!str_contains(json_encode($failed),'server_secret_marker'),'exception message never persisted');
    $before=$calls;$now+=3599;$check($service->check(onlyIfDue:true)===$failed&&$calls===$before,'failed check backs off one hour');
    ++$now;$failure=false;$recovered=$service->check(onlyIfDue:true);$check($calls===$before+1&&$recovered['available']&&$recovered['error']===null,'one hour failure retry recovers');
    $check($make('2.0.0')->status()['checked_at']===null,'changed installed version invalidates old result');
    $check($make('1.0.0',new Config(['updates'=>['repository'=>'other/repo']]))->status()['revision']===0,'changed repository invalidates result');
    $check($make('1.0.0',new Config(['updates'=>['repository'=>'owner/repo','token'=>'changed']]))->status()['revision']===0,'changed server token permits fresh check');
    $reject(fn()=>$service->check('custom',"x\r\n"),'INVALID_UPDATE_CHANNEL');
    $saved=file_get_contents($directory.'/check.json');
    foreach(['{',str_repeat('a',16385)] as $bad){file_put_contents($directory.'/check.json',$bad);$reject(fn()=>$service->status(),'UPDATE_STATE_INVALID');}
    $value=json_decode($saved,true);
    foreach([['available'=>'yes'],['error'=>'server_secret_marker'],['extra'=>'secret'],['release'=>['tag'=>'<script>']],['revision'=>-1],['checked_at'=>0]] as $change){$bad=$value;$bad['state']=array_replace($bad['state'],$change);file_put_contents($directory.'/check.json',json_encode($bad));$reject(fn()=>$service->status(),'UPDATE_STATE_INVALID');}
    file_put_contents($directory.'/check.json',$saved);
    $old=$service->status();$now-=100000;$service->check(onlyIfDue:true);$check($service->status()['revision']===$old['revision']+1,'clock rollback does not retain cache indefinitely');
    $rows=[];$check($make('1.0.0')->check()['available']===false,'empty source is distinct from failure');
    $rows=[['id'=>1,'tag_name'=>'v1.0.0','prerelease'=>false,'draft'=>false,'published_at'=>'2026-10-01T00:00:00Z']];$check(!$service->check()['available'],'equal version is not newer');
    $rows[0]['tag_name']='v0.9.0';$check(!$service->check()['available'],'older version is not newer');
    $reject(fn()=>$make('../unsafe'),'INVALID_UPDATE_CONFIGURATION');
    $beforeCalls=$calls;$beforeState=$service->status();
    $denyAudit=new UpdateChecks($directory,$config,'1.0.0',$transport,$clock,static function(){throw new RuntimeException('Audit storage failure');});
    try{$denyAudit->check('beta');throw new LogicException('Audit failure accepted');}catch(RuntimeException $e){$check($e->getMessage()==='Audit storage failure','audit failure is propagated');}
    $check($calls===$beforeCalls&&$service->status()===$beforeState,'audit failure prevents network and metadata mutation');
}finally{foreach(glob($directory.'/*')?:[] as $path)unlink($path);if(is_file($directory.'/.write.lock'))unlink($directory.'/.write.lock');if(is_dir($directory))rmdir($directory);}
echo "$count update metadata checks passed.\n";

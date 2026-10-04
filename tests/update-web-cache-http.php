<?php
declare(strict_types=1);
// Destructive only inside a deliberately marked, disposable Apache deployment, never an app DB.
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('TEST_WEB_CACHE_DEPLOYMENT')!=='1')exit(1);
$root=dirname(__DIR__);
if(!is_file($root.'/storage/web-cache-test-only')||file_exists($root.'/config/config.php'))throw new RuntimeException('Disposable Apache fixture required');
require $root.'/app/Services/UpdateAccess.php';
$count=0;$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$http=static function(string $path='/'):array{
 $body=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>10,'header'=>'Accept-Language: en']]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return [(int)$match[1],$body];
};
$source=static fn(string $value)=>'<?php header("Content-Type: application/json");echo json_encode(["value"=>'.var_export($value,true).',"sapi"=>PHP_SAPI,"timestamps"=>ini_get("opcache.validate_timestamps"),"cached"=>opcache_is_script_cached(__FILE__),"pid"=>getmypid()]);';
file_put_contents($root.'/app/bootstrap.php',$source('before'));
$gate=new App\Services\UpdateAccess($root.'/storage/updates/access');
[$status,$body]=$http();$first=json_decode($body,true,flags:JSON_THROW_ON_ERROR);
$check($status===200&&$first['value']==='before','Apache loads initial application');
$check($first['sapi']==='apache2handler'&&$first['timestamps']==='0'&&$first['cached'],'real Apache OPcache and timestamps-disabled precondition');
file_put_contents($root.'/app/bootstrap.php',$source('after!'));
[$status,$body]=$http();$check($status===200&&json_decode($body,true)['value']==='before','real Apache serves stale PHP before generation change');
$gate->exclusive(function()use($http,$check){[$status,$body]=$http('/api/admin/update');$check($status===503&&json_decode($body,true)['error']['code']==='UPDATE_IN_PROGRESS','API stays stopped while exclusive update owns gate');[$status,$body]=$http('/');$check($status===503&&str_contains($body,'An update is in progress')&&!str_contains($body,'after!'),'Web stopped before bootstrap during replacement');});
$generation=$gate->generation();$check(preg_match('/^[a-f0-9]{32}$/D',$generation)===1,'exclusive completion advances update generation');
[$status,$body]=$http();$check($status===200&&json_decode($body,true)['value']==='after!','first resumed Apache request loads changed PHP');
$pids=[];for($i=0;$i<20;$i++){[$status,$body]=$http();$data=json_decode($body,true);if($status!==200||$data['value']!=='after!')throw new RuntimeException('Stale Apache child');$pids[$data['pid']]=true;}
$check(count($pids)>1,'multiple Apache children share the refreshed generation');
$marker=$root.'/storage/updates/access/web-cache-'.$generation.'.php';$check(is_file($marker)&&(fileperms($marker)&0077)===0,'Web cache acknowledgement remains private');
file_put_contents($marker,'corrupt');[$status,$body]=$http('/api/admin/update');$check($status===503&&json_decode($body,true)['error']['code']==='UPDATE_IN_PROGRESS'&&!str_contains($body,'Warning'),'corrupt cache acknowledgement fails closed without diagnostics');
unlink($marker);[$status,$body]=$http();$check($status===200&&json_decode($body,true)['value']==='after!','cache acknowledgement can be repaired without changing data');
$gate->exclusive(function()use($root,$source){file_put_contents($root.'/app/bootstrap.php',$source('before'));});
[$status,$body]=$http();$check($status===200&&json_decode($body,true)['value']==='before','rollback generation restores previous PHP in Apache');
$check($gate->generation()!==$generation&&!file_exists($marker),'rollback gets distinct generation and removes old acknowledgement');
$check(!file_exists($root.'/config/config.php')&&!is_dir($root.'/storage/logs/pending'),'fixture never reads real configuration or connects to a database');
echo "$count Apache Web cache checks passed.\n";

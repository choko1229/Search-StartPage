<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\{Database,Migrator};
use App\Repositories\{AuthRepository,SitePolicyRepository,BackgroundRepository};
use App\Services\{PolicyState,SitePolicy,BackgroundInput};
use App\Auth\DeviceAgent;
use App\Http\HttpException;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$config=Config::load($root);$pdo=Database::connect($config->get('database'));
$repository=new SitePolicyRepository($pdo);$state=new PolicyState($root.'/storage/policy');$initial=$repository->read();$ids=[];$jars=[[],[]];$csrf=[null,null];$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$http=static function(int $client,string $path,string $method='GET',?array $body=null,bool $useCsrf=true,string $locale='en')use(&$jars,&$csrf):array{
    $cookies=[];foreach($jars[$client] as $key=>$value)$cookies[]=$key.'='.$value;
    $headers=['Cookie: '.implode('; ',$cookies),'Accept-Language: '.$locale];if($body!==null)$headers[]='Content-Type: application/json';if($useCsrf&&$csrf[$client])$headers[]='X-CSRF-Token: '.$csrf[$client];
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true];if($body!==null)$options['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $response=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match))$jars[$client][$match[1]]=$match[2];return [(int)$status[1],$response];
};
$auth=new AuthRepository($pdo);
try{
    $check((new Migrator($pdo,$root.'/database/migrations'))->migrate()===[],'policy migration repeat safe');
    foreach(['999999999999999958','999999999999999959'] as $index=>$discord){$id=$auth->upsertIdentity(['id'=>$discord,'username'=>'Policy verification','display_name'=>null,'avatar'=>null],'en');$ids[]=$id;$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($id,$device,hash('sha256',$token),DeviceAgent::parse('Windows Chrome/120'),time());$jars[$index]['search_remember']=$device.'.'.$token;}
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    foreach([0,1] as $client){[, $body]=$http($client,'/api/csrf');$csrf[$client]=json_decode($body,true)['data']['csrf_token'];}
    $check($http(1,'/api/admin/policy')[0]===403,'regular user cannot read policy administration');
    $check($http(1,'/api/admin/policy','POST',['policy'=>$initial['policy'],'version'=>$initial['version']])[0]===403,'regular user cannot change policy');
    $check($http(0,'/api/admin/policy','POST',['policy'=>$initial['policy'],'version'=>$initial['version']],false)[0]===403,'policy changes require CSRF');
    foreach(['en'=>'Features and limits','ja'=>'機能切替・制限'] as $locale=>$label)$check(str_contains($http(0,'/admin/policy',locale:$locale)[1],$label),'policy page '.$locale);
    foreach(['background_max_bytes'=>-1,'login_attempts'=>0,'login_window_seconds'=>86401] as $key=>$value){$bad=$initial['policy'];$bad['limits'][$key]=$value;$check($http(0,'/api/admin/policy','POST',['policy'=>$bad,'version'=>$initial['version']])[0]===422,'invalid limit rejected '.$key);}
    $bad=$initial['policy'];$bad['flags']['weather']=1;$check($http(0,'/api/admin/policy','POST',['policy'=>$bad,'version'=>$initial['version']])[0]===422,'nonboolean flag rejected');
    $disabled=$initial['policy'];foreach($disabled['flags'] as &$flag)$flag=false;unset($flag);$disabled['limits']['background_max_bytes']=1;
    [$status,$body]=$http(0,'/api/admin/policy','POST',['policy'=>$disabled,'version'=>$initial['version']]);$result=json_decode($body,true)['data'];
    $check($status===200 && $result['version']===$initial['version']+1,'admin policy changes committed');
    $check($http(0,'/api/admin/policy','POST',['policy'=>$initial['policy'],'version'=>$initial['version']])[0]===409,'stale editor rejected');
    foreach(['/api/sync','/api/settings','/api/favorites','/api/backgrounds','/api/search/suggest?q=test','/api/favorites/metadata?url=https%3A%2F%2Fexample.test','/api/weather'] as $path)$check($http(1,$path)[0]===403,'disabled server feature '.$path);
    $check($http(1,'/api/backgrounds/upload','POST',[])[0]===403,'disabled upload rejected before file processing');
    [, $body]=$http(1,'/api/site-policy');$check(json_decode($body,true)['data']['flags']===$disabled['flags'],'public policy exposes flags only');
    $check($http(1,'/')[0]===200 && $http(0,'/admin')[0]===200,'local home and administration remain available');
    $audit=$pdo->prepare('SELECT error_code,file_written FROM log_entries WHERE id=?');$audit->execute([$result['audit_id']]);$row=$audit->fetch(PDO::FETCH_ASSOC);$check($row['error_code']==='SITE_POLICY_CHANGED'&&(int)$row['file_written']===1,'policy change audited in DB and file');
    $enabled=$initial['policy'];$enabled['limits']['background_max_bytes']=1;
    $repository->update($enabled,$repository->read()['version'],$ids[0],$state);
    $resolve=static fn(bool $lock=false):int=>SitePolicy::effective($repository->read($lock)['policy'],$config)['limits']['background_max_bytes'];
    $background=new BackgroundRepository($pdo,0,$resolve);
    $item=BackgroundInput::validate((object)['id'=>'policy-quota','name'=>'Generated quota'],true);
    $file=['filename'=>str_repeat('a',48).'.png','bytes'=>1,'type'=>'image','mime'=>'image/png'];
    $saved=$background->save($ids[1],$item,null,$file);$check($background->usage($ids[1])['limit_bytes']===1,'dynamic quota visible');
    try{$other=$item;$other['id']='policy-overflow';$background->save($ids[1],$other,null,$file);throw new RuntimeException('quota accepted');}catch(HttpException $error){$check($error->errorCode==='BACKGROUND_QUOTA_EXCEEDED','dynamic quota rejects extra bytes');}
    $lower=$enabled;$lower['limits']['background_max_bytes']=0;$repository->update($lower,$repository->read()['version'],$ids[0],$state);
    $larger=$file;$larger['bytes']=2;$saved=$background->save($ids[1],$item,(int)$saved['version'],$larger);
    $repository->update($enabled,$repository->read()['version'],$ids[0],$state);
    $item['name']='Retained over quota';$saved=$background->save($ids[1],$item,(int)$saved['version']);
    $check((int)$saved['file_size']===2,'lowering limit preserves file and allows metadata edits');
    $saved=$background->save($ids[1],$item,(int)$saved['version'],$file);$check((int)$saved['file_size']===1,'over quota file can shrink');
    $check($http(1,'/api/sync')[0]===200,'re-enabled cloud sync resumes');
    $loginPolicy=$enabled;$loginPolicy['limits']['login_attempts']=1;
    $repository->update($loginPolicy,$repository->read()['version'],$ids[0],$state);
    $http(1,'/api/auth/discord/callback?state=generated-policy-test');
    $check($http(1,'/api/auth/discord/callback?state=generated-policy-test')[0]===429,'dynamic login attempt limit applies to real OAuth route');
}finally{
    if($ids)$repository->update($initial['policy'],$repository->read()['version'],$ids[0],$state);
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$count policy checks passed.\n";

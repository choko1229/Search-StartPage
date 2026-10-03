<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\AuthRepository;
use App\Services\DiscordOAuth;
use App\Auth\DeviceAgent;
if(PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));$repository=new AuthRepository($pdo);
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$uid=$repository->upsertIdentity(DiscordOAuth::validateIdentity(['id'=>'999999999999999957','username'=>'Error log HTTP verification']),'en');
$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$repository->createDevice($uid,$device,hash('sha256',$token),DeviceAgent::parse('Windows Chrome/120'),time());
$cookies=['search_remember'=>$device.'.'.$token];$csrf=null;$eventIds=[];
$http=static function(string $path,string $method='GET',?string $body=null,bool $useCsrf=true)use(&$cookies,&$csrf):array {
    $cookie=[];foreach($cookies as $name=>$value)$cookie[]=$name.'='.$value;
    $headers=['Cookie: '.implode('; ',$cookie),'Accept-Language: en'];
    if($body!==null)$headers[]='Content-Type: application/json';
    if($useCsrf && $csrf)$headers[]='X-CSRF-Token: '.$csrf;
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true];if($body!==null)$options['content']=$body;
    $result=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match))$cookies[$match[1]]=$match[2];
    return [(int)$status[1],$result];
};
try {
    [, $body]=$http('/api/csrf');$csrf=json_decode($body,true)['data']['csrf_token'];
    foreach ([['/api/unknown?secret=generated-query-marker','GET',null,404,'api_error','NOT_FOUND',true],
        ['/api/auth/discord/callback?code=generated-code-marker&state=generated-state-marker','GET',null,400,'oauth_error','OAUTH_STATE_INVALID',true],
        ['/api/sync','POST','{"password":"generated-body-marker"}',422,'sync_error','INVALID_INPUT',true],
        ['/api/sync','POST','{}',403,'security','CSRF_INVALID',false],
        ['/api/sync','POST','[]',400,'sync_error','INVALID_JSON',true]] as [$path,$method,$payload,$expectedStatus,$type,$code,$useCsrf]) {
        $before=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM log_entries')->fetchColumn();
        [$status,$body]=$http($path,$method,$payload,$useCsrf);
        $check($status===$expectedStatus && json_decode($body,true)['error']['code']===$code,'real error response '.$code);
        $query=$pdo->prepare('SELECT event_id,type,error_code,context_json,file_written FROM log_entries WHERE id>?');$query->execute([$before]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
        $check(count($rows)===1 && $rows[0]['type']===$type && $rows[0]['error_code']===$code && (int)$rows[0]['file_written']===1,'real error logged once '.$code);
        $eventIds[]=$rows[0]['event_id'];
        $check(!str_contains($rows[0]['context_json'],'generated-') && !str_contains($body,'Stack trace') && !str_contains($body,'Fatal error'),'real error omits input secrets and stack '.$code);
    }
    // A valid sync write followed by a stale write returns a direct Response,
    // rather than throwing. The response delivery hook must collect it once.
    [, $body]=$http('/api/sync');$state=json_decode($body,false,512,JSON_THROW_ON_ERROR)->data;
    $payload=json_encode(['version'=>$state->version,'document'=>$state->document],JSON_THROW_ON_ERROR);
    $check($http('/api/sync','POST',$payload)[0]===200,'ordinary sync write remains successful');
    $before=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM log_entries')->fetchColumn();
    $check($http('/api/sync','POST',$payload)[0]===409,'direct sync conflict response unchanged');
    $query=$pdo->prepare('SELECT event_id,type,error_code FROM log_entries WHERE id>?');$query->execute([$before]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
    $check(count($rows)===1 && $rows[0]['type']==='sync_error' && $rows[0]['error_code']==='SYNC_CONFLICT','direct response collected once without decoding user data: '.json_encode(array_map(static fn(array $row):array=>['type'=>$row['type'],'code'=>$row['error_code']],$rows)));$eventIds[]=$rows[0]['event_id'];
    $file=file_get_contents($root.'/storage/logs/'.gmdate('Y-m-d').'.jsonl');
    foreach($eventIds as $id)$check(str_contains($file,$id),'real event persisted to file');
    $check(!str_contains($file,'generated-query-marker') && !str_contains($file,'generated-code-marker') && !str_contains($file,'generated-body-marker'),'real HTTP secrets never enter files');
} finally {
    foreach($eventIds as $id)$pdo->prepare('DELETE FROM log_entries WHERE event_id=?')->execute([$id]);
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
}
echo "$count HTTP error log checks passed.\n";

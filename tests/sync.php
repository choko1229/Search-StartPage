<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\{Config};use App\Database\Database;use App\Repositories\{AuthRepository,SyncRepository};use App\Services\SyncDocument;use App\Http\HttpException;
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));$auth=new AuthRepository($pdo);$repo=new SyncRepository($pdo);
$uid=$auth->upsertIdentity(['id'=>'999999999999999981','username'=>'Sync Test','display_name'=>null,'avatar'=>null],'en');
$other=$auth->upsertIdentity(['id'=>'999999999999999982','username'=>'Other Sync','display_name'=>null,'avatar'=>null],'en');
$count=0;$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;echo "PASS: $label\n";};
try{
    $document=SyncDocument::validate(json_decode('{"settings":{"theme":"dark"},"favorites":{}}'));
    $check($repo->read($uid)['version']===0,'initial version zero');
    $check($repo->write($uid,0,$document)['version']===1,'first CAS update');
    $check($repo->write($uid,0,(object)[])===null,'stale write rejected');
    $check($repo->read($uid)['document']->settings->theme==='dark','conflict preserves cloud data');
    $check($repo->read($other)['version']===0,'owners isolated');
    $check($repo->write($uid,1,$document)['version']===2,'matching version advances');
    foreach(['{"secrets":{}}','{"settings":{"__proto__":{}}}','{"favorites":{"a":{"id":"b","url":"https://example.test"}}}','{"favorites":{"a":{"id":"a","url":"javascript:alert(1)"}}}'] as $bad){try{SyncDocument::validate(json_decode($bad));throw new RuntimeException('invalid data accepted');}catch(HttpException $e){$check($e->status===422,'invalid sync document rejected');}}
    $device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($uid,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());
    $cookies=['search_remember'=>$device.'.'.$token];
    $request=static function(string $method,string $path,?array $body=null,?string $csrf=null)use(&$cookies){
        $headers=['Cookie: '.implode('; ',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies))];if($body!==null)$headers[]='Content-Type: application/json';if($csrf)$headers[]='X-CSRF-Token: '.$csrf;
        $result=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body?json_encode($body):'','ignore_errors'=>true]]));
        foreach($http_response_header as $line)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$m))$cookies[$m[1]]=$m[2];
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);return [(int)$status[1],json_decode($result,true,flags:JSON_THROW_ON_ERROR)];
    };
    [, $json]=$request('GET','/api/csrf');$csrf=$json['data']['csrf_token'];
    [$status,$json]=$request('GET','/api/sync');$check($status===200&&$json['data']['version']===2,'authenticated read');
    [$status]=$request('PUT','/api/sync',['version'=>2,'document'=>$document]);$check($status===403,'write requires CSRF');
    [$status,$json]=$request('PUT','/api/sync',['version'=>2,'document'=>$document],$csrf);$check($status===200&&$json['data']['version']===3,'HTTP save advances version');
    [$status,$json]=$request('PUT','/api/sync',['version'=>2,'document'=>$document],$csrf);$check($status===409&&$json['data']['version']===3,'HTTP conflict returns current cloud');
    [$status]=$request('PUT','/api/sync',['version'=>3,'document'=>(object)[],'user_id'=>(string)$other],$csrf);$check($status===403,'account switch hint cannot select another owner');
    $check($repo->read($uid)['version']===3 && $repo->read($other)['version']===0,'account switch rejection preserves both owners');
    $cookies=[];[$status]=$request('GET','/api/sync');$check($status===401,'anonymous sync rejected');
}finally{$pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$uid,$other]);}
echo "$count sync assertions passed.\n";

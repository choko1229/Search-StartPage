<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
use App\Config;
use App\Database\Database;
use App\Repositories\AuthRepository;
use App\Services\UpdateChecks;
use App\Auth\DeviceAgent;
$root=dirname(__DIR__);$config=Config::load($root);$pdo=Database::connect($config->get('database'));$ids=[];$jars=[[],[],[]];$csrf=[];$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$http=static function(int $client,string $path,string $method='GET',?array $body=null,bool $withCsrf=true,string $locale='en')use(&$jars,&$csrf):array{
    $cookies=[];foreach($jars[$client] as $key=>$value)$cookies[]=$key.'='.$value;
    $headers=['Cookie: '.implode('; ',$cookies),'Accept-Language: '.$locale];
    if($body!==null)$headers[]='Content-Type: application/json';if($withCsrf&&isset($csrf[$client]))$headers[]='X-CSRF-Token: '.$csrf[$client];
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>60];if($body!==null)$options['content']=json_encode((object)$body,JSON_THROW_ON_ERROR);
    $response=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$m))$jars[$client][$m[1]]=$m[2];return [(int)$status[1],$response];
};
$directory=$root.'/storage/updates/checks';$path=$directory.'/check.json';$backup=is_file($path)?file_get_contents($path):null;$auth=new AuthRepository($pdo);
try{
    foreach(['999999999999999941','999999999999999942'] as $index=>$discord){$id=$auth->upsertIdentity(['id'=>$discord,'username'=>'Update verification','display_name'=>null,'avatar'=>null],'en');$ids[]=$id;$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($id,$device,hash('sha256',$token),DeviceAgent::parse('Windows Chrome/120'),time());$jars[$index]['search_remember']=$device.'.'.$token;}
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    foreach([0,1,2] as $client){[, $body]=$http($client,'/api/csrf');$csrf[$client]=json_decode($body,true)['data']['csrf_token'];}
    foreach(['/admin/update','/api/admin/update'] as $route){
        $check($http(2,$route)[0]===401,'guest cannot read '.$route);$check($http(1,$route)[0]===403,'regular user cannot read '.$route);
        $check($http(2,$route,'POST',[])[0]===401,'guest cannot check '.$route);$check($http(1,$route,'POST',[])[0]===403,'regular user cannot check '.$route);
        $check($http(0,$route,'POST',[],false)[0]===403,'CSRF required '.$route);
    }
    $row=['id'=>1,'tag_name'=>'v999.0.0','prerelease'=>false,'draft'=>false,'published_at'=>'2026-10-04T00:00:00Z'];
    $state=(new UpdateChecks($directory,$config,trim(file_get_contents($root.'/VERSION')),static fn()=>['status'=>200,'body'=>json_encode([$row])]))->check('stable');
    [$status,$body]=$http(0,'/api/admin/update');$data=json_decode($body,true)['data'];
    $check($status===200&&$data['available']===true&&$data['release']['tag']==='v999.0.0','admin reads persisted release fixture');
    $check(!str_contains($body,'identity')&&!str_contains($body,'Authorization')&&!str_contains($body,'token'),'private fields excluded');
    foreach(['en'=>'Updates','ja'=>'更新管理'] as $locale=>$label){[$status,$html]=$http(0,'/admin/update',locale:$locale);$check($status===200&&str_contains($html,$label)&&str_contains($html,'v999.0.0'),'update screen '.$locale);}
    [$status,$html]=$http(0,'/admin');$check($status===200&&str_contains($html,'A new version is available:')&&str_contains($html,'v999.0.0'),'dashboard notification from actual cached data');
    $check(!str_contains($http(1,'/')[1],'v999.0.0'),'regular home does not notify');
    $check(!str_contains($http(2,'/')[1],'v999.0.0'),'guest home does not notify');
    $check(str_contains($http(0,'/')[1],'v999.0.0'),'current administrator home notifies');
    foreach([['channel'=>'evil','revision'=>$state['revision']],['channel'=>'custom','custom_tag'=>'<script>','revision'=>$state['revision']],['channel'=>'stable','revision'=>'0'],['channel'=>[],'revision'=>$state['revision']],['channel'=>'stable','revision'=>-1]] as $bad)$check($http(0,'/api/admin/update','POST',$bad)[0]===422,'invalid input rejected');
    $check($http(0,'/api/admin/update','POST',['channel'=>'stable','revision'=>$state['revision']-1])[0]===409,'stale editor rejected before external check');
    [$status,$body]=$http(0,'/api/admin/update','POST',['channel'=>'stable','revision'=>$state['revision']]);
    $check(in_array($status,[200,502,503],true),'manual route reaches real source and returns bounded result');
    $result=json_decode($body,true);$state=(new UpdateChecks($directory,$config,trim(file_get_contents($root.'/VERSION'))))->status();
    $check($state['revision']===$data['revision']+1,'manual result persisted once');
    $audit=$pdo->prepare("SELECT context_json,file_written FROM log_entries WHERE user_id=? AND type='admin_audit' AND error_code='UPDATE_CHECK_REQUESTED' ORDER BY id DESC LIMIT 1");$audit->execute([$ids[0]]);$entry=$audit->fetch(PDO::FETCH_ASSOC);
    $check($entry!==false&&(int)$entry['file_written']===1&&json_decode($entry['context_json'],true)['requested_channel']==='stable','manual attempt audited before source access');
    if($status===200)$check($result['success']===true&&is_bool($state['available']),'real check success matches stored result');
    else $check($result['success']===false&&$result['error']['code']===$state['error']&&$state['available']===null,'real failure is not no update');
    (new UpdateChecks($directory,$config,trim(file_get_contents($root.'/VERSION')),static fn()=>['status'=>200,'body'=>json_encode([$row])]))->check('stable');
    $check(str_contains($http(0,'/')[1],'v999.0.0'),'notification exists immediately before revocation');
    $pdo->prepare('UPDATE administrators SET admin_flag=0 WHERE user_id=?')->execute([$ids[0]]);
    $check($http(0,'/api/admin/update')[0]===403,'revocation applies to existing session');
    $check(!str_contains($http(0,'/')[1],'v999.0.0'),'revoked home does not notify');
}finally{
    App\Services\LogFileLock::run($directory,static function()use($path,$backup):void{if($backup===null){if(is_file($path))unlink($path);}else{file_put_contents($path,$backup);chmod($path,0600);}});
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$count admin update checks passed.\n";

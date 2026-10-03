<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Repositories\{AdminRoleRepository,AuthRepository};
$root=dirname(__DIR__);$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');$migrator->migrate();$roles=new AdminRoleRepository($pdo);
if((int)$pdo->query('SELECT COUNT(*) FROM administrators WHERE admin_flag=1')->fetchColumn()!==0)throw new RuntimeException('Empty disposable administrator environment required');
$ids=[];$jars=[[],[],[]];$csrf=[null,null,null];$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);$count++;echo "PASS: $name\n";};
$http=static function(int $client,string $path,string $method='GET',mixed $body=null,bool $useCsrf=true,string $locale='en',bool $form=false)use(&$jars,&$csrf):array{
    $headers=['Cookie: '.implode('; ',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($jars[$client]),$jars[$client])),'Accept-Language: '.$locale];
    if($body!==null)$headers[]='Content-Type: '.($form?'application/x-www-form-urlencoded':'application/json');if($useCsrf&&$csrf[$client])$headers[]='X-CSRF-Token: '.$csrf[$client];
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true];if($body!==null)$options['content']=$form?http_build_query($body):json_encode($body,JSON_THROW_ON_ERROR);
    $response=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match))$jars[$client][$match[1]]=$match[2];return [(int)$status[1],$response,json_decode($response,true)];
};
$change=static fn(int $target,bool $enabled,bool $expected,int $version)=>['user_id'=>$target,'admin_flag'=>$enabled,'expected_admin_flag'=>$expected,'version'=>$version];
try{
    $check($migrator->migrate()===[],'role migration repeat safe');$auth=new AuthRepository($pdo);
    foreach(range(0,1) as $client){$discord='999'.str_pad((string)random_int(1,999999999999999),15,'0',STR_PAD_LEFT);$id=$auth->upsertIdentity(['id'=>$discord,'username'=>'Role verification','display_name'=>null,'avatar'=>null],'en');$ids[]=$id;$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($id,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());$jars[$client]['search_remember']=$device.'.'.$token;}
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    foreach(range(0,2) as $client)$csrf[$client]=$http($client,'/api/csrf')[2]['data']['csrf_token'];
    $version=$roles->version();$body=$change($ids[1],true,false,$version);
    $check($http(2,'/api/admin/users/role','POST',$body)[0]===401,'guest cannot grant role');
    $check($http(1,'/api/admin/users/role','POST',$body)[0]===403,'regular user cannot self grant');
    $check($http(0,'/api/admin/users/role','POST',$body,false)[0]===403,'role changes require CSRF');
    foreach([['user_id'=>0],['user_id'=>"' OR 1=1"],['user_id'=>[]],['admin_flag'=>1],['expected_admin_flag'=>'false'],['version'=>0]] as $bad)$check($http(0,'/api/admin/users/role','POST',array_replace($body,$bad))[0]===422,'invalid role input');
    $last=$http(0,'/api/admin/users/role','POST',$change($ids[0],false,true,$version));$check($last[0]===409&&$last[2]['error']['code']==='LAST_ADMIN_REQUIRED','last administrator protected');
    $check($roles->version()===$version,'failed change leaves version intact');
    $check($http(0,'/api/admin/users/role','POST',$change(9007199254740000,true,false,$version))[0]===404,'missing target rejected');
    $result=$http(0,'/api/admin/users/role','POST',$body);$data=$result[2]['data'];$check($result[0]===200&&$data['changed']&&$data['version']===$version+1,'administrator granted');
    $check($http(1,'/api/admin/dashboard')[0]===200,'existing login gains server verified access');
    $query=$pdo->prepare('SELECT created_by,admin_flag FROM administrators WHERE user_id=?');$query->execute([$ids[1]]);$row=$query->fetch(PDO::FETCH_ASSOC);$check((int)$row['created_by']===$ids[0]&&(int)$row['admin_flag']===1,'creator and role persisted');
    $audit=$pdo->prepare('SELECT user_id,error_code,context_json,file_written FROM log_entries WHERE id=?');$audit->execute([$data['audit_id']]);$row=$audit->fetch(PDO::FETCH_ASSOC);$context=json_decode($row['context_json'],true);
    $check((int)$row['user_id']===$ids[0]&&$row['error_code']==='ADMIN_ROLE_CHANGED'&&(int)$row['file_written']===1&&$context['before']===false&&$context['after']===true,'before after actor audit DB and file');
    $check($http(0,'/api/admin/users/role','POST',$body)[0]===409,'stale edit rejected');
    $check($http(0,'/api/admin/users/role','POST',$change($ids[1],false,false,$roles->version()))[0]===409,'stale expected role rejected');
    $noop=$http(0,'/api/admin/users/role','POST',$change($ids[1],true,true,$roles->version()));$check($noop[0]===200&&!$noop[2]['data']['changed']&&!isset($noop[2]['data']['audit_id']),'no-op does not fabricate change audit');
    foreach(['en'=>'Revoke administrator access','ja'=>'管理者権限を解除'] as $locale=>$label)$check(str_contains($http(0,'/admin/users',locale:$locale)[1],$label),'role controls '.$locale);
    $form=$change($ids[1],false,true,$roles->version());$form['user_id']=(string)$form['user_id'];$form['version']=(string)$form['version'];$form['admin_flag']='0';$form['expected_admin_flag']='1';$form['_csrf']=$csrf[0];
    $check($http(0,'/admin/users/role','POST',$form,form:true)[0]===303,'HTML role revoke redirects');
    $check($http(1,'/api/admin/dashboard')[0]===403,'existing login loses administrator access');
    $query->execute([$ids[1]]);$check((int)$query->fetch(PDO::FETCH_ASSOC)['created_by']===$ids[0],'revocation preserves creator');
    $check($http(0,'/api/admin/users/role','POST',$change($ids[1],true,false,$roles->version()))[0]===200,'revoked role can be granted again');
    $check($http(0,'/api/admin/users/role','POST',$change($ids[0],false,true,$roles->version()))[0]===200,'self revocation allowed with another administrator');
    try{$roles->update($ids[1],false,true,$roles->version(),$ids[0]);throw new RuntimeException('revoked actor accepted');}catch(App\Http\HttpException $error){$check($error->status===403,'repository rechecks revoked actor under lock');}
    $last=$http(1,'/api/admin/users/role','POST',$change($ids[1],false,true,$roles->version()));$check($last[0]===409,'remaining administrator still protected');
}finally{foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
echo "$count administrator role checks passed.\n";

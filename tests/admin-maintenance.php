<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\{Database,Migrator};
use App\Repositories\{AuthRepository,AdminSettingsRepository};
use App\Services\{DiscordOAuth,MaintenanceState,AdminAuditLogger,FileLogger};
use App\Auth\DeviceAgent;
if (PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1') exit(1);
$root=dirname(__DIR__);
$pdo=Database::connect(Config::load($root)->get('database'));
$checkCount=0;
$check=static function(bool $ok,string $name) use(&$checkCount):void { if (!$ok) throw new RuntimeException($name); ++$checkCount; echo "PASS: $name\n"; };
$check((new Migrator($pdo,$root.'/database/migrations'))->migrate()===[],'migration repeat safe');
$signal=new MaintenanceState($root.'/storage/runtime');
$settings=new AdminSettingsRepository($pdo,$signal);
$initial=$settings->maintenance();
if ($initial['enabled']) throw new RuntimeException('Dedicated test environment must start outside maintenance');
$ids=[]; $jars=[[],[],[]];
$request=static function(int $client,string $path,string $method='GET',?array $body=null,bool $csrf=true,string $locale='en',bool $form=false) use(&$jars):array {
    $cookies=[]; foreach ($jars[$client] as $key=>$value) $cookies[]=$key.'='.$value;
    $headers=['Accept-Language: '.$locale,'Cookie: '.implode('; ',$cookies)];
    if ($body!==null) $headers[]='Content-Type: '.($form ? 'application/x-www-form-urlencoded' : 'application/json');
    if ($csrf && isset($jars[$client]['_csrf_token'])) $headers[]='X-CSRF-Token: '.$jars[$client]['_csrf_token'];
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'follow_location'=>0];
    if ($body!==null) $options['content']=$form ? http_build_query($body) : json_encode($body,JSON_THROW_ON_ERROR);
    $result=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach ($http_response_header as $header) if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match)) $jars[$client][$match[1]]=$match[2];
    return [(int)$status[1],$result,$http_response_header];
};
$repository=new AuthRepository($pdo);
try {
    foreach (['999999999999999953','999999999999999954'] as $index=>$discordId) {
        $id=$repository->upsertIdentity(DiscordOAuth::validateIdentity(['id'=>$discordId,'username'=>'Maintenance verification']),'en');
        $ids[]=$id;
        $device=bin2hex(random_bytes(16)); $token=bin2hex(random_bytes(32));
        $repository->createDevice($id,$device,hash('sha256',$token),DeviceAgent::parse('Windows Chrome/120'),time());
        $jars[$index+1]['search_remember']=$device.'.'.$token;
    }
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    foreach ([0,1,2] as $client) {
        [, $body]=$request($client,'/api/csrf');
        $jars[$client]['_csrf_token']=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data']['csrf_token'];
    }
    $check($request(0,'/api/admin/maintenance','POST',['enabled'=>true,'version'=>$initial['version']])[0]===401,'guest cannot change maintenance');
    $check($request(2,'/api/admin/maintenance','POST',['enabled'=>true,'version'=>$initial['version']])[0]===403,'regular user cannot change maintenance');
    $check($request(1,'/api/admin/maintenance','POST',['enabled'=>true,'version'=>$initial['version']],false)[0]===403,'admin changes require CSRF');
    foreach ([['enabled'=>'true','version'=>$initial['version']],['enabled'=>true,'version'=>0],['enabled'=>true,'version'=>'1'],['enabled'=>[],'version'=>1]] as $invalid) {
        $check($request(1,'/api/admin/maintenance','POST',$invalid)[0]===422,'invalid maintenance payload rejected');
    }
    $check($settings->maintenance()===$initial,'failed changes preserve settings');
    try { $settings->setMaintenance(true,$initial['version'],0); throw new RuntimeException('Missing audit actor accepted'); }
    catch (PDOException $error) { $check($error->getCode()==='23000','invalid actor rolls back the setting transaction'); }
    $check($settings->maintenance()===$initial && !$signal->active(),'DB failure preserves version and public state');
    [$status,$body]=$request(1,'/api/admin/maintenance','POST',['enabled'=>true,'version'=>$initial['version']]);
    $result=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data'];
    $check($status===200 && $result['enabled'] && $result['version']===$initial['version']+1 && $signal->active(),'admin enables durable maintenance');
    $audit=$pdo->prepare('SELECT type,error_code,user_id,context_json,file_written FROM log_entries WHERE id=?'); $audit->execute([$result['audit_id']]); $row=$audit->fetch(PDO::FETCH_ASSOC);
    $check($row['type']==='admin_audit' && $row['error_code']==='MAINTENANCE_CHANGED' && (int)$row['user_id']===$ids[0] && (int)$row['file_written']===1,'DB audit and file delivery recorded');
    $check(json_decode($row['context_json'],true)['after']===true,'audit stores exact safe change');
    $file=file_get_contents($root.'/storage/logs/'.gmdate('Y-m-d').'.jsonl');
    $check(str_contains($file,'"audit_id":'.$result['audit_id'].',') && !str_contains($row['context_json'],'search_remember'),'file audit written without login secrets');
    foreach (['/','/account','/admin','/installer','/unknown-route','/api/health','/api/user','/api/csrf','/api/search/suggest?q=test','/api/sync','/api/backgrounds','/api/admin/users'] as $path) {
        foreach ([0,2] as $client) $check($request($client,$path)[0]===503,'full stop '.$client.' '.$path);
    }
    foreach (['POST','PUT','DELETE'] as $method) $check($request(2,'/api/sync',$method,[],false)[0]===503,'maintenance blocks mutation '.$method);
    [$status,$body,$headers]=$request(0,'/',locale:'ja');
    $check($status===503 && str_contains($body,'メンテナンス中です') && str_contains($body,'再読み込み') && !str_contains($body,'site-header'),'Japanese maintenance-only page');
    $check(in_array('Retry-After: 60',$headers,true),'maintenance retry header');
    $check(str_contains($request(0,'/api/user',locale:'ja')[1],'メンテナンス中です'),'Japanese API maintenance message');
    foreach (['/','/account','/admin','/admin/maintenance','/api/user','/api/sync','/api/admin/maintenance'] as $path) $check($request(1,$path)[0]===200,'administrator remains operational '.$path);
    $check($request(1,'/api/admin/maintenance','POST',['enabled'=>false,'version'=>$initial['version']])[0]===409,'stale editor cannot disable maintenance');
    $check($settings->maintenance()['enabled'],'stale write keeps full stop');
    [$status,$body]=$request(1,'/api/admin/maintenance','POST',['enabled'=>false,'version'=>$result['version']]);
    $check($status===200 && !json_decode($body,true)['data']['enabled'] && !$signal->active(),'administrator disables maintenance');
    $check($request(0,'/')[0]===200 && $request(2,'/api/user')[0]===200,'public and authenticated access restored');
    $current=$settings->maintenance();
    $check($request(1,'/admin/maintenance','POST',['enabled'=>'0','version'=>(string)$current['version']],form:true)[0]===303,'HTML form saves and redirects');
    $check($request(1,'/admin/maintenance','POST',['enabled'=>'x','version'=>'1'],form:true)[0]===422,'HTML form validates enabled');
    $check($request(1,'/admin/maintenance','POST',['enabled'=>'0','version'=>'1'],csrf:false,form:true)[0]===403,'HTML form requires CSRF');
    [$privateStatus,$privateBody]=$request(0,'/storage/runtime/maintenance.json');
    $check(in_array($privateStatus,[403,404],true) && !str_contains($privateBody,'"enabled"'),'maintenance signal is private');

    // Simulate an unavailable file sink; the DB outbox must survive and retry.
    $recovery=$root.'/storage/audit-recovery-'.bin2hex(random_bytes(8)); mkdir($recovery,0700);
    file_put_contents($recovery.'/blocked','generated');
    $pdo->prepare("INSERT INTO log_entries(type,error_code,user_id,context_json,created_at) VALUES ('admin_audit','AUDIT_RECOVERY_TEST',?,'{}',UTC_TIMESTAMP())")->execute([$ids[0]]);
    $recoveryId=(int)$pdo->lastInsertId();
    set_error_handler(static function(int $severity,string $message):never { throw new RuntimeException('Injected file sink failure'); });
    try { (new AdminAuditLogger($pdo,new FileLogger($recovery.'/blocked')))->flush(); } finally { restore_error_handler(); }
    $audit->execute([$recoveryId]); $check((int)$audit->fetch(PDO::FETCH_ASSOC)['file_written']===0,'file failure remains pending in DB');
    (new AdminAuditLogger($pdo,new FileLogger($recovery.'/logs')))->flush();
    $audit->execute([$recoveryId]); $check((int)$audit->fetch(PDO::FETCH_ASSOC)['file_written']===1,'pending file audit retries successfully');
    unlink($recovery.'/logs/'.gmdate('Y-m-d').'.jsonl'); unlink($recovery.'/logs/.write.lock'); rmdir($recovery.'/logs'); unlink($recovery.'/blocked'); rmdir($recovery);
} finally {
    if ($ids && $settings->maintenance()['enabled']) $settings->setMaintenance(false,$settings->maintenance()['version'],$ids[0]);
    foreach ($ids as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$checkCount maintenance checks passed.\n";

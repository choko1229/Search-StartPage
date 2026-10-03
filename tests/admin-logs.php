<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\{AuthRepository,LogRepository};
use App\Services\{DiscordOAuth,LogFilters,LogRetention,FileLogger};
use App\Auth\DeviceAgent;
if (PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1') exit(1);
$root=dirname(__DIR__); $pdo=Database::connect(Config::load($root)->get('database'));
$auth=new AuthRepository($pdo); $logs=new LogRepository($pdo); $ids=[]; $cookies=[]; $logIds=[];
$marker='generated-log-'.bin2hex(random_bytes(8));
$count=0;
$check=static function(bool $ok,string $name)use(&$count):void {if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$http=static function(string $path,string $cookie='',string $locale='en'):array {
    $body=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['header'=>"Accept-Language: $locale\r\nCookie: $cookie",'ignore_errors'=>true]]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    return [(int)$status[1],$body];
};
$testDirectory=sys_get_temp_dir().'/log-retention-'.bin2hex(random_bytes(8));
try {
    foreach (['999999999999999955','999999999999999956'] as $discordId) {
        $id=$auth->upsertIdentity(DiscordOAuth::validateIdentity(['id'=>$discordId,'username'=>'Log verification']),'en');$ids[]=$id;
        $device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));
        $auth->createDevice($id,$device,hash('sha256',$token),DeviceAgent::parse('Windows Chrome/120'),time());
        $cookies[]='search_remember='.$device.'.'.$token;
    }
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    $insert=$pdo->prepare('INSERT INTO log_entries(type,error_code,user_id,context_json,created_at,file_written) VALUES (?,?,?,?,?,1)');
    foreach (LogFilters::TYPES as $type) {
        $insert->execute([$type,'GENERATED_LOG_TEST',$ids[0],json_encode(['marker'=>$marker,'generated'=>'<img onerror=alert(1)>%_'],JSON_THROW_ON_ERROR),gmdate('Y-m-d').' 12:00:00']);
        $logIds[]=(int)$pdo->lastInsertId();
    }
    $insert->execute(['api_error','GENERATED_YESTERDAY',$ids[1],json_encode(['marker'=>$marker]),gmdate('Y-m-d',time()-86400).' 23:59:59']);$logIds[]=(int)$pdo->lastInsertId();
    foreach (['/admin/logs','/admin/audit-logs','/api/admin/logs','/api/admin/audit-logs'] as $path) {
        $check($http($path)[0]===401,'guest denied '.$path);
        $check($http($path,$cookies[1])[0]===403,'regular user denied '.$path);
        $check($http($path,$cookies[0])[0]===200,'admin reads '.$path);
    }
    $url='/api/admin/logs?q='.$marker;
    [, $body]=$http($url.'&page_size=1',$cookies[0]); $data=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data'];
    $check($data['total']===8 && count($data['items'])===1 && $data['items'][0]['id']===$logIds[6],'stable pagination first page');
    [, $body]=$http($url.'&page_size=1&page=2',$cookies[0]);
    $check(json_decode($body,true)['data']['items'][0]['id']===$logIds[5],'stable pagination second page');
    foreach (LogFilters::TYPES as $type) {
        [, $body]=$http($url.'&type='.$type,$cookies[0]);
        $data=json_decode($body,true)['data'];
        $check($data['total']===($type==='api_error' ? 2 : 1) && $data['items'][0]['type']===$type,'type filter '.$type);
    }
    [, $body]=$http('/api/admin/audit-logs?q='.$marker.'&type=oauth_error',$cookies[0]);
    $check(json_decode($body,true)['data']['total']===1 && json_decode($body,true)['data']['items'][0]['type']==='admin_audit','audit view stays audit-only');
    [, $body]=$http($url.'&user_id='.$ids[1],$cookies[0]);
    $check(json_decode($body,true)['data']['total']===1,'user ID filter');
    [, $body]=$http($url.'&error_code=GENERATED_LOG_TEST',$cookies[0]);
    $check(json_decode($body,true)['data']['total']===7,'error code exact filter');
    [, $body]=$http($url.'&from='.gmdate('Y-m-d').'&to='.gmdate('Y-m-d'),$cookies[0]);
    $check(json_decode($body,true)['data']['total']===7,'inclusive UTC date range');
    [, $body]=$http('/api/admin/logs?q='.rawurlencode($marker).'&type=api_error&user_id='.$ids[1].'&from='.gmdate('Y-m-d',time()-86400).'&to='.gmdate('Y-m-d',time()-86400).'&error_code=GENERATED_YESTERDAY',$cookies[0]);
    $check(json_decode($body,true)['data']['total']===1,'combined filters include end-day last second');
    [, $body]=$http('/api/admin/logs?q='.rawurlencode("' OR 1=1 --"),$cookies[0]);
    $check(json_decode($body,true)['data']['total']===0,'SQL injection is literal search');
    [, $body]=$http('/api/admin/logs?q='.rawurlencode('%_').'&user_id='.$ids[0],$cookies[0]);
    $check(json_decode($body,true)['data']['total']===7,'wildcard characters are literal');
    foreach (['en'=>'Logs','ja'=>'ログ'] as $locale=>$title) {
        [, $body]=$http('/admin/logs?q='.$marker,$cookies[0],$locale);
        $check(str_contains($body,'<h1>'.$title.'</h1>') && str_contains($body,'lang="'.$locale.'"'),'localized logs '.$locale);
        $check(str_contains($body,'&lt;img onerror=alert(1)&gt;') && !str_contains($body,'<img onerror=alert(1)>'),'log context escaped '.$locale);
    }
    [, $body]=$http('/admin/logs?q='.rawurlencode('"><img onerror=alert(1)>'),$cookies[0]);
    $check(!str_contains($body,'"><img onerror=alert(1)>') && str_contains($body,'&quot;&gt;&lt;img'),'filter input escaped');
    foreach (['type=unknown','type[]=php_error','from=2026-02-30','from=0000-01-01','from=2026-10-04&to=2026-10-03','user_id=-1','user_id[]=1','user_id=1%20OR%201=1','page=0','page_size=101','q[]=x','q=%FF','error_code=%00'] as $invalid) {
        $check($http('/api/admin/logs?'.$invalid,$cookies[0])[0]===422,'invalid filter rejected '.$invalid);
    }

    $now=strtotime(gmdate('Y-m-d').' 12:00:00 UTC');$cutoff=$now-90*86400;
    $insert->execute(['api_error','GENERATED_EXPIRED',$ids[0],'{}',gmdate('Y-m-d H:i:s',$cutoff-1)]);$oldId=(int)$pdo->lastInsertId();$logIds[]=$oldId;
    $insert->execute(['api_error','GENERATED_BOUNDARY',$ids[0],'{}',gmdate('Y-m-d H:i:s',$cutoff)]);$boundaryId=(int)$pdo->lastInsertId();$logIds[]=$boundaryId;
    $fileLogger=new FileLogger($testDirectory);
    $fileLogger->write('api_error','OLD_DAY',[],$cutoff-86400);
    $fileLogger->write('api_error','OLD_SECOND',[],$cutoff-1);
    $fileLogger->write('api_error','BOUNDARY_SECOND',[],$cutoff);
    $fileLogger->write('api_error','RECENT',[],$now);
    file_put_contents($testDirectory.'/notes.jsonl','generated unrelated file');
    file_put_contents($testDirectory.'/2000-99-99.jsonl','generated invalid filename');
    $external=$testDirectory.'/external.txt';file_put_contents($external,'generated protected file');
    $link=$testDirectory.'/2000-01-01.jsonl';symlink($external,$link);
    $result=(new LogRetention($logs,$testDirectory))->run($now);
    $check($result['database_rows']>=1 && $result['files']===1 && $result['file_entries']===1,'90 day retention prunes DB and exact file boundary');
    $query=$pdo->prepare('SELECT COUNT(*) FROM log_entries WHERE id=?');$query->execute([$oldId]);$check((int)$query->fetchColumn()===0,'expired DB event removed');
    $query->execute([$boundaryId]);$check((int)$query->fetchColumn()===1,'exact 90 day boundary retained');
    $file=file_get_contents($testDirectory.'/'.gmdate('Y-m-d',$cutoff).'.jsonl');
    $check(!str_contains($file,'OLD_SECOND') && str_contains($file,'BOUNDARY_SECOND'),'boundary file preserves current entries');
    $check(is_file($external) && is_link($link) && is_file($testDirectory.'/notes.jsonl') && is_file($testDirectory.'/2000-99-99.jsonl'),'retention ignores links and unrelated files');
    $check((new LogRetention($logs,$testDirectory))->run($now)['file_entries']===0,'retention repeat safe');
    unlink($testDirectory.'/.write.lock');symlink($external,$testDirectory.'/.write.lock');
    try {$fileLogger->write('api_error','GENERATED',[],$now);throw new RuntimeException('Linked lock accepted');}
    catch (RuntimeException $error) {$check($error->getMessage()==='Log lock cannot be a link','file writer rejects linked lock');}
    unlink($testDirectory.'/.write.lock');
    $todayPath=$testDirectory.'/'.gmdate('Y-m-d',$now).'.jsonl';rename($todayPath,$testDirectory.'/saved.txt');symlink($external,$todayPath);
    try {$fileLogger->write('api_error','GENERATED',[],$now);throw new RuntimeException('Linked daily file accepted');}
    catch (RuntimeException $error) {$check($error->getMessage()==='Log file cannot be a link' && file_get_contents($external)==='generated protected file','file writer rejects linked daily file');}
} finally {
    foreach ($logIds as $id) $pdo->prepare('DELETE FROM log_entries WHERE id=?')->execute([$id]);
    foreach ($ids as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    if(is_dir($testDirectory)) {foreach(glob($testDirectory.'/*') ?: [] as $path) if(is_file($path) || is_link($path))unlink($path);if(is_file($testDirectory.'/.write.lock'))unlink($testDirectory.'/.write.lock');rmdir($testDirectory);}
}
echo "$count log checks passed.\n";

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\StatisticsPeriod;
use App\Repositories\{StatisticsRepository,StatisticsReportRepository,AuthRepository};
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);$count++;echo "PASS: $name\n";};
$now=new DateTimeImmutable('2026-10-04',new DateTimeZone('UTC'));
$check(StatisticsPeriod::parse([],$now)['start']==='2026-09-05','default 30 UTC days');
foreach([['start'=>'2026-02-30'],['end'=>'2026-10-05'],['start'=>[]],['start'=>"' OR 1=1"],['start'=>'2026-10-04','end'=>'2026-10-03'],['start'=>'2025-01-01','end'=>'2026-10-04']] as $query){
    try{StatisticsPeriod::parse($query,$now);throw new RuntimeException('invalid period accepted');}catch(App\Http\HttpException $error){$check($error->status===422,'invalid date rejected');}
}
$check(StatisticsPeriod::parse(['start'=>'2024-02-29','end'=>'2025-02-28'],$now)['start']==='2024-02-29','leap day and 366 inclusive days accepted');
$pdo=App\Database\Database::connect(App\Config::load(dirname(__DIR__))->get('database'));
$writer=new StatisticsRepository($pdo);$report=new StatisticsReportRepository($pdo);
$period=StatisticsPeriod::parse(['start'=>'2001-01-01','end'=>'2001-01-10']);
$baseline=$report->report($period);$anonymous=array_map(static fn()=>bin2hex(random_bytes(16)),range(1,4));$ids=[];$cookies=[];
$http=static function(string $path,string $cookie='',string $locale='en'):array{
    $body=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\nAccept-Language: $locale",'ignore_errors'=>true]]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return [(int)$match[1],$body];
};
try{
    $events=[];
    foreach([[0,'2001-01-01 00:00:00','visit',[],'web'],[0,'2001-01-08 00:00:00','search',['provider'=>'google'],'web'],[0,'2001-01-08 23:59:59','ai_search',['provider'=>'custom'],'extension'],[0,'2001-01-10 23:59:59','favorite_open',[],'web'],[1,'2001-01-01 23:59:59','visit',[],'web'],[1,'2001-01-07 12:00:00','feature',['feature'=>'settings'],'web'],[2,'2001-01-09 00:00:00','visit',[],'extension'],[3,'2000-12-31 12:00:00','visit',[],'web'],[3,'2001-01-05 12:00:00','search',['provider'=>'google'],'extension'],[0,'2001-01-11 00:00:00','search',['provider'=>'custom'],'web']] as [$id,$date,$type,$data,$source]){
        $events[]=['event_id'=>bin2hex(random_bytes(16)),'anonymous_id'=>$anonymous[$id],'event_type'=>$type,'event_data'=>$data,'source'=>$source,'created_at'=>(new DateTimeImmutable($date,new DateTimeZone('UTC')))->getTimestamp()];
    }
    $writer->record($events);$data=$report->report($period);
    foreach(['searches'=>2,'ai_searches'=>1,'favorite_opens'=>1,'dau'=>1,'wau'=>4,'mau'=>4,'new_anonymous_devices'=>3] as $key=>$expected)$check($data['summary'][$key]-$baseline['summary'][$key]===$expected,'actual aggregate '.$key);
    $check($data['retention_d7']['eligible']-$baseline['retention_d7']['eligible']===2&&$data['retention_d7']['returning']-$baseline['retention_d7']['returning']===1,'D7 excludes immature and older cohorts');
    $check($baseline['retention_d7']['eligible']===0&&$data['retention_d7']['percent']===50.0,'D7 percentage 50 with deterministic fixture');
    $check(count($data['daily'])===10&&$data['daily'][1]['dau']===0,'missing days filled with zero');
    $check($data['daily'][7]['searches']===1&&$data['daily'][7]['ai_searches']===1&&$data['daily'][9]['favorite_opens']===1,'daily counts and inclusive end boundary');
    $check($data['breakdowns']['search_engines']===[['label'=>'google','total'=>2]],'engine breakdown excludes next day');
    $check($data['breakdowns']['ai_providers']===[['label'=>'custom','total'=>1]],'AI breakdown');
    $check($data['breakdowns']['features']===[['label'=>'settings','total'=>1]],'feature breakdown');
    $check($data['breakdowns']['sources'][0]['label']==='extension'&&$data['breakdowns']['sources'][0]['total']===3&&$data['breakdowns']['sources'][0]['percent']===37.5,'source event ratio');
    $serialized=json_encode($data);foreach($anonymous as $identity)$check(!str_contains($serialized,$identity),'report hides anonymous identifiers');
    $auth=new AuthRepository($pdo);
    foreach(range(1,2) as $index){
        $identity=App\Services\DiscordOAuth::validateIdentity(['id'=>'999'.str_pad((string)random_int(1,999999999999999),15,'0',STR_PAD_LEFT),'username'=>'Statistics verification']);
        $id=$auth->upsertIdentity($identity,'en');$ids[]=$id;$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));
        $auth->createDevice($id,$device,hash('sha256',$token),App\Auth\DeviceAgent::parse('Windows Chrome/120'),time());$cookies[]='search_remember='.$device.'.'.$token;
    }
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    $pdo->prepare("INSERT INTO backgrounds(id,user_id,name,type,source_type,file_size,settings_json,deleted,created_at,updated_at) VALUES (?,?,'Statistics fixture','image','url',37,'{}',0,UTC_TIMESTAMP(),UTC_TIMESTAMP()),(?,?,'Deleted fixture','image','url',100,'{}',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([bin2hex(random_bytes(16)),$ids[0],bin2hex(random_bytes(16)),$ids[0]]);
    $snapshot=$report->report($period)['summary'];
    $check($snapshot['users']-$baseline['summary']['users']===2,'current account count from actual users');
    $check($snapshot['logged_in_users']-$baseline['summary']['logged_in_users']===2,'unexpired login counts distinct accounts');
    $check($snapshot['storage_bytes']-$baseline['summary']['storage_bytes']===37,'current storage excludes deleted files');
    $check($snapshot['new_users']===$baseline['summary']['new_users'],'new accounts use selected dates rather than current total');
    $pdo->prepare("UPDATE users SET created_at='2001-01-10 23:59:59' WHERE id=?")->execute([$ids[0]]);
    $check($report->report($period)['summary']['new_users']-$baseline['summary']['new_users']===1,'new account end date is inclusive');
    foreach(['/admin/statistics','/api/admin/statistics'] as $path){
        $check($http($path)[0]===401,'guest rejected '.$path);$check($http($path,$cookies[1])[0]===403,'regular user rejected '.$path);
        [$status,$body]=$http($path.'?start=2001-01-01&end=2001-01-10',$cookies[0]);$check($status===200,'administrator report '.$path);
        $check(!str_contains($body,'discord_id')&&!str_contains($body,'token_hash'),'report excludes account secrets');
    }
    foreach(['ja'=>'統計','en'=>'Statistics'] as $locale=>$title){
        [$status,$body]=$http('/admin/statistics?start=2001-01-01&end=2001-01-10',$cookies[0],$locale);
        $check($status===200&&str_contains($body,'<h1>'.$title.'</h1>')&&str_contains($body,'lang="'.$locale.'"'),'translated report '.$locale);
        $check(str_contains($body,'<svg')&&str_contains($body,'<caption>'),'graphs have accessible numeric table '.$locale);
    }
    $check($http('/api/admin/statistics?start=2001-02-30',$cookies[0])[0]===422,'HTTP invalid period rejected');
    $check($http('/admin/statistics?start='.rawurlencode('\"><script>alert(1)</script>'),$cookies[0])[0]===422,'HTML input rejected');
    $empty=$report->report(StatisticsPeriod::parse(['start'=>'1998-01-01','end'=>'1998-01-01']));
    $check($empty['summary']['searches']===0&&$empty['retention_d7']['percent']===null,'empty period and no eligible cohort');
    $pdo->prepare('UPDATE administrators SET admin_flag=0 WHERE user_id=?')->execute([$ids[0]]);
    $check($http('/api/admin/statistics',$cookies[0])[0]===403,'administrator revocation applies immediately');
}finally{
    foreach($anonymous as $identity)$pdo->prepare('DELETE FROM statistics_events WHERE anonymous_id=?')->execute([$identity]);
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$count admin statistics checks passed.\n";

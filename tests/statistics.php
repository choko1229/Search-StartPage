<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');$migrator->migrate();
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);$count++;echo "PASS: $name\n";};
$check($migrator->migrate()===[],'statistics migration repeat safe');
$cookies=[];$csrf=null;
$http=static function(string $path,string $method='GET',mixed $body=null,bool $token=true,string $locale='en')use(&$cookies,&$csrf):array{
    $headers=['Cookie: '.implode('; ',$cookies),'Accept-Language: '.$locale];
    if($body!==null)$headers[]='Content-Type: application/json';if($token&&$csrf)$headers[]='X-CSRF-Token: '.$csrf;
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true];if($body!==null)$options['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $response=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match))$cookies[$match[1]]=$match[1].'='.$match[2];
    return [(int)$status[1],$response,json_decode($response)];
};
$anonymous=bin2hex(random_bytes(16));
$event=(object)['event_id'=>bin2hex(random_bytes(16)),'anonymous_id'=>$anonymous,'event_type'=>'visit','event_data'=>(object)[],'source'=>'web','created_at'=>time()];
try{
    $csrf=$http('/api/csrf')[2]->data->csrf_token;
    $check($http('/api/statistics/event','POST',['events'=>[$event]],false)[0]===403,'statistics require CSRF');
    $result=$http('/api/statistics/event','POST',['events'=>[$event]]);$check($result[0]===200&&$result[2]->data->accepted===[$event->event_id],'anonymous guest event accepted');
    $check($http('/api/statistics/event','POST',['events'=>[$event]])[0]===200,'lost response retry accepted');
    $query=$pdo->prepare('SELECT COUNT(*) FROM statistics_events WHERE anonymous_id=?');$query->execute([$anonymous]);$check((int)$query->fetchColumn()===1,'retry deduplicated');
    foreach(['search','ai_search','favorite_open','feature'] as $type){
        $next=clone $event;$next->event_id=bin2hex(random_bytes(16));$next->event_type=$type;
        $next->event_data=match($type){'search'=>(object)['provider'=>'google'],'ai_search'=>(object)['provider'=>'custom'],'feature'=>(object)['feature'=>'command_palette'],default=>(object)[]};
        $next->source='extension';$check($http('/api/statistics/event','POST',['events'=>[$next]])[0]===200,'allowed event '.$type);
    }
    foreach(['query','url','discord_id','user_id','ip','custom_name'] as $field){$bad=clone $event;$bad->event_id=bin2hex(random_bytes(16));$bad->event_data=(object)[$field=>'private'];$check($http('/api/statistics/event','POST',['events'=>[$bad]])[0]===422,'private data rejected '.$field);}
    foreach(['bad_type','bad_source','bad_provider','future','extra','bad_identity'] as $case){
        $bad=clone $event;$bad->event_id=bin2hex(random_bytes(16));
        match($case){'bad_type'=>$bad->event_type='private','bad_source'=>$bad->source='other','bad_provider'=>[$bad->event_type='search',$bad->event_data=(object)['provider'=>"' OR 1=1"]],
            'future'=>$bad->created_at=time()+301,'extra'=>$bad->discord_id='private','bad_identity'=>$bad->anonymous_id='123456789012345678'};
        $check($http('/api/statistics/event','POST',['events'=>[$bad]])[0]===422,'invalid event rejected '.$case);
    }
    $valid=clone $event;$valid->event_id=bin2hex(random_bytes(16));$bad=clone $event;$bad->event_type='wrong';
    $check($http('/api/statistics/event','POST',['events'=>[$valid,$bad]])[0]===422,'whole batch validated before writes');
    $query->execute([$anonymous]);$check((int)$query->fetchColumn()===5,'invalid batches leave no partial events');
    $check($http('/api/statistics/event','POST',['events'=>array_fill(0,101,$event)])[0]===422,'oversized batch rejected');
    $old=clone $event;$old->event_id=bin2hex(random_bytes(16));$old->created_at=0;
    $check($http('/api/statistics/event','POST',['events'=>[$old]])[0]===200,'old offline event retained');
    $directory=sys_get_temp_dir().'/statistics-retention-'.bin2hex(random_bytes(8));mkdir($directory,0700);
    try{(new App\Services\LogRetention(new App\Repositories\LogRepository($pdo),$directory))->run();}finally{if(is_file($directory.'/.write.lock'))unlink($directory.'/.write.lock');rmdir($directory);}
    $query->execute([$anonymous]);$check((int)$query->fetchColumn()===6,'90 day log cleanup excludes statistics');
    $columns=$pdo->query('SHOW COLUMNS FROM statistics_events')->fetchAll(PDO::FETCH_COLUMN);
    $check(!array_intersect($columns,['user_id','discord_id','ip','url','query']),'schema contains no direct identifiers');
    foreach(['ja','en'] as $locale){$page=$http('/privacy','GET',null,true,$locale);$check($page[0]===200&&str_contains($page[1],$locale==='ja'?'無期限':'indefinitely')&&str_contains($page[1],'lang="'.$locale.'"'),'privacy disclosure '.$locale);}
    $check(App\Middleware\FeatureFlags::feature(new App\Http\Request('POST','/api/statistics/event'))===null,'statistics independent of cloud feature flag');
}finally{$pdo->prepare('DELETE FROM statistics_events WHERE anonymous_id=?')->execute([$anonymous]);}
echo "$count statistics checks passed.\n";

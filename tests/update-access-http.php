<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\UpdateAccess;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('SEARCH_LOCAL_DEVELOPMENT')!=='1')exit(1);
$root=dirname(__DIR__);$access=new UpdateAccess($root.'/storage/updates/access');$count=0;
$check=static function($ok,$name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$request=static function(string $path,string $method='GET',string $language='en'):array{
    $options=['method'=>$method,'header'=>"Accept-Language: $language",'timeout'=>5,'ignore_errors'=>true,'follow_location'=>0];
    if($method==='POST'){$options['header'].="\r\nContent-Type: application/json";$options['content']='{"generated":"test"}';}
    $context=stream_context_create(['http'=>$options]);
    $body=file_get_contents('http://127.0.0.1'.$path,false,$context);$status=0;
    foreach($http_response_header??[] as $header)if(preg_match('~^HTTP/\S+ (\d{3})~',$header,$match))$status=(int)$match[1];
    return [$status,$body,$http_response_header??[]];
};
[$status]=$request('/');$check($status===200,'ordinary home works before gate');
$configHash=hash_file('sha256',$root.'/config/config.php');$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$counts=static fn()=>[$pdo->query('SELECT COUNT(*) FROM log_entries')->fetchColumn(),$pdo->query('SELECT COUNT(*) FROM statistics_events')->fetchColumn()];$before=$counts();
$access->exclusive(function()use($check,$request,$counts,$before,$root):void{
    foreach([['/','GET','ja'],['/account','GET','en'],['/api/admin/update','GET','en'],['/api/sync','POST','ja'],['/installer','POST','en'],['/','HEAD','en']] as [$path,$method,$language]){
        [$status,$body,$headers]=$request($path,$method,$language);$check($status===503,'HTTP stopped '.$method.' '.$path);$check(in_array('Retry-After: 30',$headers,true),'retry header '.$method.' '.$path);$check(!str_contains($body,'Stack trace')&&!str_contains($body,'Warning:'),'no diagnostic details '.$path);
        if(str_starts_with($path,'/api/')){$json=json_decode($body,true);$check(($json['success']??true)===false&&($json['error']['code']??null)==='UPDATE_IN_PROGRESS','API safe error '.$path);}
        elseif($method==='HEAD')$check($body==='','HEAD has no body');
        else $check(str_contains($body,$language==='ja'?'更新作業中':'Update in progress'),'localized maintenance '.$language);
    }
    $check($counts()===$before,'blocked requests write no DB error/statistics rows');
    $process=proc_open([PHP_BINARY,$root.'/bin/cleanup-logs.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$check(proc_close($process)===1&&$out===''&&$err==="Log cleanup failed.\n",'actual manual cleanup stopped');$check($counts()===$before,'blocked CLI writes no DB rows');
});
[$status]=$request('/');$check($status===200,'ordinary home resumes after gate');$check(hash_equals($configHash,hash_file('sha256',$root.'/config/config.php')),'actual config unchanged');echo "$count update access HTTP checks passed.\n";

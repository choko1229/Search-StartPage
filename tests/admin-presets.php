<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{ProviderPresets,PresetState};
use App\Repositories\{ProviderPresetRepository,AuthRepository};
$root=dirname(__DIR__);$pdo=App\Database\Database::connect(App\Config::load($root)->get('database'));
$migrator=new App\Database\Migrator($pdo,$root.'/database/migrations');$migrator->migrate();
$repository=new ProviderPresetRepository($pdo);$state=new PresetState($root.'/storage/presets');$initial=$repository->read();$ids=[];$jars=[[],[],[]];$csrf=[null,null,null];$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);$count++;echo "PASS: $name\n";};
$http=static function(int $client,string $path,string $method='GET',mixed $body=null,bool $useCsrf=true,string $locale='en',bool $form=false)use(&$jars,&$csrf):array{
    $cookies=[];foreach($jars[$client] as $key=>$value)$cookies[]=$key.'='.$value;
    $headers=['Cookie: '.implode('; ',$cookies),'Accept-Language: '.$locale];
    if($body!==null)$headers[]='Content-Type: '.($form?'application/x-www-form-urlencoded':'application/json');if($useCsrf&&$csrf[$client])$headers[]='X-CSRF-Token: '.$csrf[$client];
    $options=['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true];
    if($body!==null)$options['content']=$form?http_build_query($body):json_encode($body,JSON_THROW_ON_ERROR);
    $response=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>$options]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$match))$jars[$client][$match[1]]=$match[2];
    return [(int)$status[1],$response,json_decode($response,true)];
};
try{
    $check($migrator->migrate()===[],'preset migration repeat safe');
    $seed=require $root.'/database/migrations/015_provider_presets.php';$seed->up($pdo);$check($repository->read()===$initial,'repeated seed preserves edited catalog');
    $auth=new AuthRepository($pdo);
    foreach(range(0,1) as $index){$discord='999'.str_pad((string)random_int(1,999999999999999),15,'0',STR_PAD_LEFT);$id=$auth->upsertIdentity(['id'=>$discord,'username'=>'Preset verification','display_name'=>null,'avatar'=>null],'en');$ids[]=$id;$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($id,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());$jars[$index]['search_remember']=$device.'.'.$token;}
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$ids[0]]);
    foreach(range(0,2) as $client)$csrf[$client]=$http($client,'/api/csrf')[2]['data']['csrf_token'];
    foreach(['/admin/presets','/api/admin/presets'] as $path){$check($http(2,$path)[0]===401,'guest blocked '.$path);$check($http(1,$path)[0]===403,'regular user blocked '.$path);$check($http(0,$path)[0]===200,'administrator read '.$path);}
    $body=['version'=>$initial['version'],'presets'=>$initial['presets']];
    $check($http(1,'/api/admin/presets','POST',$body)[0]===403,'regular user mutation blocked');
    $check($http(0,'/api/admin/presets','POST',$body,false)[0]===403,'CSRF required');
    foreach(['scheme','credentials','noquery','duplicateid','duplicateprefix','emptyenabled','flag','extra','order'] as $case){
        $bad=$body;
        switch($case){
            case 'scheme':$bad['presets']['web'][0]['url']='javascript:alert(1)';break;
            case 'credentials':$bad['presets']['web'][0]['url']='https://user:password@example.test/?q={query}';break;
            case 'noquery':$bad['presets']['web'][0]['url']='https://example.test/';break;
            case 'duplicateid':$bad['presets']['ai'][0]['id']=$bad['presets']['web'][0]['id'];break;
            case 'duplicateprefix':$bad['presets']['ai'][0]['prefix']=strtoupper($bad['presets']['web'][0]['prefix']);break;
            case 'emptyenabled':foreach($bad['presets']['web'] as &$row)$row['enabled']=false;unset($row);break;
            case 'flag':$bad['presets']['web'][0]['copy']='true';break;
            case 'extra':$bad['presets']['web'][0]['secret']='reject';break;
            case 'order':$bad['presets']['web'][0]['sort_order']=-1;break;
        }
        $check($http(0,'/api/admin/presets','POST',$bad)[0]===422,'invalid preset '.$case);
    }
    $changed=$body['presets'];$changed['web'][0]['name']='Preset <img onerror=alert(1)>'; $changed['web'][0]['sort_order']=9999;
    $changed['ai'][1]['enabled']=false;
    [$status,,$result]=$http(0,'/api/admin/presets','POST',['version'=>$initial['version'],'presets'=>$changed]);$result=$result['data'];
    $check($status===200&&$result['version']===$initial['version']+1,'catalog updated with version');
    $check($http(0,'/api/admin/presets','POST',$body)[0]===409,'stale editor cannot overwrite');
    $check($http(2,'/api/provider-presets')[2]['data']['presets']===ProviderPresets::client($result['presets']),'public catalog follows update');
    $document=(object)['providers-web'=>(object)[],'providers-ai'=>(object)[]];
    foreach(ProviderPresets::client($result['presets']) as $mode=>$rows)foreach($rows as $item)$document->{'providers-'.$mode}->{$item['id']}=(object)$item;
    $check(App\Services\SyncDocument::validate($document)===$document,'public presets satisfy cloud sync schema');
    [$status,$home]=$http(2,'/');$check($status===200&&str_contains($home,'Preset')&&str_contains($home,'\\u003Cimg'),'home bootstrap uses safe updated catalog');
    foreach(['en'=>'Search and AI presets','ja'=>'検索・AIプリセット'] as $locale=>$label){$page=$http(0,'/admin/presets',locale:$locale);$check($page[0]===200&&str_contains($page[1],$label),'translated editor '.$locale);$check(str_contains($page[1],'&lt;img onerror=alert(1)&gt;')&&!str_contains($page[1],'<img onerror=alert(1)>'),'escaped names '.$locale);}
    $audit=$pdo->prepare('SELECT error_code,file_written,context_json FROM log_entries WHERE id=?');$audit->execute([$result['audit_id']]);$row=$audit->fetch(PDO::FETCH_ASSOC);
    $check($row['error_code']==='PROVIDER_PRESETS_CHANGED'&&(int)$row['file_written']===1&&json_decode($row['context_json'],true)['before']===$initial['presets'],'before and after audit durable DB and file');
    // The normal HTML form performs the same validated transaction.
    $form=['version'=>(string)$result['version'],'_csrf'=>$csrf[0]];foreach($result['presets'] as $mode=>$rows){foreach($rows as &$row){$row['enabled']=$row['enabled']?'1':'0';$row['copy']=$row['copy']?'1':'0';$row['sort_order']=(string)$row['sort_order'];}unset($row);$form[$mode]=$rows;}
    $formResult=$http(0,'/admin/presets','POST',$form,form:true);
    $check($formResult[0]===303&&$http(0,'/admin/presets')[0]===200,'HTML editor saves and redirects');
    $check($repository->read()['version']===$result['version']+1,'HTML save advances version');
    $seed->up($pdo);$check($repository->read()['presets']===$result['presets'],'migration rerun does not reset admin edits');
    $large=$result['presets'];
    foreach(['web','ai'] as $mode)foreach(range(0,29) as $index)$large[$mode][]=['id'=>$mode.'-large-'.$index,'name'=>'Generated large preset','prefix'=>$mode.'large'.$index,'icon'=>'','enabled'=>true,'copy'=>false,'sort_order'=>$index+100,'url'=>'https://example.test/?q={query}&padding='.str_repeat('a',1800)];
    $largeResult=$repository->update($large,$repository->read()['version'],$ids[0],$state);
    $check(strlen(json_encode($repository->read()['presets']))>65535,'large catalog persists beyond TEXT limit');
    $query=$pdo->prepare('SELECT OCTET_LENGTH(context_json) FROM log_entries WHERE id=?');$query->execute([$largeResult['audit_id']]);$check((int)$query->fetchColumn()>65535,'large before and after audit retained without truncation');
    $cached=$state->resolve(static function(){throw new RuntimeException('Cached catalog must avoid database');});$check($cached===$repository->read()['presets'],'cached catalog remains available without database');
    $pdo->prepare('UPDATE administrators SET admin_flag=0 WHERE user_id=?')->execute([$ids[0]]);$check($http(0,'/api/admin/presets')[0]===403,'revocation enforced');
}finally{
    if($ids)$repository->update($initial['presets'],$repository->read()['version'],$ids[0],$state);
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}
echo "$count preset checks passed.\n";

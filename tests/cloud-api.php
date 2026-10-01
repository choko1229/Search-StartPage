<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\Database;use App\Repositories\{AuthRepository,SyncRepository};
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));$auth=new AuthRepository($pdo);$repo=new SyncRepository($pdo);
$uid=$auth->upsertIdentity(['id'=>'999999999999999961','username'=>'Cloud API Test','display_name'=>null,'avatar'=>null],'en');
$other=$auth->upsertIdentity(['id'=>'999999999999999962','username'=>'Cloud Other','display_name'=>null,'avatar'=>null],'en');
$count=0;$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;echo "PASS: $label\n";};
$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($uid,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());
$cookies=['search_remember'=>$device.'.'.$token];
$request=static function(string $method,string $path,?array $body=null,?string $csrf=null)use(&$cookies){
    $headers=['Cookie: '.implode('; ',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies))];if($body!==null)$headers[]='Content-Type: application/json';if($csrf)$headers[]='X-CSRF-Token: '.$csrf;
    $result=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body===null?'':json_encode($body),'ignore_errors'=>true]]));
    foreach($http_response_header as $line)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$m))$cookies[$m[1]]=$m[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);return [(int)$status[1],json_decode($result,true,flags:JSON_THROW_ON_ERROR)];
};
try {
    $repo->write($other,0,json_decode('{"favorites":{"foreign":{"id":"foreign","name":"Private","url":"https://example.test"}}}'));
    [, $json]=$request('GET','/api/csrf');$csrf=$json['data']['csrf_token'];$version=0;
    foreach(['/api/settings','/api/favorites','/api/favorite-folders','/api/search/history','/api/search-engines','/api/ai-providers'] as $path) {
        [$status,$json]=$request('GET',$path);$check($status===200 && $json['data']['version']===0,'authenticated empty list '.$path);
    }
    [$status]=$request('POST','/api/favorite-folders',['version'=>0,'item'=>(object)['name'=>'No CSRF']]);$check($status===403,'entity create requires CSRF');
    [$status]=$request('POST','/api/favorite-folders',['item'=>(object)['name'=>'No version']],$csrf);$check($status===422,'entity write requires version');
    [$status]=$request('POST','/api/favorite-folders',['version'=>0,'user_id'=>(string)$other,'item'=>(object)['name'=>'Wrong owner']],$csrf);$check($status===403,'owner hint cannot choose another user');
    [$status,$json]=$request('POST','/api/favorite-folders',['version'=>$version,'item'=>(object)['id'=>'folder','name'=>'Work']],$csrf);$check($status===201,'folder created');$version=$json['data']['version'];
    [$status,$json]=$request('POST','/api/favorites',['version'=>$version,'item'=>(object)['id'=>'favorite','name'=>'Favorite','url'=>'https://example.test','folderId'=>'folder','tags'=>['Tag']]],$csrf);$check($status===201 && $json['data']['document']['favorites']['favorite']['visible']===true,'favorite created with defaults');$version=$json['data']['version'];
    [$status,$json]=$request('GET','/api/favorites');$check($status===200 && count($json['data']['items'])===1 && $json['data']['items'][0]['id']==='favorite','other owner favorite is not listed');
    [$status]=$request('PUT','/api/favorites/foreign',['version'=>$version,'item'=>(object)['name'=>'Attack']],$csrf);$check($status===404,'foreign favorite cannot be edited');
    [$status]=$request('POST','/api/favorites',['version'=>$version,'item'=>(object)['id'=>'favorite','name'=>'Duplicate','url'=>'https://example.test']],$csrf);$check($status===409,'duplicate client ID rejected');
    [$status,$json]=$request('PUT','/api/favorites/favorite',['version'=>$version,'item'=>(object)['name'=>'Changed']],$csrf);$check($status===200 && $json['data']['document']['favorites']['favorite']['tags']===['Tag'],'partial update preserves other fields');$version=$json['data']['version'];
    [$status]=$request('PUT','/api/favorites/favorite',['version'=>$version,'item'=>(object)['id'=>'different']],$csrf);$check($status===422,'item ID cannot change');
    [$status,$json]=$request('POST','/api/favorites/favorite/open',['version'=>$version],$csrf);$check($status===200 && $json['data']['document']['favorites']['favorite']['usageCount']===1 && $json['data']['document']['favorites']['favorite']['lastAccess']>0,'favorite open records usage');$version=$json['data']['version'];
    [$status,$json]=$request('DELETE','/api/favorite-folders/folder',['version'=>$version],$csrf);$check($status===200 && $json['data']['document']['favorites']['favorite']['folderId']===null,'folder deletion keeps and detaches favorite');$version=$json['data']['version'];
    $query=$pdo->prepare('SELECT folder_id FROM favorites WHERE user_id=? AND client_id=?');$query->execute([$uid,'favorite']);$check($query->fetchColumn()===null,'folder detach reaches relational DB');
    [$status,$json]=$request('POST','/api/search/history',['version'=>$version,'item'=>(object)['query'=>'Test query','provider'=>'google','mode'=>'web']],$csrf);$historyId=array_key_first($json['data']['document']['history']);$check($status===201 && is_string($historyId) && $json['data']['document']['history'][$historyId]['at']>0,'history ID and time generated');$version=$json['data']['version'];
    [$status,$json]=$request('GET','/api/search/history');$check($status===200 && count($json['data']['items'])===1,'history listed');
    foreach(['/api/search-engines'=>'engine','/api/ai-providers'=>'ai'] as $path=>$id) {
        [$status,$json]=$request('POST',$path,['version'=>$version,'item'=>(object)['id'=>$id,'name'=>'Provider','url'=>'https://example.test/?q={query}','prefix'=>$id]],$csrf);$check($status===201,'provider create '.$path);$version=$json['data']['version'];
        [$status,$json]=$request('PUT',$path.'/'.$id,['version'=>$version,'item'=>(object)['enabled'=>false]],$csrf);$check($status===200,'provider update '.$path);$version=$json['data']['version'];
        [$status,$json]=$request('GET',$path);$check($status===200 && $json['data']['items'][0]['enabled']===false,'provider list '.$path);
        [$status,$json]=$request('DELETE',$path.'/'.$id,['version'=>$version],$csrf);$check($status===200,'provider delete '.$path);$version=$json['data']['version'];
    }
    [$status,$json]=$request('PUT','/api/settings',['version'=>$version,'settings'=>(object)['theme'=>'light','fontSize'=>16]],$csrf);$check($status===200,'settings update');$version=$json['data']['version'];
    [$status,$json]=$request('GET','/api/settings');$check($status===200 && $json['data']['settings']['theme']==='light','settings read');
    [$status,$json]=$request('PUT','/api/settings',['version'=>$version,'settings'=>(object)['theme'=>'light','fontSize'=>16,'favoriteStats'=>false]],$csrf);$version=$json['data']['version'];
    [$status,$json]=$request('POST','/api/favorites/favorite/open',['version'=>$version],$csrf);$check($status===200 && $json['data']['document']['favorites']['favorite']['usageCount']===1,'disabled stats do not count opens');$version=$json['data']['version'];
    [$status]=$request('PUT','/api/settings',['version'=>$version,'settings'=>(object)['syncHistory'=>true]],$csrf);$check($status===422,'local-only settings rejected');
    [$status]=$request('DELETE','/api/favorites/favorite',['version'=>$version-1],$csrf);$check($status===409,'stale entity version rejected');
    $previous=$repo->read($uid)['document'];$local=json_decode(json_encode($previous));$local->settings->theme='custom';
    [$status,$json]=$request('PUT','/api/settings',['version'=>$version,'settings'=>(object)['theme'=>'dark','fontSize'=>18]],$csrf);$version=$json['data']['version'];
    [$status,$json]=$request('POST','/api/sync/resolve-conflict',['version'=>$version,'previous'=>$previous,'local'=>$local],$csrf);
    $check($status===409 && count($json['data']['conflicts'])===1 && $json['data']['conflicts'][0]['previous']['value']==='light','unresolved conflict reports Previous Local Cloud');
    $check($repo->read($uid)['version']===$version,'unresolved conflict does not write');
    $choices=(object)['["settings","theme"]'=>'local'];
    [$status,$json]=$request('POST','/api/sync/resolve-conflict',['version'=>$version,'previous'=>$previous,'local'=>$local,'choices'=>$choices,'rules'=>$choices],$csrf);
    $check($status===200 && $json['data']['document']['settings']['theme']==='custom' && $json['data']['document']['settings']['fontSize']===18,'selected conflict and independent field merged');$version=$json['data']['version'];
    $check($json['data']['rules']['["settings","theme"]']==='local','resolution returns saved rule');
    [$status,$json]=$request('POST','/api/sync',['version'=>$version,'document'=>$repo->read($uid)['document']],$csrf);$check($status===200,'specified POST sync supported');$version=$json['data']['version'];
    $previous=$repo->read($uid)['document'];$local=json_decode(json_encode($previous));unset($local->favorites->favorite);
    [$status,$json]=$request('PUT','/api/favorites/favorite',['version'=>$version,'item'=>(object)['name'=>'Cloud edited']],$csrf);$version=$json['data']['version'];
    [$status,$json]=$request('POST','/api/sync/resolve-conflict',['version'=>$version,'previous'=>$previous,'local'=>$local],$csrf);
    $check($status===409 && $json['data']['conflicts'][0]['path']===['favorites','favorite'] && $json['data']['conflicts'][0]['local']['present']===false,'API deletion versus edit conflict');
    [$status,$json]=$request('POST','/api/sync/resolve-conflict',['version'=>$version,'previous'=>$previous,'local'=>$local,'choices'=>(object)['["favorites","favorite"]'=>'cloud']],$csrf);
    $check($status===200 && $json['data']['document']['favorites']['favorite']['name']==='Cloud edited','API choice keeps cloud-edited record');$version=$json['data']['version'];
    [$status,$json]=$request('DELETE','/api/search/history/'.$historyId,['version'=>$version],$csrf);$check($status===200 && $json['data']['document']['history']===[],'history delete');$version=$json['data']['version'];
    [$status,$json]=$request('DELETE','/api/favorites/favorite',['version'=>$version],$csrf);$check($status===200 && $json['data']['document']['favorites']===[],'favorite delete');$version=$json['data']['version'];
    $check($repo->read($other)['document']->favorites->foreign->name==='Private','all mutations preserve other owner');
    $cookies=[];foreach(['/api/settings','/api/favorites','/api/favorite-folders','/api/search/history','/api/search-engines','/api/ai-providers'] as $path) {[$status]=$request('GET',$path);$check($status===401,'anonymous entity API denied '.$path);}
} finally {$pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$uid,$other]);}
echo "$count cloud API assertions passed.\n";

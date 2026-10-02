<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\{Database,Migrator};use App\Repositories\{AuthRepository,BackgroundRepository};use App\Services\BackgroundInput;use App\Http\HttpException;
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$migrator=new Migrator($pdo,$root.'/database/migrations');$migrator->migrate();
$auth=new AuthRepository($pdo);$repository=new BackgroundRepository($pdo);
$user=$auth->upsertIdentity(['id'=>'999999999999999941','username'=>'Background Test','display_name'=>null,'avatar'=>null],'en');
$other=$auth->upsertIdentity(['id'=>'999999999999999942','username'=>'Background Other','display_name'=>null,'avatar'=>null],'en');
$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($user,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());
$cookies=['search_remember'=>$device.'.'.$token];$passed=0;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";};
$request=static function(string $method,string $path,?array $body=null,?string $csrf=null,?string $multipart=null,?string $boundary=null,array $extra=[])use(&$cookies):array {
    $headers=['Cookie: '.implode('; ',array_map(static fn($key,$value)=>$key.'='.$value,array_keys($cookies),$cookies)),...$extra];
    if($csrf)$headers[]='X-CSRF-Token: '.$csrf;
    if($body!==null)$headers[]='Content-Type: application/json';
    if($multipart!==null)$headers[]='Content-Type: multipart/form-data; boundary='.$boundary;
    $response=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),
        'content'=>$multipart??($body===null?'':json_encode($body,JSON_THROW_ON_ERROR)),'ignore_errors'=>true,'timeout'=>15]]));
    foreach($http_response_header as $line)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$match))$cookies[$match[1]]=$match[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);
    return [(int)$match[1],json_decode($response,true),$response,$http_response_header];
};
try {
    $authenticated=$cookies;$cookies=[];[$status]=$request('GET','/api/backgrounds');$check($status===401,'guest metadata denied');$cookies=$authenticated;
    [, $json]=$request('GET','/api/csrf');$csrf=$json['data']['csrf_token'];
    [$status]=$request('GET','/api/backgrounds',null,null,null,null,['X-Background-Owner: '.$user]);$check($status===200,'matching read owner hint accepted');
    [$status]=$request('GET','/api/backgrounds',null,null,null,null,['X-Background-Owner: '.$other]);$check($status===403,'read owner hint mismatch denied');
    [$status]=$request('POST','/api/backgrounds/url',['item'=>['name'=>'Bad','url'=>'https://example.test/image.png']]);$check($status===403,'CSRF required');
    [$status]=$request('POST','/api/backgrounds/url',['user_id'=>$other,'item'=>['name'=>'Bad','url'=>'https://example.test/image.png']],$csrf);$check($status===403,'owner hint cannot select another account');
    [$status]=$request('POST','/api/backgrounds/url',['item'=>['name'=>'Bad','url'=>'javascript:alert(1)']],$csrf);$check($status===422,'unsafe URL denied');
    [$status]=$request('POST','/api/backgrounds/url',['item'=>['name'=>'Bad','url'=>'https://example.test/image.png','cloudSync'=>null]],$csrf);$check($status===422,'invalid sync flag cannot become enabled by default');
    [$status]=$request('POST','/api/backgrounds/url',['item'=>['name'=>'Bad','url'=>'https://example.test/image.png','rule'=>['type'=>'date','date'=>'2026-02-30']]],$csrf);$check($status===422,'invalid calendar date rejected');
    [$status,$json]=$request('POST','/api/backgrounds/url',['item'=>['id'=>'shared-id','name'=>'URL','url'=>'https://example.test/image.png','rule'=>['operator'=>'and','conditions'=>[['type'=>'login','value'=>true],['type'=>'time','start'=>'18:00','end'=>'06:00']]]]],$csrf);
    $check($status===201&&$json['data']['item']['version']===1,'URL background and rule created');
    $foreign=BackgroundInput::validate((object)['id'=>'shared-id','name'=>'Other','url'=>'https://example.test/other.png']);$repository->save($other,$foreign);
    [$status,$json]=$request('GET','/api/backgrounds');$check($status===200&&count($json['data']['items'])===1&&$json['data']['items'][0]['name']==='URL','same client ID belongs to separate owners');
    [$status]=$request('PUT','/api/backgrounds/shared-id',['version'=>0,'item'=>['name'=>'Old']],$csrf);$check($status===409,'stale background version rejected');
    [$status,$json]=$request('PUT','/api/backgrounds/shared-id',['version'=>1,'item'=>['name'=>'Edited']],$csrf);$check($status===200&&$json['data']['item']['version']===2&&isset($json['data']['item']['rule']),'partial update retains conditions');
    $repository->save($other,BackgroundInput::validate((object)['id'=>'foreign-only','name'=>'Other','url'=>'https://example.test/other.png']));
    [$status]=$request('PUT','/api/backgrounds/foreign-only',['version'=>1,'item'=>['name'=>'Attack']],$csrf);$check($status===404,'foreign-only metadata cannot be changed');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII=');
    $boundary='background-'.bin2hex(random_bytes(16));
    $upload=static function(string $id,string $bytes,string $name='image.png')use($boundary):string {
        return '--'.$boundary."\r\nContent-Disposition: form-data; name=\"item\"\r\n\r\n".json_encode(['id'=>$id,'name'=>'Uploaded'])
            ."\r\n--".$boundary."\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$name\"\r\nContent-Type: text/html\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
    };
    $nonce=bin2hex(random_bytes(32));$receiptHeader=['X-Background-Request: '.$nonce];
    [$status,$json]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('file-id',$png),$boundary,$receiptHeader);
    $check($status===201&&$json['data']['item']['sourceType']==='upload'&&$json['data']['item']['fileSize']===strlen($png),'real app multipart upload saved with measured size');
    $initialRevision=$json['data']['item']['fileRevision'];
    [$status,$replay]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('file-id',$png),$boundary,$receiptHeader);
    $check($status===201&&$replay['data']['replayed']&&$replay['data']['item']['fileRevision']===$initialRevision&&$repository->find($user,'file-id')['version']==1,'upload replay retains original file and version');
    [$status]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('different-id',$png),$boundary,$receiptHeader);
    $check($status===409&&$repository->find($user,'different-id')===null,'request ID cannot be reused for different metadata');
    [$status]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('file-id',$png."\0"),$boundary,$receiptHeader);
    $check($status===409&&(int)$repository->find($user,'file-id')['version']===1,'request ID cannot be reused with different file bytes');
    [$status]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('bad-request',$png),$boundary,['X-Background-Request: invalid']);
    $check($status===422,'malformed request ID rejected');
    $check(!isset($json['data']['item']['file_path'])&&!str_contains(json_encode($json),'/var/www/'),'private filenames and server paths not exposed');
    [$status,,$bytes,$headers]=$request('GET','/api/backgrounds/file-id/file');$check($status===200&&$bytes===$png,'authenticated media streaming');
    [$status]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['X-Background-Owner: '.$other]);$check($status===403,'file read owner hint mismatch denied');
    [$status,,$bytes,$headers]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['Range: bytes=0-7']);$check($status===206&&$bytes===substr($png,0,8),'media byte range');
    [$status,,$bytes]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['Range: bytes=-4']);$check($status===206&&$bytes===substr($png,-4),'suffix range');
    [$status]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['Range: bytes=999999-']);$check($status===416,'out-of-bounds range refused');
    $authenticated=$cookies;$cookies=[];[$status]=$request('GET','/api/backgrounds/file-id/file');$check($status===401,'guest file download denied');$cookies=$authenticated;
    $otherDevice=bin2hex(random_bytes(16));$otherToken=bin2hex(random_bytes(32));$auth->createDevice($other,$otherDevice,hash('sha256',$otherToken),['browser'=>'Test','os'=>'Test'],time());
    $cookies=['search_remember'=>$otherDevice.'.'.$otherToken];[$status]=$request('GET','/api/backgrounds/file-id/file');$check($status===404,'another authenticated owner cannot download the file');$cookies=$authenticated;
    $filesBefore=count(glob($root.'/storage/uploads/backgrounds/'.$user.'/*'));
    [$status]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('file-id',$png),$boundary);$check($status===409&&count(glob($root.'/storage/uploads/backgrounds/'.$user.'/*'))===$filesBefore,'duplicate upload cleans staged file on DB rejection');
    [$status]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('bad-file','<?php echo "bad";'),$boundary);$check($status===422,'disguised script denied by app API');
    $fileRow=$repository->find($user,'file-id');$limited=new BackgroundRepository($pdo,strlen($png));
    try{$limited->save($user,BackgroundInput::validate((object)['id'=>'quota-test','name'=>'Quota'],true),null,['filename'=>str_repeat('a',48).'.png','bytes'=>1,'type'=>'image','mime'=>'image/png']);throw new RuntimeException('Quota allowed');}
    catch(HttpException $error){$check($error->errorCode==='BACKGROUND_QUOTA_EXCEEDED'&&$repository->find($user,'quota-test')===null,'quota rejection leaves metadata unchanged');}
    [$status,$json]=$request('DELETE','/api/backgrounds/file-id',['version'=>1],$csrf);$check($status===200&&$json['data']['item']['deleted'],'delete archives background');
    [$status]=$request('GET','/api/backgrounds/file-id/file');$check($status===404,'archived file not served');
    $check($repository->usage($user)['used_bytes']===strlen($png),'archived files still count toward usage');
    [$status,$json]=$request('PUT','/api/backgrounds/file-id',['version'=>2,'item'=>['deleted'=>false]],$csrf);$check($status===200&&!$json['data']['item']['deleted'],'archived background can be restored');
    $before=$repository->find($user,'file-id');$revision=$json['data']['item']['fileRevision'];$oldPath=$root.'/storage/uploads/backgrounds/'.$user.'/'.$before['file_path'];
    [$status,,$bytes]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['If-Match: "'.$revision.'"']);$check($status===200&&$bytes===$png,'matching file revision served');
    [$status]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['If-Match: "'.str_repeat('0',64).'"']);$check($status===412,'stale file revision cannot retrieve newer bytes');
    $replace=static fn(string $version,string $id='file-id'):string=>str_replace('--'.$boundary."--\r\n",'--'.$boundary."\r\nContent-Disposition: form-data; name=\"version\"\r\n\r\n".$version."\r\n--".$boundary."--\r\n",$upload($id,$png));
    [$status]=$request('POST','/api/backgrounds/file-id/upload',null,null,$replace('3'),$boundary);$check($status===403,'replacement requires CSRF');
    [$status]=$request('POST','/api/backgrounds/foreign-only/upload',null,$csrf,$replace('1','foreign-only'),$boundary);$check($status===404,'foreign replacement denied');
    [$status]=$request('POST','/api/backgrounds/file-id/upload',null,$csrf,$replace('2'),$boundary);$check($status===409&&is_file($oldPath),'stale replacement preserves prior file');
    [$status]=$request('POST','/api/backgrounds/file-id/upload',null,$csrf,$replace('3x'),$boundary);$check($status===422&&is_file($oldPath),'malformed multipart version rejected');
    $replaceHeader=['X-Background-Request: '.bin2hex(random_bytes(32))];
    [$status,$json]=$request('POST','/api/backgrounds/file-id/upload',null,$csrf,$replace('3'),$boundary,$replaceHeader);
    clearstatcache(true,$oldPath);
    $check($status===200&&$json['data']['item']['version']===4&&$json['data']['item']['fileRevision']!==$revision&&!is_file($oldPath),'replacement commits new revision then removes old file');
    $newRevision=$json['data']['item']['fileRevision'];
    [$status,$replay]=$request('POST','/api/backgrounds/file-id/upload',null,$csrf,$replace('3'),$boundary,$replaceHeader);
    $check($status===200&&$replay['data']['replayed']&&$replay['data']['item']['fileRevision']===$newRevision&&(int)$repository->find($user,'file-id')['version']===4,'replacement replay accepts original version without repeating replacement');
    [$status,,$bytes]=$request('GET','/api/backgrounds/file-id/file');$check($status===200&&$bytes===$png&&$repository->usage($user)['used_bytes']===strlen($png),'replacement bytes and quota are correct');
    [$status]=$request('GET','/api/backgrounds/file-id/file',null,null,null,null,['If-Match: "'.$revision.'"']);$check($status===412,'old revision is invalid after replacement');
    $currentPath=$root.'/storage/uploads/backgrounds/'.$user.'/'.$repository->find($user,'file-id')['file_path'];
    [$status]=$request('PUT','/api/backgrounds/file-id',['version'=>4,'item'=>['sourceType'=>'url']],$csrf);$check($status===422&&is_file($currentPath),'image URL conversion requires an explicit URL');
    [$status]=$request('PUT','/api/backgrounds/file-id',['version'=>4,'item'=>['sourceType'=>'url','url'=>'javascript:alert(1)']],$csrf);$check($status===422&&is_file($currentPath),'invalid URL conversion preserves upload');
    [$status,$json]=$request('PUT','/api/backgrounds/file-id',['version'=>4,'item'=>['sourceType'=>'url','url'=>'https://example.test/replaced.png']],$csrf);clearstatcache(true,$currentPath);
    $check($status===200&&$json['data']['item']['sourceType']==='url'&&$json['data']['item']['fileSize']===0&&$json['data']['item']['fileRevision']===null&&!is_file($currentPath)&&$repository->usage($user)['used_bytes']===0,'conversion to URL commits before file cleanup and releases quota');
    [$status,$replay]=$request('POST','/api/backgrounds/upload',null,$csrf,$upload('file-id',$png),$boundary,$receiptHeader);
    $check($status===201&&$replay['data']['item']['version']===1&&(int)$repository->find($user,'file-id')['version']===5&&$repository->usage($user)['used_bytes']===0,'historical receipt cannot overwrite subsequent edits or restore deleted media');
    $check(count(glob($root.'/storage/uploads/backgrounds/'.$user.'/*'))===0,'replayed and rejected upload candidates cleaned');
    [$status]=$request('PUT','/api/backgrounds/file-id',['version'=>5,'item'=>['sourceType'=>'upload']],$csrf);$check($status===422,'upload source cannot be declared without actual file');
    $statement=$pdo->prepare('SELECT COUNT(*) FROM background_rules WHERE user_id=?');$statement->execute([$user]);$check((int)$statement->fetchColumn()===1,'conditions stored in separate owned table');
    echo "$passed background API assertions passed.\n";
} finally {
    foreach([$user,$other] as $owner){foreach($repository->list($owner) as $row)if($row['file_path']!==null)@unlink($root.'/storage/uploads/backgrounds/'.$owner.'/'.$row['file_path']);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$owner]);
        $directory=$root.'/storage/uploads/backgrounds/'.$owner;if(is_dir($directory)&&count(scandir($directory))===2)rmdir($directory);
    }
}

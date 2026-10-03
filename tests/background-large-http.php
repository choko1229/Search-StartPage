<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\{AuthRepository,BackgroundRepository};
use App\Services\{BackgroundUpload,BackgroundCompression};
$seed=$argv[1]??'';
if(!is_file($seed)||filesize($seed)>1048576||BackgroundUpload::inspect($seed,'seed.mp4')['type']!=='video')throw new RuntimeException('Generated video seed required');
if(BackgroundCompression::capabilities()['ffmpeg'])throw new RuntimeException('Use codec-free app for exact retained-size boundary test');
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$auth=new AuthRepository($pdo);$repository=new BackgroundRepository($pdo);$storage=new BackgroundUpload($root);
$identity='999999999999999963';
$lookup=$pdo->prepare('SELECT id FROM users WHERE discord_id=?');$lookup->execute([$identity]);
if($lookup->fetchColumn())throw new RuntimeException('Large test identity already exists; inspect interrupted fixture');
$user=$auth->upsertIdentity(['id'=>$identity,'username'=>'Large background verification','display_name'=>null,'avatar'=>null],'en');
$work=sys_get_temp_dir().'/search-large-'.bin2hex(random_bytes(8));mkdir($work,0700);
$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));
$auth->createDevice($user,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());
// The Cookie lives only in this private curl configuration and cookie jar.
$curlConfig=$work.'/curl.conf';file_put_contents($curlConfig,'cookie = "search_remember='.$device.'.'.$token.'"'."\n");chmod($curlConfig,0600);
$jar=$work.'/cookies';$passed=0;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";};
$request=static function(string $path,array $arguments=[])use($work,$curlConfig,$jar):array{
    $body=$work.'/response';$headers=$work.'/headers';$errors=$work.'/curl-errors';
    $command=['/usr/bin/curl','--silent','--show-error','--max-time','180','--config',$curlConfig,'--cookie',$jar,'--cookie-jar',$jar,'--output',$body,'--dump-header',$headers,'--write-out','%{http_code}',...$arguments,'http://127.0.0.1'.$path];
    $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file',$errors,'w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Curl unavailable');
    $status=stream_get_contents($pipes[1]);fclose($pipes[1]);$exit=proc_close($process);
    if($exit!==0)throw new RuntimeException('HTTP transfer failed');
    $raw=file_get_contents($body);return [(int)$status,json_decode($raw,true),$raw,file_get_contents($headers)];
};
try {
    [$status,$json]=$request('/api/csrf');$check($status===200,'normal authenticated CSRF session established');$csrf=$json['data']['csrf_token'];
    $video=$work.'/boundary.mp4';copy($seed,$video);
    $handle=fopen($video,'r+b');fseek($handle,0,SEEK_END);$remaining=BackgroundUpload::VIDEO_LIMIT-filesize($seed);
    // An ISO BMFF free box preserves the real generated MP4 stream. Sparse
    // creation avoids allocating 500 MiB in PHP; curl still sends every byte.
    fwrite($handle,pack('N',$remaining).'free');ftruncate($handle,BackgroundUpload::VIDEO_LIMIT);fclose($handle);
    $check(filesize($video)===524288000,'valid generated MP4 padded to exact 500 MiB');
    $upload=static fn(string $id)=>['--header','X-CSRF-Token: '.$csrf,'--form','item='.json_encode(['id'=>$id,'name'=>'Large generated video','type'=>'video']), '--form','file=@'.$video.';filename=boundary.mp4;type=video/mp4'];
    [$status,$json,$raw]=$request('/api/backgrounds/upload',$upload('large-boundary'));
    $check($status===201&&($json['data']['item']['fileSize']??0)===524288000,'actual multipart accepts exact 500 MiB without memory failure');
    $check(($json['data']['warning']??'')==='BACKGROUND_COMPRESSION_UNAVAILABLE','missing optional video encoder preserves file with explicit warning');
    $row=$repository->find($user,'large-boundary');$stored=$storage->existingPath($user,$row['file_path']);
    $check(filesize($stored)===524288000&&hash_file('sha256',$stored)===hash_file('sha256',$video),'private retained bytes match generated upload');
    $check($repository->usage($user)['used_bytes']===524288000,'DB quota usage counts retained final bytes');
    [$status,,$range,$headers]=$request('/api/backgrounds/large-boundary/file',['--header','Range: bytes=0-31']);
    $check($status===206&&strlen($range)===32&&$range===file_get_contents($seed,false,null,0,32)&&str_contains(strtolower($headers),'content-range: bytes 0-31/524288000'),'large private video supports bounded range download');
    file_put_contents($video,"\0",FILE_APPEND);clearstatcache(true,$video);
    [$status,$json,$raw]=$request('/api/backgrounds/upload',$upload('large-rejected'));
    $check($status===413&&($json['error']['code']??'')==='BACKGROUND_TOO_LARGE','actual multipart refuses 500 MiB plus one byte');
    $check($repository->find($user,'large-rejected')===null&&$repository->usage($user)['used_bytes']===524288000,'oversize rejection leaves metadata and quota unchanged');
    $check(count(glob(dirname($stored).'/*'))===1,'oversize request leaves no orphan private file');
    $patch=json_encode(['version'=>1,'item'=>['sourceType'=>'url','url'=>'https://example.test/generated.mp4']]);
    [$status]=$request('/api/backgrounds/large-boundary',['--request','PUT','--header','X-CSRF-Token: '.$csrf,'--header','Content-Type: application/json','--data',$patch]);
    $check($status===200&&!is_file($stored)&&$repository->usage($user)['used_bytes']===0,'source conversion removes giant file and clears charged storage');
    $check(!preg_match('/PHP (?:Warning|Notice|Fatal)|Stack trace/i',$raw),'oversize API does not expose PHP diagnostics');
    echo "$passed large background HTTP assertions passed.\n";
} finally {
    foreach($repository->list($user) as $row)if($row['file_path']!==null){try{unlink($storage->existingPath($user,$row['file_path']));}catch(\App\Http\HttpException){}}
    $pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$user,$identity]);
    foreach(glob($work.'/*') as $path)unlink($path);rmdir($work);
}

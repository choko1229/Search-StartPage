<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\{AuthRepository,BackgroundRepository};
use App\Services\{BackgroundUpload,BackgroundCompression};
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$auth=new AuthRepository($pdo);$repository=new BackgroundRepository($pdo);$storage=new BackgroundUpload($root);
$identity='999999999999999965';$lookup=$pdo->prepare('SELECT id FROM users WHERE discord_id=?');$lookup->execute([$identity]);
if($lookup->fetchColumn())throw new RuntimeException('Compression HTTP identity already exists; inspect interrupted fixture');
if(!BackgroundCompression::capabilities()['imagick']&&!BackgroundCompression::capabilities()['gd'])throw new RuntimeException('Real image encoder required');
$owner=$auth->upsertIdentity(['id'=>$identity,'username'=>'Compressed HTTP verification','display_name'=>null,'avatar'=>null],'en');
$device=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$auth->createDevice($owner,$device,hash('sha256',$token),['browser'=>'Test','os'=>'Test'],time());
$cookies=['search_remember'=>$device.'.'.$token];$passed=0;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";};
$request=static function(string $method,string $path,?string $body=null,array $headers=[])use(&$cookies):array{
    $headers[]='Cookie: '.implode('; ',array_map(static fn($key,$value)=>$key.'='.$value,array_keys($cookies),$cookies));
    $bytes=file_get_contents('http://127.0.0.1'.$path,false,stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body??'','ignore_errors'=>true,'timeout'=>30]]));
    foreach($http_response_header as $line)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$match))$cookies[$match[1]]=$match[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return [(int)$match[1],json_decode($bytes,true),$bytes];
};
$chunk=static fn(string $type,string $bytes)=>pack('N',strlen($bytes)).$type.$bytes.pack('N',crc32($type.$bytes));
$raw=str_repeat("\0".str_repeat("\x00\x80\xff\x40",128),96);
$png="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',128,96,8,6,0,0,0)).$chunk('IDAT',gzcompress($raw,0)).$chunk('IEND','');
$videoPath=null;
try {
    [$status,$json]=$request('GET','/api/csrf');$check($status===200,'normal remember authentication and CSRF session established');$csrf=$json['data']['csrf_token'];
    $boundary='compressed-'.bin2hex(random_bytes(16));
    $upload=static function(?int $version=null,?string $video=null)use($boundary,$png):string{
        $body='--'.$boundary."\r\nContent-Disposition: form-data; name=\"item\"\r\n\r\n".json_encode(['id'=>'compressed-http','name'=>'Generated media','type'=>$video===null?'image':'video']);
        if($version!==null)$body.="\r\n--".$boundary."\r\nContent-Disposition: form-data; name=\"version\"\r\n\r\n".$version;
        $name=$video===null?'generated.png':'generated.mp4';$mime=$video===null?'image/png':'video/mp4';
        return $body."\r\n--".$boundary."\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$name\"\r\nContent-Type: $mime\r\n\r\n".($video??$png)."\r\n--".$boundary."--\r\n";
    };
    $headers=['X-CSRF-Token: '.$csrf,'Content-Type: multipart/form-data; boundary='.$boundary];
    [$status,$json]=$request('POST','/api/backgrounds/upload',$upload(),$headers);
    $size=$json['data']['item']['fileSize']??0;
    $check($status===201&&$size>0&&$size<strlen($png)&&$json['data']['warning']===null,'product upload API compresses real image before reporting final size');
    $row=$repository->find($owner,'compressed-http');$first=$storage->existingPath($owner,$row['file_path']);
    $check(filesize($first)===$size&&(int)$row['file_size']===$size&&$repository->usage($owner)['used_bytes']===$size,'HTTP response, private file, DB metadata and charged storage agree');
    $check(count(glob(dirname($first).'/*'))===1,'successful compression discards staged original');
    [$status,,$download]=$request('GET','/api/backgrounds/compressed-http/file');$dimensions=getimagesizefromstring($download);
    $check($status===200&&strlen($download)===$size&&hash('sha256',$download)===hash_file('sha256',$first),'authenticated private download returns actual compressed bytes');
    $check($dimensions[0]===128&&$dimensions[1]===96&&$dimensions['mime']==='image/png','downloaded image retains dimensions and MIME');
    [$status,$json]=$request('POST','/api/backgrounds/compressed-http/upload',$upload(1),$headers);
    $secondRow=$repository->find($owner,'compressed-http');$second=$storage->existingPath($owner,$secondRow['file_path']);
    clearstatcache(true,$first);
    $check($status===200&&$json['data']['item']['version']===2&&$second!==$first&&!is_file($first),'compressed replacement commits before removing old file');
    $check(count(glob(dirname($second).'/*'))===1&&$repository->usage($owner)['used_bytes']===(int)$secondRow['file_size'],'replacement has one private file and no doubled quota');
    [$status]=$request('POST','/api/backgrounds/compressed-http/upload',$upload(1),$headers);
    $check($status===409&&is_file($second)&&count(glob(dirname($second).'/*'))===1,'stale replacement refuses without discarding current file or leaving staged files');
    $patch=json_encode(['version'=>2,'item'=>['sourceType'=>'url','url'=>'https://example.test/image.png']]);
    [$status]=$request('PUT','/api/backgrounds/compressed-http',$patch,['X-CSRF-Token: '.$csrf,'Content-Type: application/json']);
    clearstatcache(true,$second);
    $check($status===200&&!is_file($second)&&$repository->usage($owner)['used_bytes']===0,'URL conversion cleans compressed file and clears quota');
    if(!BackgroundCompression::capabilities()['ffmpeg'])throw new RuntimeException('Real FFmpeg required for video HTTP verification');
    $videoPath=sys_get_temp_dir().'/search-http-video-'.bin2hex(random_bytes(12)).'.mp4';
    $process=proc_open([BackgroundCompression::ffmpegPath(),'-nostdin','-loglevel','error','-y','-f','lavfi','-i','testsrc=size=128x96:rate=15','-t','2','-c:v','mpeg4','-q:v','1',$videoPath],
        [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    $check(is_resource($process)&&proc_close($process)===0,'synthetic video generated for real encoder HTTP test');
    $original=file_get_contents($videoPath);
    [$status,$json]=$request('POST','/api/backgrounds/compressed-http/upload',$upload(3,$original),$headers);
    $videoSize=$json['data']['item']['fileSize']??0;
    $check($status===200&&$videoSize>0&&$videoSize<strlen($original)&&$json['data']['item']['type']==='video'&&$json['data']['warning']===null,'product upload API applies real FFmpeg compression');
    $videoRow=$repository->find($owner,'compressed-http');$videoStored=$storage->existingPath($owner,$videoRow['file_path']);
    $check($videoRow['mime']==='video/mp4'&&filesize($videoStored)===$videoSize&&$repository->usage($owner)['used_bytes']===$videoSize,'compressed video file, DB and quota use final measured bytes');
    [$status,,$videoDownload]=$request('GET','/api/backgrounds/compressed-http/file');
    $check($status===200&&strlen($videoDownload)===$videoSize&&hash('sha256',$videoDownload)===hash_file('sha256',$videoStored),'authenticated download returns compressed video bytes');
    $check(count(glob(dirname($videoStored).'/*'))===1,'video conversion leaves only committed compressed output');
    echo "$passed compressed background HTTP assertions passed.\n";
} finally {
    foreach($repository->list($owner) as $row)if($row['file_path']!==null){try{unlink($storage->existingPath($owner,$row['file_path']));}catch(\App\Http\HttpException){}}
    $pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$owner,$identity]);
    if($videoPath!==null&&is_file($videoPath))unlink($videoPath);
}

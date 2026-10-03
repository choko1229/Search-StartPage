<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;
use App\Database\Database;
use App\Repositories\{AuthRepository,BackgroundRepository};
use App\Services\{BackgroundCompression,BackgroundInput};
use App\Http\HttpException;
$root=dirname(__DIR__);$pdo=Database::connect(Config::load($root)->get('database'));
$identity='999999999999999964';$lookup=$pdo->prepare('SELECT id FROM users WHERE discord_id=?');$lookup->execute([$identity]);
if($lookup->fetchColumn())throw new RuntimeException('Compression test identity already exists; inspect interrupted fixture');
$auth=new AuthRepository($pdo);$owner=$auth->upsertIdentity(['id'=>$identity,'username'=>'Compressed quota test','display_name'=>null,'avatar'=>null],'en');
$work=sys_get_temp_dir().'/search-compressed-quota-'.bin2hex(random_bytes(8));mkdir($work,0700);$passed=0;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";};
$chunk=static fn(string $type,string $bytes)=>pack('N',strlen($bytes)).$type.$bytes.pack('N',crc32($type.$bytes));
try {
    $path=$work.'/'.bin2hex(random_bytes(24)).'.png';
    $raw=str_repeat("\0".str_repeat("\x00\x80\xff\x40",128),96);
    $png="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',128,96,8,6,0,0,0)).$chunk('IDAT',gzcompress($raw,0)).$chunk('IEND','');file_put_contents($path,$png);
    $compressed=(new BackgroundCompression())->compress($path);
    $check($compressed['compressed']&&$compressed['bytes']<strlen($png),'real image encoder produces smaller verified file');
    $limit=$compressed['bytes']*2;$repository=new BackgroundRepository($pdo,$limit);
    $check(strlen($png)>$limit,'original file exceeds entire test quota');
    $item=static fn(string $id)=>BackgroundInput::validate((object)['id'=>$id,'name'=>'Generated image','type'=>'image'],true);
    $first=$repository->save($owner,$item('compressed-a'),null,$compressed);
    $check((int)$first['file_size']===filesize($compressed['path'])&&$first['file_path']===basename($compressed['path']),'DB stores actual final file size and filename');
    $check($repository->usage($owner)['used_bytes']===$compressed['bytes'],'charged size excludes uncompressed input');
    $second=$repository->save($owner,$item('compressed-b'),null,$compressed);
    $check($repository->usage($owner)===['used_bytes'=>$limit,'limit_bytes'=>$limit],'two compressed files accepted exactly at quota');
    $rejected=false;try{$repository->save($owner,$item('compressed-c'),null,$compressed);}catch(HttpException $error){$rejected=$error->status===413&&$error->errorCode==='BACKGROUND_QUOTA_EXCEEDED';}
    $check($rejected&&$repository->find($owner,'compressed-c')===null&&$repository->usage($owner)['used_bytes']===$limit,'third compressed file rejects atomically without quota change');
    $changed=$item('compressed-a');$changed['name']='Replacement';
    $replacement=$repository->save($owner,$changed,1,$compressed);
    $check((int)$replacement['version']===2&&$repository->usage($owner)['used_bytes']===$limit,'replacement subtracts previous final size instead of double charging');
    $check(is_file($path)&&hash_file('sha256',$path)===hash('sha256',$png),'compression and DB commit preserve caller-owned original');
    echo "$passed real compression and DB quota assertions passed.\n";
} finally {
    $pdo->prepare('DELETE FROM users WHERE id=? AND discord_id=?')->execute([$owner,$identity]);
    foreach(glob($work.'/*') as $file)unlink($file);rmdir($work);
}

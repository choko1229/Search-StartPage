<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if(PHP_SAPI!=='cli')exit(1);
use App\Services\{UpdatePackagePaths,UpdateManifest,UpdatePackage,ReleasePackageBuilder};
use App\Http\HttpException;
$count=0;$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $action,string $code)use($check):void{try{$action();throw new LogicException('Unexpected success');}catch(HttpException $e){$check($e->errorCode===$code,$code);}};
$temporary=sys_get_temp_dir().'/search-package-'.bin2hex(random_bytes(8));mkdir($temporary,0700);$root=$temporary.'/source';mkdir($root,0700);
$write=static function(string $path,string $contents)use($root):void{$parent=dirname($root.'/'.$path);if(!is_dir($parent))mkdir($parent,0700,true);file_put_contents($root.'/'.$path,$contents);};
$remove=static function(string $path):void{if(is_link($path)||is_file($path)){unlink($path);return;}if(is_dir($path)){$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($files as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($path);}};
$pack=static function(array $entries,string $path):void{$stream=fopen($path,'wb');foreach($entries as $name=>$data){fwrite($stream,UpdatePackage::header($name,strlen($data)));fwrite($stream,$data);if(strlen($data)%512)fwrite($stream,str_repeat("\0",512-strlen($data)%512));}fwrite($stream,str_repeat("\0",1024));fclose($stream);};
try{
    foreach(['VERSION'=>"1.2.0\n",'app/autoload.php'=>'<?php // generated','app/bootstrap.php'=>'<?php // generated','public/index.php'=>'<?php // generated','config/config.example.php'=>'<?php return [];','public/assets/a.js'=>'console.log(1);','public/.htaccess'=>'Require all granted',
        'config/config.php'=>'secret_marker','config/install.key'=>'setup_marker','storage/uploads/backgrounds/user.png'=>'user_bytes','storage/user-data.json'=>'user_data_marker','spec.md'=>'user_spec_marker','progress.md'=>'progress_marker','tests/test.php'=>'test_marker','public/_test/preview.php'=>'preview_marker'] as $path=>$contents)$write($path,$contents);
    $long='public/assets/'.str_repeat('a',90).'/long.txt';$write($long,'long path');
    $archive=$temporary.'/release.tar';$built=(new ReleasePackageBuilder())->build($root,$archive);
    $check($built['version']==='1.2.0'&&$built['files']===8,'builder includes only distribution files');
    $contents=file_get_contents($archive);$check(strlen(UpdatePackage::header('VERSION',6))===512,'standard header size');
    foreach(['secret_marker','setup_marker','user_bytes','user_data_marker','user_spec_marker','progress_marker','test_marker','preview_marker'] as $marker)$check(!str_contains($contents,$marker),'protected contents excluded '.$marker);
    $stage=$temporary.'/stage';$manifest=(new UpdatePackage())->verify($archive,$stage,'v1.2.0');
    $check($manifest['version']==='1.2.0'&&file_get_contents($stage.'/'.$long)==='long path','verified package supports conventional v tag and prefix header');
    $check((fileperms($stage.'/app/bootstrap.php')&0777)===0600&&(fileperms($stage)&0777)===0700,'staged code is private');
    $check(!is_file($stage.'/config/config.php')&&!is_dir($stage.'/storage'),'stage contains no writable user data');
    $check(file_get_contents($root.'/config/config.php')==='secret_marker'&&file_get_contents($root.'/storage/user-data.json')==='user_data_marker','source configuration and user data unchanged');
    $reject(fn()=>(new UpdatePackage())->verify($archive,$stage,'v1.2.0'),'INVALID_UPDATE_PACKAGE');
    $reject(fn()=>(new ReleasePackageBuilder())->build($root,$archive),'UPDATE_PACKAGE_EXISTS');
    foreach(['../app/a.php','/app/a.php','app/../config/config.php','app\\a.php','public/a.php:stream','public/CON.txt','public/foo.','public/.env','public/_test/test.php','config/config.php','config/install.key','storage/uploads/a.php','.git/config','spec.md','progress.md','App/x.php','app/a/../../x'] as $path)$reject(fn()=>UpdatePackagePaths::validate($path),'INVALID_UPDATE_PATH');
    $bad=$manifest;$bad['files']['storage/user-data.json']=['bytes'=>1,'sha256'=>str_repeat('a',64)];$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['files']['public/INDEX.php']=$bad['files']['public/index.php'];$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['files']['public/assets']=$bad['files']['public/index.php'];$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['files']['public/index.php']['bytes']=UpdateManifest::FILE_LIMIT+1;$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;unset($bad['files']['app/bootstrap.php']);$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['files']['VERSION']['bytes']=513;$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $reject(fn()=>UpdateManifest::decode(json_encode($manifest),'1.3.0'),'INVALID_UPDATE_PACKAGE');
    $reject(fn()=>UpdateManifest::decode(json_encode($manifest),'1.2.0+different'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['php_min']='8.9.999';$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'UPDATE_PHP_REQUIRED');
    $reject(fn()=>UpdateManifest::decode('{}','1.2.0'),'INVALID_UPDATE_PACKAGE');
    $entries=['release-manifest.json'=>json_encode($manifest)];foreach($manifest['files'] as $path=>$metadata)$entries[$path]=file_get_contents($root.'/'.$path);
    $broken=$entries;$broken['public/index.php']=str_repeat('X',strlen($entries['public/index.php']));$pack($broken,$temporary.'/bad-hash.tar');$reject(fn()=>(new UpdatePackage())->verify($temporary.'/bad-hash.tar',$temporary.'/bad-stage','1.2.0'),'INVALID_UPDATE_PACKAGE');
    $check(!file_exists($temporary.'/bad-stage'),'partial stage removed on mismatch');
    $broken=$entries;unset($broken['app/bootstrap.php']);$pack($broken,$temporary.'/missing.tar');$reject(fn()=>(new UpdatePackage())->verify($temporary.'/missing.tar',$temporary.'/missing-stage','1.2.0'),'INVALID_UPDATE_PACKAGE');
    $broken=$entries;$broken['config/config.php']='overwrite';$pack($broken,$temporary.'/protected.tar');$reject(fn()=>(new UpdatePackage())->verify($temporary.'/protected.tar',$temporary.'/protected-stage','1.2.0'),'INVALID_UPDATE_PATH');
    $broken=$entries;$broken['../escape.txt']='escape';$pack($broken,$temporary.'/traversal.tar');$reject(fn()=>(new UpdatePackage())->verify($temporary.'/traversal.tar',$temporary.'/traversal-stage','1.2.0'),'INVALID_UPDATE_PATH');
    $check(!file_exists($temporary.'/escape.txt'),'traversal cannot write outside stage');
    $bad=$manifest;for($i=0;$i<5;++$i)$bad['files']['public/large'.$i.'.bin']=['bytes'=>UpdateManifest::FILE_LIMIT,'sha256'=>str_repeat('a',64)];$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['files']['VERSION']['sha256']=hash('sha256',"1.3.0\n");$broken=$entries;$broken['release-manifest.json']=json_encode($bad);$broken['VERSION']="1.3.0\n";$pack($broken,$temporary.'/wrong-version.tar');$reject(fn()=>(new UpdatePackage())->verify($temporary.'/wrong-version.tar',$temporary.'/wrong-version-stage','1.2.0'),'INVALID_UPDATE_PACKAGE');
    $bad=$manifest;$bad['php_min']='7.4.0';$reject(fn()=>UpdateManifest::decode(json_encode($bad),'1.2.0'),'INVALID_UPDATE_PACKAGE');
    $duplicate=$contents; $offset=512+(int)(ceil(strlen($entries['release-manifest.json'])/512)*512);$firstHeader=substr($contents,$offset,512);$firstSize=intval(trim(substr($firstHeader,124,12),"\0 "),8);$duplicate=substr($contents,0,-1024).substr($contents,$offset,512+(int)(ceil($firstSize/512)*512)).str_repeat("\0",1024);file_put_contents($temporary.'/duplicate.tar',$duplicate);
    $reject(fn()=>(new UpdatePackage())->verify($temporary.'/duplicate.tar',$temporary.'/duplicate-stage','1.2.0'),'INVALID_UPDATE_PACKAGE');
    foreach(['2','1','5','x','g'] as $type){$link=$contents;$header=substr($link,$offset,512);$header[156]=$type;$header=substr_replace($header,str_repeat(' ',8),148,8);$header=substr_replace($header,sprintf('%06o',array_sum(unpack('C*',$header)))."\0 ",148,8);$link=substr_replace($link,$header,$offset,512);file_put_contents($temporary.'/link.tar',$link);$reject(fn()=>(new UpdatePackage())->verify($temporary.'/link.tar',$temporary.'/link-stage','1.2.0'),'INVALID_UPDATE_PACKAGE');}
    foreach([substr($contents,0,-600),$contents.'not-zero',substr_replace($contents,'X',10,1)] as $badArchive){file_put_contents($temporary.'/broken.tar',$badArchive);$reject(fn()=>(new UpdatePackage())->verify($temporary.'/broken.tar',$temporary.'/broken-stage','1.2.0'),'INVALID_UPDATE_PACKAGE');}
    if(function_exists('symlink')){symlink($root.'/config/config.php',$root.'/public/link.php');$reject(fn()=>(new ReleasePackageBuilder())->build($root,$temporary.'/linked.tar'),'INVALID_UPDATE_PATH');unlink($root.'/public/link.php');
        symlink($stage,$temporary.'/linked-dir');$reject(fn()=>(new UpdatePackage())->verify($archive,$temporary.'/linked-dir/new','1.2.0'),'INVALID_UPDATE_PATH');unlink($temporary.'/linked-dir');}
}finally{$remove($temporary);}
echo "$count update package checks passed.\n";

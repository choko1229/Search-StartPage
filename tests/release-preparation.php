<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
$root=dirname(__DIR__);$temporary=sys_get_temp_dir().'/release-preparation-'.bin2hex(random_bytes(8));mkdir($temporary,0700);$source=$temporary.'/source';mkdir($source,0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$copy=static function(string $from,string $to)use(&$copy):void{if(is_link($from))throw new RuntimeException('Unexpected source link');if(is_dir($from)){mkdir($to,0700);foreach(scandir($from) as $name)if($name!=='.'&&$name!=='..')$copy($from.'/'.$name,$to.'/'.$name);}else{copy($from,$to);chmod($to,0600);}};
$remove=static function(string $path)use(&$remove):void{if(is_link($path)||is_file($path)){unlink($path);return;}foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);};
$run=static function(string $tag,string $output)use($source):array{
    $process=proc_open([PHP_BINARY,$source.'/bin/prepare-release.php',$tag,$output],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$source);if(!is_resource($process))throw new RuntimeException('Release process unavailable');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($process),$out,$err];
};
try{
    foreach(['app','bin','database','lang','public','config'] as $part){
        if($part==='config'){mkdir($source.'/config',0700);foreach(['config.example.php','providers.php'] as $file)$copy($root.'/config/'.$file,$source.'/config/'.$file);}
        else $copy($root.'/'.$part,$source.'/'.$part);
    }
    foreach(['README.md','composer.json'] as $file)$copy($root.'/'.$file,$source.'/'.$file);
    file_put_contents($source.'/VERSION','2.3.4');mkdir($source.'/storage',0700);file_put_contents($source.'/storage/private.txt','generated_private_data');file_put_contents($source.'/config/config.php','<?php /* generated_secret_marker */ return [];');
    $configHash=hash_file('sha256',$source.'/config/config.php');
    foreach(['stable'=>['2.3.4','v2.3.4'],'beta'=>['2.3.5-beta.1','2.3.5-beta.1'],'nightly'=>['nightly-20261004','nightly-20261004'],'custom'=>['custom/test','custom/test']] as $channel=>[$version,$tag]){
        file_put_contents($source.'/VERSION',$version);$destination=$temporary.'/'.$channel;[$exit,$out,$err]=$run($tag,$destination);
        $check($exit===0&&$err==='','real CLI prepares '.$channel.' package');$metadata=json_decode($out,true,flags:JSON_THROW_ON_ERROR);
        $check($metadata['tag']===$tag&&$metadata['version']===$version&&$metadata['asset']==='search-startpage.tar','tag and version contract '.$channel);
        $check($metadata['sha256']===hash_file('sha256',$destination.'/search-startpage.tar')&&$metadata['bytes']===filesize($destination.'/search-startpage.tar')&&file_get_contents($destination.'/release.json')===$out&&file_get_contents($destination.'/search-startpage.tar.sha256')===$metadata['sha256']."  search-startpage.tar\n",'published sidecar matches actual archive '.$channel);
        $check(!is_dir($destination.'/stage')&&(fileperms($destination)&0777)===0700&&(fileperms($destination.'/search-startpage.tar')&0777)===0600,'private output and stage cleanup '.$channel);
        $archive=file_get_contents($destination.'/search-startpage.tar');$check(!str_contains($archive,'generated_secret_marker')&&!str_contains($archive,'generated_private_data'),'private source excluded '.$channel);
    }
    [$exit,$out,$err]=$run('different-tag',$temporary.'/mismatch');$check($exit===1&&$out===''&&$err==="INVALID_UPDATE_PACKAGE\n"&&!file_exists($temporary.'/mismatch'),'wrong tag produces no output');
    [$exit,$out,$err]=$run('custom/test',$temporary.'/custom');$check($exit===1&&$out===''&&$err==="UPDATE_PACKAGE_EXISTS\n",'existing release is never overwritten');
    [$exit,$out,$err]=$run('custom/test',$source.'/output');$check($exit===1&&$out===''&&$err==="INVALID_UPDATE_PATH\n"&&!file_exists($source.'/output'),'output inside source rejected');
    symlink($temporary,$temporary.'/linked');[$exit,$out,$err]=$run('custom/test',$temporary.'/linked/output');$check($exit===1&&$err==="INVALID_UPDATE_PATH\n",'linked output parent rejected');unlink($temporary.'/linked');
    file_put_contents($source.'/app/generated-invalid.php','<?php this is not valid syntax;');[$exit,$out,$err]=$run('custom/test',$temporary.'/invalid');$check($exit===1&&$out===''&&!file_exists($temporary.'/invalid')&&!str_contains($err,'Stack trace'),'invalid candidate leaves no distributable files');
    $check(hash_file('sha256',$source.'/config/config.php')===$configHash&&file_get_contents($source.'/storage/private.txt')==='generated_private_data','preparation never changes private configuration or data');
    echo "$count release preparation checks passed.\n";
}finally{$remove($temporary);}

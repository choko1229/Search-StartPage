<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=sys_get_temp_dir().'/update-execution-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/bin');mkdir($root.'/storage');
copy(dirname(__DIR__).'/bin/update-execution-worker.php',$root.'/bin/update-execution-worker.php');$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$run=static function(array $arguments=[],?array $environment=null)use($root):array{
    $p=proc_open([PHP_BINARY,$root.'/bin/update-execution-worker.php',...$arguments],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$environment);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
};
$remove=function(string $path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
try{
    file_put_contents($root.'/bin/run-update.php','<?php file_put_contents(__DIR__."/../first", "yes");file_put_contents(__FILE__,\'<?php file_put_contents(__DIR__."/../second","yes");\');echo str_repeat("private-value",100000);fwrite(STDERR,"private-value");');
    [$code,$out,$err]=$run(['--cycles=2']);$rows=array_map(fn($line)=>json_decode($line,true,flags:JSON_THROW_ON_ERROR),explode("\n",trim($out)));
    $check($code===0&&count($rows)===2,'two scheduled operations finish');
    $check(file_exists($root.'/first')&&file_exists($root.'/second'),'next child loads replaced PHP source');
    $check(!str_contains($out.$err,'private-value')&&strlen($out)<512,'large child output drained without publishing diagnostics');
    $check((fileperms($root.'/storage/update-execution-worker.lock')&0077)===0,'private singleton lock');
    $held=fopen($root.'/storage/update-execution-worker.lock','c');flock($held,LOCK_EX);
    [$code,$out,$err]=$run(['--cycles=1']);$check($code===1&&$out===''&&$err==="Update execution worker unavailable.\n",'concurrent supervisor refused');flock($held,LOCK_UN);fclose($held);
    file_put_contents($root.'/bin/run-update.php','<?php fwrite(STDERR,"private-value");exit(7);');
    [$code,$out,$err]=$run(['--cycles=1']);$check($code===1&&json_decode(trim($out),true)['status']==='failed'&&!str_contains($out.$err,'private-value'),'failed child reported using fixed status');
    unlink($root.'/bin/run-update.php');[$code,$out]=$run(['--cycles=1']);$check($code===1&&json_decode(trim($out),true)['status']==='failed','missing entry fails safely');
    foreach([['--cycles=0'],['--cycles=1','extra'],['--other']] as $args){[$code,$out]=$run($args);$check($code===1&&$out==='','invalid options refused');}
    [$code,$out]=$run(['--cycles=1'],['SEARCH_TEST_MODE'=>'0']);$check($code===1&&$out==='','bounded cycles forbidden outside tests');
    unlink($root.'/storage/update-execution-worker.lock');file_put_contents($root.'/outside','preserve');symlink($root.'/outside',$root.'/storage/update-execution-worker.lock');[$code,$out]=$run(['--cycles=1']);
    $check($code===1&&$out===''&&file_get_contents($root.'/outside')==='preserve','symlink lock never follows target');
    echo "$count execution worker checks passed.\n";
}finally{$remove($root);}

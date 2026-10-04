<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=sys_get_temp_dir().'/update-execution-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/bin');mkdir($root.'/storage');
copy(dirname(__DIR__).'/bin/update-execution-worker.php',$root.'/bin/update-execution-worker.php');$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$run=static function(array $arguments=[],?array $environment=null)use($root):array{
    $p=proc_open([PHP_BINARY,$root.'/bin/update-execution-worker.php',...$arguments],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$environment);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];
};
$daemon=null;$daemonPipes=[];
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
    unlink($root.'/storage/update-execution-worker.lock');
    file_put_contents($root.'/bin/run-update.php','<?php file_put_contents(__DIR__."/../started","yes");usleep(1500000);file_put_contents(__DIR__."/../completed","yes");');
    $daemon=proc_open([PHP_BINARY,$root.'/bin/update-execution-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$daemonPipes,$root);fclose($daemonPipes[0]);unset($daemonPipes[0]);
    $until=microtime(true)+5;while(!is_file($root.'/started')){if(microtime(true)>=$until)throw new RuntimeException('Daemon not ready');usleep(10000);}
    $started=hrtime(true);[$code,$out,$err]=$run(['--stop']);$elapsed=(hrtime(true)-$started)/1e9;
    $check($code===0&&json_decode(trim($out),true)['status']==='stopped'&&$err==='','stop command waits for running instance');
    $check($elapsed>1&&is_file($root.'/completed'),'stop drains child instead of terminating it');
    $out=stream_get_contents($daemonPipes[1]);$err=stream_get_contents($daemonPipes[2]);foreach($daemonPipes as $pipe)fclose($pipe);$daemonPipes=[];$code=proc_close($daemon);$daemon=null;
    $rows=array_map(fn($line)=>json_decode($line,true,flags:JSON_THROW_ON_ERROR),explode("\n",trim($out)));
    $check($code===0&&$err===''&&array_column($rows,'status')===['finished','stopped'],'daemon stops after one completed child');
    $check(!file_exists($root.'/storage/update-execution-worker.stop')&&filesize($root.'/storage/update-execution-worker.lock')===0,'stop marker and instance identity cleaned');
    file_put_contents($root.'/bin/run-update.php','<?php echo "idle";');
    $daemon=proc_open([PHP_BINARY,$root.'/bin/update-execution-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$daemonPipes,$root);fclose($daemonPipes[0]);unset($daemonPipes[0]);$first=fgets($daemonPipes[1]);
    $check(json_decode(trim($first),true)['status']==='finished','daemon runs without test cycles after restart');
    $started=hrtime(true);[$code,$out]=$run(['--stop']);$check($code===0&&(hrtime(true)-$started)/1e9<2,'idle stop interrupts polling delay');
    $out=stream_get_contents($daemonPipes[1]);$err=stream_get_contents($daemonPipes[2]);foreach($daemonPipes as $pipe)fclose($pipe);$daemonPipes=[];$code=proc_close($daemon);$daemon=null;
    $check($code===0&&$err===''&&json_decode(trim($out),true)['status']==='stopped','idle daemon launches no additional child');
    file_put_contents($root.'/storage/update-execution-worker.stop',str_repeat('a',32));chmod($root.'/storage/update-execution-worker.stop',0600);[$code]=$run(['--cycles=1']);$check($code===0&&!file_exists($root.'/storage/update-execution-worker.stop'),'explicit restart clears a valid previous instance stop');
    [$code,$out]=$run(['--stop']);$check($code===0&&json_decode(trim($out),true)['status']==='stopped','stop is idempotent without active worker');
    file_put_contents($root.'/bin/run-update.php','<?php file_put_contents(__DIR__."/../storage/update-execution-worker.stop","corrupt");chmod(__DIR__."/../storage/update-execution-worker.stop",0600);');
    [$code,$out,$err]=$run(['--cycles=1']);$check($code===1&&$err==="Update execution worker unavailable.\n",'control corruption after successful child makes supervisor fail');[$code]=$run(['--stop']);$check($code===1,'corrupt control refused even without active daemon');unlink($root.'/storage/update-execution-worker.stop');
    symlink($root.'/outside',$root.'/storage/update-execution-worker.stop');[$code]=$run(['--cycles=1']);$check($code===1&&file_get_contents($root.'/outside')==='preserve','symlink stop control refused without changing target');[$code]=$run(['--stop']);$check($code===1&&file_get_contents($root.'/outside')==='preserve','inactive stop never follows symlink control');
    echo "$count execution worker checks passed.\n";
}finally{if(is_resource($daemon)){$run(['--stop']);foreach($daemonPipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($daemon);}$remove($root);}

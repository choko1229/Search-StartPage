<?php
declare(strict_types=1);
$leakProbe=($argv[2]??null)==='--leak-probe';
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||__DIR__!=='/tmp/worker-soak-source/tests'
    ||!is_file('/tmp/update-worker-soak-only')||!(count($argv)===2||count($argv)===3&&$leakProbe)||!ctype_digit($argv[1])||(int)$argv[1]<60||(int)$argv[1]>3600)exit(1);
$seconds=(int)$argv[1];$root=sys_get_temp_dir().'/worker-soak-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/bin',0700);mkdir($root.'/storage',0700);
copy(dirname(__DIR__).'/bin/update-execution-worker.php',$root.'/bin/update-execution-worker.php');$daemon=null;$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";flush();};
$remove=static function(string $path)use(&$remove):void{if(is_file($path)||is_link($path)){unlink($path);return;}foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);};
$runner=static function(int $revision)use($root):void{
    $code='<?php $s=__DIR__."/../storage";usleep(100000);$n=is_file($s."/cycles")?(int)file_get_contents($s."/cycles"):0;file_put_contents($s."/cycles",(string)($n+1));file_put_contents($s."/revision",'.var_export((string)$revision,true).');echo "generated-private-soak-output";';
    $temporary=$root.'/bin/generated-next.php';file_put_contents($temporary,$code);rename($temporary,$root.'/bin/run-update.php');
};
$stop=static function()use($root):void{
    $p=proc_open([PHP_BINARY,$root.'/bin/update-execution-worker.php','--stop'],[0=>['file','/dev/null','r'],1=>['file',$root.'/stop-out','w'],2=>['file',$root.'/stop-err','w']],$pipes);
    if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Soak stop failed');
};
try{
    if($leakProbe){
        // Deliberately retain one descriptor per cycle in this disposable copy only.
        $worker=file_get_contents($root.'/bin/update-execution-worker.php');$needle='for($cycle=0;$cycles===0||$cycle<$cycles;$cycle++){';
        $worker=str_replace($needle,'$generatedLeaks=[];'.$needle.'$generatedLeaks[]=fopen($storage."/generated-leak","a");',$worker,$injections);
        if($injections!==1)throw new RuntimeException('Leak fixture injection unavailable');file_put_contents($root.'/bin/update-execution-worker.php',$worker);
    }
    $runner(1);$daemon=proc_open([PHP_BINARY,$root.'/bin/update-execution-worker.php'],[0=>['file','/dev/null','r'],1=>['file',$root.'/worker-out','w'],2=>['file',$root.'/worker-err','w']],$pipes);
    if(!is_resource($daemon))throw new RuntimeException('Soak worker unavailable');$state=proc_get_status($daemon);$pid=$state['pid'];$started=microtime(true);$deadline=$started+$seconds;
    $baseline=null;$maximumRss=0;$maximumFds=0;$samples=0;$changed=false;$nextReport=$started+60;
    $sampleDelay=getenv('SEARCH_SOAK_FAST')==='1'?1000:250000;$windowStart=null;$windowMin=PHP_INT_MAX;$windowMax=0;$maximumKinds=[];$windowFloors=[];
    while(microtime(true)<$deadline){
        $state=proc_get_status($daemon);if(!$state['running']||$state['pid']!==$pid)throw new RuntimeException('Soak supervisor exited unexpectedly');
        $status=file_get_contents('/proc/'.$pid.'/status');if(!preg_match('/^VmRSS:\s+(\d+)\s+kB$/m',$status,$rss))throw new RuntimeException('Soak memory observation unavailable');
        $fds=scandir('/proc/'.$pid.'/fd');if($fds===false)throw new RuntimeException('Soak descriptor observation unavailable');$fdCount=count($fds)-2;
        $cycles=is_file($root.'/storage/cycles')?(int)file_get_contents($root.'/storage/cycles'):0;
        if($cycles>=3){
            if($baseline===null){$baseline=[(int)$rss[1],$fdCount];$windowStart=microtime(true);}
            $maximumRss=max($maximumRss,(int)$rss[1]);
            if($fdCount>$maximumFds){$maximumFds=$fdCount;$maximumKinds=[];foreach($fds as $fd)if($fd!=='.'&&$fd!=='..'){
                $target=@readlink('/proc/'.$pid.'/fd/'.$fd);$kind=$target===false?'vanished':(str_starts_with($target,'pipe:')?'pipe':(str_starts_with($target,'socket:')?'socket':(str_starts_with($target,'anon_inode:')?'anonymous':'file')));
                $maximumKinds[$kind]=($maximumKinds[$kind]??0)+1;
            }}
            ++$samples;$windowMin=min($windowMin,$fdCount);$windowMax=max($windowMax,$fdCount);
            if(microtime(true)>=$windowStart+10){$windowFloors[]=$windowMin;if(getenv('SEARCH_SOAK_FAST')==='1'||$leakProbe){echo 'SOAK_FD_WINDOW min='.$windowMin.' max='.$windowMax."\n";flush();}$windowStart=microtime(true);$windowMin=PHP_INT_MAX;$windowMax=0;}
        }
        if(!$changed&&microtime(true)>=$started+$seconds/2){$runner(2);$changed=true;}
        if(microtime(true)>=$nextReport){echo 'SOAK_PROGRESS elapsed='.(int)(microtime(true)-$started).' cycles='.$cycles."\n";flush();$nextReport+=60;}
        usleep($sampleDelay);
    }
    $check(proc_get_status($daemon)['running']&&$samples>0,'same supervisor survives bounded soak');
    $check($baseline!==null&&$maximumRss<=$baseline[0]+8192,'resident memory stays within 8 MiB of warmed baseline');
    echo 'SOAK_FD_OBSERVATION baseline='.($baseline[1]??-1).' maximum='.$maximumFds.' kinds='.json_encode($maximumKinds,JSON_THROW_ON_ERROR)."\n";flush();
    // Each ten-second window spans complete generated children and the intervening idle time.
    // A retained descriptor raises the floor; proc_open's temporary pipe peak does not.
    $stableFloor=count($windowFloors)>=2&&count(array_unique($windowFloors))===1;
    echo 'SOAK_FD_FLOORS '.json_encode($windowFloors,JSON_THROW_ON_ERROR)."\n";
    $check($leakProbe?!$stableFloor:$stableFloor,$leakProbe?'retained descriptor growth is detected':'released descriptor floor stays constant across cycles');
    $check($cycles>=(int)floor($seconds/6),'scheduled children continue throughout soak');
    $check(file_get_contents($root.'/storage/revision')==='2','fresh child loads replaced source during soak');
    $stop();$exit=proc_close($daemon);$daemon=null;
    $check($exit===0&&!is_file($root.'/storage/update-execution-worker.stop')&&filesize($root.'/storage/update-execution-worker.lock')===0,'soaked worker stops and clears singleton state');
    $out=file_get_contents($root.'/worker-out');$err=file_get_contents($root.'/worker-err');
    $rows=array_map(static fn($line)=>json_decode($line,true,flags:JSON_THROW_ON_ERROR),explode("\n",trim($out)));
    $check($err===''&&!str_contains($out,'generated-private-soak-output')&&end($rows)['status']==='stopped'
        &&count(array_filter($rows,static fn($row)=>$row['status']==='failed'))===0,'soak reports no failed operations or private child output');
    echo ($leakProbe?'SOAK_NEGATIVE_PASSED':'SOAK_PASSED').' seconds='.$seconds.' cycles='.$cycles.' samples='.$samples.' max_rss_kib='.$maximumRss.' max_fds='.$maximumFds.' checks='.$count."\n";
}finally{if(is_resource($daemon)){$stop();proc_close($daemon);}$remove($root);}

<?php
declare(strict_types=1);
// This supervisor deliberately loads no application classes. Each operation starts fresh PHP.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$lock=null;$exit=1;
try{
    $cycles=0;
    if(count($argv)>2||isset($argv[1])&&(getenv('SEARCH_TEST_MODE')!=='1'||!preg_match('/^--cycles=([1-9][0-9]{0,3})$/D',$argv[1],$match)))throw new RuntimeException();
    if(isset($argv[1]))$cycles=(int)$match[1];
    $storage=$root.'/storage';$path=$storage.'/update-execution-worker.lock';
    if(realpath($root)!==$root||is_link($storage)||!is_dir($storage)||is_link($path)||file_exists($path)&&!is_file($path))throw new RuntimeException();
    $lock=fopen($path,'c');
    if($lock===false||!chmod($path,0600)||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException();
    for($cycle=0;$cycles===0||$cycle<$cycles;$cycle++){
        $script=$root.'/bin/run-update.php';$process=null;$pipes=[];$code=1;
        try{
            if(is_link($root.'/bin')||realpath($root.'/bin')!==$root.'/bin'||is_link($script)||!is_file($script))throw new RuntimeException();
            $process=proc_open([PHP_BINARY,'-d','display_errors=0','-d','log_errors=0',$script],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,null,['bypass_shell'=>true]);
            if(!is_resource($process))throw new RuntimeException();
            fclose($pipes[0]);unset($pipes[0]);
            foreach($pipes as $pipe)if(!stream_set_blocking($pipe,false))throw new RuntimeException();
            // Drain output without storing or publishing diagnostics or secrets. Never time out
            // and kill a process midway through file/DB replacement or recovery.
            do{
                foreach($pipes as $pipe)if(stream_get_contents($pipe,8192)===false)throw new RuntimeException();
                $status=proc_get_status($process);if($status===false)throw new RuntimeException();
                if(!$status['running']){$code=$status['exitcode'];break;}
                usleep(10000);
            }while(true);
        }catch(Throwable){$code=1;}
        finally{
            // Even an output failure must wait for the child instead of aborting an update.
            if(is_resource($process))do{
                foreach($pipes as $pipe)if(is_resource($pipe)){stream_set_blocking($pipe,false);stream_get_contents($pipe,8192);}
                $remaining=proc_get_status($process);if($remaining===false||!$remaining['running'])break;
                usleep(10000);
            }while(true);
            foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);
            if(is_resource($process)){$closed=proc_close($process);if($code<0)$code=$closed;}
        }
        $exit=$code===0?0:1;
        echo json_encode(['at'=>gmdate(DATE_ATOM),'status'=>$exit===0?'finished':'failed'],JSON_THROW_ON_ERROR)."\n";flush();
        if($cycles===0||$cycle+1<$cycles)sleep($exit===0?5:30);
    }
}catch(Throwable){fwrite(STDERR,"Update execution worker unavailable.\n");}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
exit($exit);

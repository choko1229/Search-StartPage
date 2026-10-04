<?php
declare(strict_types=1);
// This supervisor deliberately loads no application classes. Each operation starts fresh PHP.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$lock=null;$exit=1;$instance=null;$stopping=false;
try{
    $cycles=0;$stop=($argv[1]??null)==='--stop';
    if(count($argv)>2||isset($argv[1])&&!$stop&&(getenv('SEARCH_TEST_MODE')!=='1'||!preg_match('/^--cycles=([1-9][0-9]{0,3})$/D',$argv[1],$match)))throw new RuntimeException();
    if(isset($argv[1])&&!$stop)$cycles=(int)$match[1];
    $storage=$root.'/storage';$path=$storage.'/update-execution-worker.lock';
    if(realpath($root)!==$root||is_link($storage)||!is_dir($storage)||is_link($path)||file_exists($path)&&!is_file($path))throw new RuntimeException();
    $stopPath=$storage.'/update-execution-worker.stop';
    $readStop=static function()use($stopPath):?string{
        clearstatcache(true,$stopPath);if(is_link($stopPath)||file_exists($stopPath)&&!is_file($stopPath))throw new RuntimeException();
        if(!file_exists($stopPath))return null;
        if(filesize($stopPath)!==32||(fileperms($stopPath)&0077)!==0)throw new RuntimeException();
        $value=file_get_contents($stopPath);if(!is_string($value)||!preg_match('/^[a-f0-9]{32}$/D',$value))throw new RuntimeException();return $value;
    };
    if($stop){
        $readStop();
        if(file_exists($path)){
            $lock=fopen($path,'r+');if($lock===false)throw new RuntimeException();
            $deadline=microtime(true)+2;$target=null;
            do{
                if(flock($lock,LOCK_EX|LOCK_NB))break;
                rewind($lock);$value=stream_get_contents($lock,33);
                if(is_string($value)&&preg_match('/^[a-f0-9]{32}$/D',$value)){$target=$value;break;}
                if(microtime(true)>=$deadline)throw new RuntimeException();usleep(10000);
            }while(true);
            if($target!==null){
                $readStop();$temporary=tempnam($storage,'.worker-stop-');if($temporary===false)throw new RuntimeException();
                try{if(!chmod($temporary,0600)||file_put_contents($temporary,$target)!==32||!rename($temporary,$stopPath))throw new RuntimeException();}
                finally{if(is_file($temporary))unlink($temporary);}
                // Wait for this instance to drain its child and release the singleton lock.
                do{
                    if(flock($lock,LOCK_EX|LOCK_NB))break;
                    rewind($lock);$value=stream_get_contents($lock,33);
                    if(is_string($value)&&preg_match('/^[a-f0-9]{32}$/D',$value)&&$value!==$target)break;
                    usleep(100000);
                }while(true);
            }
        }
        echo json_encode(['status'=>'stopped'],JSON_THROW_ON_ERROR)."\n";$exit=0;
    }else{
    $lock=fopen($path,'c');
    if($lock===false||!chmod($path,0600)||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException();
    $stale=$readStop();if($stale!==null&&!unlink($stopPath))throw new RuntimeException();
    $instance=bin2hex(random_bytes(16));if(!ftruncate($lock,0)||fwrite($lock,$instance)!==32||!fflush($lock))throw new RuntimeException();
    for($cycle=0;$cycles===0||$cycle<$cycles;$cycle++){
        if($readStop()===$instance){$stopping=true;break;}
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
        if($readStop()===$instance){$stopping=true;break;}
        if($cycles===0||$cycle+1<$cycles){
            $until=microtime(true)+($exit===0?5:30);
            do{if($readStop()===$instance){$stopping=true;break;}usleep(100000);}while(microtime(true)<$until);
            if($stopping)break;
        }
    }
    if($stopping){$exit=0;echo json_encode(['status'=>'stopped'],JSON_THROW_ON_ERROR)."\n";}
    }
}catch(Throwable){$exit=1;fwrite(STDERR,"Update execution worker unavailable.\n");}
finally{if(is_resource($lock)){if($instance!==null){try{if($readStop()===$instance)unlink($stopPath);}catch(Throwable){}ftruncate($lock,0);fflush($lock);}flock($lock,LOCK_UN);fclose($lock);}}
exit($exit);

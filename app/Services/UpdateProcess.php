<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Child output is internal diagnostic data and must never be forwarded to a public response. */
final class UpdateProcess
{
    public function lint(string $file,int $timeout=10): void
    {
        $file=$this->file($file);
        $this->run([PHP_BINARY,'-n','-d','display_errors=0','-d','log_errors=0','-d','memory_limit=128M','-l',$file],dirname($file),$timeout);
    }
    public function script(string $file,array $arguments=[],int $timeout=120): string
    {
        $file=$this->file($file);
        if(!array_is_list($arguments))throw new HttpException(422,'INVALID_UPDATE_PROCESS');
        foreach($arguments as $argument)if(!is_string($argument)||str_contains($argument,"\0")||strlen($argument)>4096)throw new HttpException(422,'INVALID_UPDATE_PROCESS');
        return $this->run([PHP_BINARY,'-d','display_errors=0','-d','log_errors=0',$file,...$arguments],dirname($file),$timeout);
    }
    private function file(string $file): string
    {
        UpdatePackagePaths::directory(dirname($file));
        if(is_link($file)||!is_file($file))throw new HttpException(422,'INVALID_UPDATE_PATH');
        $resolved=realpath($file);if($resolved===false)throw new HttpException(422,'INVALID_UPDATE_PATH');return $resolved;
    }
    private function run(array $command,string $directory,int $timeout): string
    {
        if(PHP_SAPI!=='cli')throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
        if($timeout<1||$timeout>300)throw new HttpException(422,'INVALID_UPDATE_PROCESS');
        $process=@proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$directory,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
        $output='';$bytes=0;$exit=null;$started=hrtime(true);
        try{
            fclose($pipes[0]);unset($pipes[0]);
            foreach($pipes as $pipe)if(!stream_set_blocking($pipe,false))throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
            do{
                foreach($pipes as $number=>$pipe){
                    $chunk=stream_get_contents($pipe,8192);if($chunk===false)throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
                    $bytes+=strlen($chunk);if($bytes>65536)throw new HttpException(422,'UPDATE_PROCESS_OUTPUT_LIMIT');
                    if($number===1)$output.=$chunk;
                }
                $status=proc_get_status($process);if($status===false)throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
                if(!$status['running']){
                    $exit=$status['exitcode'];
                    // Drain both pipes after process exit without risking a full pipe deadlock.
                    foreach($pipes as $number=>$pipe){$chunk=stream_get_contents($pipe);if($chunk===false)throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');$bytes+=strlen($chunk);if($bytes>65536)throw new HttpException(422,'UPDATE_PROCESS_OUTPUT_LIMIT');if($number===1)$output.=$chunk;}
                    break;
                }
                if((hrtime(true)-$started)/1000000000>=$timeout)throw new HttpException(422,'UPDATE_PROCESS_TIMEOUT');
                usleep(10000);
            }while(true);
        }finally{
            if($exit===null)proc_terminate($process,9);
            foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);
            $closed=proc_close($process);if($exit===null||$exit<0)$exit=$closed;
        }
        if($exit!==0)throw new HttpException(422,'UPDATE_PROCESS_FAILED');
        return $output;
    }
}

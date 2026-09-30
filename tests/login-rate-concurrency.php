<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Http\{Request,Response,HttpException};
use App\Middleware\LoginRateLimit;
if(($argv[1]??'')==='--worker'){
    $directory=$argv[2];$deadline=microtime(true)+10;
    while(!is_file($directory.'/start')){if(microtime(true)>$deadline)exit(3);usleep(1000);}
    try{(new LoginRateLimit($directory,5,60))(new Request('GET','/api/auth/discord',server:['REMOTE_ADDR'=>'192.0.2.10']),static fn()=>Response::json([]));exit(0);}
    catch(HttpException $e){exit($e->status===429?2:3);}
}
$directory=sys_get_temp_dir().'/search-rate-concurrent-'.bin2hex(random_bytes(8));
mkdir($directory,0700);
$processes=[];$pipes=[];
try{
    for($i=0;$i<24;$i++){
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$directory],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$workerPipes);
        if(!is_resource($process))throw new RuntimeException('Worker did not start');
        $processes[]=$process;$pipes[]=$workerPipes;
    }
    touch($directory.'/start');$allowed=0;$limited=0;
    foreach($processes as $i=>$process){
        foreach($pipes[$i] as $pipe){stream_get_contents($pipe);fclose($pipe);}
        $status=proc_close($process);$processes[$i]=null;
        if($status===0)$allowed++;elseif($status===2)$limited++;else throw new RuntimeException('Worker failure');
    }
    if($allowed!==5 || $limited!==19)throw new RuntimeException("Concurrent rate limit failed: $allowed/$limited");
    echo "PASS: 24 concurrent processes allow exactly 5 and reject 19\n";
}finally{
    foreach($processes as $process)if(is_resource($process)){proc_terminate($process);proc_close($process);}
    foreach(['start','login.json'] as $name)if(is_file($directory.'/'.$name))unlink($directory.'/'.$name);
    rmdir($directory);
}

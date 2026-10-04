<?php
declare(strict_types=1);
// Real FPM processes; deliberately replaces bootstrap only in a fresh disposable deployment.
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('TEST_FPM_CACHE_DEPLOYMENT')!=='1')exit(1);
$root=dirname(__DIR__);
if(!str_starts_with($root,'/tmp/search-fpm-cache-')||is_link($root)||!is_file($root.'/storage/web-cache-test-only')||file_exists($root.'/config/config.php'))throw new RuntimeException('Fresh isolated FPM deployment required');
require $root.'/app/Services/UpdateAccess.php';
$count=0;$processes=[];$pipes=[];
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$record=static function(int $type,string $content):string{return pack('CCnnCC',1,$type,1,strlen($content),0,0).$content;};
$length=static fn(int $n):string=>$n<128?chr($n):pack('N',$n|0x80000000);
$request=static function(int $port,string $path='/',?string $script=null,string $query='')use($root,$record,$length):array{
    $socket=stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,2);
    if($socket===false)throw new RuntimeException('FPM connection unavailable');
    stream_set_timeout($socket,5);$output='';$diagnostics='';
    try{
        $params=['SCRIPT_FILENAME'=>$script??$root.'/public/index.php','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET','REQUEST_URI'=>$path,'QUERY_STRING'=>$query,'SERVER_PROTOCOL'=>'HTTP/1.1','GATEWAY_INTERFACE'=>'CGI/1.1','SERVER_NAME'=>'localhost','SERVER_PORT'=>'80','HTTP_ACCEPT_LANGUAGE'=>'en','DOCUMENT_ROOT'=>$root.'/public'];$encoded='';
        foreach($params as $key=>$value)$encoded.=$length(strlen($key)).$length(strlen($value)).$key.$value;
        $bytes=$record(1,pack('nCxxxxx',1,0)).$record(4,$encoded).$record(4,'').$record(5,'');$offset=0;
        while($offset<strlen($bytes)){$written=fwrite($socket,substr($bytes,$offset));if(!$written)throw new RuntimeException('FastCGI write failed');$offset+=$written;}
        $read=static function(int $n)use($socket):string{$data='';while(strlen($data)<$n){$chunk=fread($socket,$n-strlen($data));if($chunk===false||$chunk==='')throw new RuntimeException('FastCGI incomplete response');$data.=$chunk;}return $data;};
        do{
            $header=unpack('Cversion/Ctype/nid/nlength/Cpadding/Creserved',$read(8));
            if($header['version']!==1||$header['id']!==1)throw new RuntimeException('Unexpected FastCGI response');
            $content=$header['length']?$read($header['length']):'';if($header['padding'])$read($header['padding']);
            if($header['type']===6)$output.=$content;if($header['type']===7)$diagnostics.=$content;
            if(strlen($output)>1048576||strlen($diagnostics)>8192)throw new RuntimeException('Oversized FastCGI response');
            if($header['type']===3){$end=unpack('Nstatus/Cprotocol',substr($content,0,5));if($end['status']!==0||$end['protocol']!==0)throw new RuntimeException('FPM application failed');}
        }while($header['type']!==3);
    }finally{fclose($socket);}
    if($diagnostics!=='')throw new RuntimeException('FPM emitted request diagnostics');
    $parts=explode("\r\n\r\n",$output,2);if(count($parts)!==2)throw new RuntimeException('Invalid CGI response');
    $status=preg_match('/(?:^|\r\n)Status: ([0-9]{3})\b/',$parts[0],$match)?(int)$match[1]:200;
    return [$status,$parts[1]];
};
$source=static fn(string $value):string=>'<?php header("Content-Type: application/json");echo json_encode(["value"=>'.var_export($value,true).',"sapi"=>PHP_SAPI,"timestamps"=>ini_get("opcache.validate_timestamps"),"cached"=>opcache_is_script_cached(__FILE__),"pid"=>getmypid()]);';
$ports=[19000,19001];$gate=new App\Services\UpdateAccess($root.'/storage/updates/access');
try{
    file_put_contents($root.'/app/bootstrap.php',$source('before'));
    foreach($ports as $index=>$port){
        $config=$root.'/storage/fpm-'.$index.'.conf';
        file_put_contents($config,"[global]\nerror_log = $root/storage/fpm-$index.log\ndaemonize = no\n[isolated]\nlisten = 127.0.0.1:$port\nuser = www-data\ngroup = www-data\npm = static\npm.max_children = 2\nclear_env = yes\nsecurity.limit_extensions = .php\n");
        $process=proc_open(['php-fpm','-F','-y',$config,'-d','opcache.enable=1','-d','opcache.validate_timestamps=0','-d','opcache.file_update_protection=0','-d','display_errors=0'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$streams,$root);
        if(!is_resource($process))throw new RuntimeException('FPM did not start');$processes[]=$process;fclose($streams[0]);stream_set_blocking($streams[1],false);stream_set_blocking($streams[2],false);$pipes[]=$streams;
        $deadline=microtime(true)+10;
        do{$ready=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,0.1);if($ready!==false){fclose($ready);break;}if(!proc_get_status($process)['running']||microtime(true)>$deadline)throw new RuntimeException('FPM startup failed');usleep(10000);}while(true);
        [$status,$body]=$request($port);$data=json_decode($body,true,flags:JSON_THROW_ON_ERROR);
        $check($status===200&&$data['value']==='before'&&$data['sapi']==='fpm-fcgi'&&$data['timestamps']==='0'&&$data['cached'],'independent FPM '.$index.' caches initial PHP with timestamps disabled');
    }
    file_put_contents($root.'/app/bootstrap.php',$source('after!'));
    foreach($ports as $index=>$port){[$status,$body]=$request($port);$check($status===200&&json_decode($body,true)['value']==='before','FPM '.$index.' retains stale PHP before generation change');}
    $gate->exclusive(function()use($ports,$request,$check):void{foreach($ports as $index=>$port){[$status,$body]=$request($port,'/api/admin/update');$check($status===503&&json_decode($body,true)['error']['code']==='UPDATE_IN_PROGRESS','FPM '.$index.' blocks API before bootstrap during exclusive update');}});
    $generation=$gate->generation();$marker=$root.'/storage/updates/access/web-cache-'.$generation.'.php';
    [$status,$body]=$request($ports[0]);$check($status===200&&json_decode($body,true)['value']==='after!','first FPM cache refreshes after generation change');
    $check(is_file($marker)&&(fileperms($marker)&0077)===0,'first FPM publishes private generation marker');
    $probe=$root.'/public/cache-test-probe.php';
    file_put_contents($probe,'<?php header("Content-Type: application/json");echo json_encode(["cached"=>opcache_is_script_cached('.var_export($marker,true).')]);');
    [$status,$body]=$request($ports[0],'/cache-test-probe.php',$probe);$check($status===200&&json_decode($body,true)['cached']===true,'first master acknowledges generation in its OPcache');
    [$status,$body]=$request($ports[1],'/cache-test-probe.php',$probe);$check($status===200&&json_decode($body,true)['cached']===false,'second master has independent cache despite shared marker on disk');
    [$status,$body]=$request($ports[1]);$check($status===200&&json_decode($body,true)['value']==='after!','second FPM independently refreshes from shared generation');
    [$status,$body]=$request($ports[1],'/cache-test-probe.php',$probe);$check($status===200&&json_decode($body,true)['cached']===true,'second master now acknowledges its own cache');
    foreach($ports as $index=>$port){$pids=[];for($i=0;$i<12;$i++){[$status,$body]=$request($port);$data=json_decode($body,true);if($status!==200||$data['value']!=='after!')throw new RuntimeException('Stale FPM child');$pids[$data['pid']]=true;}$check(count($pids)===2,'both children of FPM '.$index.' serve refreshed code');}
    file_put_contents($marker,'corrupt');
    foreach($ports as $index=>$port){[$status,$body]=$request($port,'/api/admin/update');$check($status===503&&json_decode($body,true)['error']['code']==='UPDATE_IN_PROGRESS'&&!str_contains($body,'Warning'),'FPM '.$index.' rejects corrupt marker without request diagnostics');}
    unlink($marker);
    foreach($ports as $index=>$port){[$status,$body]=$request($port);$check($status===200&&json_decode($body,true)['value']==='after!','FPM '.$index.' recovers after marker repair');}
    $gate->exclusive(function()use($root,$source):void{file_put_contents($root.'/app/bootstrap.php',$source('before'));});
    foreach($ports as $index=>$port){[$status,$body]=$request($port);$check($status===200&&json_decode($body,true)['value']==='before','FPM '.$index.' refreshes previous PHP after rollback generation');}
    $check($gate->generation()!==$generation&&!file_exists($marker),'rollback creates distinct generation and cleans previous marker');
    $check(!file_exists($root.'/config/config.php'),'FPM fixture never reads real configuration or connects to DB');
    echo "$count two-master FPM cache checks passed.\n";
}finally{
    foreach($processes as $index=>$process){proc_terminate($process);foreach($pipes[$index] as $stream)if(is_resource($stream))fclose($stream);proc_close($process);}
}

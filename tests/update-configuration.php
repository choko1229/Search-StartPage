<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
$root=dirname(__DIR__);$directory=sys_get_temp_dir().'/update-config-'.bin2hex(random_bytes(8));mkdir($directory,0700);mkdir($directory.'/bin',0700);mkdir($directory.'/config',0700);$count=0;
$copy=static function(string $from,string $to)use(&$copy):void{if(is_dir($from)){mkdir($to,0700);foreach(scandir($from) as $name)if($name!=='.'&&$name!=='..')$copy($from.'/'.$name,$to.'/'.$name);}else{copy($from,$to);chmod($to,0600);}};
$remove=static function(string $path)use(&$remove):void{if(is_file($path)||is_link($path)){unlink($path);return;}foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);};
$check=static function(bool $ok,string $name)use(&$count):void{if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$run=static function(string $input,array $arguments=[])use($directory):array{
    $process=proc_open([PHP_BINARY,$directory.'/bin/configure-updates.php',...$arguments],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Config process unavailable');fwrite($pipes[0],$input);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($process),$out,$err];
};
$path=$directory.'/config/config.php';
$original=['installed'=>true,'database'=>['password'=>'generated_db_private'],'discord'=>['client_secret'=>'generated_oauth_private'],'site'=>['url'=>'http://localhost'],'session'=>['secure'=>false],'updates'=>['channel'=>'custom','custom_tag'=>'keep-me','extra'=>['keep'=>true]]];
$save=static function(array $config)use($path):void{file_put_contents($path,'<?php return '.var_export($config,true).';');chmod($path,0600);};
try{
    $copy($root.'/app',$directory.'/app');copy($root.'/bin/configure-updates.php',$directory.'/bin/configure-updates.php');$save($original);
    $input=json_encode(['repository'=>'owner/private-repo','token'=>'generated_test_token'],JSON_THROW_ON_ERROR);[$exit,$out,$err]=$run($input);$updated=require $path;
    $check($exit===0&&$out==="Update source configuration saved.\n"&&$err==='','real CLI accepts secret through stdin without printing it');
    $check($updated===array_replace($original,['updates'=>array_replace($original['updates'],['repository'=>'owner/private-repo','token'=>'generated_test_token'])]),'only repository and token change; other configuration retained');
    $check((fileperms($path)&0777)===0600&&glob($directory.'/config/.config-*')===[],'private atomic replacement leaves no temporary secret files');
    [$exit,$out,$err]=$run(json_encode(['repository'=>'owner/public-repo','token'=>'']));$check($exit===0&&(require $path)['updates']['token']==='','empty token explicitly supports public source');
    foreach(['{broken',json_encode(['repository'=>'../escape','token'=>'generated_test_token']),json_encode(['repository'=>'owner/repo','token'=>"secret\nheader"]),json_encode(['repository'=>'owner/repo']),json_encode(['repository'=>'owner/repo','token'=>'x','installed'=>false]),str_repeat('x',4097)] as $invalid){
        $before=hash_file('sha256',$path);[$exit,$out,$err]=$run($invalid);
        $check($exit===1&&$out===''&&$err==="UPDATE_CONFIGURATION_FAILED\n"&&hash_file('sha256',$path)===$before,'invalid input rejected privately without changing configuration');
    }
    [$exit,$out,$err]=$run($input,['unexpected']);$check($exit===1&&$out===''&&$err==="UPDATE_CONFIGURATION_FAILED\n",'unexpected CLI arguments rejected');
    $save(array_replace($original,['installed'=>false]));$before=hash_file('sha256',$path);[$exit,$out,$err]=$run($input);$check($exit===1&&hash_file('sha256',$path)===$before,'uninstalled configuration never configured');
    $save($original);chmod($path,0644);$before=hash_file('sha256',$path);[$exit,$out,$err]=$run($input);$check($exit===1&&hash_file('sha256',$path)===$before,'public-readable secret configuration rejected');chmod($path,0600);
    rename($path,$directory.'/kept.php');symlink($directory.'/kept.php',$path);$before=hash_file('sha256',$directory.'/kept.php');[$exit,$out,$err]=$run($input);$check($exit===1&&hash_file('sha256',$directory.'/kept.php')===$before,'linked configuration does not modify target');unlink($path);rename($directory.'/kept.php',$path);
    file_put_contents($path,'<?php echo "generated_secret_output"; return '.var_export($original,true).';');$before=hash_file('sha256',$path);[$exit,$out,$err]=$run($input);$check($exit===1&&$out===''&&!str_contains($err,'generated_secret_output')&&hash_file('sha256',$path)===$before,'unexpected config output is suppressed and write refused');
    echo "$count update configuration checks passed.\n";
}finally{$remove($directory);}

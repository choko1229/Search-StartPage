<?php
declare(strict_types=1);
// Executes only inside the networkless disposable VM, never on a normal deployment.
$root='/srv/search-startpage';$storage=$root.'/storage';$unit='search-update-execution.service';
if(PHP_SAPI!=='cli'||dirname(__DIR__)!==$root||!is_file('/etc/search-systemd-vm-test-only')||trim(file_get_contents('/proc/1/comm'))!=='systemd')exit(1);
$count=0;
$command=static function(array $args,bool $required=true):array{
 $p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('VM command unavailable');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($required&&$exit!==0)throw new RuntimeException('VM command failed');return [$exit,trim($out),$err];
};
$property=static fn(string $name):string=>$command(['systemctl','show',$unit,'--property='.$name,'--value'])[1];
$check=static function(bool $ok,string $label)use(&$count):void{if(!$ok)throw new RuntimeException($label);++$count;echo "PASS: $label\n";};
$wait=static function(callable $ready,int $seconds=30):void{$until=microtime(true)+$seconds;while(!$ready()){if(microtime(true)>$until)throw new RuntimeException('VM observation timeout');usleep(100000);}};
try{
 $check(version_compare(PHP_VERSION,'8.3.0','>='),'VM uses PHP 8.3 or newer');
 if(is_file($storage.'/verify-second-boot')){
  $wait(fn()=>$property('ActiveState')==='active'&&(int)$property('MainPID')>0);
  $check($command(['systemctl','is-enabled',$unit])[1]==='enabled','enabled worker starts on second VM boot');
  $wait(fn()=>is_file($storage.'/completed'));
  $check($property('User')==='www-data'&&$property('NoNewPrivileges')==='yes','booted service retains user and privilege restrictions');
  $command(['systemctl','stop',$unit]);
  $check($property('ActiveState')==='inactive'&&$property('Result')==='success','booted worker stops through manager');
  $check(!is_file($storage.'/update-execution-worker.stop')&&filesize($storage.'/update-execution-worker.lock')===0,'booted worker clears singleton identity and stop marker');
  echo "ISOLATED_SYSTEMD_BOOT_PASSED $count\n";flush();$command(['systemctl','poweroff']);exit(0);
 }
 $runner='<?php $s=__DIR__."/../storage";file_put_contents($s."/started","yes");if(is_file($s."/slow"))usleep(8000000);file_put_contents($s."/completed","yes");echo "generated-private-child-output";';
 file_put_contents($root.'/bin/run-update.php',$runner);chmod($root.'/bin/run-update.php',0644);chown($root.'/bin/run-update.php','www-data');
 file_put_contents($storage.'/slow','yes');chown($storage.'/slow','www-data');
 $command(['systemctl','enable',$unit]);$command(['systemctl','start',$unit]);
 $wait(fn()=>is_file($storage.'/started'));
 $check($property('ActiveState')==='active'&&(int)$property('MainPID')>0,'actual manager starts shipped worker');
 $started=microtime(true);$command(['systemctl','stop',$unit]);
 $check(microtime(true)-$started>1&&is_file($storage.'/completed'),'manager ExecStop drains running child');
 $check($property('ActiveState')==='inactive'&&$property('Result')==='success','graceful manager stop succeeds');
 $check(!is_file($storage.'/update-execution-worker.stop')&&filesize($storage.'/update-execution-worker.lock')===0,'manager stop clears worker control state');
 unlink($storage.'/slow');unlink($storage.'/started');unlink($storage.'/completed');
 $command(['systemctl','start',$unit]);$wait(fn()=>is_file($storage.'/completed'));
 sleep(1);
 $firstPid=(int)$property('MainPID');$restarts=(int)$property('NRestarts');
 $command(['systemctl','kill','--kill-whom=main','--signal=SIGKILL',$unit]);
 $wait(fn()=>$property('ActiveState')==='active'&&(int)$property('MainPID')>0&&(int)$property('MainPID')!==$firstPid&&(int)$property('NRestarts')>$restarts);
 $check(true,'manager restarts failed idle worker with new PID');
 $check($property('Restart')==='on-failure','shipped restart policy remains on-failure');
 $command(['systemctl','stop',$unit]);$wait(fn()=>$property('ActiveState')==='inactive');
 $restarts=(int)$property('NRestarts');sleep(6);
 $check($property('ActiveState')==='inactive'&&(int)$property('NRestarts')===$restarts,'normal manager stop does not restart worker');
 $journal=$command(['journalctl','-u',$unit,'--no-pager','-o','cat'])[1];
 $check(!str_contains($journal,'generated-private-child-output'),'service journal excludes child private output');
 unlink($storage.'/completed');file_put_contents($storage.'/verify-second-boot','yes');
 echo "ISOLATED_SYSTEMD_FIRST_PASSED $count\n";flush();$command(['systemctl','reboot']);
}catch(Throwable $e){echo "ISOLATED_SYSTEMD_FAILED ".$e->getMessage()."\n";flush();$command(['systemctl','poweroff'],false);exit(1);}

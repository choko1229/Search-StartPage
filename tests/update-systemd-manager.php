<?php
declare(strict_types=1);
// Executes only inside the networkless disposable VM, never on a normal deployment.
$root='/srv/search-startpage';$storage=$root.'/storage';$unit='search-update-execution.service';
$commandTest=PHP_SAPI==='cli'&&__DIR__==='/seed'&&getenv('SEARCH_TEST_MODE')==='1'&&is_file('/tmp/search-systemd-vm-test-only')&&count($argv)===2&&$argv[1]==='--test-command';
if(!$commandTest&&(PHP_SAPI!=='cli'||dirname(__DIR__)!==$root||!is_file('/etc/search-systemd-vm-test-only')||trim(file_get_contents('/proc/1/comm'))!=='systemd'))exit(1);
$count=0;
$command=static function(array $args,bool $required=true):array{
 $p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('VM command unavailable');fclose($pipes[0]);unset($pipes[0]);$out='';$err='';$exit=-1;
 try{
  foreach($pipes as $pipe)stream_set_blocking($pipe,false);$deadline=microtime(true)+60;
  do{
   $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);
   if(strlen($out)>1048576||strlen($err)>1048576)throw new RuntimeException('VM command output limit');
   $status=proc_get_status($p);if(!$status['running']){$exit=$status['exitcode'];break;}
   if(microtime(true)>$deadline)throw new RuntimeException('VM command timeout');usleep(10000);
  }while(true);
  // Descendants can retain the pipes after the command exits; do not wait for their EOF.
  $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);
 }finally{
  if(proc_get_status($p)['running']){proc_terminate($p);usleep(100000);if(proc_get_status($p)['running'])proc_terminate($p,9);}
  foreach($pipes as $pipe)fclose($pipe);$closed=proc_close($p);if($exit<0)$exit=$closed;
 }
 if($required&&$exit!==0)throw new RuntimeException('VM command failed');return [$exit,trim($out),$err];
};
$property=static fn(string $name):string=>$command(['systemctl','show',$unit,'--property='.$name,'--value'])[1];
$check=static function(bool $ok,string $label)use(&$count):void{if(!$ok)throw new RuntimeException($label);++$count;echo "PASS: $label\n";};
$wait=static function(callable $ready,int $seconds=30):void{$until=microtime(true)+$seconds;while(!$ready()){if(microtime(true)>$until)throw new RuntimeException('VM observation timeout');usleep(100000);}};
if($commandTest){
 [$exit,$out,$err]=$command([PHP_BINARY,'-r','echo "generated-out";']);$check($exit===0&&$out==='generated-out'&&$err==='','command captures exit and stdout');
 $started=microtime(true);[$exit,$out,$err]=$command(['sh','-c','sleep 2 & printf generated-out; printf generated-err >&2; exit 0']);
 $check($exit===0&&$out==='generated-out'&&$err==='generated-err'&&microtime(true)-$started<1.5,'command completion does not wait for inherited descendant pipes');
 [$exit,$out,$err]=$command([PHP_BINARY,'-r','fwrite(STDERR,"generated-error");exit(7);'],false);$check($exit===7&&$out===''&&$err==='generated-error','optional failed command preserves exit and stderr');
 try{$command([PHP_BINARY,'-r','exit(7);']);throw new RuntimeException('Expected command failure');}catch(RuntimeException $e){$check($e->getMessage()==='VM command failed','required failed command is rejected');}
 echo "$count isolated command checks passed.\n";exit(0);
}
try{
 $check(version_compare(PHP_VERSION,'8.3.0','>='),'VM uses PHP 8.3 or newer');
 if(is_file($storage.'/verify-second-boot')){
  $wait(fn()=>$property('ActiveState')==='active'&&(int)$property('MainPID')>0);
  $check($command(['systemctl','is-enabled',$unit])[1]==='enabled','enabled worker starts on second VM boot');
  $wait(fn()=>is_file($storage.'/completed'));
  $check($property('User')==='www-data'&&$property('NoNewPrivileges')==='yes','booted service retains user and privilege restrictions');
  echo "VM_PROOF_STATE ".$command(['systemctl','show','search-isolated-boot-proof.service','--property=Type','--property=SubState','--property=Job'])[1]."\n";flush();
  $command(['systemctl','stop','--no-block',$unit]);
  $wait(fn()=>$property('ActiveState')==='inactive',60);
  $check($property('ActiveState')==='inactive'&&$property('Result')==='success','booted worker stops through manager');
  $check(!is_file($storage.'/update-execution-worker.stop')&&filesize($storage.'/update-execution-worker.lock')===0,'booted worker clears singleton identity and stop marker');
  echo "ISOLATED_SYSTEMD_BOOT_PASSED $count\n";flush();$command(['systemctl','poweroff','--no-block']);exit(0);
 }
 $installedGuard='/usr/local/libexec/search-startpage/stop-update-execution.sh';
 $check(is_file($installedGuard)&&!is_link($installedGuard)&&fileowner($installedGuard)===0&&filegroup($installedGuard)===0&&(fileperms($installedGuard)&0777)===0644
     &&fileowner(dirname($installedGuard))===0&&(fileperms(dirname($installedGuard))&0022)===0,'installed stop guard is root owned outside update targets');
 if(is_file($root.'/bin/systemd/stop-update-execution.sh'))unlink($root.'/bin/systemd/stop-update-execution.sh');
 $check(!is_file($root.'/bin/systemd/stop-update-execution.sh'),'live guard removal leaves installed stop guard available');
 $runner='<?php $s=__DIR__."/../storage";file_put_contents($s."/started","yes");if(is_file($s."/slow"))usleep(8000000);if(is_file($s."/fault-slow"))usleep(30000000);file_put_contents($s."/completed","yes");echo "generated-private-child-output";';
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
 // Compare the old direct stop with the shipped guard on the same generated child.
 foreach([true,false] as $legacyStop){
 $dropin='/etc/systemd/system/'.$unit.'.d';
 if($legacyStop){mkdir($dropin,0755);file_put_contents($dropin.'/generated-baseline.conf',"[Service]\nExecStop=\nExecStop=/usr/bin/php /srv/search-startpage/bin/update-execution-worker.php --stop\n");}
 else{unlink($dropin.'/generated-baseline.conf');rmdir($dropin);}
 $command(['systemctl','daemon-reload']);$command(['systemctl','reset-failed',$unit]);
 if(is_file($storage.'/started'))unlink($storage.'/started');if(is_file($storage.'/completed'))unlink($storage.'/completed');
 file_put_contents($storage.'/fault-slow','yes');chown($storage.'/fault-slow','www-data');
 $command(['systemctl','start',$unit]);$wait(fn()=>is_file($storage.'/started'));
 file_put_contents($storage.'/update-execution-worker.stop','generated-corrupt-control');chmod($storage.'/update-execution-worker.stop',0600);chown($storage.'/update-execution-worker.stop','www-data');
 $command(['systemctl','stop',$unit],false);
 $check($legacyStop?!is_file($storage.'/completed'):is_file($storage.'/completed'),$legacyStop?'baseline direct failed stop aborts generated child':'failed stop CLI does not abort active child');
 $check($property('ActiveState')==='failed'&&$property('Result')==='exit-code','failed stop remains visible to manager');
 unlink($storage.'/fault-slow');unlink($storage.'/update-execution-worker.stop');
 }
 unlink($storage.'/started');unlink($storage.'/completed');
 $command(['systemctl','reset-failed',$unit]);$command(['systemctl','start',$unit]);$wait(fn()=>is_file($storage.'/completed'));
 $command(['systemctl','stop',$unit]);
 $check($property('ActiveState')==='inactive'&&$property('Result')==='success','service recovers after explicit control repair');
 unlink($storage.'/completed');file_put_contents($storage.'/verify-second-boot','yes');
 echo "ISOLATED_SYSTEMD_FIRST_PASSED $count\n";flush();$command(['systemctl','reboot']);
}catch(Throwable $e){echo "ISOLATED_SYSTEMD_FAILED ".$e->getMessage()."\n";echo "VM_FAILURE_STATE ".$command(['systemctl','show',$unit,'--property=ActiveState','--property=SubState','--property=Job','--property=ControlPID'],false)[1]."\n";flush();$command(['systemctl','poweroff','--no-block'],false);exit(1);}

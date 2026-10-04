<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\{UpdateAccess,UpdateProcess,ReleasePackageBuilder,UpdatePackage};
use App\Database\Database;
use App\Http\HttpException;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||!in_array(getenv('TEST_BACKUP_HOST'),['search-update-backup-mysql-20261004','search-update-backup-mariadb-20261004'],true))exit(1);
$directory=sys_get_temp_dir().'/update-runtime-'.bin2hex(random_bytes(8));mkdir($directory,0700);$count=0;
$check=static function(bool $ok,string $name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$reject=static function(callable $call)use($check){try{$call();throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='UPDATE_PROCESS_FAILED','runtime failure reported safely');}};
$remove=function($path)use(&$remove){if(is_dir($path)&&!is_link($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
$root=dirname(__DIR__);$clone=$directory.'/clone';$pdo=null;
try{
    (new ReleasePackageBuilder())->build($root,$directory.'/source.tar');(new UpdatePackage())->verify($directory.'/source.tar',$clone,trim(file_get_contents($root.'/VERSION')),true);
    mkdir($clone.'/storage',0700);$settings=require $root.'/config/config.example.php';$settings['installed']=true;$settings['database']=['host'=>getenv('TEST_BACKUP_HOST'),'port'=>3306,'name'=>'update_backup','user'=>'backup','password'=>getenv('TEST_BACKUP_PASSWORD')];$settings['session']=['name'=>'search_update_runtime','secure'=>false];
    file_put_contents($clone.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($clone.'/config/config.php',0600);$configHash=hash_file('sha256',$clone.'/config/config.php');$pdo=Database::connect($settings['database']);
    $check($pdo->query('SHOW TABLES')->fetchAll()===[],'dedicated schema starts empty');
    $gate=new UpdateAccess($clone.'/storage/updates/access');$process=new UpdateProcess();$version=trim(file_get_contents($clone.'/VERSION'));$task=$clone.'/bin/update-task.php';
    $runtime=new App\Services\UpdateRuntime($clone);$result=static fn($taskName)=>$runtime->run($taskName,$version,$gate);
    $gate->exclusive(function()use($result,$check,$gate,$version,$pdo,$clone){
        $first=$result('migrate');$check($first['format']===1&&$first['task']==='migrate'&&$first['version']===$version&&$first['applied']===17&&$first['pid']!==getmypid(),'all 17 migrations in a new PHP process');
        $repeat=$result('migrate');$check($repeat['applied']===0&&$repeat['pid']!==$first['pid'],'repeat migration uses another clean process');
        $pdo->prepare("UPDATE site_settings SET value_json=? WHERE setting_key='maintenance'")->execute(['true']);
        $signal=new App\Services\MaintenanceState($clone.'/storage/runtime');$signal->synchronized(fn()=>$signal->publish(true));$signalHash=hash_file('sha256',$clone.'/storage/runtime/maintenance.json');
        $health=$result('health');$check($health['task']==='health'&&$health['migrations']===17&&$health['html_bytes']['ja']>1000&&$health['html_bytes']['en']>1000,'new runtime loads routes DB health and Japanese English home views');
        $check($pdo->query("SELECT value_json FROM site_settings WHERE setting_key='maintenance'")->fetchColumn()==='true'&&hash_file('sha256',$clone.'/storage/runtime/maintenance.json')===$signalHash,'health preserves separately enabled manual maintenance');
        $check($gate->enter()===null,'runtime health does not open ordinary access early');
    });$lease=$gate->enter();$check($lease!==null,'completion reopens clone access');$lease->release();$check(hash_file('sha256',$clone.'/config/config.php')===$configHash,'runtime preserves private configuration');
    $gate->exclusive(function()use($process,$task,$gate,$reject,$version){$reject(fn()=>$process->guardedScript($task,['health','wrong-version'],$gate));$reject(fn()=>$process->guardedScript($task,['unknown',$version],$gate));});
    $pdo->prepare('UPDATE migrations SET checksum=? WHERE name=?')->execute([str_repeat('0',64),'001_core.php']);
    try{$gate->exclusive(fn()=>$result('health'));throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='UPDATE_PROCESS_FAILED','changed migration checksum fails health');}
    $check($gate->enter()===null&&is_file($clone.'/storage/updates/access/pending'),'failed health retains maintenance marker');
    $pdo->prepare('UPDATE migrations SET checksum=? WHERE name=?')->execute([hash_file('sha256',$clone.'/database/migrations/001_core.php'),'001_core.php']);
    $gate->exclusive(function()use($result,$check){$check($result('health')['migrations']===17,'verified recovery checks real runtime');},true);
    $fault=$clone.'/database/migrations/018_runtime_failure.php';file_put_contents($fault,'<?php return new class {public function up(PDO $pdo):void{$pdo->exec("CREATE TABLE runtime_partial(id INT PRIMARY KEY) ENGINE=InnoDB");throw new RuntimeException("generated secret");}};');
    try{$gate->exclusive(fn()=>$result('migrate'));throw new LogicException('Unexpected success');}catch(HttpException $error){$check($error->errorCode==='UPDATE_PROCESS_FAILED','real migration exception fails child safely');}
    $check($pdo->query("SHOW TABLES LIKE 'runtime_partial'")->fetchAll()!==[]&&$gate->enter()===null,'DDL partial result remains stopped for engine rollback');
    $pdo->exec('DROP TABLE runtime_partial');unlink($fault);$gate->exclusive(function()use($result,$check){$check($result('health')['migrations']===17,'partial migration fixture removed before recovery health');},true);
    $check(hash_file('sha256',$clone.'/config/config.php')===$configHash,'configuration unchanged after all failures');
    $originalTask=file_get_contents($task);$base=['format'=>1,'protocol'=>1,'task'=>'health','version'=>$version,'pid'=>getmypid()+100000,'migrations'=>16,'html_bytes'=>['ja'=>1000,'en'=>1000]];
    $responses=['not json',json_encode($base+['secret'=>'generated secret']),json_encode(array_replace($base,['protocol'=>2])),json_encode(array_replace($base,['pid'=>getmypid()])),json_encode(array_replace($base,['html_bytes'=>['ja'=>1000]])),json_encode(array_replace($base,['version'=>'other']))];
    $gate->exclusive(function()use($responses,$task,$runtime,$version,$gate,$check){foreach($responses as $response){file_put_contents($task,'<?php echo '.var_export($response,true).';');try{$runtime->run('health',$version,$gate);throw new LogicException('Unexpected response');}catch(HttpException $error){$check($error->errorCode==='UPDATE_TASK_INVALID_RESPONSE'&&!str_contains($error->getMessage(),'generated secret'),'invalid runtime schema rejected without leaking child output');}}});file_put_contents($task,$originalTask);
    echo "$count isolated runtime checks passed.\n";
}finally{
    if($pdo!==null){$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $name){if(!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new RuntimeException('Unexpected test table');$pdo->exec('DROP TABLE `'.$name.'`');}$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
    $remove($directory);
}

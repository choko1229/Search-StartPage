<?php
declare(strict_types=1);

// Deliberately accepts only this disposable deployment and these dedicated DB hosts.
$root=dirname(__DIR__);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||$root!=='/tmp/search-real-release-source'
    ||is_link($root)||!is_file($root.'/real-github-release-test-only')
    ||trim(file_get_contents($root.'/real-github-release-test-only'))!=='isolated-release-verification'
    ||is_file($root.'/config/config.php')
    ||!in_array(getenv('TEST_RELEASE_HOST'),['search-real-release-mysql-20261005','search-real-release-mariadb-20261005'],true)){
    fwrite(STDERR,"ISOLATED_RELEASE_ENVIRONMENT_REQUIRED\n");exit(1);
}
require $root.'/app/autoload.php';
use App\Services\{UpdateChecks,UpdateCommands,UpdateRequests,UpdateJournal,UpdatePackage,GitHubUpdateAsset};
use App\Repositories\{AdminRepository,UpdateHistoryRepository,AuthRepository};
use App\Database\{Database,Migrator};

$baselineHash='053dcbee9ac93dabb25a83e7fa5195a024923b93968e530abb6edc1a45d0e03e';
$candidateHash='a5f630228ae70c82fce5ed1738b8848a2f0d0e688fc4981e3437f9c590479120';
$releaseId=getenv('TEST_RELEASE_ID');
if(!extension_loaded('curl')||!is_string($releaseId)||!ctype_digit($releaseId)||(int)$releaseId<1
    ||!is_file($root.'/baseline.tar')||is_link($root.'/baseline.tar')
    ||!hash_equals($baselineHash,hash_file('sha256',$root.'/baseline.tar'))
    ||!is_file($root.'/expected-candidate.tar')||is_link($root.'/expected-candidate.tar')
    ||!hash_equals($candidateHash,hash_file('sha256',$root.'/expected-candidate.tar'))){
    fwrite(STDERR,"FIXED_RELEASE_PREREQUISITES_REQUIRED\n");exit(1);
}
$directory=sys_get_temp_dir().'/real-release-integration-'.bin2hex(random_bytes(8));
if(!mkdir($directory,0700))throw new RuntimeException('Fresh private deployment unavailable');
$pdo=null;$owned=false;$count=0;
$check=static function(bool $ok,string $name)use(&$count):void{
    if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";
};
$remove=function(string $path)use(&$remove):void{
    if(is_link($path))throw new RuntimeException('Unexpected private deployment link');
    if(is_dir($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}
    elseif(is_file($path))unlink($path);
};
try{
    $live=$directory.'/live';$baseline=(new UpdatePackage())->verify($root.'/baseline.tar',$live,'0.1.1-dev',true);
    // Local candidate is an independent file oracle, never an acquisition transport.
    $candidate=(new UpdatePackage())->verify($root.'/expected-candidate.tar',$directory.'/expected-candidate','0.1.2-dev',true);
    $entryHash=hash_file('sha256',$live.'/bin/run-update.php');mkdir($live.'/storage',0700);
    $settings=require $live.'/config/config.example.php';$settings['installed']=true;
    $settings['database']=['host'=>getenv('TEST_RELEASE_HOST'),'port'=>3306,'name'=>'release_integration','user'=>'release_test','password'=>getenv('TEST_RELEASE_PASSWORD')];
    $settings['site']['url']='http://localhost';
    $settings['updates']=['repository'=>'choko1229/Search-StartPage','channel'=>'custom','custom_tag'=>'v0.1.2-dev','token'=>''];
    $until=time()+60;
    do{try{$pdo=Database::connect($settings['database']);break;}catch(PDOException){if(time()>=$until)throw new RuntimeException('Dedicated release DB unavailable');usleep(200000);}}while(true);
    $check($pdo->query('SHOW TABLES')->fetchAll()===[],'release verification owns an empty dedicated schema');$owned=true;
    file_put_contents($live.'/config/config.php','<?php return '.var_export($settings,true).';');chmod($live.'/config/config.php',0600);
    $configHash=hash_file('sha256',$live.'/config/config.php');
    $migrations=(new Migrator($pdo,$live.'/database/migrations'))->migrate();
    $check(count($migrations)===17&&(new Migrator($pdo,$live.'/database/migrations'))->migrate()===[],'fixed baseline applies all seventeen migrations idempotently');
    $user=(new AuthRepository($pdo))->upsertIdentity(['id'=>'999999999999999970','username'=>'Generated isolated real release operator','display_name'=>null,'avatar'=>null],'en');
    $pdo->prepare('INSERT INTO administrators(user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$user]);
    mkdir($live.'/storage/generated-upload',0700);file_put_contents($live.'/storage/generated-upload/keep.bin',"generated\0release-upload");
    $uploadHash=hash_file('sha256',$live.'/storage/generated-upload/keep.bin');
    $history=new UpdateHistoryRepository($pdo);$admin=new AdminRepository($pdo);
    $commands=new UpdateCommands($live.'/storage/updates/commands',fn($id)=>$admin->isAdministrator($id),fn($r,$e)=>$history->record($r,$e),fn($r)=>$history->matches($r));
    $journal=new UpdateJournal($live.'/storage/updates/journal');
    // Neither the metadata client nor the unchanged production entry receives a fixture transport.
    $checks=new UpdateChecks($live.'/storage/updates/checks',new App\Config($settings),'0.1.1-dev');
    $selected=$checks->check('custom','v0.1.2-dev',0);
    $check($selected['available']===true&&$selected['release']['id']===(int)$releaseId&&$selected['release']['tag']==='v0.1.2-dev'&&$selected['release']['prerelease']===true,'real GitHub selects the pinned published development release');
    $asset=(new GitHubUpdateAsset('choko1229/Search-StartPage'))->select((int)$releaseId);
    $check($asset['size']===3516928&&$asset['digest']==='sha256:'.$candidateHash,'real GitHub binds the archive to the independently pinned size and digest');
    $requests=new UpdateRequests($live,$checks,$commands,$journal);$status=$requests->status();
    $accepted=$requests->enqueue($user,'apply',$status['command_revision'],$selected['revision'],$status['engine_revision']);$applyId=$accepted['request']['id'];
    $check($accepted['request']['status']==='queued'&&trim(file_get_contents($live.'/VERSION'))==='0.1.1-dev','real release acceptance audits without installing inside the request');
    $execute=static function()use($live):array{
        // Do not terminate an updater on a test timeout. Product HTTPS calls are bounded.
        $process=proc_open([PHP_BINARY,$live.'/bin/run-update.php'],[0=>['pipe','r'],1=>['file',$live.'/storage/real-entry.out','w'],2=>['file',$live.'/storage/real-entry.err','w']],$pipes,$live);
        if(!is_resource($process))throw new RuntimeException('Production release entry unavailable');
        fclose($pipes[0]);$exit=proc_close($process);
        $out=file_get_contents($live.'/storage/real-entry.out');$err=file_get_contents($live.'/storage/real-entry.err');
        if(strlen($out)>65536||$err!==''||$exit!==0)throw new RuntimeException('Production release entry failed');
        return json_decode(trim($out),true,flags:JSON_THROW_ON_ERROR);
    };
    $check(hash_equals($entryHash,hash_file('sha256',$live.'/bin/run-update.php')),'baseline production entry is unchanged before acquisition');
    $result=$execute();$state=$journal->status();
    $check($result['request_id']===$applyId&&$result['status']==='complete'&&$state['job']['phase']==='complete'&&trim(file_get_contents($live.'/VERSION'))==='0.1.2-dev','unchanged production CLI acquires and applies the actual GitHub asset');
    foreach($candidate['files'] as $path=>$file)$check(hash_equals($file['sha256'],hash_file('sha256',$live.'/'.$path)),'actual installed candidate file '.$path);
    $check($history->listing()[0]['status']==='complete'&&(int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn()===17,'real published update commits successful history and migrations');
    $check(hash_equals($configHash,hash_file('sha256',$live.'/config/config.php'))&&hash_equals($uploadHash,hash_file('sha256',$live.'/storage/generated-upload/keep.bin')),'actual published update preserves private configuration and upload bytes');
    $pdo->prepare('UPDATE users SET locale=? WHERE id=?')->execute(['ja',$user]);
    $checks=new UpdateChecks($live.'/storage/updates/checks',new App\Config($settings),'0.1.2-dev');
    $requests=new UpdateRequests($live,$checks,$commands,$journal);$status=$requests->status();
    $check($status['rollback']===['from_version'=>'0.1.2-dev','to_version'=>'0.1.1-dev'],'actual published update retains the canonical previous generation');
    $accepted=$requests->enqueue($user,'rollback',$status['command_revision'],$checks->status()['revision'],$status['engine_revision']);$rollbackId=$accepted['request']['id'];
    $result=$execute();
    $check($result['request_id']===$rollbackId&&$result['status']==='rolled_back'&&trim(file_get_contents($live.'/VERSION'))==='0.1.1-dev','updated production entry rolls back the actual received generation');
    $query=$pdo->prepare('SELECT locale FROM users WHERE id=?');$query->execute([$user]);
    $check($query->fetchColumn()==='ja'&&(int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn()===17,'actual rollback retains later user data and valid migrations');
    $mapped=array_column($history->listing(),'status','request_id');
    $check(($mapped[$applyId]??null)==='complete'&&($mapped[$rollbackId]??null)==='rolled_back','actual rollback preserves both audited outcomes');
    $check(hash_equals($configHash,hash_file('sha256',$live.'/config/config.php'))&&hash_equals($uploadHash,hash_file('sha256',$live.'/storage/generated-upload/keep.bin')),'actual rollback preserves configuration and upload bytes');
    $check($requests->status()['rollback']===null&&array_diff(scandir($live.'/storage/updates/incoming'),['.','..'])===[],'real acquisition work is removed and rollback generation consumed');
    foreach($baseline['files'] as $path=>$file)$check(hash_equals($file['sha256'],hash_file('sha256',$live.'/'.$path)),'restored baseline file '.$path);
    echo "$count real GitHub release integration checks passed.\n";
}finally{
    if($owned&&$pdo!==null){
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $name){if(!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new RuntimeException('Unexpected dedicated test table');$pdo->exec('DROP TABLE `'.$name.'`');}
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    $remove($directory);
}

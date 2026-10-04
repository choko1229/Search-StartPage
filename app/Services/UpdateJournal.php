<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Durable metadata only. No credentials, download URLs, SQL, or user rows. */
final class UpdateJournal
{
    private const TRANSITIONS=[
        'queued'=>['verified','failed'],'verified'=>['backing_up','failed'],'backing_up'=>['backed_up','failed'],
        'backed_up'=>['replacing','failed'],'replacing'=>['migrating','rolling_back'],
        'migrating'=>['checking','rolling_back'],'checking'=>['complete','rolling_back'],
        'complete'=>['rolling_back'],'rolling_back'=>['rolled_back','rollback_failed'],
        'rollback_failed'=>['rolling_back'],'rolled_back'=>[],'failed'=>[]
    ];
    private const ERRORS=['UPDATE_DOWNLOAD_FAILED','UPDATE_BACKUP_FAILED','UPDATE_APPLY_FAILED','UPDATE_MIGRATION_FAILED','UPDATE_HEALTH_FAILED','UPDATE_ROLLBACK_FAILED','UPDATE_INTERRUPTED'];
    public function __construct(private readonly string $directory,private readonly ?\Closure $clock=null) {}
    private function synchronized(callable $operation): mixed
    {
        UpdatePackagePaths::directory(dirname($this->directory));
        if(is_link($this->directory))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        return LogFileLock::run($this->directory,function()use($operation){UpdatePackagePaths::directory($this->directory);return $operation();});
    }
    private function now(): int {$now=$this->clock?($this->clock)():time();if(!is_int($now)||$now<1)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');return $now;}
    private static function keys(array $value,array $keys): bool {$actual=array_keys($value);sort($actual);sort($keys);return $actual===$keys;}
    private static function id(mixed $id): bool {return is_string($id)&&preg_match('/^[a-f0-9]{32}$/D',$id)===1;}
    private static function digest(mixed $digest): bool {return is_string($digest)&&preg_match('/^[a-f0-9]{64}$/D',$digest)===1;}
    private static function version(mixed $version): bool {return is_string($version)&&preg_match('~^[A-Za-z0-9][A-Za-z0-9.+_/-]{0,127}$~D',$version)===1;}
    private static function backup(mixed $backup): bool
    {
        if(!is_array($backup)||!self::keys($backup,['files','database']))return false;
        $files=$backup['files'];$db=$backup['database'];
        return is_array($files)&&self::keys($files,['version','files','bytes','sha256'])&&self::version($files['version'])&&is_int($files['files'])&&$files['files']>=5&&$files['files']<=10000
            &&is_int($files['bytes'])&&$files['bytes']>=1536&&$files['bytes']<=UpdateManifest::TOTAL_LIMIT+12582912&&self::digest($files['sha256'])
            &&is_array($db)&&self::keys($db,['bytes','sha256','database','tables','rows'])&&is_int($db['bytes'])&&$db['bytes']>0&&$db['bytes']<=2147483647
            &&self::digest($db['sha256'])&&self::digest($db['database'])&&is_int($db['tables'])&&$db['tables']>=0&&$db['tables']<=4096&&is_int($db['rows'])&&$db['rows']>=0;
    }
    private static function job(mixed $job): bool
    {
        if(!is_array($job)||!self::keys($job,['id','phase','from_version','to_version','created_at','updated_at','error','backup'])||!self::id($job['id'])||!is_string($job['phase'])||!isset(self::TRANSITIONS[$job['phase']])
            ||!self::version($job['from_version'])||!self::version($job['to_version'])||!is_int($job['created_at'])||$job['created_at']<1||!is_int($job['updated_at'])||$job['updated_at']<$job['created_at']
            ||($job['error']!==null&&!in_array($job['error'],self::ERRORS,true)))return false;
        if($job['backup']!==null&&(!self::backup($job['backup'])||$job['backup']['files']['version']!==$job['from_version']))return false;
        if(in_array($job['phase'],['backed_up','replacing','migrating','checking','complete','rolling_back','rolled_back','rollback_failed'],true)&&$job['backup']===null)return false;
        if(in_array($job['phase'],['queued','verified','backing_up','backed_up','replacing','migrating','checking','complete'],true)&&$job['error']!==null)return false;
        if(in_array($job['phase'],['failed','rollback_failed'],true)&&$job['error']===null)return false;
        return true;
    }
    private static function validate(mixed $state): array
    {
        if(!is_array($state)||!self::keys($state,['format','revision','job','backup','history'])||$state['format']!==1||!is_int($state['revision'])||$state['revision']<0||$state['revision']>9007199254740990
            ||($state['job']!==null&&!self::job($state['job']))||!is_array($state['history'])||!array_is_list($state['history'])||count($state['history'])>20)throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        $seen=[];foreach($state['history'] as $job){if(!self::job($job)||!in_array($job['phase'],['complete','failed','rolled_back'],true)||isset($seen[$job['id']]))throw new HttpException(503,'UPDATE_JOURNAL_INVALID');$seen[$job['id']]=true;}
        if($state['job']!==null&&isset($seen[$state['job']['id']]))throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        if(($state['job']['phase']??null)==='complete'&&($state['backup']['id']??null)!==$state['job']['id'])throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        if($state['backup']!==null){
            $backup=$state['backup'];if(!is_array($backup)||!self::keys($backup,['id','from_version','to_version','snapshot'])||!self::id($backup['id'])||!self::version($backup['from_version'])||!self::version($backup['to_version'])||!self::backup($backup['snapshot'])||$backup['snapshot']['files']['version']!==$backup['from_version'])throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
            $owner=null;foreach([$state['job'],...$state['history']] as $job)if($job!==null&&$job['id']===$backup['id'])$owner=$job;
            if($owner===null||!in_array($owner['phase'],['complete','rolling_back','rollback_failed'],true)||$owner['from_version']!==$backup['from_version']||$owner['to_version']!==$backup['to_version']||$owner['backup']!==$backup['snapshot'])throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        }
        return $state;
    }
    private function read(): array
    {
        $path=$this->directory.'/journal.json';clearstatcache(true,$path);
        if(is_link($path))throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        if(!file_exists($path))return ['format'=>1,'revision'=>0,'job'=>null,'backup'=>null,'history'=>[]];
        if(!is_file($path)||filesize($path)>2097152)throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        try{$json=file_get_contents($path);$state=json_decode($json,true,24,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(503,'UPDATE_JOURNAL_INVALID');}
        return self::validate($state);
    }
    private function write(array $state): array
    {
        if($state['revision']>=9007199254740990)throw new HttpException(503,'UPDATE_JOURNAL_INVALID');++$state['revision'];self::validate($state);
        $json=json_encode($state,JSON_THROW_ON_ERROR);if(strlen($json)>2097152)throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        $path=$this->directory.'/journal.json';if(is_link($path))throw new HttpException(503,'UPDATE_JOURNAL_INVALID');
        $temporary=$this->directory.'/journal-'.bin2hex(random_bytes(8)).'.tmp';$stream=@fopen($temporary,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        try{
            if(!chmod($temporary,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset=0;
            while($offset<strlen($json)){$written=fwrite($stream,substr($json,$offset));if($written===false||$written===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$written;}
            if(!fflush($stream)||!fsync($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');fclose($stream);$stream=null;
            if(!rename($temporary,$path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');return $state;
        }finally{if(is_resource($stream))fclose($stream);if(is_file($temporary)&&!unlink($temporary))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
    }
    public function status(): array {return $this->synchronized(fn()=>$this->read());}
    public function start(string $fromVersion,string $toVersion,int $revision): array
    {
        if(!self::version($fromVersion)||!self::version($toVersion))throw new HttpException(422,'INVALID_UPDATE_VERSION');
        return $this->synchronized(function()use($fromVersion,$toVersion,$revision){
            $state=$this->read();if($state['revision']!==$revision)throw new HttpException(409,'UPDATE_STATE_CHANGED');
            if($state['job']!==null){
                if(!in_array($state['job']['phase'],['complete','failed','rolled_back'],true))throw new HttpException(409,'UPDATE_IN_PROGRESS');
                array_unshift($state['history'],$state['job']);$state['history']=array_slice($state['history'],0,20);
                // Keep the sole rollback generation's metadata even across many failed attempts.
                if($state['backup']!==null&&!in_array($state['backup']['id'],array_column($state['history'],'id'),true)){
                    $previous=$this->read();foreach($previous['history'] as $job)if($job['id']===$state['backup']['id']){$state['history'][19]=$job;break;}
                }
            }
            $now=$this->now();$state['job']=['id'=>bin2hex(random_bytes(16)),'phase'=>'queued','from_version'=>$fromVersion,'to_version'=>$toVersion,'created_at'=>$now,'updated_at'=>$now,'error'=>null,'backup'=>null];return $this->write($state);
        });
    }
    public function advance(string $id,int $revision,string $phase,?array $backup=null,?string $error=null): array
    {
        return $this->synchronized(function()use($id,$revision,$phase,$backup,$error){
            $state=$this->read();$job=$state['job'];
            if($state['revision']!==$revision||$job===null||$job['id']!==$id)throw new HttpException(409,'UPDATE_STATE_CHANGED');
            if(!in_array($phase,self::TRANSITIONS[$job['phase']],true)||($error!==null&&!in_array($error,self::ERRORS,true)))throw new HttpException(409,'INVALID_UPDATE_TRANSITION');
            if($backup!==null&&($phase!=='backed_up'||!self::backup($backup)||$backup['files']['version']!==$job['from_version']))throw new HttpException(422,'INVALID_UPDATE_BACKUP');
            if($phase==='complete')$state['backup']=['id'=>$id,'from_version'=>$job['from_version'],'to_version'=>$job['to_version'],'snapshot'=>$job['backup']];
            if($phase==='rolled_back'&&($state['backup']['id']??null)===$id)$state['backup']=null;
            $job['phase']=$phase;$job['updated_at']=max($job['updated_at'],$this->now());$job['error']=$error;if($backup!==null)$job['backup']=$backup;$state['job']=$job;
            if(!self::job($job))throw new HttpException(409,'INVALID_UPDATE_TRANSITION');return $this->write($state);
        });
    }
}

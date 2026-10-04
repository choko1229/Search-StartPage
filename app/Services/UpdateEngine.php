<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Internal CLI engine; callers provide a release already trusted by GitHub asset metadata. */
final class UpdateEngine
{
    private UpdateAccess $access;
    private UpdateJournal $journal;
    private string $jobs;
    public function __construct(private readonly string $root,private readonly ?\Closure $checkpoint=null)
    {
        if(PHP_SAPI!=='cli')throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
        UpdatePackagePaths::directory($root);UpdatePackagePaths::directory($root.'/storage');
        $this->privateDirectory($root.'/storage/updates');$this->jobs=$root.'/storage/updates/jobs';$this->privateDirectory($this->jobs);
        $this->access=new UpdateAccess($root.'/storage/updates/access');$this->journal=new UpdateJournal($root.'/storage/updates/journal');
    }
    private function privateDirectory(string $path): void
    {
        if(!is_dir($path)&&!is_link($path)&&!mkdir($path,0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');UpdatePackagePaths::directory($path);
        clearstatcache(true,$path);if((fileperms($path)&0077)!==0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    private function job(array $state): string
    {
        $id=$state['job']['id']??'';if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new HttpException(503,'UPDATE_JOURNAL_INVALID');return $this->jobs.'/'.$id;
    }
    private function event(string $event,array $state,?string $path=null): void {if($this->checkpoint)($this->checkpoint)($event,$this->job($state),$path);}
    private static function manifestJson(array $manifest): string
    {
        $manifest=UpdateManifest::decode(json_encode($manifest,JSON_THROW_ON_ERROR),$manifest['version']??'');$files=$manifest['files'];ksort($files);
        foreach($files as &$metadata)$metadata=['bytes'=>$metadata['bytes'],'sha256'=>$metadata['sha256']];unset($metadata);
        return json_encode(['format'=>1,'version'=>$manifest['version'],'php_min'=>$manifest['php_min'],'files'=>$files],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }
    private function save(string $path,string $body): void
    {
        $stream=@fopen($path,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        try{if(!chmod($path,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset=0;while($offset<strlen($body)){$bytes=fwrite($stream,substr($body,$offset));if($bytes===false||$bytes===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$bytes;}if(!fflush($stream)||!fsync($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}finally{fclose($stream);}
    }
    private function archive(string $source,string $target): void
    {
        UpdatePackagePaths::directory(dirname($source));if(is_link($source)||!is_file($source)||filesize($source)>UpdateManifest::TOTAL_LIMIT+12582912)throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
        $input=@fopen($source,'rb');if($input===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$output=@fopen($target,'xb');if($output===false){fclose($input);throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        try{if(!chmod($target,0600)||stream_copy_to_stream($input,$output)===false||!fflush($output)||!fsync($output))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}finally{fclose($input);fclose($output);}
    }
    private function database(): UpdateDatabase
    {
        $config=\App\Config::load($this->root);if(!$config->get('installed'))throw new HttpException(503,'NOT_INSTALLED');return new UpdateDatabase(\App\Database\Database::connect($config->get('database')));
    }
    private function locked(callable $operation): mixed
    {
        $directory=$this->root.'/storage/updates/engine';$this->privateDirectory($directory);return LogFileLock::run($directory,$operation);
    }
    private function sync(string $path): void
    {
        $stream=@fopen($path,'r+');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        try{if(!fflush($stream)||!fsync($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}finally{fclose($stream);}
    }
    private function advance(array &$state,string $phase,?array $backup=null,?string $error=null): void {$state=$this->journal->advance($state['job']['id'],$state['revision'],$phase,$backup,$error);}
    private function fileHash(string $path,array $metadata): void
    {
        UpdatePackagePaths::directory(dirname($path));clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||filesize($path)!==$metadata['bytes']||!hash_equals($metadata['sha256'],hash_file('sha256',$path)))throw new HttpException(422,'INVALID_UPDATE_BACKUP');
    }
    private function candidate(array $state): array
    {
        $path=$this->job($state).'/manifest.json';UpdatePackagePaths::directory(dirname($path));clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||filesize($path)>2097152||!is_string($state['job']['manifest_sha256'])||!hash_equals($state['job']['manifest_sha256'],hash_file('sha256',$path)))throw new HttpException(422,'INVALID_UPDATE_BACKUP');
        return UpdateManifest::decode(file_get_contents($path),$state['job']['to_version']);
    }
    private function databaseDescriptor(array $state,string $purpose,?array $metadata=null):array
    {
        if(!in_array($purpose,['baseline','rollback-database'],true))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        $path=$this->job($state).'/'.$purpose.'.json';
        if($metadata!==null)$this->save($path,json_encode(['format'=>1,'job_id'=>$state['job']['id'],'manifest_sha256'=>$state['job']['manifest_sha256'],'old_database_sha256'=>$state['job']['backup']['database']['sha256'],'database'=>$metadata],JSON_THROW_ON_ERROR));
        clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||filesize($path)>4096||(fileperms($path)&0077)!==0)throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        try{$value=json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');}
        if(!is_array($value)||array_keys($value)!==['format','job_id','manifest_sha256','old_database_sha256','database']||$value['format']!==1||$value['job_id']!==$state['job']['id']||$value['manifest_sha256']!==$state['job']['manifest_sha256']||$value['old_database_sha256']!==$state['job']['backup']['database']['sha256']||!is_array($value['database']))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        $db=$value['database'];if(array_keys($db)!==['bytes','sha256','database','tables','rows']||!is_int($db['bytes'])||$db['bytes']<1||$db['bytes']>2147483647||!is_int($db['tables'])||$db['tables']<0||$db['tables']>4096||!is_int($db['rows'])||$db['rows']<0||!is_string($db['sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$db['sha256'])||$db['database']!==$state['job']['backup']['database']['database'])throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        return $db;
    }
    private function remove(string $path): void
    {
        if(is_link($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        if(is_dir($path)){UpdatePackagePaths::directory($path);foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$this->remove($path.'/'.$name);if(!rmdir($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        elseif(!is_file($path)||!unlink($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    private function cleanup(array $state): void
    {
        $keep=$state['backup']['id']??null;
        if($keep!==null){
            $path=$this->jobs.'/'.$keep;$this->privateDirectory($path);$this->fileHash($path.'/files.tar',$state['backup']['snapshot']['files']);$this->fileHash($path.'/database.jsonl',$state['backup']['snapshot']['database']);
            $owner=null;foreach([$state['job'],...$state['history']] as $job)if($job!==null&&$job['id']===$keep)$owner=$job;
            if($owner===null)throw new HttpException(503,'UPDATE_JOURNAL_INVALID');$this->candidate(['job'=>$owner]);
            if(file_exists($path.'/baseline.json')||is_link($path.'/baseline.json'))$this->fileHash($path.'/baseline.jsonl',$this->databaseDescriptor(['job'=>$owner],'baseline'));
        }
        foreach(scandir($this->jobs) as $name){if($name==='.'||$name==='..')continue;if(!preg_match('/^[a-f0-9]{32}$/D',$name))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$path=$this->jobs.'/'.$name;UpdatePackagePaths::directory($path);
            if($name!==$keep)$this->remove($path);else foreach(scandir($path) as $file)if(!in_array($file,['.','..','files.tar','database.jsonl','manifest.json','baseline.json','baseline.jsonl'],true))$this->remove($path.'/'.$file);
        }
    }
    private function health(string $version): void {(new UpdateRuntime($this->root))->run('health',$version,$this->access);}
    private function restore(array &$state,?string $cause): void
    {
        if($state['job']['phase']!=='rolling_back')$this->advance($state,'rolling_back',error:$cause);
        $directory=$this->job($state);$backup=$state['job']['backup'];$errors=false;
        try{
            $manifest=$this->candidate($state);$this->fileHash($directory.'/files.tar',$backup['files']);
            if(file_exists($directory.'/restore')||is_link($directory.'/restore'))$this->remove($directory.'/restore');
            (new UpdateFiles())->restore($directory.'/files.tar',$directory.'/restore',$this->root,$state['job']['from_version'],$manifest);
        }catch(\Throwable){$errors=true;}
        // Always attempt DB recovery as well, even if file recovery failed.
        try{
            // Manual rollback reactivates the retained successful owner. Its merged
            // snapshot is mandatory even if the descriptor has been lost.
            $manual=($state['backup']['id']??null)===$state['job']['id']||file_exists($directory.'/rollback-database.json')||is_link($directory.'/rollback-database.json');
            $metadata=$manual?$this->databaseDescriptor($state,'rollback-database'):$backup['database'];
            $this->database()->restore($directory.($manual?'/rollback-database.jsonl':'/database.jsonl'),$metadata);
        }catch(\Throwable){$errors=true;}
        if(!$errors)try{$this->health($state['job']['from_version']);}catch(\Throwable){$errors=true;}
        if($errors){$this->advance($state,'rollback_failed',error:'UPDATE_ROLLBACK_FAILED');throw new HttpException(503,'UPDATE_ROLLBACK_FAILED');}
        $this->advance($state,'rolled_back',error:$cause);
    }
    public function apply(string $archive,array $manifest,?string $requestId=null,?string $fromVersion=null): array
    {
        return $this->locked(fn()=>$this->executeApply($archive,$manifest,$requestId,$fromVersion));
    }
    private function executeApply(string $archive,array $manifest,?string $requestId,?string $fromVersion): array
    {
        $json=self::manifestJson($manifest);$manifest=UpdateManifest::decode($json,$manifest['version']);$lease=$this->access->enter();if($lease===null)throw new HttpException(409,'UPDATE_IN_PROGRESS');
        try{$version=trim(file_get_contents($this->root.'/VERSION'));if($fromVersion!==null&&$version!==$fromVersion)throw new HttpException(409,'UPDATE_SOURCE_CHANGED');$state=$this->journal->status();$state=$this->journal->start($version,$manifest['version'],$state['revision'],hash('sha256',$json),$requestId);}finally{$lease->release();}$directory=$this->job($state);
        try{
            $this->privateDirectory($directory);$this->archive($archive,$directory.'/candidate.tar');
            $actual=(new UpdatePackage())->verify($directory.'/candidate.tar',$directory.'/candidate',$manifest['version'],false,static fn(string $stage,array $candidate)=>(new UpdateCompatibility())->validate($stage,$candidate));
            if(!hash_equals(hash('sha256',$json),hash('sha256',self::manifestJson($actual))))throw new HttpException(422,'INVALID_UPDATE_PACKAGE');
            $this->save($directory.'/manifest.json',$json);$this->advance($state,'verified');$this->event('verified',$state);
            (new UpdateRescue())->prepare($this->root);
        }catch(\Throwable){$this->advance($state,'failed',error:'UPDATE_DOWNLOAD_FAILED');throw new HttpException(422,'UPDATE_DOWNLOAD_FAILED');}
        return $this->access->exclusive(function()use(&$state,$directory,$manifest,$version){
            try{
                if(trim(file_get_contents($this->root.'/VERSION'))!==$version)throw new HttpException(409,'UPDATE_SOURCE_CHANGED');$this->health($version);
                $this->advance($state,'backing_up');$this->event('backing_up',$state);
                $files=(new UpdateFiles())->snapshot($this->root,$directory.'/files.tar');$database=$this->database()->snapshot($directory.'/database.jsonl');
                $this->sync($directory.'/files.tar');$this->sync($directory.'/database.jsonl');$this->fileHash($directory.'/files.tar',$files);$this->fileHash($directory.'/database.jsonl',$database);
                $previous=(new UpdatePackage())->verify($directory.'/files.tar',$directory.'/previous',$version,true);
                $this->advance($state,'backed_up',['files'=>$files,'database'=>$database]);$this->event('backed_up',$state);
                (new UpdateFiles())->preflight($directory.'/candidate',$this->root,$manifest,$previous);
                $this->advance($state,'replacing');$this->event('replacing',$state);
                (new UpdateFiles())->replace($directory.'/candidate',$this->root,$manifest,$previous,function(string $path)use(&$state){$this->event('file',$state,$path);});
                $this->advance($state,'migrating');$this->event('migrating',$state);(new UpdateRuntime($this->root))->run('migrate',$state['job']['to_version'],$this->access);
                $this->advance($state,'checking');$this->event('checking',$state);$this->health($state['job']['to_version']);$this->candidate($state);
                $baseline=$this->database()->snapshot($directory.'/baseline.jsonl');$this->sync($directory.'/baseline.jsonl');$this->databaseDescriptor($state,'baseline',$baseline);$this->advance($state,'complete');
            }catch(\Throwable $error){
                $phase=$state['job']['phase'];$cause=$error instanceof HttpException&&$error->errorCode==='UPDATE_TARGET_NOT_WRITABLE'?'UPDATE_TARGET_NOT_WRITABLE':match($phase){'migrating'=>'UPDATE_MIGRATION_FAILED','checking'=>'UPDATE_HEALTH_FAILED','replacing','backed_up'=>'UPDATE_APPLY_FAILED',default=>'UPDATE_BACKUP_FAILED'};
                if(in_array($phase,['replacing','migrating','checking'],true))$this->restore($state,$cause);
                else{$this->advance($state,'failed',error:$cause);$this->health($version);}
            }
            // Cleanup errors retain the stop marker; a completed update is recovered forward.
            $this->cleanup($state);return $state;
        });
    }
    public function recover(?string $requestId=null): array
    {
        return $this->locked(fn()=>$this->executeRecovery($requestId));
    }
    private function executeRecovery(?string $requestId): array
    {
        $state=$this->journal->status();if($state['job']===null)throw new HttpException(409,'UPDATE_BACKUP_UNAVAILABLE');
        if($requestId!==null&&($state['job']['rollback_request_id']??$state['job']['request_id'])!==$requestId)throw new HttpException(409,'UPDATE_STATE_CHANGED');
        return $this->access->exclusive(function()use(&$state){
            $phase=$state['job']['phase'];
            if(in_array($phase,['replacing','migrating','checking','rolling_back','rollback_failed'],true))$this->restore($state,'UPDATE_INTERRUPTED');
            else{
                if(in_array($phase,['queued','verified','backing_up','backed_up'],true))$this->advance($state,'failed',error:'UPDATE_INTERRUPTED');
                $this->health($state['job']['phase']==='complete'?$state['job']['to_version']:$state['job']['from_version']);
            }
            $this->cleanup($state);return $state;
        },true);
    }
    public function rollback(?string $requestId=null,?string $fromVersion=null,?string $toVersion=null): array
    {
        return $this->locked(fn()=>$this->executeRollback($requestId,$fromVersion,$toVersion));
    }
    private function executeRollback(?string $requestId,?string $fromVersion,?string $toVersion): array
    {
        $state=$this->journal->status();if($state['backup']===null||trim(file_get_contents($this->root.'/VERSION'))!==$state['backup']['to_version'])throw new HttpException(409,'UPDATE_BACKUP_UNAVAILABLE');
        if(($fromVersion!==null&&$fromVersion!==$state['backup']['to_version'])||($toVersion!==null&&$toVersion!==$state['backup']['from_version']))throw new HttpException(409,'UPDATE_SOURCE_CHANGED');
        $owner=null;foreach([$state['job'],...$state['history']] as $job)if($job!==null&&$job['id']===$state['backup']['id'])$owner=$job;
        if($owner===null)throw new HttpException(503,'UPDATE_JOURNAL_INVALID');$ownerState=['job'=>$owner];$directory=$this->job($ownerState);
        if(!is_file($directory.'/baseline.json'))throw new HttpException(409,'UPDATE_BACKUP_UNAVAILABLE');
        $failure=null;$result=$this->access->exclusive(function()use(&$state,$requestId,$ownerState,$directory,&$failure){
            try{
                $baseline=$this->databaseDescriptor($ownerState,'baseline');$this->fileHash($directory.'/baseline.jsonl',$baseline);
                foreach(['current-database.jsonl','rollback-database.jsonl','rollback-database.json'] as $file)if(file_exists($directory.'/'.$file)||is_link($directory.'/'.$file))$this->remove($directory.'/'.$file);
                $current=$this->database()->snapshot($directory.'/current-database.jsonl');$this->sync($directory.'/current-database.jsonl');
                $db=\App\Database\Database::connect(\App\Config::load($this->root)->get('database'));
                $merged=(new UpdateDatabaseMerge($db))->prepare(['path'=>$directory.'/database.jsonl','metadata'=>$ownerState['job']['backup']['database']],['path'=>$directory.'/baseline.jsonl','metadata'=>$baseline],['path'=>$directory.'/current-database.jsonl','metadata'=>$current],$directory.'/rollback-database.jsonl');
                $this->fileHash($directory.'/rollback-database.jsonl',$merged);$this->databaseDescriptor($ownerState,'rollback-database',$merged);$this->remove($directory.'/current-database.jsonl');
            }catch(\Throwable $error){
                // Preparation changes only temporary tables/private files; keep the installed version.
                foreach(['current-database.jsonl','rollback-database.jsonl','rollback-database.json'] as $file)if(file_exists($directory.'/'.$file)||is_link($directory.'/'.$file))$this->remove($directory.'/'.$file);
                $this->health($state['backup']['to_version']);$failure=$error;return $state;
            }
            $state=$this->journal->beginRollback($state['revision'],$requestId);$this->event('manual_rollback',$state);$this->restore($state,null);$this->cleanup($state);return $state;
        });
        if($failure!==null)throw $failure;return $result;
    }
    public function status(): array {return $this->journal->status();}
}

<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Server-selected admin requests. No download, process launch, or file/DB replacement. */
final class UpdateRequests
{
    public const PROTOCOL=1;
    public function __construct(private readonly string $root,private readonly UpdateChecks $checks,
        private readonly UpdateCommands $commands,private readonly UpdateJournal $journal) {}
    private function current():string
    {
        $path=$this->root.'/VERSION';UpdatePackagePaths::directory($this->root);
        if(is_link($path)||!is_file($path)||filesize($path)>256)throw new HttpException(503,'INVALID_UPDATE_CONFIGURATION');
        return UpdateManifest::version(trim(file_get_contents($path)));
    }
    private static function idle(array $state):void
    {
        if($state['job']!==null&&!in_array($state['job']['phase'],['complete','failed','rolled_back'],true))throw new HttpException(409,'UPDATE_IN_PROGRESS');
    }
    private function workerReady():bool
    {
        foreach([$this->root,$this->root.'/app',$this->root.'/public'] as $directory)if(is_link($directory)||!is_dir($directory)||!is_writable($directory))return false;
        $path=$this->root.'/storage/update-execution-worker.lock';$stop=$this->root.'/storage/update-execution-worker.stop';
        clearstatcache(true,$path);if(is_link($path)||!is_file($path)||filesize($path)!==32||(fileperms($path)&0077)!==0||file_exists($stop)||is_link($stop))return false;
        $lock=@fopen($path,'r+');if($lock===false)return false;
        try{
            if(flock($lock,LOCK_EX|LOCK_NB)){flock($lock,LOCK_UN);return false;}
            $instance=stream_get_contents($lock,33);return is_string($instance)&&preg_match('/^[a-f0-9]{32}$/D',$instance)===1;
        }finally{fclose($lock);}
    }
    public function status():array
    {
        $commands=$this->commands->status();$engine=$this->journal->status();$request=$commands['request'];
        if($request!==null)$request=array_intersect_key($request,array_flip(['id','operation','from_version','to_version','status','created_at','completed_at','error']));
        $backup=$engine['backup'];$rollback=null;
        if($backup!==null&&$this->current()===$backup['to_version']&&is_file($this->root.'/storage/updates/jobs/'.$backup['id'].'/baseline.json'))$rollback=['from_version'=>$backup['to_version'],'to_version'=>$backup['from_version']];
        return ['command_revision'=>$commands['revision'],'engine_revision'=>$engine['revision'],'request'=>$request,'rollback'=>$rollback,'worker_ready'=>$this->workerReady(),
            'busy'=>($request!==null&&!in_array($request['status'],UpdateCommands::TERMINAL,true))||($engine['job']!==null&&!in_array($engine['job']['phase'],['complete','failed','rolled_back'],true))];
    }
    public function enqueue(int $actor,string $operation,int $commandRevision,int $checkRevision,int $engineRevision):array
    {
        foreach([$commandRevision,$checkRevision,$engineRevision] as $revision)if($revision<0||$revision>9007199254740990)throw new HttpException(422,'INVALID_INPUT');
        if(!in_array($operation,['apply','rollback'],true)||$actor<1)throw new HttpException(422,'INVALID_INPUT');
        $accept=function(array $check)use($actor,$operation,$commandRevision,$engineRevision):array{
            return $this->journal->withStatus($engineRevision,function(array $engine)use($actor,$operation,$commandRevision,$check):array{
                self::idle($engine);$current=$this->current();
                if($check['current_version']!==$current)throw new HttpException(409,'UPDATE_SOURCE_CHANGED');
                if($operation==='apply')$selection=['from_version'=>$current,'to_version'=>$check['release']['tag'],'channel'=>$check['channel'],'repository'=>$check['repository'],'release_id'=>$check['release']['id']];
                else{
                    $backup=$engine['backup'];
                    if($backup===null||$backup['to_version']!==$current||!is_file($this->root.'/storage/updates/jobs/'.$backup['id'].'/baseline.json'))throw new HttpException(409,'UPDATE_BACKUP_UNAVAILABLE');
                    $selection=['from_version'=>$current,'to_version'=>$backup['from_version'],'channel'=>$check['channel'],'repository'=>$check['repository'],'release_id'=>null];
                }
                $this->commands->enqueue($actor,$operation,$selection,$commandRevision);return $selection;
            });
        };
        if($operation==='apply')$this->checks->withSelection($checkRevision,$accept);
        // Restoring a local generation remains available if GitHub is unreachable.
        else $this->checks->withState($checkRevision,$accept);
        return $this->status();
    }
}

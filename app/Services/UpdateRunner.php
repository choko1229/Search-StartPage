<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** One CLI worker operation, with durable request/job binding and DB projection replay. */
final class UpdateRunner
{
    public function __construct(private readonly string $root,private readonly UpdateCommands $commands,
        private readonly UpdateEngine $engine,private readonly \Closure $prepare) {}
    private static function matches(array $job,array $request):bool
    {
        return $request['operation']==='apply'
            ? $job['request_id']===$request['id']&&$job['rollback_request_id']===null&&$job['from_version']===$request['from_version']&&$job['to_version']===$request['to_version']
            : $job['rollback_request_id']===$request['id']&&$job['from_version']===$request['to_version']&&$job['to_version']===$request['from_version'];
    }
    private function remove(string $path):void
    {
        if(is_link($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        if(is_dir($path)){UpdatePackagePaths::directory($path);foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$this->remove($path.'/'.$name);if(!rmdir($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
        elseif(file_exists($path)&&(!is_file($path)||!unlink($path)))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    private function directory(string $path):void
    {
        UpdatePackagePaths::directory(dirname($path));if(!is_dir($path)&&!is_link($path)&&!mkdir($path,0700))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        UpdatePackagePaths::directory($path);if((fileperms($path)&0077)!==0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
    }
    private function finish(array $state,array $job):array
    {
        if(!self::matches($job,$state['request']))throw new HttpException(409,'UPDATE_STATE_CHANGED');
        $status=match($job['phase']){'complete'=>'complete','failed'=>'failed','rolled_back'=>'rolled_back',default=>'recovery_required'};
        $error=$job['error'];if($status==='recovery_required')$error='UPDATE_ROLLBACK_FAILED';
        $state=$this->commands->finish($state['request']['id'],$state['revision'],$status,$error);
        return $this->commands->reproject($state['revision']);
    }
    public function run():array
    {
        if(PHP_SAPI!=='cli')throw new HttpException(503,'UPDATE_PROCESS_UNAVAILABLE');
        $directory=$this->root.'/storage/updates/runner';$this->directory($directory);
        return LogFileLock::run($directory,fn()=>$this->execute());
    }
    private function execute():array
    {
        $state=$this->commands->status();$request=$state['request'];
        if($request===null)return $state;
        if(in_array($request['status'],UpdateCommands::TERMINAL,true))return $this->commands->reproject($state['revision']);
        $fresh=in_array($request['status'],['prepared','queued'],true);
        if($fresh)$state=$this->commands->claim($state['revision']);
        $request=$state['request'];$job=$this->engine->status()['job'];$incoming=$this->root.'/storage/updates/incoming';$work=$incoming.'/'.$request['id'];
        try{
            if(!$fresh){
                if($job===null||!self::matches($job,$request)){
                    // Never execute a claimed request again when its outcome is not bound.
                    $state=$this->commands->finish($request['id'],$state['revision'],'failed','UPDATE_INTERRUPTED');
                    return $this->commands->reproject($state['revision']);
                }
                $result=$this->engine->recover($request['id']);
            }elseif($request['operation']==='rollback'){
                $result=$this->engine->rollback($request['id'],$request['from_version'],$request['to_version']);
            }else{
                $this->directory($incoming);if(file_exists($work)||is_link($work))$this->remove($work);$this->directory($work);
                $manifest=($this->prepare)($request,$work.'/candidate.tar',$work.'/candidate');
                $result=$this->engine->apply($work.'/candidate.tar',$manifest,$request['id'],$request['from_version']);
            }
        }catch(\Throwable $error){
            $job=$this->engine->status()['job'];
            if($job!==null&&self::matches($job,$request)){
                try{$recovered=$this->engine->recover($request['id']);}
                catch(\Throwable){
                    // Keep the request open while the engine requires recovery.
                    $this->commands->finish($request['id'],$state['revision'],'recovery_required','UPDATE_ROLLBACK_FAILED');
                    throw new HttpException(503,'UPDATE_ROLLBACK_FAILED');
                }
                return $this->finish($state,$recovered['job']);
            }
            $code=$error instanceof HttpException&&in_array($error->errorCode,UpdateCommands::ERRORS,true)?$error->errorCode:'UPDATE_APPLY_FAILED';
            $state=$this->commands->finish($request['id'],$state['revision'],'failed',$code);
            return $this->commands->reproject($state['revision']);
        }finally{
            if(file_exists($work)||is_link($work))$this->remove($work);
        }
        return $this->finish($state,$result['job']);
    }
}

<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

/** Durable admin requests outside DB snapshots. Never executes an update itself. */
final class UpdateCommands
{
    public const TERMINAL=['complete','failed','rolled_back'];
    public const ERRORS=['FORBIDDEN','UPDATE_AUDIT_FAILED','UPDATE_INTERRUPTED','UPDATE_DOWNLOAD_FAILED','UPDATE_BACKUP_FAILED','UPDATE_APPLY_FAILED','UPDATE_MIGRATION_FAILED','UPDATE_HEALTH_FAILED','UPDATE_ROLLBACK_FAILED','UPDATE_TARGET_NOT_WRITABLE','UPDATE_SOURCE_CHANGED','UPDATE_SOURCE_NOT_FOUND','UPDATE_RATE_LIMITED','UPDATE_SOURCE_UNAVAILABLE','INVALID_UPDATE_RELEASE','INVALID_UPDATE_ASSET','INVALID_UPDATE_PACKAGE','UPDATE_PHP_REQUIRED','UPDATE_GATE_INCOMPATIBLE','UPDATE_BACKUP_UNAVAILABLE','UPDATE_RUNTIME_UNAVAILABLE'];
    public function __construct(private readonly string $directory,private readonly \Closure $authorize,
        private readonly \Closure $audit,private readonly \Closure $recorded,private readonly ?\Closure $clock=null) {}
    private static function keys(array $value,array $expected):bool {$actual=array_keys($value);sort($actual);sort($expected);return $actual===$expected;}
    public static function validateRequest(mixed $request):array
    {
        try{
            if(!is_array($request)||!self::keys($request,['id','operation','from_version','to_version','channel','repository','release_id','actor','status','created_at','updated_at','completed_at','error','audited'])
                ||!is_string($request['id'])||!preg_match('/^[a-f0-9]{32}$/D',$request['id'])||!in_array($request['operation'],['apply','rollback'],true)
                ||!is_string($request['from_version'])||!is_string($request['to_version'])||!is_string($request['channel'])||!is_string($request['repository'])
                ||!is_int($request['actor'])||$request['actor']<1||!in_array($request['status'],['prepared','queued','running','recovery_required',...self::TERMINAL],true)
                ||!is_int($request['created_at'])||$request['created_at']<1||!is_int($request['updated_at'])||$request['updated_at']<$request['created_at']||!is_bool($request['audited'])
                ||($request['error']!==null&&!in_array($request['error'],self::ERRORS,true)))throw new \RuntimeException();
            UpdateManifest::version($request['from_version']);UpdateManifest::version($request['to_version']);ReleaseCatalog::repository($request['repository']);ReleaseCatalog::channel($request['channel'],$request['channel']==='custom'?$request['to_version']:'');
            if($request['operation']==='apply'?(!is_int($request['release_id'])||$request['release_id']<1):$request['release_id']!==null)throw new \RuntimeException();
            if(in_array($request['status'],self::TERMINAL,true)){
                if(!is_int($request['completed_at'])||$request['completed_at']<$request['created_at']||$request['completed_at']>$request['updated_at'])throw new \RuntimeException();
            }elseif($request['completed_at']!==null)throw new \RuntimeException();
            if(in_array($request['status'],['prepared','queued','running','complete'],true)&&$request['error']!==null)throw new \RuntimeException();
            if(in_array($request['status'],['failed','recovery_required'],true)&&$request['error']===null)throw new \RuntimeException();
            if($request['status']==='prepared'&&$request['audited'])throw new \RuntimeException();
            return $request;
        }catch(\Throwable){throw new HttpException(503,'UPDATE_COMMAND_INVALID');}
    }
    private function run(callable $call):mixed
    {
        UpdatePackagePaths::directory(dirname($this->directory));if(is_link($this->directory))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        return LogFileLock::run($this->directory,function()use($call){UpdatePackagePaths::directory($this->directory);clearstatcache(true,$this->directory);if((fileperms($this->directory)&0077)!==0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');return $call();});
    }
    private static function validate(mixed $state):array
    {
        if(!is_array($state)||!self::keys($state,['format','revision','request','history'])||$state['format']!==1||!is_int($state['revision'])||$state['revision']<0||$state['revision']>9007199254740990||!is_array($state['history'])||!array_is_list($state['history'])||count($state['history'])>20)throw new HttpException(503,'UPDATE_COMMAND_INVALID');
        $seen=[];if($state['request']!==null){$request=self::validateRequest($state['request']);$seen[$request['id']]=true;}
        foreach($state['history'] as $request){self::validateRequest($request);if(!in_array($request['status'],self::TERMINAL,true)||!$request['audited']||isset($seen[$request['id']]))throw new HttpException(503,'UPDATE_COMMAND_INVALID');$seen[$request['id']]=true;}
        return $state;
    }
    private function read():array
    {
        $path=$this->directory.'/commands.json';clearstatcache(true,$path);if(is_link($path))throw new HttpException(503,'UPDATE_COMMAND_INVALID');
        if(!file_exists($path))return ['format'=>1,'revision'=>0,'request'=>null,'history'=>[]];
        if(!is_file($path)||filesize($path)>1048576||(fileperms($path)&0077)!==0)throw new HttpException(503,'UPDATE_COMMAND_INVALID');
        try{return self::validate(json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR));}catch(\Throwable){throw new HttpException(503,'UPDATE_COMMAND_INVALID');}
    }
    private function save(array &$state):void
    {
        if($state['revision']>=9007199254740990)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');++$state['revision'];self::validate($state);
        $bytes=json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$temporary=$this->directory.'/command-'.bin2hex(random_bytes(8)).'.tmp';$stream=@fopen($temporary,'xb');
        if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        try{
            if(!chmod($temporary,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset=0;
            while($offset<strlen($bytes)){$written=fwrite($stream,substr($bytes,$offset));if($written===false||$written===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$written;}
            if(!fflush($stream)||!fsync($stream)||!rename($temporary,$this->directory.'/commands.json'))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        }finally{fclose($stream);if(is_file($temporary)&&!unlink($temporary))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');}
    }
    private function now(array $state):int {$now=$this->clock?($this->clock)():time();if(!is_int($now)||$now<1)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');return max($now,$state['request']['updated_at']??0);}
    private static function revision(array $state,int $revision):void {if($revision!==$state['revision'])throw new HttpException(409,'UPDATE_STATE_CHANGED');}
    private function audit(array &$state,string $event):void
    {
        try{($this->audit)($state['request'],$event);}catch(\Throwable){throw new HttpException(503,'UPDATE_AUDIT_FAILED');}
        $state['request']['audited']=true;$state['request']['updated_at']=$this->now($state);$this->save($state);
    }
    private function reject(array &$state,string $error):void
    {
        $at=$this->now($state);$state['request']=array_replace($state['request'],['status'=>'failed','error'=>$error,'updated_at'=>$at,'completed_at'=>$at,'audited'=>false]);$this->save($state);
    }
    public function status():array {return $this->run(fn()=>$this->read());}
    public function enqueue(int $actor,string $operation,array $selection,int $revision):array
    {
        if(!self::keys($selection,['from_version','to_version','channel','repository','release_id']))throw new HttpException(422,'INVALID_INPUT');
        return $this->run(function()use($actor,$operation,$selection,$revision){
            $state=$this->read();self::revision($state,$revision);
            if(($this->authorize)($actor)!==true)throw new HttpException(403,'FORBIDDEN');
            if($state['request']!==null){
                if(!in_array($state['request']['status'],self::TERMINAL,true))throw new HttpException(409,'UPDATE_IN_PROGRESS');
                if(!$state['request']['audited'])$this->audit($state,'finished');
                array_unshift($state['history'],$state['request']);$state['history']=array_slice($state['history'],0,20);
            }
            $at=$this->now($state);$request=['id'=>bin2hex(random_bytes(16)),'operation'=>$operation,...$selection,'actor'=>$actor,'status'=>'prepared','created_at'=>$at,'updated_at'=>$at,'completed_at'=>null,'error'=>null,'audited'=>false];
            try{self::validateRequest($request);}catch(HttpException){throw new HttpException(422,'INVALID_INPUT');}$state['request']=$request;$this->save($state);
            try{($this->audit)($request,'requested');if(($this->recorded)($request)!==true)throw new \RuntimeException();}
            catch(\Throwable){$this->reject($state,'UPDATE_AUDIT_FAILED');throw new HttpException(503,'UPDATE_AUDIT_FAILED');}
            $state['request']['status']='queued';$state['request']['audited']=true;$state['request']['updated_at']=$this->now($state);$this->save($state);return $state;
        });
    }
    public function claim(int $revision):array
    {
        return $this->run(function()use($revision){
            $state=$this->read();self::revision($state,$revision);$request=$state['request'];
            if($request===null||!in_array($request['status'],['prepared','queued'],true))throw new HttpException(409,'UPDATE_IN_PROGRESS');
            try{$allowed=($this->authorize)($request['actor'])===true;$recorded=($this->recorded)($request)===true;}catch(\Throwable){throw new HttpException(503,'UPDATE_AUDIT_FAILED');}
            if(!$allowed||!$recorded){$error=$allowed?'UPDATE_AUDIT_FAILED':'FORBIDDEN';$this->reject($state,$error);try{$this->audit($state,'finished');}catch(\Throwable){}throw new HttpException($allowed?503:403,$error);}
            $state['request']=array_replace($request,['status'=>'running','updated_at'=>$this->now($state),'audited'=>false]);$this->save($state);
            try{$this->audit($state,'started');}catch(\Throwable){$this->reject($state,'UPDATE_AUDIT_FAILED');throw new HttpException(503,'UPDATE_AUDIT_FAILED');}return $state;
        });
    }
    public function finish(string $id,int $revision,string $status,?string $error=null):array
    {
        if(!in_array($status,[...self::TERMINAL,'recovery_required'],true)||($error!==null&&!in_array($error,self::ERRORS,true)))throw new HttpException(422,'INVALID_INPUT');
        return $this->run(function()use($id,$revision,$status,$error){
            $state=$this->read();self::revision($state,$revision);
            if(($state['request']['id']??null)!==$id||!in_array($state['request']['status'],['running','recovery_required'],true))throw new HttpException(409,'UPDATE_STATE_CHANGED');
            $at=$this->now($state);$state['request']=array_replace($state['request'],['status'=>$status,'error'=>$error,'updated_at'=>$at,'completed_at'=>in_array($status,self::TERMINAL,true)?$at:null,'audited'=>false]);
            try{self::validateRequest($state['request']);}catch(HttpException){throw new HttpException(422,'INVALID_INPUT');}$this->save($state);$this->audit($state,'finished');return $state;
        });
    }
    public function reconcile(int $revision):array
    {
        return $this->run(function()use($revision){$state=$this->read();self::revision($state,$revision);if($state['request']!==null&&$state['request']['status']!=='prepared'&&!$state['request']['audited'])$this->audit($state,$state['request']['status']==='running'?'started':'finished');return $state;});
    }
}

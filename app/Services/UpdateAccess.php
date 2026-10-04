<?php
declare(strict_types=1);
namespace App\Services;

/** No autoload/config/DB dependencies: entry points acquire this before loading app code. */
final class UpdateAccess
{
    public const PROTOCOL=1;
    private bool $exclusiveActive=false;
    private $exclusiveOwner=null;
    private $exclusiveAccess=null;
    public function __construct(private readonly string $directory) {}
    private function initialize(): void
    {
        $cursor=$this->directory;
        while(true){if(is_link($cursor))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');$parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;}
        if(!is_dir($this->directory)&&!@mkdir($this->directory,0700,true)&&!is_dir($this->directory))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
    }
    private function lock(string $name)
    {
        $this->initialize();$path=$this->directory.'/'.$name;
        if(is_link($path))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
        $stream=@fopen($path,'c');if($stream===false)throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
        if(!@chmod($path,0600)){fclose($stream);throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');}return $stream;
    }
    private function pending(): bool {return file_exists($this->directory.'/pending')||is_link($this->directory.'/pending');}
    public function enter(): ?UpdateAccessLease
    {
        $stream=$this->lock('access.lock');
        if(!flock($stream,LOCK_SH|LOCK_NB)){fclose($stream);return null;}
        if($this->pending()){flock($stream,LOCK_UN);fclose($stream);return null;}
        return new UpdateAccessLease($stream);
    }
    public function generation(): string
    {
        $this->initialize();$path=$this->directory.'/generation';
        if(!file_exists($path)&&!is_link($path))return '';
        if(is_link($path)||!is_file($path)||filesize($path)!==32)throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
        $generation=@file_get_contents($path);if(!is_string($generation)||!preg_match('/^[a-f0-9]{32}$/D',$generation))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');return $generation;
    }
    private function advance(): void
    {
        if(is_link($this->directory.'/generation'))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
        $temporary=@tempnam($this->directory,'epoch-');if($temporary===false)throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
        try{if(!@chmod($temporary,0600)||file_put_contents($temporary,bin2hex(random_bytes(16)))!==32||!rename($temporary,$this->directory.'/generation'))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');}
        finally{if(is_file($temporary))unlink($temporary);}
    }
    /** Exceptions/crashes retain the marker. Recovery is only for a verified update journal. */
    public function exclusive(callable $operation,bool $recover=false,int $timeout=30): mixed
    {
        if($timeout<1||$timeout>60)throw new \InvalidArgumentException('Invalid update drain timeout');
        $owner=$this->lock('operation.lock');$access=null;
        try{
            if(!flock($owner,LOCK_EX|LOCK_NB))throw new UpdateAccessPaused();
            if($this->pending()&&!$recover)throw new UpdateAccessPaused();
            if($this->pending()&&(is_link($this->directory.'/pending')||!is_file($this->directory.'/pending')))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
            if(!$this->pending()){
                $path=$this->directory.'/pending';$marker=@fopen($path,'xb');
                if($marker===false)throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');
                try{if(!@chmod($path,0600)||fwrite($marker,'update')!==6||!fflush($marker)||!fsync($marker))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');}finally{fclose($marker);}
            }
            $access=$this->lock('access.lock');$deadline=microtime(true)+$timeout;
            while(!flock($access,LOCK_EX|LOCK_NB)){if(microtime(true)>=$deadline)throw new UpdateAccessPaused();usleep(100000);}
            $this->exclusiveActive=true;$this->exclusiveOwner=$owner;$this->exclusiveAccess=$access;
            $result=$operation();$this->advance();
            if(!unlink($this->directory.'/pending'))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');return $result;
        }finally{
            $this->exclusiveActive=false;$this->exclusiveOwner=null;$this->exclusiveAccess=null;
            if(is_resource($access)){flock($access,LOCK_UN);fclose($access);}flock($owner,LOCK_UN);fclose($owner);
        }
    }
    /** Only a drained exclusive owner can hand its held lock descriptions to a child. */
    public function childDescriptors(): array
    {
        if(!$this->exclusiveActive||!is_resource($this->exclusiveAccess)||!is_resource($this->exclusiveOwner)||!$this->pending())throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');
        return [3=>$this->exclusiveAccess,4=>$this->exclusiveOwner];
    }
    /** Retain inherited locks through child shutdown, including if the parent exits. */
    public static function authorizeInherited(string $directory): UpdateAccessTaskLease
    {
        if(PHP_SAPI!=='cli'||!is_dir($directory))throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');
        $cursor=$directory;while(true){if(is_link($cursor))throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');$parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;}
        $pending=$directory.'/pending';if(is_link($pending)||!is_file($pending)||filesize($pending)!==6||file_get_contents($pending)!=='update')throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');
        $streams=[];
        try{
            foreach([3=>'access.lock',4=>'operation.lock'] as $descriptor=>$name){
                $path=$directory.'/'.$name;if(is_link($path)||!is_file($path))throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');
                $stream=@fopen('php://fd/'.$descriptor,'r+');if($stream===false)throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');$streams[]=$stream;
                $inherited=fstat($stream);$expected=stat($path);
                if($inherited===false||$expected===false||($inherited['mode']&0170000)!==0100000||$inherited['dev']!==$expected['dev']||$inherited['ino']!==$expected['ino'])throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');
                $probe=@fopen($path,'r+');if($probe===false)throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');
                try{
                    // Shared access must fail, proving an exclusive drain, not just a pending marker.
                    if(flock($probe,($descriptor===3?LOCK_SH:LOCK_EX)|LOCK_NB)){flock($probe,LOCK_UN);throw new \RuntimeException('UPDATE_TASK_NOT_AUTHORIZED');}
                }finally{fclose($probe);}
            }
            return new UpdateAccessTaskLease($streams);
        }catch(\Throwable $error){foreach($streams as $stream)fclose($stream);throw $error;}
    }
}
final class UpdateAccessTaskLease
{
    public function __construct(private array $streams) {}
    public function __destruct() {foreach($this->streams as $stream)if(is_resource($stream))fclose($stream);}
}
final class UpdateAccessLease
{
    public function __construct(private $stream) {}
    public function release(): void {if(is_resource($this->stream)){flock($this->stream,LOCK_UN);fclose($this->stream);$this->stream=null;}}
    public function __destruct() {$this->release();}
}
final class UpdateAccessPaused extends \RuntimeException
{
    public function __construct() {parent::__construct('UPDATE_IN_PROGRESS');}
}
final class UpdateAccessRestart extends \RuntimeException
{
    public function __construct() {parent::__construct('UPDATE_WORKER_RESTART');}
}

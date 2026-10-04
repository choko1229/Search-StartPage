<?php
declare(strict_types=1);
namespace App\Services;

/** No autoload/config/DB dependencies: entry points acquire this before loading app code. */
final class UpdateAccess
{
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
            $result=$operation();$this->advance();
            if(!unlink($this->directory.'/pending'))throw new \RuntimeException('UPDATE_ACCESS_UNAVAILABLE');return $result;
        }finally{
            if(is_resource($access)){flock($access,LOCK_UN);fclose($access);}flock($owner,LOCK_UN);fclose($owner);
        }
    }
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

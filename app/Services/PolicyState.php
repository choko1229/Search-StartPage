<?php
declare(strict_types=1);
namespace App\Services;

final class PolicyState
{
    public function __construct(private readonly string $directory) {}
    public function synchronized(callable $operation): mixed {return LogFileLock::run($this->directory,$operation);}
    public function read(): ?array
    {
        $path=$this->directory.'/policy.json';
        if (!is_file($path) || is_link($path)) return null;
        try {return SitePolicy::validate(json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR));}catch(\Throwable){return null;}
    }
    public function invalidate(): void
    {
        $path=$this->directory.'/policy.json';
        if(is_file($path)||is_link($path))if(!unlink($path))throw new \RuntimeException('Policy state unavailable');
    }
    public function publish(array $value): void
    {
        $contents=json_encode(SitePolicy::validate($value),JSON_THROW_ON_ERROR);
        $temporary=tempnam($this->directory,'policy-');
        if($temporary===false)throw new \RuntimeException('Policy state unavailable');
        try {if(file_put_contents($temporary,$contents,LOCK_EX)!==strlen($contents)||!chmod($temporary,0600)||!rename($temporary,$this->directory.'/policy.json'))throw new \RuntimeException('Policy state publication failed');}
        finally{if(is_file($temporary))unlink($temporary);}
    }
    public function resolve(\Closure $load): array
    {
        return $this->synchronized(function()use($load):array {
            $value=$this->read();
            if($value===null){$value=$load();$this->publish($value);}
            return $value;
        });
    }
}

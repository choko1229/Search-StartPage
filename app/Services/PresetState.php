<?php
declare(strict_types=1);
namespace App\Services;

final class PresetState
{
    public function __construct(private readonly string $directory){}
    public function synchronized(callable $operation):mixed {return LogFileLock::run($this->directory,$operation);}
    public function invalidate():void
    {
        $path=$this->directory.'/presets.json';
        if((is_file($path)||is_link($path))&&!unlink($path))throw new \RuntimeException('Preset state unavailable');
    }
    public function publish(array $value):void
    {
        $contents=json_encode(ProviderPresets::validate($value),JSON_THROW_ON_ERROR);$temporary=tempnam($this->directory,'presets-');
        if($temporary===false)throw new \RuntimeException('Preset state unavailable');
        try{if(file_put_contents($temporary,$contents,LOCK_EX)!==strlen($contents)||!chmod($temporary,0600)||!rename($temporary,$this->directory.'/presets.json'))throw new \RuntimeException('Preset publication failed');}
        finally{if(is_file($temporary))unlink($temporary);}
    }
    public function resolve(\Closure $load):array
    {
        return $this->synchronized(function()use($load):array{
            $path=$this->directory.'/presets.json';
            if(is_file($path)&&!is_link($path))try{return ProviderPresets::validate(json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR));}catch(\Throwable){}
            $value=$load();$this->publish($value);return $value;
        });
    }
}

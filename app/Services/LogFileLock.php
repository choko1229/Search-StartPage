<?php
declare(strict_types=1);
namespace App\Services;

final class LogFileLock
{
    public static function run(string $directory,callable $operation): mixed
    {
        if (is_link($directory)) throw new \RuntimeException('Log directory cannot be a link');
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Log directory unavailable');
        if (is_link($directory.'/.write.lock')) throw new \RuntimeException('Log lock cannot be a link');
        $lock=fopen($directory.'/.write.lock','c');
        if ($lock===false) throw new \RuntimeException('Log lock unavailable');
        try {
            if (!flock($lock,LOCK_EX) || !chmod($directory.'/.write.lock',0600)) throw new \RuntimeException('Log lock unavailable');
            return $operation();
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
}

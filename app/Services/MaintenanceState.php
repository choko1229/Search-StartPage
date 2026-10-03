<?php
declare(strict_types=1);
namespace App\Services;

/** Private durable signal lets normal local pages work without a DB connection. */
final class MaintenanceState
{
    public function __construct(private readonly string $directory) {}

    public function active(): bool
    {
        $path=$this->directory.'/maintenance.json';
        if (!is_file($path)) return true; // Unknown state is resolved from the DB.
        $contents=file_get_contents($path);
        $value=$contents===false ? null : json_decode($contents,true);
        return !is_array($value) || !array_key_exists('enabled',$value) || $value['enabled']!==false;
    }
    public function synchronized(callable $operation): mixed
    {
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new \RuntimeException('Maintenance storage unavailable');
        $lock=fopen($this->directory.'/maintenance.lock','c');
        if ($lock===false) throw new \RuntimeException('Maintenance lock unavailable');
        try {
            if (!flock($lock,LOCK_EX)) throw new \RuntimeException('Maintenance lock unavailable');
            return $operation();
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    /** Caller holds synchronized() across the DB commit and signal publication. */
    public function publish(bool $enabled): void
    {
        $temporary=tempnam($this->directory,'maintenance-');
        if ($temporary===false) throw new \RuntimeException('Maintenance storage unavailable');
        try {
            if (file_put_contents($temporary,json_encode(['enabled'=>$enabled],JSON_THROW_ON_ERROR),LOCK_EX)===false
                || !chmod($temporary,0600) || !rename($temporary,$this->directory.'/maintenance.json')) throw new \RuntimeException('Maintenance publication failed');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }
}

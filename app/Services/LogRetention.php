<?php
declare(strict_types=1);
namespace App\Services;

final class LogRetention
{
    public function __construct(private readonly \App\Repositories\LogRepository $repository,private readonly string $directory) {}

    public function run(?int $now=null): array
    {
        $now ??= time();
        $rows=$this->repository->purgeExpired($now);
        return LogFileLock::run($this->directory,fn():array=>$this->pruneFiles($now,$rows));
    }
    private function pruneFiles(int $now,int $rows): array
    {
        $removed=0; $entries=0;
        $cutoff=gmdate('Y-m-d',$now-90*86400);
        foreach (glob($this->directory.'/*.jsonl') ?: [] as $path) {
            $name=basename($path);
            if (is_link($path) || !is_file($path) || !preg_match('/^(\d{4}-\d{2}-\d{2})\.jsonl$/D',$name,$match)) continue;
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$match[1],new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d')!==$match[1] || $match[1]>$cutoff) continue;
            if ($match[1]===$cutoff) { $entries+=$this->pruneBoundary($path,$now-90*86400); continue; }
            if (!unlink($path)) throw new \RuntimeException('Expired log removal failed');
            ++$removed;
        }
        return ['database_rows'=>$rows,'files'=>$removed,'file_entries'=>$entries];
    }
    private function pruneBoundary(string $path,int $cutoff): int
    {
        $source=fopen($path,'r');
        $temporary=tempnam($this->directory,'retention-');
        if ($source===false || $temporary===false) throw new \RuntimeException('Log cleanup unavailable');
        $output=fopen($temporary,'w');
        if ($output===false) { fclose($source); unlink($temporary); throw new \RuntimeException('Log cleanup unavailable'); }
        $removed=0;
        try {
            while (($line=fgets($source))!==false) {
                $entry=json_decode($line,true);
                $at=is_array($entry) && is_string($entry['at'] ?? null) ? strtotime($entry['at']) : false;
                if ($at!==false && $at<$cutoff) { ++$removed; continue; }
                if (fwrite($output,$line)!==strlen($line)) throw new \RuntimeException('Log cleanup write failed');
            }
            if (!feof($source) || !fflush($output)) throw new \RuntimeException('Log cleanup read failed');
            fclose($source); $source=null; fclose($output); $output=null;
            if ($removed>0 && (!chmod($temporary,0600) || !rename($temporary,$path))) throw new \RuntimeException('Log cleanup publication failed');
            return $removed;
        } finally {
            if (is_resource($source)) fclose($source);
            if (is_resource($output)) fclose($output);
            if (is_file($temporary)) unlink($temporary);
        }
    }
}

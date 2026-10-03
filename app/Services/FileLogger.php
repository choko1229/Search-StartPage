<?php
declare(strict_types=1);

namespace App\Services;

final class FileLogger
{
    public function __construct(private readonly string $directory)
    {
    }

    public function exception(\Throwable $error, string $id): void
    {
        // Never persist exception messages, request bodies, tokens or SQL parameters.
        $this->write('error', 'UNHANDLED_EXCEPTION', [
            'request_id' => $id, 'class' => get_class($error),
            'file' => basename($error->getFile()), 'line' => $error->getLine(),
        ]);
    }

    public function write(string $level, string $code, array $safeContext = [],?int $timestamp=null): void
    {
        $timestamp ??= time();
        $entry = json_encode(['at' => gmdate(DATE_ATOM,$timestamp), 'level' => $level, 'code' => $code, 'context' => $safeContext], JSON_THROW_ON_ERROR);
        LogFileLock::run($this->directory,function() use($entry,$timestamp):void {
            $path=$this->directory.'/'.gmdate('Y-m-d',$timestamp).'.jsonl';
            if (is_link($path)) throw new \RuntimeException('Log file cannot be a link');
            $file=fopen($path,'c+b');
            if ($file===false) throw new \RuntimeException('Log write failed');
            $start=null;
            try {
                if (!flock($file,LOCK_EX) || fseek($file,0,SEEK_END)!==0) throw new \RuntimeException('Log write failed');
                $start=ftell($file);
                if ($start===false) throw new \RuntimeException('Log write failed');
                $payload=$entry."\n";$offset=0;
                while ($offset<strlen($payload)) {
                    $written=fwrite($file,substr($payload,$offset));
                    if ($written===false || $written===0) throw new \RuntimeException('Log write failed');
                    $offset+=$written;
                }
                if (!fflush($file)) throw new \RuntimeException('Log write failed');
            } catch (\Throwable $error) {
                if (is_int($start)) {ftruncate($file,$start);fflush($file);}
                throw $error;
            } finally {flock($file,LOCK_UN);fclose($file);}
        });
    }
}

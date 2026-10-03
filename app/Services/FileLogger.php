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
            if (is_link($this->directory.'/'.gmdate('Y-m-d',$timestamp).'.jsonl')) throw new \RuntimeException('Log file cannot be a link');
            if (file_put_contents($this->directory . '/' . gmdate('Y-m-d',$timestamp) . '.jsonl', $entry . "\n", FILE_APPEND | LOCK_EX) === false) throw new \RuntimeException('Log write failed');
        });
    }
}

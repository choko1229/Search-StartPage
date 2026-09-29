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

    public function write(string $level, string $code, array $safeContext = []): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Log directory unavailable');
        }
        $entry = json_encode(['at' => gmdate(DATE_ATOM), 'level' => $level, 'code' => $code, 'context' => $safeContext], JSON_THROW_ON_ERROR);
        if (file_put_contents($this->directory . '/' . gmdate('Y-m-d') . '.jsonl', $entry . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Log write failed');
        }
    }
}

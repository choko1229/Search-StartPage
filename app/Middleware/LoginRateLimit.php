<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Http\{HttpException, Request, Response};

final class LoginRateLimit
{
    public function __construct(private readonly string $directory, private readonly int $limit = 20, private readonly int $window = 60) {}

    public function __invoke(Request $request, callable $next): Response
    {
        // Trust the connection peer only, never client-supplied forwarding headers.
        $key = hash('sha256', $request->server['REMOTE_ADDR'] ?? 'unknown');
        $now = time();
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new HttpException(503, 'RATE_LIMIT_UNAVAILABLE');
        }
        $file = fopen($this->directory . '/login.json', 'c+');
        if (!$file) { throw new HttpException(503, 'RATE_LIMIT_UNAVAILABLE'); }
        try {
            if (!flock($file, LOCK_EX)) { throw new HttpException(503, 'RATE_LIMIT_UNAVAILABLE'); }
            chmod($this->directory . '/login.json', 0600);
            $raw = stream_get_contents($file);
            $rows = $raw === '' ? [] : json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            foreach ($rows as $id => $row) { if ($row['until'] <= $now) unset($rows[$id]); }
            if (!isset($rows[$key]) && count($rows) >= 10000) { throw new HttpException(503, 'RATE_LIMIT_UNAVAILABLE'); }
            $row = $rows[$key] ?? ['count'=>0, 'until'=>$now + max(1, $this->window)];
            $allowed = $row['count'] < max(1, $this->limit);
            if ($allowed) $row['count']++;
            $rows[$key] = $row;
            $json = json_encode($rows, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                throw new HttpException(503, 'RATE_LIMIT_UNAVAILABLE');
            }
        } finally { flock($file, LOCK_UN); fclose($file); }
        if (!$allowed) { throw new HttpException(429, 'RATE_LIMITED'); }
        return $next($request);
    }
}

<?php
declare(strict_types=1);

namespace App\Http;

final class Transport
{
    public static function allowsLocalHttp(array $server): bool
    {
        if (in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
            return true;
        }
        // Explicit opt-in for loopback-published Docker ports, never enabled in production.
        return getenv('SEARCH_LOCAL_DEVELOPMENT') === '1'
            && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:[0-9]+)?$/D', $server['HTTP_HOST'] ?? '') === 1;
    }
}

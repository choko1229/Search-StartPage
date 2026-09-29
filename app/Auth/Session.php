<?php
declare(strict_types=1);

namespace App\Auth;

use App\Config;

final class Session
{
    public static function start(Config $config): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($config->get('session.name', 'search_session'));
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/',
            'secure' => $config->get('session.secure', true),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
        if (!isset($_SESSION['created_at'])) {
            session_regenerate_id(true);
            $_SESSION['created_at'] = time();
        }
    }

    public static function csrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(string $token): bool
    {
        return $token !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}

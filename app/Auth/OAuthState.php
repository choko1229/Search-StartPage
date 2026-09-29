<?php
declare(strict_types=1);

namespace App\Auth;

use App\Http\HttpException;

final class OAuthState
{
    public static function issue(array &$session, int $now): string
    {
        $state = bin2hex(random_bytes(32));
        $session['oauth_state'] = ['hash' => hash('sha256', $state), 'expires' => $now + 600];
        return $state;
    }

    public static function consume(array &$session, mixed $state, int $now): void
    {
        $pending = $session['oauth_state'] ?? null;
        unset($session['oauth_state']);
        if (!is_string($state) || strlen($state) !== 64 || !is_array($pending)
            || $pending['expires'] <= $now || !hash_equals($pending['hash'], hash('sha256', $state))) {
            throw new HttpException(400, 'OAUTH_STATE_INVALID');
        }
    }
}

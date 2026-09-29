<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Session;
use App\Http\{HttpException, Request, Response};

final class Csrf
{
    public function __invoke(Request $request, callable $next): Response
    {
        $token = $request->server['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf');
        if (!is_string($token) || !Session::verifyCsrf($token)) {
            throw new HttpException(403, 'CSRF_INVALID');
        }
        return $next($request);
    }
}

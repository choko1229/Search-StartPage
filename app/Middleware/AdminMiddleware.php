<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Http\{Request,Response,HttpException};

final class AdminMiddleware
{
    /** Resolver restores authentication from the persisted login token. */
    public function __construct(private readonly \Closure $resolve) {}

    public function __invoke(Request $request, callable $next): Response
    {
        [$auth, $repository] = ($this->resolve)();
        $user = $auth->requireUser();
        // Always query current membership. Session/client flags never grant access.
        if (!$repository->isAdministrator((int)$user['id'])) throw new HttpException(403, 'ADMIN_REQUIRED');
        return $next($request);
    }
}

<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Auth;
use App\Config;
use App\Database\Database;
use App\Http\{Request, Response};
use App\Repositories\AuthRepository;

final class OptionalAuthentication
{
    public function __construct(private readonly Config $config) {}

    public function __invoke(Request $request, callable $next): Response
    {
        $cookie = $_COOKIE['search_remember'] ?? '';
        if ($this->config->get('installed') && is_string($cookie)
            && preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D', $cookie)) {
            try {
                $repository = new AuthRepository(Database::connect($this->config->get('database')));
                (new Auth($this->config, $repository))->restore();
            } catch (\PDOException) {
                // Local features need no authenticated identity during an outage.
                // Keep the remember cookie so a recovered DB can validate it again.
                unset($_SESSION['user_id'], $_SESSION['device_id']);
            }
        } else {
            unset($_SESSION['user_id'], $_SESSION['device_id']);
        }
        return $next($request);
    }
}

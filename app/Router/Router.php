<?php
declare(strict_types=1);

namespace App\Router;

use App\Http\{HttpException, Request, Response};

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $pattern = preg_quote($path, '~');
        $pattern = preg_replace('/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [$method, '~^' . $pattern . '$~D', $handler, $middleware];
    }

    public function dispatch(Request $request): Response
    {
        $matched = false;
        foreach ($this->routes as [$method, $pattern, $handler, $middleware]) {
            if (!preg_match($pattern, $request->path, $params)) {
                continue;
            }
            $matched = true;
            if ($method !== ($request->method === 'HEAD' ? 'GET' : $request->method)) {
                continue;
            }
            $params = array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY);
            $next = static fn (Request $r): Response => $handler($r, $params);
            foreach (array_reverse($middleware) as $layer) {
                $inner = $next;
                $next = static fn (Request $r): Response => $layer($r, $inner);
            }
            return $next($request);
        }
        throw new HttpException($matched ? 405 : 404, $matched ? 'METHOD_NOT_ALLOWED' : 'NOT_FOUND');
    }
}

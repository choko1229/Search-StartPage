<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Http\{Request, Response};
use App\Services\{WeatherCache, WeatherService};

final class WeatherController
{
    public function __construct(private readonly WeatherService $service, private readonly WeatherCache $cache) {}

    public function current(Request $request): Response
    {
        $region = WeatherService::region($request->body);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        return Response::json($this->cache->read($region, $this->service, time()));
    }
}

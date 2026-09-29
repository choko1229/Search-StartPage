<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\{HttpException, Request, Response};
use App\Services\MetadataService;

final class FavoriteMetadataController
{
    public function metadata(Request $request): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $url = $request->query['url'] ?? '';
        if (!is_string($url) || $url === '') { throw new HttpException(422, 'INVALID_INPUT'); }
        return Response::json((new MetadataService())->inspect($url));
    }
}

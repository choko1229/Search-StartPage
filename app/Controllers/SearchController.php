<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\{HttpException, Request, Response};
use App\Services\SuggestService;

final class SearchController
{
    public function suggest(Request $request): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $query = $request->query['q'] ?? '';
        if (!is_string($query) || mb_strlen($query) > 200) {
            throw new HttpException(422, 'INVALID_INPUT');
        }
        return Response::json(['suggestions' => trim($query) === '' ? [] : (new SuggestService())->suggest($query)]);
    }
}

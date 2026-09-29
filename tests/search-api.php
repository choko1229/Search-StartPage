<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') {
    exit(1);
}
foreach (['' => 200, 'q=php' => 200, 'q[]=invalid' => 422, 'q=' . str_repeat('a', 201) => 422] as $query => $expected) {
    $body = file_get_contents('http://127.0.0.1/api/search/suggest?' . $query, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
    $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!str_contains($http_response_header[0], (string) $expected) || $payload['success'] !== ($expected === 200)) {
        throw new RuntimeException('Unexpected suggest response');
    }
    if ($expected === 200 && !is_array($payload['data']['suggestions'])) {
        throw new RuntimeException('Invalid suggestions');
    }
}
echo "Search API: success, empty query, array rejection, length validation passed.\n";

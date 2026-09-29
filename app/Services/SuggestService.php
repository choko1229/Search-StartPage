<?php
declare(strict_types=1);

namespace App\Services;

final class SuggestService
{
    public function suggest(string $query): array
    {
        // The destination is fixed; user input is never used as a URL or host.
        $curl = curl_init('https://suggestqueries.google.com/complete/search?client=firefox&q=' . rawurlencode($query));
        $body = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT_MS => 400,
            CURLOPT_TIMEOUT_MS => 1000,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 65536) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok === false || $status !== 200) {
            return [];
        }
        $payload = json_decode($body, true);
        if (!is_array($payload) || !is_array($payload[1] ?? null)) {
            return [];
        }
        return array_values(array_slice(array_filter($payload[1], static fn ($value) => is_string($value) && mb_strlen($value) <= 200), 0, 8));
    }
}

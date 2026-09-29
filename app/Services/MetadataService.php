<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;

final class MetadataService
{
    public function inspect(string $url): array
    {
        if (!class_exists(\DOMDocument::class)) { throw new HttpException(422, 'METADATA_UNAVAILABLE'); }
        for ($hop = 0; $hop < 4; $hop++) {
            [$body, $headers, $status] = $this->fetch($url);
            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                $url = $this->absolute($url, $headers['location']);
                continue;
            }
            if ($status !== 200 || !str_contains(strtolower($headers['content-type'] ?? ''), 'text/html')) {
                throw new HttpException(422, 'METADATA_UNAVAILABLE');
            }
            $previous = libxml_use_internal_errors(true);
            try {
                $dom = new \DOMDocument();
                $dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($dom);
                $read = static function (string $path) use ($xpath): string {
                    return trim((string) $xpath->evaluate('string(' . $path . ')'));
                };
                $title = $read('//meta[@property="og:title"]/@content') ?: $read('//title');
                $description = $read('//meta[@property="og:description"]/@content') ?: $read('//meta[@name="description"]/@content');
                $icon = $read('//link[contains(concat(" ", normalize-space(@rel), " "), " icon ")]/@href');
                $image = $read('//meta[@property="og:image"]/@content');
                return [
                    'title' => mb_substr($title, 0, 100),
                    'description' => mb_substr($description, 0, 2000),
                    'favicon' => $this->absolute($url, $icon ?: '/favicon.ico'),
                    'image' => $image !== '' ? $this->absolute($url, $image) : null,
                ];
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }
        throw new HttpException(422, 'METADATA_UNAVAILABLE');
    }

    public function publicDestination(string $url): array
    {
        $parts = parse_url($url);
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])
            || !preg_match('/^[a-zA-Z0-9.-]+$/D', $parts['host'] ?? '')) {
            throw new HttpException(422, 'INVALID_INPUT');
        }
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        if (!in_array($port, [80, 443], true)) {
            throw new HttpException(422, 'INVALID_INPUT');
        }
        $addresses = filter_var($parts['host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? [$parts['host']] : (gethostbynamel($parts['host']) ?: []);
        if ($addresses === []) {
            throw new HttpException(422, 'METADATA_UNAVAILABLE');
        }
        foreach ($addresses as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || str_starts_with($ip, '169.254.') || preg_match('/^100\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\./', $ip)) {
                throw new HttpException(422, 'PRIVATE_ADDRESS');
            }
            foreach (['192.0.0.0/24','192.0.2.0/24','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4'] as $range) {
                [$network, $bits] = explode('/', $range);
                $mask = -1 << (32 - (int) $bits);
                if ((ip2long($ip) & $mask) === (ip2long($network) & $mask)) { throw new HttpException(422, 'PRIVATE_ADDRESS'); }
            }
        }
        return [$parts['host'], $port, $addresses[0]];
    }

    private function fetch(string $url): array
    {
        [$host, $port, $ip] = $this->publicDestination($url);
        $body = ''; $headers = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RESOLVE => ["$host:$port:$ip"], CURLOPT_NOPROXY => '*',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => 800, CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_USERAGENT => 'SearchStartPage-Metadata/0.1',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $headers[strtolower(trim($name))] = trim($value); }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 524288) { return 0; }
                $body .= $chunk; return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok === false) { throw new HttpException(422, 'METADATA_UNAVAILABLE'); }
        return [$body, $headers, $status];
    }

    private function absolute(string $base, string $path): string
    {
        if (preg_match('~^https?://~i', $path)) { return $path; }
        $parts = parse_url($base);
        if (str_starts_with($path, '//')) { return $parts['scheme'] . ':' . $path; }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) { return ''; }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . (str_starts_with($path, '/') ? $path : rtrim(dirname($parts['path'] ?? '/'), '/.') . '/' . $path);
    }
}

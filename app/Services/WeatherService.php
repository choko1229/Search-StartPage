<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;

final class WeatherService
{
    public function __construct(private readonly array $configuration = [], private readonly ?\Closure $transport = null) {}

    public static function region(array $input): array
    {
        if (array_diff(array_keys($input), ['latitude', 'longitude']) !== []) self::invalid();
        $result = [];
        foreach (['latitude'=>90, 'longitude'=>180] as $key=>$limit) {
            $value = $input[$key] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || abs($value) > $limit) self::invalid();
            $result[$key] = round((float)$value, 2);
        }
        return $result;
    }

    public function current(array $region, int $now): array
    {
        $region = self::region($region);
        $this->cacheIdentity();
        $mode = $this->configuration['mode'] ?? 'non-commercial';
        $query = $region + ['current'=>'temperature_2m,weather_code', 'temperature_unit'=>'celsius', 'timeformat'=>'unixtime', 'timezone'=>'GMT'];
        if ($mode === 'customer') $query['apikey'] = $this->configuration['api_key'];
        $host = $mode === 'customer' ? 'customer-api.open-meteo.com' : 'api.open-meteo.com';
        $url = 'https://' . $host . '/v1/forecast?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        try {
            $body = $this->transport !== null ? ($this->transport)($url) : $this->fetch($url);
            if (!is_string($body) || strlen($body) > 32768) self::unavailable();
            $payload = json_decode($body, true, 12, JSON_THROW_ON_ERROR);
        } catch (\Throwable) { self::unavailable(); }
        $current = $payload['current'] ?? null;
        $temperature = $current['temperature_2m'] ?? null;
        $code = $current['weather_code'] ?? null;
        $time = $current['time'] ?? null;
        if (!is_array($current) || ($payload['current_units']['temperature_2m'] ?? '') !== '°C'
            || (!is_int($temperature) && !is_float($temperature)) || !is_finite((float)$temperature)
            || $temperature < -100 || $temperature > 70 || !is_int($code) || !is_int($time)
            || $time < $now - 7200 || $time > $now + 900) self::unavailable();
        $weather = match ($code) {
            0, 1 => 'clear', 2, 3 => 'cloudy', 45, 48 => 'fog',
            51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82 => 'rain',
            71, 73, 75, 77, 85, 86 => 'snow', 95, 96, 97, 99 => 'storm',
            default => null,
        };
        if ($weather === null) self::unavailable();
        return ['weather'=>$weather, 'temperature'=>$temperature, 'observedAt'=>$time, 'expiresAt'=>$now + 900];
    }

    public function cacheIdentity(): string
    {
        if (($this->configuration['enabled'] ?? true) !== true) self::unavailable();
        $mode = $this->configuration['mode'] ?? 'non-commercial';
        if (!in_array($mode, ['non-commercial', 'customer'], true)) self::unavailable();
        if ($mode === 'customer') {
            $key = $this->configuration['api_key'] ?? '';
            if (!is_string($key) || $key === '' || strlen($key) > 512) self::unavailable();
        }
        return hash('sha256', $mode . '\0' . ($mode === 'customer' ? $key : ''));
    }

    private function fetch(string $url): string
    {
        // Only the two fixed HTTPS provider hosts can reach this method. Never follow redirects.
        $context = stream_context_create([
            'http'=>['timeout'=>4, 'follow_location'=>0, 'max_redirects'=>0, 'ignore_errors'=>true,
                'header'=>"Accept: application/json\r\nUser-Agent: SearchStartPage-Weather/1.0\r\nConnection: close\r\n"],
            'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true],
        ]);
        $deadline = microtime(true) + 4;
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) self::unavailable();
        try {
            $headers = $http_response_header ?? [];
            if (!preg_match('~^HTTP/\S+ 200(?: |$)~', $headers[0] ?? '')) self::unavailable();
            $json = false;
            foreach ($headers as $header) if (preg_match('~^Content-Type:\s*application/json(?:[;\s]|$)~i', $header)) $json = true;
            if (!$json) self::unavailable();
            $body = '';
            while (!feof($stream)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) self::unavailable();
                stream_set_timeout($stream, (int)$remaining, (int)(($remaining - (int)$remaining) * 1000000));
                $chunk = @fread($stream, min(8192, 32769 - strlen($body)));
                if ($chunk === false || stream_get_meta_data($stream)['timed_out']) self::unavailable();
                $body .= $chunk;
                if (strlen($body) > 32768) self::unavailable();
            }
            return $body;
        } finally { fclose($stream); }
    }

    private static function invalid(): never { throw new HttpException(422, 'INVALID_INPUT'); }
    private static function unavailable(): never { throw new HttpException(503, 'WEATHER_UNAVAILABLE'); }
}

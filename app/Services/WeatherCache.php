<?php
declare(strict_types=1);
namespace App\Services;

use App\Http\HttpException;

final class WeatherCache
{
    public function __construct(private readonly string $directory) {}

    public function read(array $region, WeatherService $service, int $now): array
    {
        $region = WeatherService::region($region);
        $key = hash('sha256', $service->cacheIdentity() . json_encode($region, JSON_THROW_ON_ERROR));
        // A bounded set of slots avoids unbounded files from arbitrary coordinates.
        $path = $this->directory . '/' . substr($key, 0, 2) . '.json';
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) self::unavailable();
        $file = @fopen($path, 'c+');
        if ($file === false) self::unavailable();
        try {
            if (!flock($file, LOCK_EX)) self::unavailable();
            @chmod($path, 0600);
            $raw = stream_get_contents($file, 4097);
            $cached = is_string($raw) && strlen($raw) <= 4096 ? json_decode($raw, true) : null;
            if (is_array($cached) && ($cached['key'] ?? '') === $key && ($cached['until'] ?? 0) > $now) {
                if (!isset($cached['data'])) self::unavailable();
                $data = $cached['data'];
                if (is_array($data) && in_array($data['weather'] ?? null, ['clear','cloudy','fog','rain','snow','storm'], true)
                    && (is_int($data['temperature'] ?? null) || is_float($data['temperature'] ?? null))
                    && is_finite((float)$data['temperature']) && $data['temperature'] >= -100 && $data['temperature'] <= 70
                    && is_int($data['observedAt'] ?? null) && $data['observedAt'] >= $now - 7200 && $data['observedAt'] <= $now + 900
                    && is_int($data['expiresAt'] ?? null) && $data['expiresAt'] > $now && $data['expiresAt'] <= $now + 900) return $data;
            }
            try { $data = $service->current($region, $now); }
            catch (HttpException $error) {
                $this->write($file, ['key'=>$key, 'until'=>$now + 60]);
                throw $error;
            }
            $this->write($file, ['key'=>$key, 'until'=>$data['expiresAt'], 'data'=>$data]);
            return $data;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }

    private function write($file, array $value): void
    {
        $raw = json_encode($value, JSON_THROW_ON_ERROR);
        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $raw) !== strlen($raw) || !fflush($file)) self::unavailable();
    }

    private static function unavailable(): never { throw new HttpException(503, 'WEATHER_UNAVAILABLE'); }
}

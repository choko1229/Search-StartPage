<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $server = [],
        public readonly ?object $jsonObject = null,
    ) {
    }

    public static function capture(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $body = $_POST;
        $jsonObject = null;
        if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $raw = file_get_contents('php://input', false, null, 0, 1048577);
            if ($raw === false || strlen($raw) > 1048576) {
                throw new HttpException(413, 'BODY_TOO_LARGE');
            }
            try {
                $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                $jsonObject = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new HttpException(400, 'INVALID_JSON');
            }
            if (!is_array($body) || !str_starts_with(ltrim($raw), '{')) {
                throw new HttpException(400, 'INVALID_JSON');
            }
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), is_string($path) ? $path : '/', $_GET, $body, $_SERVER, $jsonObject);
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $default;
        if (!is_string($value)) {
            throw new HttpException(422, 'INVALID_INPUT');
        }
        return $value;
    }
}

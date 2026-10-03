<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(public readonly string $body = '', public readonly int $status = 200, public readonly array $headers = [], private readonly ?\Closure $stream = null,public readonly ?string $errorCode=null)
    {
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(json_encode(['success' => true, 'data' => (object) $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function error(string $code, string $message, int $status): self
    {
        return new self(json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $status, ['Content-Type' => 'application/json; charset=utf-8'],errorCode:$code);
    }

    public static function redirect(string $path): self
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n]/', $path)) {
            throw new \InvalidArgumentException('Local redirect required');
        }
        return new self('', 303, ['Location' => $path]);
    }

    public function send(): void
    {
        \App\Exceptions\ErrorHandler::logResponse($this);
        http_response_code($this->status);
        $headers = $this->headers + [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https:; img-src 'self' data: blob: https:; media-src 'self' blob: https:; connect-src 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'; form-action 'self'",
        ];
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            if ($this->stream !== null) { ($this->stream)(); } else { echo $this->body; }
        }
    }
}

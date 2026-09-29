<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Helpers\{Translator, View};
use App\Http\{HttpException, Response};
use App\Services\FileLogger;

final class ErrorHandler
{
    public static function register(string $root): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        set_exception_handler(static fn (\Throwable $error) => self::render($error, $root));
        register_shutdown_function(static function () use ($root): void {
            $last = error_get_last();
            if ($last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::render(new \ErrorException('Fatal error', 0, $last['type'], $last['file'], $last['line']), $root);
            }
        });
    }

    private static function render(\Throwable $error, string $root): void
    {
        $status = $error instanceof HttpException ? $error->status : 500;
        $code = $error instanceof HttpException ? $error->errorCode : 'INTERNAL_ERROR';
        $id = bin2hex(random_bytes(8));
        if ($status >= 500) {
            try {
                (new FileLogger($root . '/storage/logs'))->exception($error, $id);
            } catch (\Throwable) {
                error_log('SEARCH_LOG_UNAVAILABLE request_id=' . $id);
            }
        }
        $locale = $_SESSION['locale'] ?? Translator::preferred($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $translator = new Translator($root, $locale);
        $message = $translator->get($code);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $response = str_starts_with($path, '/api/')
            ? Response::error($code, $message, $status)
            : new Response((new View($root, $translator))->render('error', ['message' => $message, 'request_id' => $id]), $status);
        $response->send();
    }
}

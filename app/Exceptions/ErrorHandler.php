<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Helpers\{Translator, View};
use App\Http\{HttpException, Response};
use App\Services\{FileLogger,ApplicationLogger};

final class ErrorHandler
{
    private static ?string $root=null;
    private static bool $logged=false;

    public static function register(string $root): void
    {
        self::$root=$root;self::$logged=false;
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

    public static function logCaught(\Throwable $error): bool
    {
        if (self::$root!==null && !self::$logged) self::record($error,self::$root,bin2hex(random_bytes(8)));
        return self::$root!==null;
    }

    public static function logResponse(Response $response): void
    {
        if (self::$root===null || self::$logged || $response->status<400) return;
        $candidate=$response->errorCode;
        $code=is_string($candidate) && preg_match('/^[A-Z][A-Z0-9_]{0,79}$/D',$candidate) ? $candidate : 'HTTP_ERROR';
        self::record(new HttpException($response->status,$code),self::$root,bin2hex(random_bytes(8)));
    }

    private static function record(\Throwable $error,string $root,string $id): void
    {
        self::$logged=true;
        $status = $error instanceof HttpException ? $error->status : 500;
        $code = $error instanceof HttpException ? $error->errorCode : 'INTERNAL_ERROR';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($status>=500 || str_starts_with($path,'/api/') || in_array($status,[401,403,429],true) || preg_match('~^/auth/discord(?:/|$)~',$path)) {
            try {
                $repository=null;
                if ($code!=='DATABASE_UNAVAILABLE') {
                    try {
                        $config=\App\Config::load($root);
                        if ($config->get('installed')) $repository=new \App\Repositories\LogRepository(\App\Database\Database::connect($config->get('database')));
                    }catch(\Throwable) { /* The durable queue preserves the record during DB outages. */ }
                }
                (new ApplicationLogger($repository,new FileLogger($root.'/storage/logs'),$root.'/storage/log-pending'))
                    ->record($error,$path,$id,isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,$_SERVER['REQUEST_METHOD'] ?? 'GET');
            } catch (\Throwable) {
                try {(new FileLogger($root.'/storage/logs'))->exception($error,$id);}catch(\Throwable){}
                error_log('SEARCH_LOG_UNAVAILABLE request_id=' . $id);
            }
        }
    }

    private static function render(\Throwable $error, string $root): void
    {
        $status=$error instanceof HttpException ? $error->status : 500;
        $code=$error instanceof HttpException ? $error->errorCode : 'INTERNAL_ERROR';
        $id=bin2hex(random_bytes(8));
        self::record($error,$root,$id);
        $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
        $locale = $_SESSION['locale'] ?? Translator::preferred($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $translator = new Translator($root, $locale);
        $message = $translator->get($code);
        $response = str_starts_with($path, '/api/')
            ? Response::error($code, $message, $status)
            : new Response((new View($root, $translator))->render('error', ['message' => $message, 'request_id' => $id]), $status);
        $response->send();
    }
}

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/autoload.php';

use App\Auth\Session;
use App\Config;
use App\Helpers\{Translator, View};
use App\Http\{HttpException, Request, Response};
use App\Middleware\Csrf;
use App\Router\Router;
use App\Services\{FileLogger, InstallationService};

set_error_handler(static function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});
$passed = 0;
$check = static function (bool $condition, string $label) use (&$passed): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $passed++;
    echo "PASS: $label\n";
};
$expect = static function (callable $fn, string $code) use ($check): void {
    try {
        $fn();
    } catch (HttpException $error) {
        $check($error->errorCode === $code, $code);
        return;
    }
    throw new RuntimeException('Expected ' . $code);
};

$config = new Config(['nested' => ['value' => false]]);
$check($config->get('nested.value', true) === false, 'config retains false');
$check($config->get('missing', 'fallback') === 'fallback', 'config fallback');
$check(View::escape('<script>"&') === '&lt;script&gt;&quot;&amp;', 'HTML escaping');
$check(Translator::preferred('ja-JP, en;q=0.8') === 'ja', 'Japanese browser locale');
$check(Translator::preferred('fr-FR') === 'en', 'other browser defaults to English');
$check(Translator::preferred('ja;q=0.2,en;q=0.9') === 'en', 'language quality ordering');
$check(Translator::preferred('ja;q=0') === 'en', 'excluded locale');
$root = dirname(__DIR__);
$ja = require $root . '/lang/ja.php';
$en = require $root . '/lang/en.php';
$check(array_keys($ja) === array_keys($en), 'translation key parity');
$check($ja['search_placeholder'] !== $ja['search_placeholder_color'] && $en['search_placeholder'] !== $en['search_placeholder_color'], 'search prompt differs from color setting label');
$check((new Translator($root, '../../config/config'))->locale === 'en', 'locale traversal rejected');

$router = new Router();
$order = [];
$router->add('GET', '/things/{id}', static fn (Request $r, array $p): Response => Response::json(['id' => $p['id']]), [
    static function (Request $r, callable $next) use (&$order): Response {
        $order[] = 'before';
        $result = $next($r);
        $order[] = 'after';
        return $result;
    },
]);
$response = $router->dispatch(new Request('GET', '/things/42'));
$check(json_decode($response->body, true)['data']['id'] === '42', 'route parameter capture');
$check($order === ['before', 'after'], 'middleware composition');
$check($router->dispatch(new Request('HEAD', '/things/42'))->status === 200, 'HEAD route support');
$expect(static fn () => $router->dispatch(new Request('GET', '/things/42/other')), 'NOT_FOUND');
$expect(static fn () => $router->dispatch(new Request('POST', '/things/42')), 'METHOD_NOT_ALLOWED');
$router->add('GET', '/literal.html', static fn () => new Response('ok'));
$expect(static fn () => $router->dispatch(new Request('GET', '/literalXhtml')), 'NOT_FOUND');
$check(json_decode(Response::json([])->body)->data instanceof stdClass, 'empty API data is object');
$check(json_decode(Response::error('TEST', 'message', 422)->body, true)['success'] === false, 'API error envelope');
$check(Response::redirect('/installer')->status === 303, 'POST redirect status');
foreach (['//evil.example', "https://evil.example", "/\r\nX-Test: yes"] as $bad) {
    try {
        Response::redirect($bad);
        throw new RuntimeException('Unsafe redirect accepted');
    } catch (InvalidArgumentException) {
        $check(true, 'unsafe redirect rejected');
    }
}

$_SESSION = [];
$token = Session::csrf();
$check(strlen($token) === 64 && $token === Session::csrf(), 'stable random CSRF token');
$check(!Session::verifyCsrf('') && !Session::verifyCsrf('wrong'), 'invalid CSRF tokens rejected');
$middleware = new Csrf();
$expect(static fn () => $middleware(new Request('POST', '/'), static fn () => new Response()), 'CSRF_INVALID');
$check($middleware(new Request('POST', '/', [], ['_csrf' => $token]), static fn () => new Response('ok'))->body === 'ok', 'form CSRF accepted');
$check($middleware(new Request('PUT', '/', [], [], ['HTTP_X_CSRF_TOKEN' => $token]), static fn () => new Response('ok'))->body === 'ok', 'API CSRF accepted');
$expect(static fn () => (new Request('POST', '/', [], ['field' => []]))->input('field'), 'INVALID_INPUT');

$installer = new InstallationService($root);
$check($installer->validateSite('https://example.com/') === 'https://example.com', 'site URL normalization');
$check($installer->validateSite('http://localhost:8080') === 'http://localhost:8080', 'loopback HTTP permitted');
foreach (['https://user@example.com', 'https://example.com/sub', 'https://example.com/?secret=value', 'https://example.com/#fragment', 'javascript:alert(1)'] as $url) {
    $expect(static fn () => $installer->validateSite($url), 'INVALID_INPUT');
}
$expect(static fn () => $installer->validateSite('http://example.com'), 'HTTPS_REQUIRED');
$view = new View($root, new Translator($root, 'ja'));
$html = $view->render('home', ['site_name' => '<img src=x onerror=alert(1)>']);
$check(!str_contains($html, '<img') && str_contains($html, '&lt;img'), 'view escapes untrusted site name');
$check(str_contains($html, 'lang="ja"'), 'translated document language');
try {
    $view->render('../config/config');
    throw new RuntimeException('Traversal accepted');
} catch (InvalidArgumentException) {
    $check(true, 'view traversal rejected');
}
$temp = sys_get_temp_dir() . '/search-log-test-' . bin2hex(random_bytes(8));
$logger = new FileLogger($temp);
$logger->exception(new RuntimeException('secret-password-marker'), 'test-id');
$log = file_get_contents($temp . '/' . gmdate('Y-m-d') . '.jsonl');
$check(str_contains($log, 'test-id') && !str_contains($log, 'secret-password-marker'), 'exception logs exclude secret messages');
unlink($temp . '/' . gmdate('Y-m-d') . '.jsonl');
unlink($temp . '/.write.lock');
rmdir($temp);
echo "$passed assertions passed.\n";

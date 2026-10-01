<?php
declare(strict_types=1);

use App\Auth\Session;
use App\Config;
use App\Controllers\{CoreController, InstallerController};
use App\Exceptions\ErrorHandler;
use App\Helpers\{Translator, View};
use App\Http\Request;
use App\Middleware\Csrf;
use App\Router\Router;
use App\Services\{EnvironmentCheck, FileLogger, InstallationService};

require __DIR__ . '/autoload.php';
$root = dirname(__DIR__);
ErrorHandler::register($root);
$config = Config::load($root);
$sessionConfig = $config;
if (!$config->get('installed') && App\Http\Transport::allowsLocalHttp($_SERVER)) {
    $sessionConfig = new Config(['session' => ['name' => 'search_session', 'secure' => false]]);
}
Session::start($sessionConfig);
$translator = new Translator($root, $_SESSION['locale'] ?? Translator::preferred($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
$view = new View($root, $translator);
$core = new CoreController($config, $view);
$installer = new InstallerController(new InstallationService($root), new EnvironmentCheck($root), $view, new FileLogger($root . '/storage/logs'));
$router = new Router();
if ($config->get('installed')) {
    // Authentication is resolved only for routes that need it. Local search and
    // favorites remain available when the database cannot be reached.
    $account = null;
    $handler = static function (string $method) use (&$account, $config, $view): Closure {
        return static function (Request $request, array $params = []) use (&$account, $config, $view, $method): App\Http\Response {
            if ($account === null) {
                try { $pdo = App\Database\Database::connect($config->get('database')); }
                catch (PDOException) { throw new App\Http\HttpException(503, 'DATABASE_UNAVAILABLE'); }
                $repository = new App\Repositories\AuthRepository($pdo);
                $auth = new App\Auth\Auth($config, $repository);
                $auth->restore();
                $account = new App\Controllers\AccountController($auth, $repository, new App\Services\DiscordOAuth($config), $view);
            }
            return $account->$method($request, $params);
        };
    };
    $loginLimit = new App\Middleware\LoginRateLimit($root . '/storage/rate-limits',
        (int)$config->get('login_rate_limit.attempts', 20), (int)$config->get('login_rate_limit.window_seconds', 60));
    $router->add('GET', '/account', $handler('account'));
    $router->add('POST', '/auth/discord', $handler('start'), [$loginLimit, new Csrf()]);
    $router->add('GET', '/auth/discord/callback', $handler('callback'), [$loginLimit]);
    $router->add('POST', '/auth/logout', $handler('logout'), [new Csrf()]);
    $router->add('POST', '/account/device', $handler('device'), [new Csrf()]);
    $router->add('GET', '/api/auth/discord', $handler('start'), [$loginLimit]);
    $router->add('GET', '/api/auth/discord/callback', $handler('callback'), [$loginLimit]);
    $router->add('POST', '/api/auth/logout', $handler('logout'), [new Csrf()]);
    $router->add('GET', '/api/user', $handler('currentUser'));
    $router->add('GET', '/api/user/devices', $handler('devices'));
    $router->add('DELETE', '/api/user/devices/{id}', $handler('revokeDevice'), [new Csrf()]);
    $syncHandler = static function(string $method) use ($config): Closure {
        return static function(Request $request) use ($config,$method): App\Http\Response {
            try {$pdo=App\Database\Database::connect($config->get('database'));}
            catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            $auth=new App\Auth\Auth($config,new App\Repositories\AuthRepository($pdo));$auth->restore();
            return (new App\Controllers\SyncController($auth,new App\Repositories\SyncRepository($pdo)))->$method($request);
        };
    };
    $router->add('GET','/api/sync',$syncHandler('read'));
    $router->add('PUT','/api/sync',$syncHandler('write'),[new Csrf()]);
}
$optionalAuth = new App\Middleware\OptionalAuthentication($config);
$router->add('GET', '/', $core->home(...), [$optionalAuth]);
$router->add('GET', '/api/health', $core->health(...));
$router->add('GET', '/api/csrf', $core->csrf(...), [$optionalAuth]);
$router->add('GET', '/api/search/suggest', (new App\Controllers\SearchController())->suggest(...), [$optionalAuth]);
$router->add('GET', '/api/favorites/metadata', (new App\Controllers\FavoriteMetadataController())->metadata(...), [$optionalAuth]);
$router->add('POST', '/locale', $core->locale(...), [new Csrf()]);
$router->add('GET', '/installer', $installer->handle(...));
$router->add('POST', '/installer', $installer->handle(...), [new Csrf()]);
$router->dispatch(Request::capture())->send();

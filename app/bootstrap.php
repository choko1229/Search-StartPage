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
    $authRepository = new App\Repositories\AuthRepository(App\Database\Database::connect($config->get('database')));
    $auth = new App\Auth\Auth($config, $authRepository);
    $auth->restore();
    $account = new App\Controllers\AccountController($auth, $authRepository, new App\Services\DiscordOAuth($config), $view);
    $router->add('GET', '/account', $account->account(...));
    $router->add('POST', '/auth/discord', $account->start(...), [new Csrf()]);
    $router->add('GET', '/auth/discord/callback', $account->callback(...));
    $router->add('POST', '/auth/logout', $account->logout(...), [new Csrf()]);
    $router->add('POST', '/account/device', $account->device(...), [new Csrf()]);
}
$router->add('GET', '/', $core->home(...));
$router->add('GET', '/api/health', $core->health(...));
$router->add('GET', '/api/csrf', $core->csrf(...));
$router->add('GET', '/api/search/suggest', (new App\Controllers\SearchController())->suggest(...));
$router->add('GET', '/api/favorites/metadata', (new App\Controllers\FavoriteMetadataController())->metadata(...));
$router->add('POST', '/locale', $core->locale(...), [new Csrf()]);
$router->add('GET', '/installer', $installer->handle(...));
$router->add('POST', '/installer', $installer->handle(...), [new Csrf()]);
$router->dispatch(Request::capture())->send();

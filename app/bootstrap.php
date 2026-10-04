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
$presetState=new App\Services\PresetState($root.'/storage/presets');
$resolvePresets=static function()use($presetState,$config):array {
    try{return App\Services\ProviderPresets::client($presetState->resolve(static function()use($config):array {
        $pdo=App\Database\Database::connect($config->get('database'));
        return (new App\Repositories\ProviderPresetRepository($pdo))->read()['presets'];
    }));}catch(Throwable){return App\Services\ProviderPresets::client(App\Services\ProviderPresets::defaults());}
};
$core = new CoreController($config, $view,$resolvePresets);
$installer = new InstallerController(new InstallationService($root), new EnvironmentCheck($root), $view, new FileLogger($root . '/storage/logs'));
$router = new Router();
$updateNotice=null;
if ($config->get('installed')) {
    $policyState=new App\Services\PolicyState($root.'/storage/policy');
    $resolvePolicy=static function()use($policyState,$config):array {
        return App\Services\SitePolicy::effective($policyState->resolve(static function()use($config):array {
            try{$pdo=App\Database\Database::connect($config->get('database'));}catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            return (new App\Repositories\SitePolicyRepository($pdo))->read()['policy'];
        }),$config);
    };
    $maintenanceSignal=new App\Services\MaintenanceState($root.'/storage/runtime');
    $adminContext = null;
    $resolveAdmin = static function () use (&$adminContext, $config,$maintenanceSignal): array {
        if ($adminContext === null) {
            try { $pdo = App\Database\Database::connect($config->get('database')); }
            catch (PDOException) { throw new App\Http\HttpException(503, 'DATABASE_UNAVAILABLE'); }
            $auth = new App\Auth\Auth($config, new App\Repositories\AuthRepository($pdo));
            $auth->restore();
            $adminContext = [$auth, new App\Repositories\AdminRepository($pdo),new App\Repositories\AdminSettingsRepository($pdo,$maintenanceSignal),new App\Services\AdminAuditLogger($pdo,new FileLogger(dirname(__DIR__).'/storage/logs')),$pdo];
        }
        return $adminContext;
    };
    $adminMiddleware = new App\Middleware\AdminMiddleware($resolveAdmin);
    $updateChecks=static fn(?Closure $audit=null):App\Services\UpdateChecks=>new App\Services\UpdateChecks($root.'/storage/updates/checks',$config,trim(file_get_contents($root.'/VERSION')),audit:$audit);
    $updateNotice=static function()use($resolveAdmin,$updateChecks):?array{
        if(empty($_SESSION['user_id']))return null;
        try{
            [$auth,$repository]=$resolveAdmin();
            if(!$repository->isAdministrator((int)$auth->requireUser()['id']))return null;
            $state=$updateChecks()->status();
            return $state['available']===true?['tag'=>$state['release']['tag']]:null;
        }catch(Throwable){return null;}
    };
    $updatesHandler=static function(string $method)use($updateChecks,$view,$resolveAdmin):Closure{
        return static function(Request $request)use($updateChecks,$view,$resolveAdmin,$method):App\Http\Response{
            [$auth,,,$audit,$pdo]=$resolveAdmin();
            $record=static function(array $context)use($auth,$audit,$pdo):void{
                (new App\Repositories\LogRepository($pdo))->recordApplication(['event_id'=>bin2hex(random_bytes(16)),'type'=>'admin_audit','error_code'=>'UPDATE_CHECK_REQUESTED','user_id'=>(int)$auth->requireUser()['id'],'context'=>$context,'created_at'=>gmdate('Y-m-d H:i:s'),'file_written'=>false]);
                $audit->flush();
            };
            return (new App\Controllers\AdminUpdatesController($updateChecks($method==='check'?$record:null),$view,new App\Repositories\UpdateHistoryRepository($pdo)))->$method($request);
        };
    };
    foreach(['/admin/update','/api/admin/update'] as $path){
        $router->add('GET',$path,$updatesHandler('read'),[$adminMiddleware]);
        $router->add('POST',$path,$updatesHandler('check'),[$adminMiddleware,new Csrf()]);
    }
    $adminHandler = static function (string $method) use ($resolveAdmin, $view,$config,$updateChecks): Closure {
        return static function (Request $request) use ($resolveAdmin, $view, $method,$config,$updateChecks): App\Http\Response {
            [$auth, $repository,$settings,$audit,$pdo] = $resolveAdmin();
            $storageLimit=static fn():int=>App\Services\SitePolicy::effective((new App\Repositories\SitePolicyRepository($pdo))->read()['policy'],$config)['limits']['background_max_bytes'];
            return (new App\Controllers\AdminController($repository, $view,$settings,$auth,$audit,new App\Repositories\AdminRoleRepository($pdo),$storageLimit,static fn():array=>$updateChecks()->status()))->$method($request);
        };
    };
    $router->add('GET', '/admin', $adminHandler('page'), [$adminMiddleware]);
    $router->add('GET', '/api/admin/dashboard', $adminHandler('dashboard'), [$adminMiddleware]);
    $router->add('GET', '/admin/users', $adminHandler('usersPage'), [$adminMiddleware]);
    $router->add('GET', '/admin/storage', $adminHandler('storagePage'), [$adminMiddleware]);
    $router->add('GET', '/api/admin/users', $adminHandler('users'), [$adminMiddleware]);
    $router->add('GET', '/api/admin/storage', $adminHandler('storage'), [$adminMiddleware]);
    foreach(['/admin/users/role','/api/admin/users/role'] as $path)$router->add('POST',$path,$adminHandler('updateRole'),[$adminMiddleware,new Csrf()]);
    $router->add('GET','/admin/maintenance',$adminHandler('maintenancePage'),[$adminMiddleware]);
    $router->add('POST','/admin/maintenance',$adminHandler('updateMaintenance'),[$adminMiddleware,new Csrf()]);
    $router->add('GET','/api/admin/maintenance',$adminHandler('maintenance'),[$adminMiddleware]);
    $router->add('POST','/api/admin/maintenance',$adminHandler('updateMaintenance'),[$adminMiddleware,new Csrf()]);
    $policyHandler=static function(string $method)use($resolveAdmin,$config,$policyState,$view):Closure {
        return static function(Request $request)use($resolveAdmin,$config,$policyState,$view,$method):App\Http\Response {
            [$auth,,,$audit]=$resolveAdmin();
            try{$pdo=App\Database\Database::connect($config->get('database'));}catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            return (new App\Controllers\AdminPolicyController(new App\Repositories\SitePolicyRepository($pdo),$policyState,$auth,$audit,$view,$config))->$method($request);
        };
    };
    foreach(['/admin/policy','/api/admin/policy'] as $path){
        $router->add('GET',$path,$policyHandler('read'),[$adminMiddleware]);
        $router->add('POST',$path,$policyHandler('update'),[$adminMiddleware,new Csrf()]);
    }
    $presetsHandler=static function(string $method)use($resolveAdmin,$config,$presetState,$view):Closure {
        return static function(Request $request)use($resolveAdmin,$config,$presetState,$view,$method):App\Http\Response {
            [$auth,,,$audit]=$resolveAdmin();
            try{$pdo=App\Database\Database::connect($config->get('database'));}catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            return (new App\Controllers\AdminPresetsController(new App\Repositories\ProviderPresetRepository($pdo),$presetState,$auth,$audit,$view))->$method($request);
        };
    };
    foreach(['/admin/presets','/api/admin/presets'] as $path){
        $router->add('GET',$path,$presetsHandler('read'),[$adminMiddleware]);
        $router->add('POST',$path,$presetsHandler('update'),[$adminMiddleware,new Csrf()]);
    }
    $router->add('GET','/api/provider-presets',static fn(Request $request):App\Http\Response=>App\Http\Response::json(['presets'=>$resolvePresets()]));
    $router->add('GET','/api/site-policy',static fn(Request $request):App\Http\Response=>App\Http\Response::json(['flags'=>$resolvePolicy()['flags']]));
    $statisticsReport=static function(Request $request)use($config,$view):App\Http\Response {
        try{$pdo=App\Database\Database::connect($config->get('database'));}catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
        return (new App\Controllers\AdminStatisticsController(new App\Repositories\StatisticsReportRepository($pdo),$view))->read($request);
    };
    foreach(['/admin/statistics','/api/admin/statistics'] as $path)$router->add('GET',$path,$statisticsReport,[$adminMiddleware]);
    $router->add('POST','/api/statistics/event',static function(Request $request)use($config):App\Http\Response {
        try{$pdo=App\Database\Database::connect($config->get('database'));}catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
        return (new App\Controllers\StatisticsController(new App\Repositories\StatisticsRepository($pdo)))->event($request);
    },[new Csrf()]);
    $logHandler=static function(bool $auditOnly) use($resolveAdmin,$config,$view,$root):Closure {
        return static function(Request $request) use($resolveAdmin,$config,$view,$root,$auditOnly):App\Http\Response {
            [,,,$audit]=$resolveAdmin();
            try {$pdo=App\Database\Database::connect($config->get('database'));}
            catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            $repository=new App\Repositories\LogRepository($pdo);
            return (new App\Controllers\AdminLogsController($repository,new App\Services\LogRetention($repository,$root.'/storage/logs'),$audit,$view,new App\Services\ApplicationLogger($repository,new FileLogger($root.'/storage/logs'),$root.'/storage/log-pending')))->handle($request,$auditOnly);
        };
    };
    foreach (['logs'=>false,'audit-logs'=>true] as $path=>$auditOnly) {
        $router->add('GET','/admin/'.$path,$logHandler($auditOnly),[$adminMiddleware]);
        $router->add('GET','/api/admin/'.$path,$logHandler($auditOnly),[$adminMiddleware]);
    }
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
    $loginLimit=static function(Request $request,callable $next)use($resolvePolicy,$root):App\Http\Response {
        $limits=$resolvePolicy()['limits'];
        return (new App\Middleware\LoginRateLimit($root.'/storage/rate-limits',$limits['login_attempts'],$limits['login_window_seconds']))($request,$next);
    };
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
    $router->add('POST','/api/sync',$syncHandler('write'),[new Csrf()]);
    $router->add('PUT','/api/sync',$syncHandler('write'),[new Csrf()]);
    $router->add('POST','/api/sync/resolve-conflict',$syncHandler('resolve'),[new Csrf()]);
    $cloudHandler=static function(string $collection,string $action) use($config): Closure {
        return static function(Request $request,array $params) use($config,$collection,$action): App\Http\Response {
            try {$pdo=App\Database\Database::connect($config->get('database'));}
            catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            $auth=new App\Auth\Auth($config,new App\Repositories\AuthRepository($pdo));$auth->restore();
            return (new App\Controllers\CloudDataController($auth,new App\Repositories\SyncRepository($pdo)))->handle($request,$params,$collection,$action);
        };
    };
    $router->add('GET','/api/settings',$cloudHandler('settings','read'));
    $router->add('PUT','/api/settings',$cloudHandler('settings','update'),[new Csrf()]);
    foreach(['favorites'=>'favorites','favorite-folders'=>'favorite-folders','search/history'=>'history','search-engines'=>'providers-web','ai-providers'=>'providers-ai'] as $path=>$collection) {
        $router->add('GET','/api/'.$path,$cloudHandler($collection,'read'));
        $router->add('POST','/api/'.$path,$cloudHandler($collection,'create'),[new Csrf()]);
        if($collection!=='history')$router->add('PUT','/api/'.$path.'/{id}',$cloudHandler($collection,'update'),[new Csrf()]);
        $router->add('DELETE','/api/'.$path.'/{id}',$cloudHandler($collection,'delete'),[new Csrf()]);
    }
    $router->add('POST','/api/favorites/{id}/open',$cloudHandler('favorites','open'),[new Csrf()]);
    $backgroundHandler=static function(string $method)use($config,$root):Closure {
        return static function(Request $request,array $params=[])use($config,$root,$method):App\Http\Response {
            try{$pdo=App\Database\Database::connect($config->get('database'));}
            catch(PDOException){throw new App\Http\HttpException(503,'DATABASE_UNAVAILABLE');}
            $auth=new App\Auth\Auth($config,new App\Repositories\AuthRepository($pdo));$auth->restore();
            $policyRepository=new App\Repositories\SitePolicyRepository($pdo);
            $limitResolver=static fn(bool $lock=false):int=>App\Services\SitePolicy::effective($policyRepository->read($lock)['policy'],$config)['limits']['background_max_bytes'];
            $controller=new App\Controllers\BackgroundController($auth,new App\Repositories\BackgroundRepository($pdo,0,$limitResolver),new App\Services\BackgroundUpload($root));
            return $controller->$method($request,$params);
        };
    };
    $router->add('GET','/api/backgrounds',$backgroundHandler('index'));
    $router->add('GET','/api/backgrounds/receipts/{requestId}',$backgroundHandler('receipt'));
    $router->add('POST','/api/backgrounds/url',$backgroundHandler('url'),[new Csrf()]);
    $router->add('POST','/api/backgrounds/upload',$backgroundHandler('upload'),[new Csrf()]);
    $router->add('POST','/api/backgrounds/{id}/upload',$backgroundHandler('upload'),[new Csrf()]);
    $router->add('PUT','/api/backgrounds/{id}',$backgroundHandler('update'),[new Csrf()]);
    $router->add('DELETE','/api/backgrounds/{id}',$backgroundHandler('delete'),[new Csrf()]);
    $router->add('GET','/api/backgrounds/{id}/file',$backgroundHandler('file'));
    $weather = new App\Controllers\WeatherController(new App\Services\WeatherService($config->get('weather', [])), new App\Services\WeatherCache($root . '/storage/weather'));
    $router->add('POST', '/api/weather', $weather->current(...), [new Csrf()]);
}
$optionalAuth = new App\Middleware\OptionalAuthentication($config);
$home=new CoreController($config,$view,$resolvePresets,$updateNotice);
$router->add('GET', '/', $home->home(...), [$optionalAuth]);
$router->add('GET','/privacy',$core->privacy(...));
$router->add('GET', '/api/health', $core->health(...));
$router->add('GET', '/api/csrf', $core->csrf(...), [$optionalAuth]);
$router->add('GET', '/api/search/suggest', (new App\Controllers\SearchController())->suggest(...), [$optionalAuth]);
$router->add('GET', '/api/favorites/metadata', (new App\Controllers\FavoriteMetadataController())->metadata(...), [$optionalAuth]);
$router->add('POST', '/locale', $core->locale(...), [new Csrf()]);
$router->add('GET', '/installer', $installer->handle(...));
$router->add('POST', '/installer', $installer->handle(...), [new Csrf()]);
// Internal inherited-lock health task builds the same routes without altering maintenance.
if(PHP_SAPI==='cli'&&isset($updateHealthProbe)&&$updateHealthProbe===true)return $router;
// Apply the full stop before parsing JSON or reaching route middleware.
// Malformed mutation bodies must not expose a normal API during maintenance.
$request=new Request($_SERVER['REQUEST_METHOD'] ?? 'GET',parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
$dispatch=static function(Request $request)use($router,$config,&$resolvePolicy):App\Http\Response {
    $next=static fn(Request $request):App\Http\Response=>$router->dispatch(Request::capture());
    return $config->get('installed') ? (new App\Middleware\FeatureFlags($resolvePolicy))($request,$next) : $next($request);
};
if ($config->get('installed') && $maintenanceSignal->active()) {
    [,,$maintenanceSettings]=$resolveAdmin();
    (new App\Middleware\Maintenance($maintenanceSettings,$resolveAdmin,$view,$maintenanceSignal,$translator))($request,$dispatch)->send();
} else $dispatch($request)->send();

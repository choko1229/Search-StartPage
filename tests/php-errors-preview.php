<?php
declare(strict_types=1);
// Copy only into an isolated test server's public/_test directory.
if (getenv('SEARCH_TEST_MODE')!=='1') {http_response_code(404);exit;}
$root=dirname(__DIR__,2);
require $root.'/app/autoload.php';
App\Exceptions\ErrorHandler::register($root);
App\Auth\Session::start(App\Config::load($root));
if (($_GET['mode'] ?? '')==='warning') trigger_error('generated-php-warning-secret-marker',E_USER_WARNING);
throw new RuntimeException('generated-php-exception-secret-marker');

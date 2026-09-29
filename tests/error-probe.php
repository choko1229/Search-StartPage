<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/autoload.php';
$_SERVER['REQUEST_URI'] = '/api/test-error';
App\Exceptions\ErrorHandler::register(dirname(__DIR__));
trigger_error('private-secret-marker', E_USER_WARNING);

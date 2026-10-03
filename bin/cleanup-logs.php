<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
try {
    $result=(new App\Services\LogMaintenance($root))->run();
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
} catch (Throwable) { fwrite(STDERR,"Log cleanup failed.\n"); exit(1); }

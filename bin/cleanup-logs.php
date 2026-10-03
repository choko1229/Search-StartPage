<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
try {
    $config=App\Config::load($root);
    if (!$config->get('installed')) throw new RuntimeException('Installation required');
    $pdo=App\Database\Database::connect($config->get('database'));
    $result=(new App\Services\LogRetention(new App\Repositories\LogRepository($pdo),$root.'/storage/logs'))->run();
    (new App\Services\AdminAuditLogger($pdo,new App\Services\FileLogger($root.'/storage/logs')))->flush();
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
} catch (Throwable) { fwrite(STDERR,"Log cleanup failed.\n"); exit(1); }

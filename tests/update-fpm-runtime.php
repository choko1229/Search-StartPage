<?php
declare(strict_types=1);
// Disposable Nginx/FPM proof endpoint; never copied into a release or normal public root.
$root=dirname(__DIR__);
if(PHP_SAPI!=='fpm-fcgi'||$root!=='/tmp/search-update-fpm-source'||!is_file($root.'/storage/web-cache-test-only')||file_exists($root.'/config/config.php')){
    http_response_code(404);exit;
}
header('Content-Type: application/json');
echo json_encode(['sapi'=>PHP_SAPI,'version'=>PHP_VERSION,'opcache'=>ini_get('opcache.enable'),'timestamps'=>ini_get('opcache.validate_timestamps'),'pid'=>getmypid()],JSON_THROW_ON_ERROR);

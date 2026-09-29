<?php
declare(strict_types=1);

// Development router: only serve known public static asset extensions.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$public = realpath(dirname(__DIR__) . '/public');
$file = realpath($public . $path);
if ($file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file) && preg_match('/\.(css|js|png|jpg|svg|ico|woff2)$/i', $file)) {
    return false;
}
require $public . '/index.php';

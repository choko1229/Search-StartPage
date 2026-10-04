<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/autoload.php';
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$updateLease=null;
try {
    $updateLease=(new App\Services\UpdateAccess($root.'/storage/updates/access'))->enter();if($updateLease===null)throw new App\Services\UpdateAccessPaused();
    $config = App\Config::load($root);
    if (!$config->get('installed')) {
        throw new RuntimeException('Complete the web installer first');
    }
    $pdo = App\Database\Database::connect($config->get('database'));
    $applied = (new App\Database\Migrator($pdo, $root . '/database/migrations'))->migrate();
    fwrite(STDOUT, count($applied) . " migration(s) applied.\n");
} catch (Throwable $error) {
    if($updateLease!==null)(new App\Services\FileLogger($root . '/storage/logs'))->exception($error, bin2hex(random_bytes(8)));
    fwrite(STDERR, "Migration failed. See storage/logs.\n");
    exit(1);
}

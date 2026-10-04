<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
try{require_once $root.'/app/Services/UpdateAccess.php';$updateLease=(new App\Services\UpdateAccess($root.'/storage/updates/access'))->enter();if($updateLease===null)throw new RuntimeException();}
catch(Throwable){fwrite(STDERR,"Setup unavailable during update.\n");exit(1);}
if (is_file($root . '/config/config.php') || is_file($root . '/storage/installed.lock')) {
    fwrite(STDERR, "Installation is already locked.\n");
    exit(1);
}
$path = $root . '/config/install.key';
if (!is_file($path)) {
    $file = fopen($path, 'x');
    if ($file === false) {
        throw new RuntimeException('Cannot create setup key');
    }
    chmod($path, 0600);
    fwrite($file, bin2hex(random_bytes(32)));
    fclose($file);
}
fwrite(STDOUT, trim(file_get_contents($path)) . "\n");

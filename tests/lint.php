<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failed = false;
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (preg_match('~^(?:\.git|\.tools|\.test-output|vendor|storage)[/\\\\]~', $relative) || $file->getExtension() !== 'php') {
        continue;
    }
    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
    $count++;
    $failed = $failed || $status !== 0;
}
echo "$count PHP files checked.\n";
exit($failed ? 1 : 0);

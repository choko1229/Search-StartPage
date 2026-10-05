<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1'
    || dirname(__DIR__) !== '/srv/search-startpage'
    || !is_file('/tmp/update-systemd-test-only')) exit(1);

$unit = dirname(__DIR__).'/bin/systemd/search-update-execution.service';
$source = file_get_contents($unit);
if (!is_string($source)) throw new RuntimeException('Unit unavailable');
$source = str_replace("\r\n", "\n", $source);
$root = sys_get_temp_dir().'/update-systemd-'.bin2hex(random_bytes(8));
mkdir($root, 0700);
$count = 0;
$check = static function(bool $ok, string $label) use (&$count): void {
    if (!$ok) throw new RuntimeException($label);
    ++$count; echo "PASS: $label\n";
};
$verify = static function(string $path): array {
    $process = proc_open(['/usr/bin/systemd-analyze', 'verify', '--man=no', $path],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Validator unavailable');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $output, $error];
};
try {
    [$code, $output, $error] = $verify($unit);
    if ($code !== 0 || $output !== '' || $error !== '') {
        // Validator input is the public unit example; no application configuration is copied.
        throw new RuntimeException('Static unit diagnostics: '.$output.$error);
    }
    $check($code === 0 && $output === '' && $error === '', 'shipped unit accepted without diagnostics');
    $check(str_contains($source, "Type=simple\n") && str_contains($source, "Restart=on-failure\n")
        && str_contains($source, "RestartSec=5\n"), 'foreground worker and failure restart configured');
    $check(str_contains($source, "User=www-data\n") && str_contains($source, "Group=www-data\n")
        && str_contains($source, "UMask=0077\n"), 'same web account and private creation mask');
    $check(str_contains($source, "ExecStop=/bin/sh /usr/local/libexec/search-startpage/stop-update-execution.sh\n")
        && is_file(dirname(__DIR__).'/bin/systemd/stop-update-execution.sh')
        && str_contains($source, "TimeoutStopSec=infinity\n"), 'draining stop command has no manager timeout');
    $check(str_contains($source, "NoNewPrivileges=true\n") && str_contains($source, "PrivateTmp=true\n"),
        'privilege escalation and temporary directory isolation configured');
    $check(str_contains($source, "WantedBy=multi-user.target\n"), 'boot target install declaration exists');

    $bad = $root.'/missing-executable.service';
    file_put_contents($bad, str_replace('ExecStop=/bin/sh ', 'ExecStop=/no-such-test-php ', $source));
    [$code, , $error] = $verify($bad);
    $check($code !== 0 && str_contains($error, '/no-such-test-php'), 'validator rejects missing stop executable');
    $bad = $root.'/invalid-type.service';
    file_put_contents($bad, str_replace('Type=simple', 'Type=invalid-test-type', $source));
    [$code, , $error] = $verify($bad);
    $check($error !== '' && str_contains($error, 'invalid-test-type'), 'invalid service type produces diagnostics');
    $bad = $root.'/unknown-directive.service';
    file_put_contents($bad, str_replace('RestartSec=5', 'RestartSecs=5', $source));
    [, , $error] = $verify($bad);
    $check($error !== '' && str_contains($error, 'RestartSecs'), 'unknown directive warning is not mistaken for success');
    echo "$count systemd unit checks passed.\n";
} finally {
    foreach (scandir($root) as $name) if ($name !== '.' && $name !== '..') unlink($root.'/'.$name);
    rmdir($root);
}

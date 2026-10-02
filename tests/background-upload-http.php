<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') exit(1);
// Standalone loopback fixture for the storage service, not an authentication bypass in the app.
$root = sys_get_temp_dir() . '/search-upload-http-' . bin2hex(random_bytes(8));
mkdir($root, 0700); mkdir($root . '/public', 0700);
$autoload = var_export(dirname(__DIR__) . '/app/autoload.php', true);
$fixture = <<<'PHP'
<?php
require AUTOLOAD_PATH;
if (getenv('SEARCH_TEST_MODE') !== '1') { http_response_code(404); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(404); exit; }
try {
    $stored = (new App\Services\BackgroundUpload(dirname(__DIR__)))->receive(17, $_FILES['file'] ?? []);
    unset($stored['path']);
    header('Content-Type: application/json'); echo json_encode($stored, JSON_THROW_ON_ERROR);
} catch (App\Http\HttpException $e) { http_response_code($e->status); echo $e->errorCode; }
PHP;
file_put_contents($root . '/public/index.php', str_replace('AUTOLOAD_PATH', $autoload, $fixture));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
if ($socket === false) throw new RuntimeException('Port reservation failed');
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = null; $passed = 0;
$check = static function (bool $ok, string $label) use (&$passed): void {
    if (!$ok) throw new RuntimeException($label); $passed++; echo "PASS: $label\n";
};
$request = static function (string $name, string $bytes, string $clientMime = 'image/png') use ($address): array {
    $boundary = 'search-' . bin2hex(random_bytes(16));
    $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$name\"\r\nContent-Type: $clientMime\r\n\r\n" . $bytes . "\r\n--$boundary--\r\n";
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary,
        'content' => $body, 'ignore_errors' => true, 'timeout' => 5]]);
    $result = file_get_contents('http://' . $address . '/index.php', false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $match);
    return [(int) ($match[1] ?? 0), $result];
};
try {
    $process = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/public'], [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Server startup failed');
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $message, .1);
        if ($connection !== false) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    $check($ready, 'isolated loopback server ready');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII=');
    [$status, $body] = $request('image.png', $png, 'text/html');
    $metadata = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
    $check($status === 200 && $metadata['mime'] === 'image/png', 'real HTTP upload uses server MIME');
    $check(preg_match('/^[a-f0-9]{48}\.png$/D', $metadata['filename']) === 1, 'random canonical filename');
    $path = $root . '/storage/uploads/backgrounds/17/' . $metadata['filename'];
    $check(is_file($path) && file_get_contents($path) === $png, 'HTTP upload saved under server-selected owner');
    $check((fileperms($path) & 0777) === 0600, 'file permissions private');
    $check((fileperms(dirname($path)) & 0777) === 0700, 'owner directory private');
    $check(!file_exists($root . '/public/image.png') && !isset($metadata['path']), 'private path absent from public response and web root');
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
    file_get_contents('http://' . $address . '/storage/uploads/backgrounds/17/' . $metadata['filename'], false, $context);
    $check(str_contains($http_response_header[0] ?? '', '404'), 'private file cannot be requested directly');
    [$status] = $request('image.jpg', $png);
    $check($status === 422, 'HTTP MIME mismatch refused');
    [$status] = $request('image.php.png', $png);
    $check($status === 422, 'HTTP executable double extension refused');
    [$status] = $request('image.png', '<?php echo "bad";');
    $check($status === 422, 'HTTP disguised script refused');
    $check(count(glob(dirname($path) . '/*')) === 1, 'failed uploads leave no private files');
    [$status, $body] = $request('image.png', $png);
    $second = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
    $check($status === 200 && $second['filename'] !== $metadata['filename'] && file_get_contents($path) === $png, 'same original name never overwrites an earlier upload');
    if (PHP_OS_FAMILY !== 'Windows') {
        $owner = dirname($path); rename($owner, $owner . '-original');
        mkdir($root . '/escaped', 0700); symlink($root . '/escaped', $owner);
        [$status] = $request('image.png', $png);
        $check($status === 503 && count(glob($root . '/escaped/*')) === 0, 'symlink owner directory cannot redirect storage');
        unlink($owner); rename($owner . '-original', $owner);
    }
    echo "$passed background HTTP storage assertions passed.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    // Only this random fixture directory is removed; application storage is never touched.
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) { if ($item->isLink()) unlink($item->getPathname()); elseif ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname()); }
    rmdir($root);
}

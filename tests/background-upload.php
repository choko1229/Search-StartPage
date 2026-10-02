<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') exit(1);
require dirname(__DIR__) . '/app/autoload.php';
use App\Services\BackgroundUpload;
use App\Http\HttpException;
$passed = 0;
$check = static function (bool $ok, string $label) use (&$passed): void {
    if (!$ok) throw new RuntimeException($label);
    $passed++; echo "PASS: $label\n";
};
$reject = static function (callable $call, string $code) use ($check): void {
    try { $call(); } catch (HttpException $e) { $check($e->errorCode === $code, $code); return; }
    throw new RuntimeException('Unexpected acceptance: ' . $code);
};
$file = tempnam(sys_get_temp_dir(), 'search-background-');
try {
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII='));
    $metadata = BackgroundUpload::inspect($file, '背景.PNG');
    $check($metadata['mime'] === 'image/png' && $metadata['width'] === 1 && $metadata['height'] === 1, 'real PNG dimensions and MIME');
    $check($metadata['bytes'] === filesize($file), 'usage measured from actual bytes');
    foreach (['../image.png', 'folder\\image.png', "image\0.png", "image\n.png", 'image.php.png'] as $name) {
        $reject(static fn() => BackgroundUpload::inspect($file, $name), 'INVALID_UPLOAD_NAME');
    }
    foreach (['image.svg', 'image.php', 'image.html', 'image.exe'] as $name) {
        $reject(static fn() => BackgroundUpload::inspect($file, $name), 'INVALID_UPLOAD_NAME');
    }
    $reject(static fn() => BackgroundUpload::inspect($file, 'image.txt'), 'UNSUPPORTED_BACKGROUND_FORMAT');
    $reject(static fn() => BackgroundUpload::inspect($file, 'image.jpg'), 'BACKGROUND_MIME_MISMATCH');
    $reject(static fn() => BackgroundUpload::inspect($file, 'movie.mp4'), 'BACKGROUND_MIME_MISMATCH');
    file_put_contents($file, '<?php echo "malicious";');
    $reject(static fn() => BackgroundUpload::inspect($file, 'image.png'), 'BACKGROUND_MIME_MISMATCH');
    file_put_contents($file, '');
    $reject(static fn() => BackgroundUpload::inspect($file, 'image.png'), 'INVALID_UPLOAD');
    $handle = fopen($file, 'wb'); ftruncate($handle, BackgroundUpload::IMAGE_LIMIT + 1); fclose($handle);
    $reject(static fn() => BackgroundUpload::inspect($file, 'image.png'), 'BACKGROUND_TOO_LARGE');
    $handle = fopen($file, 'wb'); ftruncate($handle, BackgroundUpload::VIDEO_LIMIT + 1); fclose($handle);
    $reject(static fn() => BackgroundUpload::inspect($file, 'movie.webm'), 'BACKGROUND_TOO_LARGE');
    $storage = new BackgroundUpload(dirname(__DIR__));
    $reject(static fn() => $storage->receive(0, []), 'AUTHENTICATION_REQUIRED');
    $reject(static fn() => $storage->receive(1, ['error' => UPLOAD_ERR_INI_SIZE]), 'BACKGROUND_TOO_LARGE');
    $reject(static fn() => $storage->receive(1, ['error' => UPLOAD_ERR_OK, 'tmp_name' => $file, 'name' => 'image.png']), 'INVALID_UPLOAD');
    $check(is_file($file), 'untrusted local file never moved');
    echo "$passed background upload assertions passed.\n";
} finally { if (is_file($file)) unlink($file); }

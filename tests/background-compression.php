<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1') exit(1);
require dirname(__DIR__) . '/app/autoload.php';
use App\Services\{BackgroundCompression, BackgroundUpload, EnvironmentCheck};
$root = sys_get_temp_dir() . '/search-compression-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
$passed = 0;
$check = static function (bool $ok, string $label) use (&$passed): void {
    if (!$ok) throw new RuntimeException($label); $passed++; echo "PASS: $label\n";
};
$chunk = static fn(string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
$oldFfmpeg = getenv('SEARCH_FFMPEG_PATH');
try {
    $raw = str_repeat("\0" . str_repeat("\x00\x80\xff\x40", 128), 96);
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 128, 96, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw, 0)) . $chunk('IEND', '');
    $source = $root . '/original.png'; file_put_contents($source, $png);
    $service = new BackgroundCompression(); $capabilities = $service::capabilities();
    $check(array_keys($capabilities) === ['imagick', 'gd', 'ffmpeg'], 'explicit optional codec capabilities');
    $result = $service->compress($source);
    $check(file_get_contents($source) === $png, 'original preserved until persistence commits');
    $check($result['bytes'] === filesize($result['path']) && $result['originalBytes'] === strlen($png), 'measured final size and original size separate');
    $check($result['width'] === 128 && $result['height'] === 96, 'image dimensions retained');
    if ($capabilities['imagick'] || $capabilities['gd']) {
        $check($result['compressed'] && $result['bytes'] < strlen($png), 'actual PNG compression reduces size');
        $check($result['engine'] === ($capabilities['imagick'] ? 'imagick' : 'gd'), 'Imagick priority and GD fallback');
        $check((fileperms($result['path']) & 0777) === 0600, 'compressed file remains private');
        if (function_exists('imagecreatefrompng')) {
            $image = imagecreatefrompng($result['path']); $pixel = imagecolorsforindex($image, imagecolorat($image, 0, 0));
            $check($pixel['red'] === 0 && $pixel['green'] === 128 && $pixel['blue'] === 255 && abs($pixel['alpha'] - 95) <= 1, 'lossless PNG color and alpha preserved');
            imagedestroy($image);
        }
    } else {
        $check(!$result['compressed'] && $result['warning'] === 'BACKGROUND_COMPRESSION_UNAVAILABLE', 'missing codec preserves source with warning');
    }
    if ($result['path'] !== $source) unlink($result['path']);
    $small = $root . '/tiny.png';
    // A compact valid PNG must never be replaced by a larger encoding.
    file_put_contents($small, "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress("\0\0\0\0\0", 9)) . $chunk('IEND', ''));
    $tiny = $service->compress($small);
    $check($tiny['bytes'] <= filesize($small) && is_file($small), 'compression never increases charged storage');
    if ($tiny['path'] !== $small) unlink($tiny['path']);
    if (function_exists('imagecreatefrompng')) {
        $jpeg = $root . '/oriented.jpg'; $image = imagecreatefrompng($source);
        imagejpeg($image, $jpeg, 100); imagedestroy($image);
        $data = file_get_contents($jpeg);
        $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x06\0\0\0\0\0\0\0";
        $comment = str_repeat('metadata to remove ', 2000);
        file_put_contents($jpeg, substr($data, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif
            . "\xff\xfe" . pack('n', strlen($comment) + 2) . $comment . substr($data, 2));
        $oriented = $service->compress($jpeg);
        $check($oriented['compressed'] && $oriented['width'] === 96 && $oriented['height'] === 128, 'JPEG EXIF rotation preserved after metadata removal');
        if ($oriented['path'] !== $jpeg) unlink($oriented['path']);
        unlink($jpeg);
    }
    if ($capabilities['imagick']) {
        $gif = $root . '/animated.gif'; $animation = new Imagick();
        foreach (['red', 'blue'] as $color) {
            $frame = new Imagick(); $frame->newImage(32, 24, $color); $frame->setImageFormat('gif');
            $frame->setImageDelay(20); $frame->setImageIterations(3);
            $frame->setImageProperty('comment', str_repeat('remove-comment ', 1000));
            $animation->addImage($frame); $frame->clear();
        }
        $animation->writeImages($gif, true); $animation->clear();
        $animated = $service->compress($gif); $reloaded = new Imagick($animated['path']);
        $check($reloaded->getNumberImages() === 2 && $reloaded->getImageDelay() === 20 && $reloaded->getImageIterations() === 3, 'GIF animation frames delay and loop retained');
        $reloaded->clear();
        if ($animated['path'] !== $gif) unlink($animated['path']);
        unlink($gif);
    }
    $checks = (new EnvironmentCheck(dirname(__DIR__)))->results();
    $ffmpegCheck = array_values(array_filter($checks, static fn(array $check): bool => str_starts_with($check['name'], 'FFmpeg')))[0];
    $check($ffmpegCheck['ok'] === $capabilities['ffmpeg'] && !$ffmpegCheck['required'], 'installer capability uses same optional detection');
    if ($capabilities['ffmpeg']) {
        $video = $root . '/original.mp4'; $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open([BackgroundCompression::ffmpegPath(), '-nostdin', '-loglevel', 'error', '-y', '-f', 'lavfi',
            '-i', 'testsrc=size=128x96:rate=15', '-t', '2', '-c:v', 'mpeg4', '-q:v', '1', $video],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes);
        $check(is_resource($process) && proc_close($process) === 0, 'synthetic test video generated locally');
        $before = hash_file('sha256', $video); $compressed = $service->compress($video);
        $check($compressed['compressed'] && $compressed['engine'] === 'ffmpeg' && $compressed['bytes'] < filesize($video), 'actual FFmpeg compression reduces size');
        $check(hash_file('sha256', $video) === $before && $compressed['mime'] === 'video/mp4', 'video source preserved and output MIME checked');
        if ($compressed['path'] !== $video) unlink($compressed['path']);
        if (PHP_OS_FAMILY !== 'Windows') {
            putenv('SEARCH_FFMPEG_PATH=/bin/false');
            $failed = $service->compress($video);
            $check(!$failed['compressed'] && $failed['warning'] === 'BACKGROUND_COMPRESSION_FAILED' && hash_file('sha256', $video) === $before, 'failed encoder preserves source with warning');
        }
    }
    $check(count(glob($root . '/*')) === ($capabilities['ffmpeg'] ? 3 : 2), 'discarded encodings leave no partial output');
    echo "$passed background compression assertions passed.\n";
} finally {
    putenv($oldFfmpeg === false ? 'SEARCH_FFMPEG_PATH' : 'SEARCH_FFMPEG_PATH=' . $oldFfmpeg);
    foreach (glob($root . '/*') as $file) unlink($file);
    rmdir($root);
}

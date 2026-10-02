<?php
declare(strict_types=1);

namespace App\Services;

final class EnvironmentCheck
{
    public function __construct(private readonly string $root)
    {
    }

    public function results(): array
    {
        $checks = [
            ['name' => 'PHP >= 8.2', 'ok' => PHP_VERSION_ID >= 80200, 'required' => true],
            ['name' => 'PHP >= 8.3', 'ok' => PHP_VERSION_ID >= 80300, 'required' => false],
        ];
        foreach (['pdo_mysql', 'curl', 'json', 'openssl', 'mbstring', 'fileinfo'] as $extension) {
            $checks[] = ['name' => $extension, 'ok' => extension_loaded($extension), 'required' => true];
        }
        foreach (['config', 'storage'] as $directory) {
            $checks[] = ['name' => $directory, 'ok' => is_writable($this->root . '/' . $directory), 'required' => true];
        }
        $checks[] = ['name' => 'GD / Imagick', 'ok' => extension_loaded('gd') || extension_loaded('imagick'), 'required' => false];
        $checks[] = ['name' => 'FFmpeg (PATH / SEARCH_FFMPEG_PATH)', 'ok' => BackgroundCompression::ffmpegPath() !== null, 'required' => false];
        return $checks;
    }

    public function ready(): bool
    {
        foreach ($this->results() as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }
        return true;
    }
}

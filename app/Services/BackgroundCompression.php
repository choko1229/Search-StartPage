<?php
declare(strict_types=1);

namespace App\Services;

/** Compression never destroys its input; persistence owns the eventual cleanup. */
final class BackgroundCompression
{
    public static function ffmpegPath(): ?string
    {
        if (!function_exists('proc_open')) return null;
        $configured = getenv('SEARCH_FFMPEG_PATH');
        $candidates = $configured !== false && $configured !== '' ? [$configured] : [];
        if (!$candidates) {
            $name = PHP_OS_FAMILY === 'Windows' ? 'ffmpeg.exe' : 'ffmpeg';
            foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
                if ($directory !== '') $candidates[] = rtrim($directory, '/\\') . '/' . $name;
            }
        }
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if ($path !== false && is_file($path) && is_executable($path)) return $path;
        }
        return null;
    }

    public static function capabilities(): array
    {
        return ['imagick' => extension_loaded('imagick'), 'gd' => extension_loaded('gd'), 'ffmpeg' => self::ffmpegPath() !== null];
    }

    public function compress(string $path): array
    {
        $input = BackgroundUpload::inspect($path, basename($path));
        $original = $input + ['path' => $path, 'filename' => basename($path), 'originalBytes' => $input['bytes'], 'compressed' => false];
        $capabilities = self::capabilities();
        $engines = $input['type'] === 'video' ? ($capabilities['ffmpeg'] ? ['ffmpeg'] : [])
            : array_keys(array_filter(['imagick' => $capabilities['imagick'], 'gd' => $capabilities['gd']]));
        if (!$engines) return $original + ['engine' => null, 'warning' => 'BACKGROUND_COMPRESSION_UNAVAILABLE'];
        foreach ($engines as $engine) {
            $extension = $engine === 'ffmpeg' ? 'mp4' : $input['extension'];
            $output = dirname($path) . '/' . bin2hex(random_bytes(24)) . '.' . $extension;
            // Reserve a private destination before an encoder opens it.
            $handle = @fopen($output, 'x+b');
            if ($handle === false) continue;
            fclose($handle);
            if (!@chmod($output, 0600)) { @unlink($output); continue; }
            try {
                $ok = match ($engine) {
                    'imagick' => $this->imagick($path, $output, $input),
                    'gd' => $this->gd($path, $output, $input),
                    'ffmpeg' => $this->video($path, $output, $input),
                };
                if (!$ok) continue;
                $metadata = BackgroundUpload::inspect($output, basename($output));
                if ($metadata['type'] !== $input['type']) continue;
                if ($input['type'] === 'image' && !in_array([$metadata['width'], $metadata['height']],
                    [[$input['width'], $input['height']], [$input['height'], $input['width']]], true)) continue;
                if ($metadata['bytes'] >= $input['bytes']) return $original + ['engine' => $engine, 'warning' => null];
                $result = $metadata + ['path' => $output, 'filename' => basename($output), 'originalBytes' => $input['bytes'],
                    'compressed' => true, 'engine' => $engine, 'warning' => null];
                $output = ''; // Transfer ownership only of a verified, smaller file.
                return $result;
            } catch (\Throwable) {
                // Codec errors are recoverable and do not expose source paths or diagnostics.
            } finally {
                if ($output !== '' && is_file($output)) @unlink($output);
            }
        }
        return $original + ['engine' => null, 'warning' => 'BACKGROUND_COMPRESSION_FAILED'];
    }

    private function imagick(string $input, string $output, array $metadata): bool
    {
        $header = file_get_contents($input, false, null, 0, 1048576);
        if ($metadata['extension'] === 'png' && str_contains($header, 'acTL')) return false;
        $limits = [\Imagick::RESOURCETYPE_MEMORY => 64 * 1024 * 1024, \Imagick::RESOURCETYPE_MAP => 128 * 1024 * 1024,
            \Imagick::RESOURCETYPE_DISK => 512 * 1024 * 1024, \Imagick::RESOURCETYPE_THREAD => 2, \Imagick::RESOURCETYPE_TIME => 30];
        $previous = []; $image = null;
        try {
            foreach ($limits as $type => $value) { $previous[$type] = \Imagick::getResourceLimit($type); \Imagick::setResourceLimit($type, $value); }
            $image = new \Imagick();
            // Force the inspected decoder. Do not interpret a filename as an ImageMagick pseudo-protocol.
            $image->readImage($metadata['extension'] . ':' . realpath($input));
            if ($image->getNumberImages() > 1 && !in_array($metadata['extension'], ['gif', 'webp'], true)) return false;
            foreach ($image as $frame) {
                $icc = $frame->getImageProfiles('icc', true);
                $delay = $frame->getImageDelay(); $dispose = $frame->getImageDispose(); $iterations = $frame->getImageIterations();
                $this->orient($frame); $frame->stripImage();
                $frame->setImageDelay($delay); $frame->setImageDispose($dispose); $frame->setImageIterations($iterations);
                foreach ($icc as $name => $profile) $frame->profileImage($name, $profile);
                $frame->setImageCompressionQuality(92);
                if ($metadata['extension'] === 'png') $frame->setOption('png:compression-level', '9');
            }
            return $image->writeImages($output, true);
        } finally {
            $image?->clear();
            foreach ($previous as $type => $value) \Imagick::setResourceLimit($type, $value);
        }
    }

    private function orient(\Imagick $image): void
    {
        if (method_exists($image, 'autoOrient')) { $image->autoOrient(); return; }
        $orientation = $image->getImageOrientation();
        if (in_array($orientation, [2, 4, 5, 7], true)) $image->flopImage();
        $rotation = [3 => 180, 4 => 180, 5 => -90, 6 => 90, 7 => 90, 8 => -90][$orientation] ?? 0;
        if ($rotation) $image->rotateImage('transparent', $rotation);
        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    private function gd(string $input, string $output, array $metadata): bool
    {
        // GD cannot preserve animated GIF/WebP/APNG. Preserve the original instead of flattening it.
        if ($metadata['extension'] === 'gif') return false;
        $header = file_get_contents($input, false, null, 0, 1048576);
        if ($metadata['extension'] === 'webp' && substr($header, 12, 4) === 'VP8X' && (ord($header[20] ?? "\0") & 2)) return false;
        if ($metadata['extension'] === 'png' && str_contains($header, 'acTL')) return false;
        if ($metadata['extension'] === 'avif' && substr($header, 8, 4) === 'avis') return false;
        $limit = ini_get('memory_limit');
        $budget = self::memoryBytes($limit === false ? '128M' : $limit);
        $required = $metadata['width'] * $metadata['height'] * 12 + $metadata['bytes'] * 2 + 16 * 1024 * 1024;
        if ($budget > 0 && $required > $budget - memory_get_usage(true)) return false;
        $decoder = 'imagecreatefrom' . ($metadata['extension'] === 'jpg' ? 'jpeg' : $metadata['extension']);
        $encoder = 'image' . ($metadata['extension'] === 'jpg' ? 'jpeg' : $metadata['extension']);
        if (!function_exists($decoder) || !function_exists($encoder)) return false;
        // Re-encoding JPEG loses EXIF orientation. Require access to its orientation before re-encoding.
        if ($metadata['extension'] === 'jpg' && str_contains($header, "Exif\0\0") && !function_exists('exif_read_data')) return false;
        $image = @$decoder($input);
        if ($image === false) return false;
        try {
            if ($metadata['extension'] === 'jpg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($input);
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
                $rotation = [3 => 180, 4 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90][$orientation] ?? 0;
                if ($rotation) { $rotated = imagerotate($image, $rotation, 0); if ($rotated === false) return false; imagedestroy($image); $image = $rotated; }
            }
            imagepalettetotruecolor($image); imagealphablending($image, false); imagesavealpha($image, true);
            return $metadata['extension'] === 'png' ? imagepng($image, $output, 9) : $encoder($image, $output, 92);
        } finally { imagedestroy($image); }
    }

    private function video(string $input, string $output, array $metadata): bool
    {
        $binary = self::ffmpegPath();
        if ($binary === null) return false;
        $command = [$binary, '-nostdin', '-hide_banner', '-loglevel', 'error', '-xerror', '-y', '-protocol_whitelist', 'file,pipe',
            '-f', $metadata['extension'] === 'mp4' ? 'mov' : 'matroska', '-i', realpath($input),
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-c:v', 'libx264', '-crf', '20',
            '-preset', 'medium', '-threads', '2', '-pix_fmt', 'yuv420p', '-vf', 'pad=ceil(iw/2)*2:ceil(ih/2)*2',
            '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', '-f', 'mp4', $output];
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = @proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes);
        if (!is_resource($process)) return false;
        $deadline = microtime(true) + 120; $exit = -1;
        try {
            do {
                $status = proc_get_status($process);
                if (!$status['running']) { $exit = $status['exitcode']; break; }
                clearstatcache(true, $output);
                if (microtime(true) >= $deadline || filesize($output) > BackgroundUpload::VIDEO_LIMIT) { proc_terminate($process, 9); break; }
                usleep(50000);
            } while (true);
        } finally { $closed = proc_close($process); }
        return ($exit === 0 || ($exit === -1 && $closed === 0));
    }

    private static function memoryBytes(string $value): int
    {
        if ($value === '-1') return -1;
        $unit = strtolower(substr(trim($value), -1));
        return (int) $value * match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
    }
}

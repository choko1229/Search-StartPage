<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;

/** Private staging storage. Callers must authenticate and enforce quota before publishing. */
final class BackgroundUpload
{
    public const IMAGE_LIMIT = 25 * 1024 * 1024;
    public const VIDEO_LIMIT = 500 * 1024 * 1024;
    private const FORMATS = [
        'jpg' => ['image/jpeg', 'image', 'jpg'], 'jpeg' => ['image/jpeg', 'image', 'jpg'],
        'png' => ['image/png', 'image', 'png'], 'gif' => ['image/gif', 'image', 'gif'],
        'webp' => ['image/webp', 'image', 'webp'], 'avif' => ['image/avif', 'image', 'avif'],
        'mp4' => ['video/mp4', 'video', 'mp4'], 'webm' => ['video/webm', 'video', 'webm'],
    ];

    public function __construct(private readonly string $projectRoot)
    {
    }

    /** Inspect actual bytes; client MIME and declared size are deliberately ignored. */
    public static function inspect(string $path, string $originalName): array
    {
        if ($originalName === '' || strlen($originalName) > 255 || preg_match('~[\\\\/\x00-\x1f\x7f]~', $originalName)
            || preg_match('/\.(php\d*|phtml|phar|cgi|exe|html?|svg)(\.|$)/i', $originalName)) {
            throw new HttpException(422, 'INVALID_UPLOAD_NAME');
        }
        $format = self::FORMATS[strtolower(pathinfo($originalName, PATHINFO_EXTENSION))] ?? null;
        if ($format === null) throw new HttpException(422, 'UNSUPPORTED_BACKGROUND_FORMAT');
        if (!is_file($path) || is_link($path) || !is_readable($path)) throw new HttpException(422, 'INVALID_UPLOAD');
        clearstatcache(true, $path);
        $bytes = filesize($path);
        if ($bytes === false || $bytes < 1) throw new HttpException(422, 'INVALID_UPLOAD');
        if ($bytes > ($format[1] === 'image' ? self::IMAGE_LIMIT : self::VIDEO_LIMIT)) {
            throw new HttpException(413, 'BACKGROUND_TOO_LARGE');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime !== $format[0]) throw new HttpException(422, 'BACKGROUND_MIME_MISMATCH');
        $dimensions = null;
        if ($format[1] === 'image') {
            $dimensions = @getimagesize($path);
            if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || ($dimensions['mime'] ?? '') !== $mime) {
                throw new HttpException(422, 'INVALID_BACKGROUND_IMAGE');
            }
        }
        return ['type' => $format[1], 'mime' => $mime, 'extension' => $format[2], 'bytes' => $bytes,
            'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null];
    }

    /** Only PHP's real HTTP upload mechanism may move a file into private storage. */
    public function receive(int $authenticatedUserId, array $file): array
    {
        if ($authenticatedUserId < 1) throw new HttpException(401, 'AUTHENTICATION_REQUIRED');
        $error = $file['error'] ?? null;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new HttpException(413, 'BACKGROUND_TOO_LARGE');
        if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)
            || !is_uploaded_file($file['tmp_name'])) throw new HttpException(422, 'INVALID_UPLOAD');
        $metadata = self::inspect($file['tmp_name'], $file['name']);
        $directory = $this->ownerDirectory($authenticatedUserId);
        $filename = bin2hex(random_bytes(24)) . '.' . $metadata['extension'];
        $destination = $directory . '/' . $filename;
        if (file_exists($destination) || !@move_uploaded_file($file['tmp_name'], $destination)) {
            throw new HttpException(503, 'BACKGROUND_STORAGE_UNAVAILABLE');
        }
        if (!@chmod($destination, 0600)) {
            @unlink($destination);
            throw new HttpException(503, 'BACKGROUND_STORAGE_UNAVAILABLE');
        }
        return $metadata + ['filename' => $filename, 'path' => $destination];
    }

    private function ownerDirectory(int $userId): string
    {
        $root = realpath($this->projectRoot);
        if ($root === false) throw new HttpException(503, 'BACKGROUND_STORAGE_UNAVAILABLE');
        $root = str_replace('\\', '/', $root);
        $directory = $root;
        foreach (['storage', 'uploads', 'backgrounds', (string) $userId] as $part) {
            $directory .= '/' . $part;
            if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700))) {
                throw new HttpException(503, 'BACKGROUND_STORAGE_UNAVAILABLE');
            }
        }
        $resolved = realpath($directory);
        if ($resolved === false || str_replace('\\', '/', $resolved) !== $directory || !is_writable($directory)) {
            throw new HttpException(503, 'BACKGROUND_STORAGE_UNAVAILABLE');
        }
        return $directory;
    }

    public function existingPath(int $userId,string $filename): string
    {
        if($userId<1||!preg_match('/^[a-f0-9]{48}\.(jpg|png|gif|webp|avif|mp4|webm)$/D',$filename))throw new HttpException(404,'NOT_FOUND');
        $path=$this->ownerDirectory($userId).'/'.$filename;
        if(!is_file($path)||is_link($path))throw new HttpException(404,'NOT_FOUND');
        return $path;
    }
}

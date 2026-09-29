<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\{Database, Migrator};
use App\Http\HttpException;
use App\Repositories\InstallationRepository;

final class InstallationService
{
    public function __construct(private readonly string $root)
    {
    }

    public function locked(): bool
    {
        return is_file($this->root . '/config/config.php') || is_file($this->root . '/storage/installed.lock');
    }

    public function authorize(string $key): bool
    {
        $path = $this->root . '/config/install.key';
        return is_file($path) && $key !== '' && hash_equals(trim(file_get_contents($path)), $key);
    }

    public function validateSite(string $url): string
    {
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new HttpException(422, 'INVALID_INPUT');
        }
        $local = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        if (($parts['scheme'] ?? '') !== 'https' && !(($parts['scheme'] ?? '') === 'http' && $local)) {
            throw new HttpException(422, 'HTTPS_REQUIRED');
        }
        return rtrim($url, '/');
    }

    public function install(array $draft): void
    {
        $handle = fopen($this->root . '/storage/install.mutex', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            throw new HttpException(409, 'INSTALL_BUSY');
        }
        try {
            if ($this->locked()) {
                throw new HttpException(409, 'ALREADY_INSTALLED');
            }
            if (!(new EnvironmentCheck($this->root))->ready()) {
                throw new HttpException(422, 'ENVIRONMENT_FAILED');
            }
            $pdo = Database::connect($draft['database']);
            (new Migrator($pdo, $this->root . '/database/migrations'))->migrate();
            (new InstallationRepository($pdo))->reserveAdministrator($draft['admin']);
            $configuration = [
                'installed' => true,
                'database' => $draft['database'],
                'site' => $draft['site'],
                'discord' => $draft['discord'],
                'initial_admin_discord_id' => $draft['admin'],
                'encryption_key' => bin2hex(random_bytes(32)),
                'session' => ['name' => 'search_session', 'secure' => str_starts_with($draft['site']['url'], 'https://'), 'same_site' => 'Lax'],
                'ffmpeg_path' => getenv('SEARCH_FFMPEG_PATH') ?: '',
            ];
            $temporary = tempnam($this->root . '/config', '.config-');
            if ($temporary === false) {
                throw new \RuntimeException('Config directory unavailable');
            }
            try {
                chmod($temporary, 0600);
                $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($configuration, true) . ";\n";
                if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
                    throw new \RuntimeException('Config write failed');
                }
                if (!rename($temporary, $this->root . '/config/config.php')) {
                    throw new \RuntimeException('Config commit failed');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            file_put_contents($this->root . '/storage/installed.lock', gmdate(DATE_ATOM), LOCK_EX);
            if (is_file($this->root . '/config/install.key')) {
                unlink($this->root . '/config/install.key');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

<?php
declare(strict_types=1);

// Only run inside the disposable Compose app containers, before installation.
require dirname(__DIR__) . '/app/autoload.php';

use App\Database\{Database, Migrator};

if (PHP_SAPI !== 'cli' || getenv('SEARCH_TEST_MODE') !== '1' || !in_array(getenv('TEST_DB_HOST'), ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "Disposable Compose test environment required.\n");
    exit(1);
}
$root = dirname(__DIR__);
if (is_file($root . '/config/config.php')) {
    fwrite(STDERR, "Already installed: use a fresh disposable Compose project for installer tests.\n");
    exit(1);
}
set_error_handler(static function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});
$count = 0;
$check = static function (bool $ok, string $name) use (&$count): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $count++;
    echo "PASS: $name\n";
};
$settings = ['host' => getenv('TEST_DB_HOST'), 'port' => 3306, 'name' => 'startpage', 'user' => 'startpage', 'password' => getenv('TEST_DB_PASSWORD')];
$pdo = Database::connect($settings);
$check($pdo->query('SHOW TABLES')->fetchAll() === [], 'empty disposable DB');
$migrator = new Migrator($pdo, $root . '/database/migrations');
$expectedMigrations = array_map('basename', glob($root . '/database/migrations/*.php'));
sort($expectedMigrations);
$check($migrator->migrate() === $expectedMigrations, 'initial migration');
$check($migrator->migrate() === [], 'migration idempotency');
$check((int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'administrators'")->fetchColumn() === 2, 'administrator foreign keys');
try {
    $pdo->exec('INSERT INTO administrators (user_id, created_at, updated_at) VALUES (999, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
    throw new RuntimeException('Foreign key accepted missing user');
} catch (PDOException $error) {
    $check($error->getCode() === '23000', 'foreign key rejects nonexistent user');
}
foreach (array_reverse($expectedMigrations) as $name) {
    $migration = require $root . '/database/migrations/' . $name;
    $migration->down($pdo);
}
$check(count($pdo->query('SHOW TABLES')->fetchAll()) === 1, 'empty schema rollback');
$pdo->exec('DROP TABLE migrations');

$cookies = [];
$request = static function (string $method, string $path, array|string $data = [], array $extraHeaders = [], int $timeout = 20) use (&$cookies): array {
    $headers = ['Accept-Language: en'];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($key, $value) => "$key=$value", array_keys($cookies), $cookies));
    }
    $body = is_array($data) ? http_build_query($data) : $data;
    if ($method !== 'GET') {
        $headers[] = is_array($data) ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
    }
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", array_merge($headers, $extraHeaders)),
        'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => $timeout,
    ]]);
    $result = file_get_contents('http://127.0.0.1' . $path, false, $context);
    $responseHeaders = $http_response_header;
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $status);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) {
            $cookies[$match[1]] = $match[2];
        }
    }
    return [(int) $status[1], $result, implode("\n", $responseHeaders)];
};
[$status] = $request('GET', '/');
$check($status === 303, 'uninstalled home redirects');
[$status, $body] = $request('GET', '/api/health');
$check($status === 503 && json_decode($body, true)['error']['code'] === 'NOT_INSTALLED', 'uninstalled health fails honestly');
[$status, $body, $headers] = $request('GET', '/installer');
$check($status === 200 && str_contains($body, 'Environment'), 'installer environment screen');
$check(str_contains($headers, 'Content-Security-Policy:') && str_contains($headers, 'X-Content-Type-Options: nosniff'), 'security headers');
[$status, $body] = $request('GET', '/api/csrf');
$csrf = json_decode($body, true)['data']['csrf_token'];
[$status] = $request('POST', '/installer', ['setup_key' => 'invalid']);
$check($status === 403, 'installer CSRF rejection');
[$status, $body] = $request('POST', '/api/missing', '{broken');
$check($status === 400 && json_decode($body, true)['error']['code'] === 'INVALID_JSON', 'invalid JSON envelope');
[$status] = $request('POST', '/locale', ['_csrf' => $csrf, 'locale' => 'ja']);
$check($status === 303, 'locale mutation');
[$status, $body] = $request('GET', '/installer');
$check(str_contains($body, 'lang="ja"') && str_contains($body, '環境確認'), 'locale persists in session');
$request('POST', '/locale', ['_csrf' => $csrf, 'locale' => 'en']);
[$status, $body] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '1', 'setup_key' => 'invalid']);
$check($status === 422 && str_contains($body, 'setup key is invalid'), 'setup key rejected');
$key = bin2hex(random_bytes(32));
file_put_contents($root . '/config/install.key', $key);
$beforeSession = $cookies['search_session'];
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '1', 'setup_key' => $key]);
$check($status === 303 && $cookies['search_session'] !== $beforeSession, 'setup session regeneration');
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '1', 'setup_key' => $key]);
$check($status === 422, 'stale setup form cannot advance');
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '2'] + $settings);
$check($status === 303, 'database configuration');
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '3', 'site_name' => '<script>alert(1)</script>', 'site_url' => 'http://localhost:8080']);
$check($status === 303, 'site configuration');
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '4', 'client_id' => '', 'client_secret' => '']);
$check($status === 303, 'OAuth can remain unconfigured');
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '5', 'admin_id' => '123456789012345678']);
$check($status === 303, 'initial admin reservation');
[$status, $body] = $request('GET', '/installer');
$check(!str_contains($body, '<script>') && !str_contains($body, $settings['password']), 'review escapes HTML and omits password');
// Initial DDL can exceed ordinary request timeouts on Docker Desktop.
[$status] = $request('POST', '/installer', ['_csrf' => $csrf, '_step' => '6'], [], 120);
$check($status === 303, 'installation completes');
[$status, $body] = $request('GET', '/installer');
$check($status === 200 && str_contains($body, 'Core installation is complete'), 'completion screen');
$check(is_file($root . '/config/config.php') && !is_file($root . '/config/install.key'), 'private config created and setup key removed');
$stored = require $root . '/config/config.php';
$check($stored['installed'] === true && strlen($stored['encryption_key']) === 64, 'installed configuration and encryption key');
[$status, $body] = $request('GET', '/api/health');
$check($status === 200 && json_decode($body, true)['data']['status'] === 'ok', 'installed DB health');
[$status, $body] = $request('GET', '/');
$check($status === 200 && !str_contains($body, '<script>'), 'home view escapes stored site name');
$check($migrator->migrate() === [], 'post-install migration regression');
$check($pdo->query('SELECT discord_id FROM installation_claims')->fetchColumn() === '123456789012345678', 'reserved administrator identity');
foreach (['/config/config.php', '/storage/logs/test', '/app/bootstrap.php', '/.git/config'] as $path) {
    [$status, $body] = $request('GET', $path);
    $check($status === 404 && !str_contains($body, $settings['password']), 'private path denied: ' . $path);
}
$cookies = [];
[$status] = $request('GET', '/installer');
$check($status === 409, 'new session cannot reinstall');
echo "$count integration assertions passed on " . getenv('TEST_DB_HOST') . ".\n";

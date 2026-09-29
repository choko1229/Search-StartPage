<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Database
{
    public static function connect(array $settings): PDO
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]+$/D', (string) $settings['host']) || !preg_match('/^[a-zA-Z0-9_]+$/D', (string) $settings['name'])) {
            throw new \InvalidArgumentException('Invalid database configuration');
        }
        $port = filter_var($settings['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new \InvalidArgumentException('Invalid database port');
        }
        $pdo = new PDO("mysql:host={$settings['host']};port={$port};dbname={$settings['name']};charset=utf8mb4", $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $maria = stripos($version, 'MariaDB') !== false;
        if (!preg_match('/(\d+\.\d+\.\d+)/', str_replace('5.5.5-', '', $version), $match) || version_compare($match[1], $maria ? '10.11.0' : '8.0.0', '<')) {
            throw new \RuntimeException('Unsupported database version');
        }
        return $pdo;
    }
}

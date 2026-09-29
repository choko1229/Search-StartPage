<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly string $directory)
    {
    }

    public function migrate(): array
    {
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute(['search_startpage_migrations']);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new \RuntimeException('Migration is locked');
        }
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $applied = $this->pdo->query('SELECT name, checksum FROM migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
            $files = glob($this->directory . '/*.php');
            sort($files);
            $completed = [];
            foreach ($files as $file) {
                $name = basename($file);
                $checksum = hash_file('sha256', $file);
                if (isset($applied[$name])) {
                    if (!hash_equals($applied[$name], $checksum)) {
                        throw new \RuntimeException('Applied migration changed');
                    }
                    continue;
                }
                // MySQL DDL auto-commits. Each migration must be safe to resume.
                $migration = require $file;
                $migration->up($this->pdo);
                $statement = $this->pdo->prepare('INSERT INTO migrations (name, checksum, applied_at) VALUES (?, ?, UTC_TIMESTAMP())');
                $statement->execute([$name, $checksum]);
                $completed[] = $name;
            }
            return $completed;
        } finally {
            $unlock = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $unlock->execute(['search_startpage_migrations']);
        }
    }
}

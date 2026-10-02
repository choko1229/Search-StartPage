<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS backgrounds (
            id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL,
            type VARCHAR(10) NOT NULL, source_type VARCHAR(10) NOT NULL,
            file_path VARCHAR(64) NULL, external_url VARCHAR(2048) NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0, mime VARCHAR(40) NULL,
            cloud_sync TINYINT(1) NOT NULL DEFAULT 1, settings_json TEXT NOT NULL,
            version BIGINT UNSIGNED NOT NULL DEFAULT 1, deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(user_id,id),
            CONSTRAINT background_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS background_rules (
            id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            background_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            conditions_json TEXT NOT NULL, created_at DATETIME NOT NULL,
            PRIMARY KEY(user_id,id),
            CONSTRAINT rule_background FOREIGN KEY(user_id,background_id) REFERENCES backgrounds(user_id,id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS background_rules');
        $pdo->exec('DROP TABLE IF EXISTS backgrounds');
    }
};

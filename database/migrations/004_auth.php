<?php
declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS devices (
            id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            browser VARCHAR(40) NOT NULL,
            os VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL,
            last_active_at DATETIME NOT NULL,
            UNIQUE KEY device_owner (user_id, id),
            CONSTRAINT device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_tokens (
            device_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX token_expiry (expires_at),
            CONSTRAINT token_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS login_tokens');
        $pdo->exec('DROP TABLE IF EXISTS devices');
    }
};

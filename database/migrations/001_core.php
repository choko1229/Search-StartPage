<?php
declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(20) NOT NULL UNIQUE,
            discord_username VARCHAR(100) NOT NULL,
            discord_display_name VARCHAR(100) NULL,
            discord_avatar VARCHAR(255) NULL,
            locale VARCHAR(5) NOT NULL DEFAULT 'en',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_login_at DATETIME NULL,
            INDEX users_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('CREATE TABLE IF NOT EXISTS administrators (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT admin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT admin_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS installation_claims (
            discord_id VARCHAR(20) PRIMARY KEY,
            claimed_at DATETIME NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    // Destructive rollback is only for an empty test database, never an updater rollback.
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS installation_claims');
        $pdo->exec('DROP TABLE IF EXISTS administrators');
        $pdo->exec('DROP TABLE IF EXISTS users');
    }
};

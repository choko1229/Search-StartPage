<?php
declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS favorite_folders (
            id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY folder_owner_id (user_id, id), UNIQUE KEY folder_name (user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
            id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, folder_id CHAR(36) NULL,
            name VARCHAR(100) NOT NULL, url TEXT NOT NULL, icon TEXT NULL, color CHAR(7) NULL,
            description TEXT NULL, shortcut VARCHAR(80) NULL, pinned BOOLEAN NOT NULL DEFAULT FALSE,
            visible BOOLEAN NOT NULL DEFAULT TRUE, sort_order INT NOT NULL DEFAULT 0,
            usage_count BIGINT UNSIGNED NOT NULL DEFAULT 0, last_access_at DATETIME NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY favorite_owner_id (user_id, id), INDEX favorites_order (user_id, sort_order),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id, folder_id) REFERENCES favorite_folders(user_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
            id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(40) NOT NULL,
            UNIQUE KEY tag_owner_id (user_id, id), UNIQUE KEY tag_name (user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS favorite_tags (
            favorite_id CHAR(36) NOT NULL, tag_id CHAR(36) NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (favorite_id, tag_id),
            FOREIGN KEY (user_id, favorite_id) REFERENCES favorites(user_id, id) ON DELETE CASCADE,
            FOREIGN KEY (user_id, tag_id) REFERENCES tags(user_id, id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $pdo): void
    {
        foreach (['favorite_tags', 'tags', 'favorites', 'favorite_folders'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }
};

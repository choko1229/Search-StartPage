<?php
declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS search_history (
            id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
            query TEXT NOT NULL, provider VARCHAR(100) NOT NULL,
            mode VARCHAR(8) NOT NULL, created_at DATETIME NOT NULL,
            INDEX history_user_date (user_id, created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['search_engines' => 'search_url', 'ai_providers' => 'query_url'] as $table => $urlColumn) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS $table (
                id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL, $urlColumn TEXT NOT NULL,
                icon VARCHAR(255) NOT NULL DEFAULT '', prefix VARCHAR(32) NOT NULL,
                enabled BOOLEAN NOT NULL DEFAULT TRUE, sort_order INT NOT NULL DEFAULT 0,
                UNIQUE KEY provider_prefix (user_id, prefix),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS ai_providers');
        $pdo->exec('DROP TABLE IF EXISTS search_engines');
        $pdo->exec('DROP TABLE IF EXISTS search_history');
    }
};

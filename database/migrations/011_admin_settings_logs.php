<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            value_json TEXT NOT NULL, version BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT site_setting_actor FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS log_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type VARCHAR(20) NOT NULL,
            error_code VARCHAR(80) NOT NULL, user_id BIGINT UNSIGNED NULL,
            context_json TEXT NOT NULL, created_at DATETIME NOT NULL, file_written TINYINT UNSIGNED NOT NULL DEFAULT 0,
            INDEX log_date(created_at), INDEX log_type_date(type,created_at), INDEX log_user_date(user_id,created_at),
            CONSTRAINT log_actor FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO site_settings(setting_key,value_json,updated_at) VALUES ('maintenance','false',UTC_TIMESTAMP())");
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS log_entries');
        $pdo->exec('DROP TABLE IF EXISTS site_settings');
    }
};

<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS update_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
            operation VARCHAR(16) NOT NULL, from_version VARCHAR(128) NOT NULL, to_version VARCHAR(128) NOT NULL,
            channel VARCHAR(16) NOT NULL, repository VARCHAR(140) NOT NULL, release_id BIGINT UNSIGNED NULL,
            requested_by BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL, error_code VARCHAR(64) NULL,
            created_at DATETIME NOT NULL, completed_at DATETIME NULL,
            INDEX update_history_created(created_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $pdo):void {$pdo->exec('DROP TABLE IF EXISTS update_history');}
};

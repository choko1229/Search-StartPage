<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS background_upload_receipts (
            user_id BIGINT UNSIGNED NOT NULL,
            request_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            response_json MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
            PRIMARY KEY(user_id,request_id),
            CONSTRAINT background_receipt_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS background_upload_receipts');
    }
};

<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo):void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS statistics_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            anonymous_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_type VARCHAR(32) NOT NULL,event_data JSON NOT NULL,
            source VARCHAR(16) NOT NULL,created_at DATETIME NOT NULL,
            UNIQUE KEY statistics_delivery (anonymous_id,event_id),
            INDEX statistics_created (created_at),INDEX statistics_actor_date (anonymous_id,created_at),
            INDEX statistics_type_date (event_type,created_at),INDEX statistics_source_date (source,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $pdo):void {$pdo->exec('DROP TABLE IF EXISTS statistics_events');}
};

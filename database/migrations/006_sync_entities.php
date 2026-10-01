<?php
declare(strict_types=1);

return new class {
    private const TABLES=['favorite_folders','favorites','search_history','search_engines','ai_providers'];
    public function up(PDO $pdo): void
    {
        foreach(self::TABLES as $table) {
            $query=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $query->execute([$table]);$columns=$query->fetchAll(PDO::FETCH_COLUMN);
            if(!in_array('client_id',$columns,true))$pdo->exec("ALTER TABLE $table ADD client_id VARCHAR(100) NULL, ADD UNIQUE KEY client_owner (user_id,client_id)");
            if(!in_array('payload',$columns,true))$pdo->exec("ALTER TABLE $table ADD payload MEDIUMTEXT NULL");
            $pdo->exec("UPDATE $table SET client_id=id WHERE client_id IS NULL");
            $pdo->exec("ALTER TABLE $table MODIFY client_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
            if($table!=='search_history')$pdo->exec("ALTER TABLE $table MODIFY sort_order BIGINT NOT NULL DEFAULT 0");
        }
        // Preserve distinct accents in names; the service rejects case duplicates.
        $pdo->exec('ALTER TABLE favorite_folders MODIFY name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
        $pdo->exec('ALTER TABLE tags MODIFY name VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_settings (
            id CHAR(36) PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,
            setting_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,setting_value MEDIUMTEXT NOT NULL,updated_at DATETIME NOT NULL,
            UNIQUE KEY setting_owner (user_id,setting_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sync_versions (
            id CHAR(36) PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,
            entity_type VARCHAR(32) NOT NULL,entity_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            version BIGINT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,
            UNIQUE KEY entity_owner (user_id,entity_type,entity_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS sync_versions');$pdo->exec('DROP TABLE IF EXISTS user_settings');
        foreach(self::TABLES as $table)$pdo->exec("ALTER TABLE $table DROP INDEX client_owner,DROP COLUMN client_id,DROP COLUMN payload");
        // Wider sort columns and name collations are intentionally retained:
        // restoring narrower columns could truncate existing user data.
    }
};

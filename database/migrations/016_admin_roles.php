<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo):void
    {
        $pdo->exec("INSERT IGNORE INTO site_settings(setting_key,value_json,version,updated_at) VALUES ('admin_roles','{}',1,UTC_TIMESTAMP())");
    }
    public function down(PDO $pdo):void {$pdo->exec("DELETE FROM site_settings WHERE setting_key='admin_roles'");}
};

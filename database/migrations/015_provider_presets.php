<?php
declare(strict_types=1);
return new class {
    public function up(PDO $pdo):void
    {
        // A catalog and its before/after audit can exceed the 64 KiB TEXT limit.
        $pdo->exec('ALTER TABLE site_settings MODIFY value_json MEDIUMTEXT NOT NULL');
        $pdo->exec('ALTER TABLE log_entries MODIFY context_json MEDIUMTEXT NOT NULL');
        $defaults=App\Services\ProviderPresets::defaults();
        $pdo->prepare("INSERT IGNORE INTO site_settings(setting_key,value_json,version,updated_at) VALUES ('provider_presets',?,1,UTC_TIMESTAMP())")->execute([json_encode($defaults,JSON_THROW_ON_ERROR)]);
    }
    public function down(PDO $pdo):void
    {
        // Test-only down must never truncate retained settings or audit history.
        if((int)$pdo->query("SELECT COUNT(*) FROM site_settings WHERE setting_key<>'provider_presets' AND OCTET_LENGTH(value_json)>65535")->fetchColumn()>0||(int)$pdo->query('SELECT COUNT(*) FROM log_entries WHERE OCTET_LENGTH(context_json)>65535')->fetchColumn()>0)throw new RuntimeException('Cannot narrow retained data');
        $pdo->exec("DELETE FROM site_settings WHERE setting_key='provider_presets'");
        $pdo->exec('ALTER TABLE site_settings MODIFY value_json TEXT NOT NULL');
        $pdo->exec('ALTER TABLE log_entries MODIFY context_json TEXT NOT NULL');
    }
};
